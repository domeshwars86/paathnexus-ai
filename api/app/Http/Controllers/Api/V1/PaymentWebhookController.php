<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseOrder;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\Organization;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PaymentWebhookController extends Controller
{
    public function sandbox(Request $request): JsonResponse
    {
        abort_unless(config('services.payments.gateway') === 'sandbox', 404);
        $secret = config('services.payments.sandbox_webhook_secret');
        abort_if(! is_string($secret) || $secret === '', 503, 'Sandbox webhook secret is not configured.');

        $rawBody = $request->getContent();
        $signature = $request->header('X-PaathNexus-Signature', '');
        $expected = hash_hmac('sha256', $rawBody, $secret);
        abort_unless(is_string($signature) && hash_equals($expected, $signature), 401, 'Invalid sandbox payment signature.');

        $payload = json_decode($rawBody, true);
        $type = is_array($payload) ? ($payload['type'] ?? null) : null;
        $isCourseEvent = $type === 'course.payment_captured';
        $validator = Validator::make(is_array($payload) ? $payload : [], [
            'event_id' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:payment.captured,course.payment_captured'],
            'organization_id' => ['required', 'uuid'],
            'invoice_id' => [$isCourseEvent ? 'prohibited' : 'required', 'uuid'],
            'course_order_id' => [$isCourseEvent ? 'required' : 'prohibited', 'uuid'],
            'amount_paise' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'method' => ['required', 'in:cash,bank_transfer,upi,cheque,other'],
            'transaction_reference' => ['required', 'string', 'max:120', 'not_regex:/^\s*$/'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid sandbox payment event.', 'errors' => $validator->errors()], 422);
        }
        $event = $validator->validated();
        $event['transaction_reference'] = trim($event['transaction_reference']);
        $bodyHash = hash('sha256', $rawBody);

        try {
            $result = DB::transaction(function () use ($event, $bodyHash): array {
                $prior = $this->priorEvent($event['event_id'], $bodyHash);
                if ($prior) return $prior;

                return $event['type'] === 'course.payment_captured'
                    ? $this->captureCourseOrder($event, $bodyHash)
                    : $this->captureInvoicePayment($event, $bodyHash);
            });
        } catch (QueryException $exception) {
            // Unique constraints are the final concurrency guard for provider event IDs and transaction references.
            $prior = DB::table('payment_webhook_events')->where('provider', 'sandbox')->where('event_id', $event['event_id'])->first();
            if ($prior) {
                abort_unless(hash_equals($prior->body_sha256, $bodyHash), 409, 'Webhook event ID was already used with a different body.');
                $result = ['duplicate' => true, 'payment_id' => $prior->payment_id, 'order_id' => $prior->course_order_id];
            } elseif (FeePayment::query()->where('organization_id', $event['organization_id'])->where('transaction_reference', $event['transaction_reference'])->exists()
                || CourseOrder::query()->where('organization_id', $event['organization_id'])->where('transaction_reference', $event['transaction_reference'])->exists()) {
                abort(409, 'This transaction reference has already been recorded.');
            } else {
                throw $exception;
            }
        }

        return response()->json([
            'status' => $result['duplicate'] ? 'duplicate' : 'processed',
            'payment_id' => $result['payment_id'], 'order_id' => $result['order_id'],
        ]);
    }

    private function captureInvoicePayment(array $event, string $bodyHash): array
    {
        Organization::query()->whereKey($event['organization_id'])->lockForUpdate()->firstOrFail();
        $invoice = FeeInvoice::query()->where('organization_id', $event['organization_id'])
            ->whereKey($event['invoice_id'])->lockForUpdate()->firstOrFail();
        // Recheck after the invoice lock because another delivery may have committed while this one waited.
        $prior = $this->priorEvent($event['event_id'], $bodyHash);
        if ($prior) return $prior;

        abort_unless(in_array($invoice->status, ['issued', 'partially_paid'], true), 409, 'This invoice cannot accept a payment.');
        abort_unless(! FeePayment::query()->where('organization_id', $invoice->organization_id)
            ->where('transaction_reference', $event['transaction_reference'])->exists(), 409, 'This transaction reference has already been recorded.');
        abort_unless(! CourseOrder::query()->where('organization_id', $invoice->organization_id)
            ->where('transaction_reference', $event['transaction_reference'])->exists(), 409, 'This transaction reference has already been recorded.');

        $paid = (int) $invoice->payments()->sum('amount_paise');
        $remaining = $invoice->amount_paise - $paid;
        abort_if($event['amount_paise'] > $remaining, 422, 'Payment exceeds the remaining invoice balance.');

        $payment = $invoice->payments()->create([
            'organization_id' => $invoice->organization_id,
            'amount_paise' => $event['amount_paise'], 'method' => $event['method'],
            'transaction_reference' => $event['transaction_reference'], 'idempotency_key' => (string) Str::uuid(),
            'receipt_number' => 'PNR-'.Str::upper(Str::random(12)), 'recorded_by' => null,
            'received_at' => now(), 'source' => 'webhook', 'provider' => 'sandbox', 'provider_event_id' => $event['event_id'],
        ]);
        $invoice->forceFill(['status' => $event['amount_paise'] === $remaining ? 'paid' : 'partially_paid'])->save();

        $this->recordEvent($event, $bodyHash, $payment->id, null);
        return ['duplicate' => false, 'payment_id' => $payment->id, 'order_id' => null];
    }

    private function captureCourseOrder(array $event, string $bodyHash): array
    {
        $initial = CourseOrder::query()->where('organization_id', $event['organization_id'])
            ->whereKey($event['course_order_id'])->firstOrFail();
        // Keep the lock order consistent with order creation and workspace suspension.
        $organization = Organization::query()->whereKey($initial->organization_id)->where('status', 'active')->lockForUpdate()->firstOrFail();
        $course = Course::query()->where('organization_id', $organization->id)->whereKey($initial->course_id)->lockForUpdate()->firstOrFail();
        abort_unless($course->status === 'active' && $course->is_public, 409, 'The public course is no longer available.');
        abort_if(DB::table('platform_content_moderations')->where('content_type', 'course')->where('content_id', $course->id)->where('status', 'hidden')->exists(), 409, 'The public course is no longer available.');
        $order = CourseOrder::query()->where('organization_id', $organization->id)->whereKey($initial->id)->lockForUpdate()->firstOrFail();
        $prior = $this->priorEvent($event['event_id'], $bodyHash);
        if ($prior) return $prior;

        abort_unless($order->provider === 'sandbox', 409, 'This order was not created for sandbox checkout.');
        abort_unless($order->status === 'pending', 409, 'This course order is not pending.');
        abort_if($order->expires_at->isPast(), 409, 'This course order has expired.');
        abort_unless($event['amount_paise'] === $order->amount_paise, 422, 'Captured amount must match the course order total exactly.');
        abort_unless(! FeePayment::query()->where('organization_id', $organization->id)
            ->where('transaction_reference', $event['transaction_reference'])->exists(), 409, 'This transaction reference has already been recorded.');
        abort_unless(! CourseOrder::query()->where('organization_id', $organization->id)
            ->where('transaction_reference', $event['transaction_reference'])->exists(), 409, 'This transaction reference has already been recorded.');

        $enrollment = CourseEnrollment::query()->where('organization_id', $organization->id)->where('course_id', $course->id)
            ->where('student_id', $order->student_id)->lockForUpdate()->first();
        if ($enrollment?->status === 'active') {
            // A manager may have enrolled this learner while payment was pending; still reconcile the valid order.
        } elseif ($enrollment) {
            $enrollment->forceFill(['status' => 'active', 'enrolled_by' => $order->buyer_user_id, 'enrolled_at' => now()])->save();
        } else {
            CourseEnrollment::create([
                'organization_id' => $organization->id, 'course_id' => $course->id,
                'student_id' => $order->student_id, 'status' => 'active',
                'enrolled_by' => $order->buyer_user_id, 'enrolled_at' => now(),
            ]);
        }

        $order->forceFill([
            'status' => 'paid', 'provider' => 'sandbox', 'provider_event_id' => $event['event_id'],
            'payment_method' => $event['method'], 'transaction_reference' => $event['transaction_reference'], 'paid_at' => now(),
        ])->save();
        $this->recordEvent($event, $bodyHash, null, $order->id);
        return ['duplicate' => false, 'payment_id' => null, 'order_id' => $order->id];
    }

    private function priorEvent(string $eventId, string $bodyHash): ?array
    {
        $prior = DB::table('payment_webhook_events')->where('provider', 'sandbox')->where('event_id', $eventId)->first();
        if (! $prior) return null;
        abort_unless(hash_equals($prior->body_sha256, $bodyHash), 409, 'Webhook event ID was already used with a different body.');
        return ['duplicate' => true, 'payment_id' => $prior->payment_id, 'order_id' => $prior->course_order_id];
    }

    private function recordEvent(array $event, string $bodyHash, ?string $paymentId, ?string $courseOrderId): void
    {
        DB::table('payment_webhook_events')->insert([
            'id' => (string) Str::uuid(), 'provider' => 'sandbox', 'event_id' => $event['event_id'],
            'event_type' => $event['type'], 'body_sha256' => $bodyHash, 'outcome' => 'processed',
            'payment_id' => $paymentId, 'course_order_id' => $courseOrderId,
            'processed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
