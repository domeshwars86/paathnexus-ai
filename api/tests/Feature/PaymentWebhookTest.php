<?php

namespace Tests\Feature;

use App\Models\FeeInvoice;
use App\Models\Organization;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_sandbox_capture_is_idempotent_and_records_no_staff_user(): void
    {
        config(['services.payments.gateway' => 'sandbox', 'services.payments.sandbox_webhook_secret' => 'test-only-secret']);
        [$organization, $invoice] = $this->invoice();
        $event = $this->event($organization->id, $invoice->id, 'evt-1', 100000);

        $this->deliver($event)->assertOk()->assertJsonPath('status', 'processed');
        $this->deliver($event)->assertOk()->assertJsonPath('status', 'duplicate');

        $this->assertDatabaseCount('fee_payments', 1);
        $this->assertDatabaseHas('fee_payments', [
            'invoice_id' => $invoice->id, 'amount_paise' => 100000, 'recorded_by' => null,
            'source' => 'webhook', 'provider' => 'sandbox', 'provider_event_id' => 'evt-1',
        ]);
        $this->assertDatabaseHas('fee_invoices', ['id' => $invoice->id, 'status' => 'paid']);
        $this->assertDatabaseCount('payment_webhook_events', 1);
    }

    public function test_webhook_requires_enabled_sandbox_and_a_valid_signature(): void
    {
        [$organization, $invoice] = $this->invoice();
        $event = $this->event($organization->id, $invoice->id, 'evt-2', 10000);
        config(['services.payments.gateway' => 'disabled']);
        $this->deliver($event)->assertNotFound();

        config(['services.payments.gateway' => 'sandbox', 'services.payments.sandbox_webhook_secret' => 'test-only-secret']);
        $raw = json_encode($event, JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/webhooks/payments/sandbox', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_PAATHNEXUS_SIGNATURE' => 'invalid',
        ], $raw)->assertUnauthorized();
        $this->assertDatabaseCount('fee_payments', 0);
    }

    public function test_overpayment_is_rejected_and_event_id_cannot_be_reused_for_a_different_body(): void
    {
        config(['services.payments.gateway' => 'sandbox', 'services.payments.sandbox_webhook_secret' => 'test-only-secret']);
        [$organization, $invoice] = $this->invoice();
        $event = $this->event($organization->id, $invoice->id, 'evt-3', 100001);
        $this->deliver($event)->assertUnprocessable();
        $this->assertDatabaseCount('fee_payments', 0);

        $valid = $this->event($organization->id, $invoice->id, 'evt-4', 25000);
        $this->deliver($valid)->assertOk();
        $changed = [...$valid, 'amount_paise' => 26000];
        $this->deliver($changed)->assertStatus(409);
        $this->assertDatabaseCount('fee_payments', 1);
    }

    private function invoice(): array
    {
        $organization = Organization::create(['name' => 'Sandbox School', 'slug' => 'sandbox-'.Str::lower(Str::random(8)), 'type' => 'school', 'status' => 'active']);
        $owner = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active']);
        $student = StudentProfile::create([
            'organization_id' => $organization->id, 'student_code' => 'SB-'.Str::upper(Str::random(6)),
            'name' => 'Synthetic Learner', 'status' => 'active',
        ]);
        $invoice = FeeInvoice::create([
            'organization_id' => $organization->id, 'student_id' => $student->id,
            'invoice_number' => 'PN-'.Str::upper(Str::random(10)), 'title' => 'Sandbox invoice',
            'amount_paise' => 100000, 'status' => 'issued', 'issued_by' => $owner->id,
        ]);
        return [$organization, $invoice];
    }

    private function event(string $organizationId, string $invoiceId, string $eventId, int $amountPaise): array
    {
        return [
            'event_id' => $eventId, 'type' => 'payment.captured', 'organization_id' => $organizationId,
            'invoice_id' => $invoiceId, 'amount_paise' => $amountPaise, 'method' => 'upi',
            'transaction_reference' => 'SANDBOX-'.$eventId,
        ];
    }

    private function deliver(array $event)
    {
        $raw = json_encode($event, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $raw, 'test-only-secret');
        return $this->call('POST', '/api/v1/webhooks/payments/sandbox', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_PAATHNEXUS_SIGNATURE' => $signature,
        ], $raw);
    }
}
