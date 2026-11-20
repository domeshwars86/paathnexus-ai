<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FeeInvoiceController extends Controller
{
    public function index(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        abort_unless($request->user()->can('viewFeeInvoices', $organization), 403);
        $filters = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ]);
        if (! empty($filters['from']) && ! empty($filters['to']) && $filters['to'] < $filters['from']) {
            throw ValidationException::withMessages(['to' => 'The end date must be on or after the start date.']);
        }
        $role = $organization->users()->where('users.id', $request->user()->id)->firstOrFail()->pivot->role;
        $query = FeeInvoice::query()->where('organization_id', $organization->id)->with(['student:id,name,student_code', 'payments']);
        if (! empty($filters['from'])) {
            $startsAt = CarbonImmutable::createFromFormat('!Y-m-d', $filters['from'], $organization->timezone)->utc();
            $query->where('created_at', '>=', $startsAt);
        }
        if (! empty($filters['to'])) {
            $endsBefore = CarbonImmutable::createFromFormat('!Y-m-d', $filters['to'], $organization->timezone)->addDay()->utc();
            $query->where('created_at', '<', $endsBefore);
        }
        if ($role === 'student') {
            $query->whereIn('student_id', $organization->students()->where('user_id', $request->user()->id)->select('id'));
        } elseif ($role === 'guardian') {
            $query->whereIn('student_id', $organization->students()->whereIn('id', function ($links) use ($request, $organization): void {
                $links->select('student_id')->from('guardian_student_links')->where('organization_id', $organization->id)
                    ->where('guardian_user_id', $request->user()->id)->where('status', 'active');
            })->select('id'));
        } elseif (! in_array($role, ['owner', 'admin', 'educator'], true)) {
            abort(403);
        }
        $paginator = $query->latest()->orderBy('id', 'desc')->paginate((int) ($filters['per_page'] ?? 50));
        return response()->json([
            'data' => $paginator->getCollection()->map(fn (FeeInvoice $invoice): array => $this->data($invoice))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function summary(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);

        $payments = DB::table('fee_payments')->where('organization_id', $organization->id)
            ->select('organization_id', 'invoice_id')->selectRaw('SUM(amount_paise) as collected_paise')
            ->groupBy('organization_id', 'invoice_id');
        $summary = DB::table('fee_invoices as invoices')->where('invoices.organization_id', $organization->id)
            ->where('invoices.status', '!=', 'void')
            ->leftJoinSub($payments, 'payment_totals', function ($join): void {
                $join->on('payment_totals.organization_id', '=', 'invoices.organization_id')
                    ->on('payment_totals.invoice_id', '=', 'invoices.id');
            })
            ->selectRaw('COUNT(*) as invoice_count')
            ->selectRaw('COALESCE(SUM(invoices.amount_paise), 0) as invoiced_paise')
            ->selectRaw('COALESCE(SUM(COALESCE(payment_totals.collected_paise, 0)), 0) as collected_paise')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(payment_totals.collected_paise, 0) < invoices.amount_paise THEN invoices.amount_paise - COALESCE(payment_totals.collected_paise, 0) ELSE 0 END), 0) as outstanding_paise')
            ->selectRaw('COALESCE(SUM(CASE WHEN invoices.due_on < ? AND COALESCE(payment_totals.collected_paise, 0) < invoices.amount_paise THEN invoices.amount_paise - COALESCE(payment_totals.collected_paise, 0) ELSE 0 END), 0) as overdue_paise', [now()->toDateString()])
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(payment_totals.collected_paise, 0) >= invoices.amount_paise THEN 1 ELSE 0 END), 0) as paid_invoice_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(payment_totals.collected_paise, 0) < invoices.amount_paise THEN 1 ELSE 0 END), 0) as outstanding_invoice_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN invoices.due_on < ? AND COALESCE(payment_totals.collected_paise, 0) < invoices.amount_paise THEN 1 ELSE 0 END), 0) as overdue_invoice_count', [now()->toDateString()])
            ->first();

        return response()->json(['data' => collect((array) $summary)->map(fn ($value): int => (int) $value)]);
    }

    public function collections(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);
        $filters = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        if ($filters['to'] < $filters['from']) {
            throw ValidationException::withMessages(['to' => 'The end date must be on or after the start date.']);
        }

        $startsAt = CarbonImmutable::createFromFormat('!Y-m-d', $filters['from'], $organization->timezone)->utc();
        $endsBefore = CarbonImmutable::createFromFormat('!Y-m-d', $filters['to'], $organization->timezone)->addDay()->utc();
        $query = FeePayment::query()->where('organization_id', $organization->id)
            ->where('received_at', '>=', $startsAt)->where('received_at', '<', $endsBefore)
            ->with(['invoice:id,organization_id,student_id,invoice_number,title', 'invoice.student:id,name,student_code']);
        $paymentCount = (clone $query)->count();
        $collectedPaise = (int) (clone $query)->sum('amount_paise');
        $paginator = $query->orderByDesc('received_at')->orderByDesc('id')->paginate((int) ($filters['per_page'] ?? 25));

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (FeePayment $payment): array => [
                'id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'invoice_number' => $payment->invoice?->invoice_number,
                'invoice_title' => $payment->invoice?->title,
                'student_name' => $payment->invoice?->student?->name,
                'student_code' => $payment->invoice?->student?->student_code,
                'amount_paise' => $payment->amount_paise,
                'method' => $payment->method,
                'transaction_reference' => $payment->transaction_reference,
                'receipt_number' => $payment->receipt_number,
                'received_at' => $payment->received_at?->toISOString(),
            ])->values(),
            'summary' => ['payment_count' => $paymentCount, 'collected_paise' => $collectedPaise],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $filters['from'],
                'to' => $filters['to'],
            ],
        ]);
    }

    public function store(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);
        $validated = $request->validate([
            'student_id' => ['required', 'uuid'], 'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'], 'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:10000000'],
            'due_on' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
        $student = $organization->students()->where('status', 'active')->whereKey($validated['student_id'])->firstOrFail();
        $invoice = FeeInvoice::create([
            'organization_id' => $organization->id, 'student_id' => $student->id,
            'invoice_number' => 'PN-'.Str::upper(Str::random(10)), 'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'amount_paise' => (int) round(((float) $validated['amount']) * 100),
            'due_on' => $validated['due_on'] ?? null, 'status' => 'issued', 'issued_by' => $request->user()->id,
        ])->load('student:id,name,student_code');
        return response()->json(['data' => $this->data($invoice)], 201);
    }

    public function void(Request $request, string $organizationId, string $invoiceId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);
        $invoice = FeeInvoice::query()->where('organization_id', $organization->id)->whereKey($invoiceId)->firstOrFail();
        abort_unless($invoice->status === 'issued' && ! $invoice->payments()->exists(), 409, 'Invoices with recorded payments cannot be voided.');
        $invoice->forceFill(['status' => 'void', 'voided_at' => now()])->save();
        return response()->json(['data' => ['id' => $invoice->id, 'status' => $invoice->status]]);
    }

    public function recordPayment(Request $request, string $organizationId, string $invoiceId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:10000000'],
            'method' => ['required', Rule::in(['cash', 'bank_transfer', 'upi', 'cheque', 'other'])],
            'transaction_reference' => ['required', 'string', 'max:120', 'not_regex:/^\s*$/'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $validated['transaction_reference'] = trim($validated['transaction_reference']);
        $amountPaise = (int) round(((float) $validated['amount']) * 100);
        $payment = DB::transaction(function () use ($organization, $request, $invoiceId, $validated, $amountPaise): FeePayment {
            $organization = Organization::query()->whereKey($organization->id)->where('status', 'active')->lockForUpdate()->firstOrFail();
            $existing = FeePayment::query()->where('organization_id', $organization->id)->where('idempotency_key', $validated['idempotency_key'])->first();
            if ($existing) {
                abort_unless($existing->invoice_id === $invoiceId && $existing->amount_paise === $amountPaise
                    && $existing->transaction_reference === $validated['transaction_reference'] && $existing->method === $validated['method'],
                    409, 'This idempotency key was already used for another payment.');
                return $existing;
            }
            abort_unless(! FeePayment::query()->where('organization_id', $organization->id)
                ->where('transaction_reference', $validated['transaction_reference'])->exists(), 409, 'This transaction reference has already been recorded.');
            abort_unless(! \App\Models\CourseOrder::query()->where('organization_id', $organization->id)
                ->where('transaction_reference', $validated['transaction_reference'])->exists(), 409, 'This transaction reference has already been recorded.');
            $invoice = FeeInvoice::query()->where('organization_id', $organization->id)->whereKey($invoiceId)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($invoice->status, ['issued', 'partially_paid'], true), 409, 'This invoice cannot accept a payment.');
            $paidPaise = (int) $invoice->payments()->sum('amount_paise');
            $remainingPaise = $invoice->amount_paise - $paidPaise;
            abort_if($amountPaise > $remainingPaise, 422, 'Payment exceeds the remaining invoice balance.');
            $payment = $invoice->payments()->create([
                'organization_id' => $organization->id, 'amount_paise' => $amountPaise,
                'method' => $validated['method'], 'transaction_reference' => $validated['transaction_reference'],
                'idempotency_key' => $validated['idempotency_key'], 'receipt_number' => 'PNR-'.Str::upper(Str::random(12)),
                'recorded_by' => $request->user()->id, 'received_at' => now(),
                'source' => 'manual',
            ]);
            $invoice->forceFill(['status' => $amountPaise === $remainingPaise ? 'paid' : 'partially_paid'])->save();
            return $payment;
        });

        return response()->json(['data' => $this->paymentData($payment)], 201);
    }

    private function organization(Request $request, string $organizationId): Organization
    {
        return $request->user()->organizations()->wherePivot('status', 'active')->where('organizations.status', 'active')
            ->where('organizations.id', $organizationId)->firstOrFail();
    }

    private function data(FeeInvoice $invoice): array
    {
        return [
            'id' => $invoice->id, 'invoice_number' => $invoice->invoice_number, 'student_id' => $invoice->student_id,
            'student_name' => $invoice->student?->name, 'student_code' => $invoice->student?->student_code,
            'title' => $invoice->title, 'description' => $invoice->description,
            'amount_paise' => $invoice->amount_paise, 'due_on' => $invoice->due_on?->toDateString(),
            'balance_paise' => max(0, $invoice->amount_paise - (int) ($invoice->payments?->sum('amount_paise') ?? $invoice->payments()->sum('amount_paise'))),
            'payments' => $invoice->payments?->map(fn (FeePayment $payment): array => $this->paymentData($payment))->values()->all() ?? [],
            'status' => $invoice->status, 'created_at' => $invoice->created_at?->toIso8601String(),
        ];
    }

    private function paymentData(FeePayment $payment): array
    {
        return [
            'id' => $payment->id, 'amount_paise' => $payment->amount_paise, 'method' => $payment->method,
            'transaction_reference' => $payment->transaction_reference, 'receipt_number' => $payment->receipt_number,
            'received_at' => $payment->received_at?->toIso8601String(),
        ];
    }
}
