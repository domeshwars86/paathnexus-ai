<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseOrder;
use App\Models\Organization;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CourseCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_sandbox_course_order_is_reused_and_signed_capture_grants_enrollment_once(): void
    {
        config(['services.payments.gateway' => 'disabled', 'services.payments.sandbox_webhook_secret' => 'course-test-secret']);
        [$organization, $course, $studentUser, $student] = $this->context();
        $orderUrl = '/api/v1/catalog/courses/'.$course->id.'/orders';
        $this->actingAs($studentUser, 'sanctum')->postJson($orderUrl)->assertStatus(503);
        $this->assertDatabaseCount('course_orders', 0);

        config(['services.payments.gateway' => 'sandbox']);
        $first = $this->actingAs($studentUser, 'sanctum')->postJson($orderUrl)->assertCreated()
            ->assertJsonPath('checkout_mode', 'sandbox')->assertJsonPath('data.amount_paise', 12550)
            ->assertJsonPath('data.status', 'pending');
        $orderId = $first->json('data.id');
        $this->actingAs($studentUser, 'sanctum')->postJson($orderUrl)->assertOk()->assertJsonPath('data.id', $orderId);
        $this->actingAs($studentUser, 'sanctum')->getJson('/api/v1/catalog/course-orders/'.$orderId)->assertOk()
            ->assertJsonPath('data.status', 'pending')->assertJsonPath('data.amount_paise', 12550);
        $this->actingAs($studentUser, 'sanctum')->getJson('/api/v1/catalog/course-orders/not-a-uuid')->assertNotFound();
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/v1/catalog/course-orders/'.$orderId)->assertNotFound();

        // A published price change after order creation does not change the amount owed on that order.
        $course->forceFill(['price_paise' => 50000])->save();
        $event = [...$this->event($organization->id, $orderId, 'course-event-1', 12550), 'transaction_reference' => 'COURSE-SANDBOX-1'];
        $this->deliver($event)->assertOk()->assertJsonPath('status', 'processed')->assertJsonPath('order_id', $orderId);
        $this->deliver($event)->assertOk()->assertJsonPath('status', 'duplicate')->assertJsonPath('order_id', $orderId);

        $this->assertDatabaseCount('course_orders', 1);
        $this->assertDatabaseHas('course_orders', [
            'id' => $orderId, 'status' => 'paid', 'amount_paise' => 12550,
            'transaction_reference' => 'COURSE-SANDBOX-1', 'provider_event_id' => 'course-event-1',
        ]);
        $this->assertDatabaseHas('course_enrollments', [
            'organization_id' => $organization->id, 'course_id' => $course->id,
            'student_id' => $student->id, 'status' => 'active', 'enrolled_by' => $studentUser->id,
        ]);
        $this->assertDatabaseHas('payment_webhook_events', ['event_id' => 'course-event-1', 'course_order_id' => $orderId, 'payment_id' => null]);
        $this->assertDatabaseCount('course_enrollments', 1);
    }

    public function test_course_order_requires_a_linked_active_student_and_capture_must_match_exact_total(): void
    {
        config(['services.payments.gateway' => 'sandbox', 'services.payments.sandbox_webhook_secret' => 'course-test-secret']);
        [$organization, $course, $studentUser] = $this->context();
        $unlinked = User::factory()->create();
        $organization->users()->attach($unlinked, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        $this->actingAs($unlinked, 'sanctum')->postJson('/api/v1/catalog/courses/'.$course->id.'/orders')->assertNotFound();

        $created = $this->actingAs($studentUser, 'sanctum')->postJson('/api/v1/catalog/courses/'.$course->id.'/orders')->assertCreated();
        $orderId = $created->json('data.id');
        $this->deliver($this->event($organization->id, $orderId, 'course-event-bad-total', 12549))->assertUnprocessable();
        $this->assertDatabaseHas('course_orders', ['id' => $orderId, 'status' => 'pending']);
        $this->assertDatabaseCount('course_enrollments', 0);

        $event = $this->event($organization->id, $orderId, 'course-event-2', 12550);
        $this->deliver($event)->assertOk();
        $changed = [...$event, 'amount_paise' => 12551];
        $this->deliver($changed)->assertStatus(409);
        $this->assertDatabaseCount('course_enrollments', 1);
    }

    public function test_course_order_expiry_and_gateway_mode_are_enforced(): void
    {
        config(['services.payments.gateway' => 'sandbox', 'services.payments.sandbox_webhook_secret' => 'course-test-secret']);
        [$organization, $course, $studentUser] = $this->context();
        $order = $this->actingAs($studentUser, 'sanctum')->postJson('/api/v1/catalog/courses/'.$course->id.'/orders')->assertCreated();
        $orderId = $order->json('data.id');
        CourseOrder::query()->whereKey($orderId)->update(['expires_at' => now()->subSecond()]);

        $this->deliver($this->event($organization->id, $orderId, 'course-event-expired', 12550))->assertStatus(409);
        config(['services.payments.gateway' => 'disabled']);
        $this->deliver($this->event($organization->id, $orderId, 'course-event-disabled', 12550))->assertNotFound();
        $this->assertDatabaseCount('course_enrollments', 0);
    }

    public function test_moderating_a_course_after_order_creation_prevents_capture_and_enrollment(): void
    {
        config(['services.payments.gateway' => 'sandbox', 'services.payments.sandbox_webhook_secret' => 'course-test-secret']);
        [$organization, $course, $studentUser] = $this->context();
        $order = $this->actingAs($studentUser, 'sanctum')->postJson('/api/v1/catalog/courses/'.$course->id.'/orders')->assertCreated();
        $orderId = $order->json('data.id');
        $admin = User::factory()->create(['email' => 'moderator@example.test']);
        config(['platform.admin_emails' => [$admin->email]]);

        $this->actingAs($admin, 'sanctum')->putJson('/api/v1/platform/content-moderation/course/'.$course->id, ['status' => 'hidden'])
            ->assertOk()->assertJsonPath('data.status', 'hidden');
        $this->deliver($this->event($organization->id, $orderId, 'moderated-course-capture', 12550))->assertStatus(409);

        $this->assertDatabaseHas('course_orders', ['id' => $orderId, 'status' => 'pending']);
        $this->assertDatabaseCount('course_enrollments', 0);
    }

    private function context(): array
    {
        $organization = Organization::create(['name' => 'Course Shop', 'slug' => 'course-shop-'.Str::lower(Str::random(6)), 'type' => 'educator', 'status' => 'active']);
        $owner = User::factory()->create();
        $studentUser = User::factory()->create();
        $organization->users()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $organization->users()->attach($studentUser, ['role' => 'student', 'status' => 'active', 'joined_at' => now()]);
        $student = StudentProfile::create(['organization_id' => $organization->id, 'student_code' => 'SHOP-'.Str::upper(Str::random(5)), 'name' => 'Buyer', 'status' => 'active', 'user_id' => $studentUser->id]);
        $course = Course::create(['organization_id' => $organization->id, 'code' => 'PAID-1', 'title' => 'Paid Course', 'status' => 'active', 'is_public' => true, 'price_paise' => 12550, 'created_by' => $owner->id]);
        return [$organization, $course, $studentUser, $student];
    }

    private function event(string $organizationId, string $orderId, string $eventId, int $amountPaise): array
    {
        return [
            'event_id' => $eventId, 'type' => 'course.payment_captured', 'organization_id' => $organizationId,
            'course_order_id' => $orderId, 'amount_paise' => $amountPaise, 'method' => 'upi',
            'transaction_reference' => 'COURSE-SANDBOX-'.Str::upper(Str::random(8)),
        ];
    }

    private function deliver(array $event)
    {
        $raw = json_encode($event, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $raw, 'course-test-secret');
        return $this->call('POST', '/api/v1/webhooks/payments/sandbox', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_PAATHNEXUS_SIGNATURE' => $signature,
        ], $raw);
    }
}
