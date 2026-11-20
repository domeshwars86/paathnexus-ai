<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\StudentProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeeInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_is_visible_only_to_its_student_and_linked_guardian_and_can_be_voided_by_staff(): void
    {
        $organization = Organization::create(['name' => 'Invoice School', 'slug' => 'invoice-school', 'type' => 'school', 'status' => 'active']);
        $owner = User::factory()->create();
        $student = User::factory()->create();
        $guardian = User::factory()->create();
        $otherStudent = User::factory()->create();
        foreach ([[$owner, 'owner'], [$student, 'student'], [$guardian, 'guardian'], [$otherStudent, 'student']] as [$user, $role]) {
            $organization->users()->attach($user, ['role' => $role, 'status' => 'active', 'joined_at' => now()]);
        }
        $profile = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'S-INV-1', 'name' => 'Learner One', 'status' => 'active', 'user_id' => $student->id]);
        $otherProfile = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'S-INV-2', 'name' => 'Learner Two', 'status' => 'active', 'user_id' => $otherStudent->id]);
        $link = \App\Models\GuardianStudentLink::create([
            'organization_id' => $organization->id, 'student_id' => $profile->id, 'guardian_user_id' => $guardian->id,
            'relationship' => 'parent', 'status' => 'active', 'linked_by' => $owner->id, 'linked_at' => now(),
        ]);

        $invoice = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/organizations/'.$organization->id.'/fee-invoices', [
            'student_id' => $profile->id, 'title' => 'Term 1 tuition', 'description' => 'April to June', 'amount' => '1250.50', 'due_on' => now()->addDays(10)->toDateString(),
        ])->assertCreated()->assertJsonPath('data.amount_paise', 125050)->json('data');
        $this->actingAs($student, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/fee-invoices')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $invoice['id'])
            ->assertJsonPath('data.0.student_name', 'Learner One')->assertJsonPath('data.0.student_code', 'S-INV-1')
            ->assertJsonPath('data.0.description', 'April to June')->assertJsonPath('data.0.amount_paise', 125050)
            ->assertJsonPath('data.0.balance_paise', 125050)->assertJsonPath('data.0.payments', [])->assertJsonPath('data.0.status', 'issued');
        $this->actingAs($guardian, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/fee-invoices')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $invoice['id'])
            ->assertJsonPath('data.0.student_name', 'Learner One')->assertJsonPath('data.0.student_code', 'S-INV-1')
            ->assertJsonPath('data.0.balance_paise', 125050)->assertJsonPath('data.0.payments', []);
        $this->actingAs($otherStudent, 'sanctum')->getJson('/api/v1/organizations/'.$organization->id.'/fee-invoices')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($guardian, 'sanctum')->postJson('/api/v1/organizations/'.$organization->id.'/fee-invoices/'.$invoice['id'].'/void')->assertForbidden();
        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/organizations/'.$organization->id.'/fee-invoices/'.$invoice['id'].'/void')
            ->assertOk()->assertJsonPath('data.status', 'void');
        $this->assertDatabaseHas('guardian_student_links', ['id' => $link->id, 'status' => 'active']);
        $this->assertDatabaseHas('fee_invoices', ['id' => $invoice['id'], 'status' => 'void', 'amount_paise' => 125050]);
        $this->assertDatabaseMissing('fee_invoices', ['student_id' => $otherProfile->id]);
    }

    public function test_fee_viewer_can_list_invoices_without_academic_management_access(): void
    {
        $organization = Organization::create(['name' => 'Fee Viewer School', 'slug' => 'fee-viewer-school', 'type' => 'school', 'status' => 'active']);
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $studentUser = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($admin, ['role' => 'admin', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($studentUser, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        $student = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'FEE-VIEW-1', 'name' => 'Fee Viewer Learner', 'status' => 'active', 'user_id' => $studentUser->id]);
        $invoice = FeeInvoice::create([
            'organization_id' => $organization->id, 'student_id' => $student->id,
            'invoice_number' => 'PN-FEE-VIEW-1', 'title' => 'Term fee', 'amount_paise' => 50000,
            'status' => 'issued', 'issued_by' => $owner->id,
        ]);
        $base = '/api/v1/organizations/'.$organization->id;
        $role = $this->actingAs($owner, 'sanctum')->postJson($base.'/roles', [
            'base_role' => 'admin', 'name' => 'Fee viewer', 'permissions' => ['organization.view', 'fees.view'],
        ])->assertCreated()->json('data');
        $this->actingAs($owner, 'sanctum')->putJson($base.'/roles/'.$role['id'].'/members/'.$admin->id)->assertOk();

        $this->actingAs($admin, 'sanctum')->getJson($base.'/fee-invoices')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $invoice->id);
        $this->actingAs($admin, 'sanctum')->getJson($base.'/fee-invoices/summary')->assertForbidden();
        $this->actingAs($admin, 'sanctum')->postJson($base.'/fee-invoices/'.$invoice->id.'/void')->assertForbidden();
        $this->actingAs($admin, 'sanctum')->postJson($base.'/fee-invoices/'.$invoice->id.'/payments', [])->assertForbidden();
    }

    public function test_reconciled_payments_are_idempotent_and_track_partial_balances(): void
    {
        $organization = Organization::create(['name' => 'Payment School', 'slug' => 'payment-school', 'type' => 'school', 'status' => 'active']);
        $owner = User::factory()->create(); $studentUser = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active']);
        $organization->users()->attach($studentUser, ['role' => 'student', 'status' => 'active']);
        $student = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'PAY-1', 'name' => 'Payment Learner', 'status' => 'active', 'user_id' => $studentUser->id]);
        $base = '/api/v1/organizations/'.$organization->id;
        $invoice = $this->actingAs($owner, 'sanctum')->postJson($base.'/fee-invoices', [
            'student_id' => $student->id, 'title' => 'Tuition', 'amount' => '1000.00',
        ])->assertCreated()->json('data');
        $payload = ['amount' => '400.00', 'method' => 'upi', 'transaction_reference' => 'UTR-PAY-1', 'idempotency_key' => 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'];

        $payment = $this->actingAs($owner, 'sanctum')->postJson($base.'/fee-invoices/'.$invoice['id'].'/payments', $payload)
            ->assertCreated()->assertJsonPath('data.amount_paise', 40000)->json('data');
        $this->actingAs($owner, 'sanctum')->postJson($base.'/fee-invoices/'.$invoice['id'].'/payments', $payload)
            ->assertCreated()->assertJsonPath('data.id', $payment['id']);
        $this->actingAs($owner, 'sanctum')->postJson($base.'/fee-invoices/'.$invoice['id'].'/payments', [
            ...$payload, 'idempotency_key' => 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a14',
        ])->assertStatus(409);
        $this->assertDatabaseCount('fee_payments', 1);
        $this->actingAs($owner, 'sanctum')->postJson($base.'/fee-invoices/'.$invoice['id'].'/payments', [
            ...$payload, 'amount' => '700.00', 'transaction_reference' => 'UTR-PAY-2', 'idempotency_key' => 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a12',
        ])->assertUnprocessable();
        $this->actingAs($studentUser, 'sanctum')->getJson($base.'/fee-invoices')->assertOk()
            ->assertJsonPath('data.0.status', 'partially_paid')->assertJsonPath('data.0.balance_paise', 60000)
            ->assertJsonPath('data.0.payments.0.receipt_number', $payment['receipt_number'])
            ->assertJsonPath('data.0.payments.0.amount_paise', 40000)
            ->assertJsonPath('data.0.payments.0.method', 'upi')
            ->assertJsonPath('data.0.payments.0.transaction_reference', 'UTR-PAY-1')
            ->assertJsonPath('data.0.payments.0.id', $payment['id']);
        $this->actingAs($owner, 'sanctum')->postJson($base.'/fee-invoices/'.$invoice['id'].'/payments', [
            'amount' => '600.00', 'method' => 'bank_transfer', 'transaction_reference' => 'UTR-PAY-2', 'idempotency_key' => 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a13',
        ])->assertCreated();
        $this->actingAs($owner, 'sanctum')->postJson($base.'/fee-invoices/'.$invoice['id'].'/void')->assertStatus(409);
        $this->assertDatabaseHas('fee_invoices', ['id' => $invoice['id'], 'status' => 'paid']);
        $this->assertDatabaseCount('fee_payments', 2);
        $this->actingAs($studentUser, 'sanctum')->getJson($base.'/fee-invoices')->assertOk()->assertJsonPath('data.0.status', 'paid')->assertJsonPath('data.0.balance_paise', 0);
    }

    public function test_fee_summary_aggregates_reconciled_receipts_and_overdue_balances_within_the_workspace(): void
    {
        $organization = Organization::create(['name' => 'Summary School', 'slug' => 'summary-school', 'type' => 'school', 'status' => 'active']);
        $owner = User::factory()->create(); $studentUser = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active']);
        $organization->users()->attach($studentUser, ['role' => 'student', 'status' => 'active']);
        $student = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'SUM-1', 'name' => 'Summary Learner', 'status' => 'active', 'user_id' => $studentUser->id]);
        $base = '/api/v1/organizations/'.$organization->id;
        $first = $this->actingAs($owner, 'sanctum')->postJson($base.'/fee-invoices', [
            'student_id' => $student->id, 'title' => 'Term fee', 'amount' => '1000.00',
        ])->assertCreated()->json('data');
        $this->actingAs($owner, 'sanctum')->postJson($base.'/fee-invoices', [
            'student_id' => $student->id, 'title' => 'Activity fee', 'amount' => '500.00',
        ])->assertCreated();
        FeeInvoice::create([
            'organization_id' => $organization->id, 'student_id' => $student->id,
            'invoice_number' => 'PN-OVERDUE-1', 'title' => 'Old balance', 'amount_paise' => 30000,
            'due_on' => now()->subDay()->toDateString(), 'status' => 'issued', 'issued_by' => $owner->id,
        ]);
        $this->actingAs($owner, 'sanctum')->postJson($base.'/fee-invoices/'.$first['id'].'/payments', [
            'amount' => '400.00', 'method' => 'upi', 'transaction_reference' => 'UTR-SUM-1',
            'idempotency_key' => 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a21',
        ])->assertCreated();

        $otherOrganization = Organization::create(['name' => 'Other Summary School', 'slug' => 'other-summary-school', 'type' => 'school', 'status' => 'active']);
        $otherStudentUser = User::factory()->create();
        $otherOrganization->users()->attach($owner, ['role' => 'owner', 'status' => 'active']);
        $otherOrganization->users()->attach($otherStudentUser, ['role' => 'student', 'status' => 'active']);
        $otherStudent = StudentProfile::create(['organization_id' => $otherOrganization->id, 'student_code' => 'SUM-2', 'name' => 'Other Learner', 'status' => 'active', 'user_id' => $otherStudentUser->id]);
        FeeInvoice::create([
            'organization_id' => $otherOrganization->id, 'student_id' => $otherStudent->id,
            'invoice_number' => 'PN-OTHER-1', 'title' => 'Other workspace fee', 'amount_paise' => 90000,
            'status' => 'issued', 'issued_by' => $owner->id,
        ]);

        $this->actingAs($owner, 'sanctum')->getJson($base.'/fee-invoices/summary')->assertOk()
            ->assertJsonPath('data.invoice_count', 3)
            ->assertJsonPath('data.paid_invoice_count', 0)
            ->assertJsonPath('data.outstanding_invoice_count', 3)
            ->assertJsonPath('data.overdue_invoice_count', 1)
            ->assertJsonPath('data.invoiced_paise', 180000)
            ->assertJsonPath('data.collected_paise', 40000)
            ->assertJsonPath('data.outstanding_paise', 140000)
            ->assertJsonPath('data.overdue_paise', 30000);
        $this->actingAs($studentUser, 'sanctum')->getJson($base.'/fee-invoices/summary')->assertForbidden();
    }

    public function test_invoice_list_paginates_and_filters_issue_dates_in_workspace_timezone(): void
    {
        $organization = Organization::create(['name' => 'Invoice Dates School', 'slug' => 'invoice-dates-school', 'type' => 'school', 'timezone' => 'Asia/Kolkata', 'status' => 'active']);
        $owner = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $student = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'DATE-1', 'name' => 'Invoice Dates Learner', 'status' => 'active']);
        $base = '/api/v1/organizations/'.$organization->id.'/fee-invoices';

        foreach ([
            ['PN-DATE-BEFORE', '2026-11-18T18:29:59Z'],
            ['PN-DATE-START', '2026-11-18T18:30:00Z'],
            ['PN-DATE-END', '2026-11-19T18:29:59Z'],
            ['PN-DATE-AFTER', '2026-11-19T18:30:00Z'],
        ] as [$number, $createdAt]) {
            $invoice = FeeInvoice::create([
                'organization_id' => $organization->id, 'student_id' => $student->id,
                'invoice_number' => $number, 'title' => $number, 'amount_paise' => 10000,
                'status' => 'issued', 'issued_by' => $owner->id,
            ]);
            $invoice->forceFill(['created_at' => CarbonImmutable::parse($createdAt), 'updated_at' => CarbonImmutable::parse($createdAt)])->saveQuietly();
        }

        $this->actingAs($owner, 'sanctum')->getJson($base.'?from=2026-11-19&to=2026-11-19&per_page=1&page=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.invoice_number', 'PN-DATE-END')
            ->assertJsonPath('meta.current_page', 1)->assertJsonPath('meta.last_page', 2)->assertJsonPath('meta.per_page', 1)->assertJsonPath('meta.total', 2);
        $this->actingAs($owner, 'sanctum')->getJson($base.'?from=2026-11-19&to=2026-11-19&per_page=1&page=2')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.invoice_number', 'PN-DATE-START')
            ->assertJsonPath('meta.current_page', 2)->assertJsonPath('meta.total', 2);
        $this->actingAs($owner, 'sanctum')->getJson($base.'?from=2026-11-20&to=2026-11-19')->assertUnprocessable();
        $this->actingAs($owner, 'sanctum')->getJson($base.'?per_page=101')->assertUnprocessable();
    }

    public function test_collections_report_filters_received_dates_and_stays_tenant_and_role_scoped(): void
    {
        $organization = Organization::create(['name' => 'Collections School', 'slug' => 'collections-school', 'type' => 'school', 'timezone' => 'Asia/Kolkata', 'status' => 'active']);
        $owner = User::factory()->create();
        $studentUser = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($studentUser, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        $student = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'COLL-1', 'name' => 'Collections Learner', 'status' => 'active', 'user_id' => $studentUser->id]);
        $base = '/api/v1/organizations/'.$organization->id;
        $addPayment = function (Organization $scope, User $recorder, StudentProfile $learner, string $number, int $amount, string $reference, string $receivedAt): void {
            $invoice = FeeInvoice::create([
                'organization_id' => $scope->id, 'student_id' => $learner->id,
                'invoice_number' => $number, 'title' => 'Tuition', 'amount_paise' => $amount,
                'status' => 'paid', 'issued_by' => $recorder->id,
            ]);
            FeePayment::create([
                'organization_id' => $scope->id, 'invoice_id' => $invoice->id, 'amount_paise' => $amount,
                'method' => 'upi', 'transaction_reference' => $reference,
                'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
                'receipt_number' => 'RCT-'.$reference, 'recorded_by' => $recorder->id,
                'received_at' => CarbonImmutable::parse($receivedAt),
            ]);
        };
        $addPayment($organization, $owner, $student, 'PN-RANGE-BEFORE', 70000, 'RANGE-BEFORE', '2026-11-18T18:29:59Z');
        $addPayment($organization, $owner, $student, 'PN-RANGE-START', 12500, 'RANGE-START', '2026-11-18T18:30:00Z');
        $addPayment($organization, $owner, $student, 'PN-RANGE-END', 40000, 'RANGE-END', '2026-11-19T18:29:59Z');
        $addPayment($organization, $owner, $student, 'PN-RANGE-AFTER', 90000, 'RANGE-AFTER', '2026-11-19T18:30:00Z');

        $otherOrganization = Organization::create(['name' => 'Other Collections School', 'slug' => 'other-collections-school', 'type' => 'school', 'timezone' => 'Asia/Kolkata', 'status' => 'active']);
        $otherOrganization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $otherStudent = StudentProfile::create(['organization_id' => $otherOrganization->id, 'student_code' => 'COLL-2', 'name' => 'Other Learner', 'status' => 'active']);
        $addPayment($otherOrganization, $owner, $otherStudent, 'PN-OTHER-COLLECTION', 50000, 'OTHER-RANGE', '2026-11-19T12:00:00Z');

        $url = $base.'/fee-invoices/collections?from=2026-11-19&to=2026-11-19&per_page=1';
        $this->actingAs($owner, 'sanctum')->getJson($url)
            ->assertOk()->assertJsonPath('summary.payment_count', 2)->assertJsonPath('summary.collected_paise', 52500)
            ->assertJsonPath('data.0.receipt_number', 'RCT-RANGE-END')->assertJsonPath('data.0.student_name', 'Collections Learner')
            ->assertJsonPath('meta.current_page', 1)->assertJsonPath('meta.last_page', 2)->assertJsonPath('meta.total', 2);
        $this->actingAs($owner, 'sanctum')->getJson($url.'&page=2')
            ->assertOk()->assertJsonPath('data.0.receipt_number', 'RCT-RANGE-START');
        $this->actingAs($studentUser, 'sanctum')->getJson($url)->assertForbidden();
        $this->actingAs($owner, 'sanctum')->getJson($base.'/fee-invoices/collections?from=2026-11-20&to=2026-11-19')->assertUnprocessable();
    }
}
