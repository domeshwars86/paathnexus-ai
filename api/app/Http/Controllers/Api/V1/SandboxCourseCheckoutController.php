<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CourseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SandboxCourseCheckoutController extends Controller
{
    public function capture(Request $request, string $orderId): JsonResponse
    {
        abort_unless(config('app.env') === 'local' && config('services.payments.gateway') === 'sandbox', 404);

        $order = CourseOrder::query()->where('buyer_user_id', $request->user()->id)->whereKey($orderId)->firstOrFail();
        if ($order->status === 'paid') {
            return $this->orderResponse($order);
        }
        abort_unless($order->status === 'pending', 409, 'This course order is not pending.');
        abort_if($order->expires_at->isPast(), 409, 'This course order has expired.');

        $secret = config('services.payments.sandbox_webhook_secret');
        abort_if(! is_string($secret) || $secret === '', 503, 'Sandbox webhook secret is not configured.');

        $event = [
            'event_id' => 'local-course-order-'.$order->id,
            'type' => 'course.payment_captured',
            'organization_id' => $order->organization_id,
            'course_order_id' => $order->id,
            'amount_paise' => (int) $order->amount_paise,
            'method' => 'upi',
            'transaction_reference' => 'LOCAL-SANDBOX-'.$order->id,
        ];
        $body = json_encode($event, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, $secret);
        $signedRequest = Request::create('/api/v1/webhooks/payments/sandbox', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAATHNEXUS_SIGNATURE' => $signature,
        ], $body);

        $webhookResponse = app(PaymentWebhookController::class)->sandbox($signedRequest);
        if ($webhookResponse->getStatusCode() >= 400) {
            return $webhookResponse;
        }

        return $this->orderResponse($order->refresh());
    }

    private function orderResponse(CourseOrder $order): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $order->id,
                'course_id' => $order->course_id,
                'organization_id' => $order->organization_id,
                'amount_paise' => (int) $order->amount_paise,
                'status' => $order->status,
                'expires_at' => $order->expires_at?->toIso8601String(),
                'paid_at' => $order->paid_at?->toIso8601String(),
            ],
            'checkout_mode' => 'sandbox',
        ]);
    }
}
