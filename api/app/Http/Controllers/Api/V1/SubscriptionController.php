<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\SubscriptionPlan;
use App\Services\PlatformUsageMetrics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    public function show(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        abort_unless($request->user()->can('view', $organization), 403);
        $subscription = OrganizationSubscription::query()->with(['plan', 'requestedPlan'])
            ->where('organization_id', $organization->id)->firstOrFail();
        $audience = $organization->type === 'educator' ? 'educator' : 'institution';
        $plans = SubscriptionPlan::query()->where('is_active', true)->whereIn('audience', ['all', $audience])
            ->orderBy('amount_paise')->orderBy('name')->get()->map(fn (SubscriptionPlan $plan): array => $this->planData($plan));
        return response()->json(['data' => [
            'status' => $subscription->status, 'plan' => $this->planData($subscription->plan),
            'requested_plan' => $subscription->requestedPlan ? $this->planData($subscription->requestedPlan) : null,
            'requested_at' => $subscription->requested_at?->toIso8601String(), 'available_plans' => $plans,
        ]]);
    }

    public function requestChange(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        $membership = $organization->users()->where('users.id', $request->user()->id)->firstOrFail();
        abort_unless($membership->pivot->role === 'owner' && $request->user()->can('view', $organization), 403);
        $validated = $request->validate(['plan_id' => ['required', 'uuid', 'exists:subscription_plans,id']]);
        $plan = SubscriptionPlan::query()->where('is_active', true)->whereKey($validated['plan_id'])->firstOrFail();
        $audience = $organization->type === 'educator' ? 'educator' : 'institution';
        abort_unless(in_array($plan->audience, ['all', $audience], true), 422, 'This plan is not available for this workspace type.');
        return DB::transaction(function () use ($organization, $plan, $request): JsonResponse {
            $subscription = OrganizationSubscription::query()->with('plan')->where('organization_id', $organization->id)->lockForUpdate()->firstOrFail();
            abort_if($subscription->status === 'change_pending', 409, 'A plan change request is already pending.');
            if ($plan->id === $subscription->plan_id) {
                return response()->json(['data' => ['status' => $subscription->status, 'plan' => $this->planData($subscription->plan)]]);
            }
            if ($plan->amount_paise === 0) {
                $subscription->forceFill(['plan_id' => $plan->id, 'requested_plan_id' => null, 'status' => 'active', 'requested_at' => null])->save();
                $this->audit($request, 'subscription.free_plan_changed', 'organization', $organization->id, ['plan_id' => $plan->id]);
                return response()->json(['data' => ['status' => 'active', 'plan' => $this->planData($plan)]]);
            }
            $subscription->forceFill(['requested_plan_id' => $plan->id, 'status' => 'change_pending', 'requested_at' => now()])->save();
            $this->audit($request, 'subscription.plan_change_requested', 'organization', $organization->id, ['plan_id' => $plan->id]);
            return response()->json(['data' => [
                'status' => $subscription->status, 'plan' => $this->planData($subscription->plan),
                'requested_plan' => $this->planData($plan), 'requested_at' => $subscription->requested_at?->toIso8601String(),
            ]], 202);
        });
    }

    public function plans(Request $request): JsonResponse
    {
        $this->requirePlatformAdmin($request);
        return response()->json(['data' => SubscriptionPlan::query()->orderBy('name')->get()->map(fn (SubscriptionPlan $plan): array => $this->planData($plan))]);
    }

    public function usage(Request $request, PlatformUsageMetrics $metrics): JsonResponse
    {
        $this->requirePlatformAdmin($request);
        return response()->json(['data' => $metrics->current()]);
    }

    public function usageHistory(Request $request, PlatformUsageMetrics $metrics): JsonResponse
    {
        $this->requirePlatformAdmin($request);
        $validated = $request->validate(['days' => ['sometimes', 'integer', 'min:7', 'max:365']]);
        $days = (int) ($validated['days'] ?? 90);

        return response()->json(['data' => [
            'days' => $days,
            'snapshots' => $metrics->history($days)->map(fn ($snapshot): array => [
                'snapshot_date' => $snapshot->snapshot_date->toDateString(),
                'organization_count' => $snapshot->organization_count,
                'active_organization_count' => $snapshot->active_organization_count,
                'suspended_organization_count' => $snapshot->suspended_organization_count,
                'active_membership_count' => $snapshot->active_membership_count,
                'active_student_count' => $snapshot->active_student_count,
                'active_course_count' => $snapshot->active_course_count,
                'active_subscription_count' => $snapshot->active_subscription_count,
            ])->values(),
        ]]);
    }

    public function organizations(Request $request): JsonResponse
    {
        $this->requirePlatformAdmin($request);
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'suspended'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = Organization::query()->select('organizations.*')
            ->leftJoin('organization_subscriptions as subscriptions', 'subscriptions.organization_id', '=', 'organizations.id')
            ->leftJoin('subscription_plans as plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->addSelect('plans.name as current_plan_name', 'subscriptions.status as subscription_status')
            ->withCount([
                'users as active_members_count' => fn ($users) => $users->where('organization_user.status', 'active'),
                'students as active_students_count' => fn ($students) => $students->where('status', 'active'),
                'courses as active_courses_count' => fn ($courses) => $courses->where('status', 'active'),
            ]);
        if (! empty($validated['status'])) $query->where('organizations.status', $validated['status']);
        if (! empty($validated['q'])) {
            $term = mb_strtolower($validated['q']);
            $query->where(fn ($search) => $search->whereRaw('LOWER(organizations.name) LIKE ?', ['%'.$term.'%'])
                ->orWhereRaw('LOWER(organizations.slug) LIKE ?', ['%'.$term.'%']));
        }
        $page = $query->orderByDesc('organizations.created_at')->paginate(20);
        return response()->json([
            'data' => $page->getCollection()->map(fn (Organization $organization): array => [
                'id' => $organization->id, 'name' => $organization->name, 'slug' => $organization->slug,
                'type' => $organization->type, 'status' => $organization->status,
                'current_plan_name' => $organization->current_plan_name, 'subscription_status' => $organization->subscription_status,
                'active_members_count' => (int) $organization->active_members_count,
                'active_students_count' => (int) $organization->active_students_count,
                'active_courses_count' => (int) $organization->active_courses_count,
                'created_at' => $organization->created_at?->toIso8601String(),
            ])->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $this->requirePlatformAdmin($request);
        $validated = $request->validate([
            'event' => ['nullable', 'string', 'max:100'],
            'subject_id' => ['nullable', 'string', 'max:64'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = DB::table('platform_audit_logs')->select(['id', 'user_id', 'event', 'subject_type', 'subject_id', 'created_at']);
        if (! empty($validated['event'])) $query->where('event', $validated['event']);
        if (! empty($validated['subject_id'])) $query->where('subject_id', $validated['subject_id']);
        $page = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(25);
        return response()->json([
            'data' => $page->getCollection()->map(fn (object $entry): array => [
                'id' => (int) $entry->id, 'actor_id' => $entry->user_id,
                'event' => $entry->event, 'subject_type' => $entry->subject_type,
                'subject_id' => $entry->subject_id,
                'created_at' => (string) $entry->created_at,
            ])->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function updateOrganizationStatus(Request $request, string $organizationId): JsonResponse
    {
        $this->requirePlatformAdmin($request);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $result = DB::transaction(function () use ($request, $organizationId, $validated): array {
            $organization = Organization::query()->whereKey($organizationId)->lockForUpdate()->firstOrFail();
            $previousStatus = $organization->status;
            if ($previousStatus !== $validated['status']) {
                $organization->forceFill(['status' => $validated['status']])->save();
                $this->audit($request, 'organization.status_changed', 'organization', $organization->id, [
                    'from' => $previousStatus, 'to' => $validated['status'], 'note' => $validated['note'] ?? null,
                ]);
            }
            return ['id' => $organization->id, 'name' => $organization->name, 'status' => $organization->status];
        });
        return response()->json(['data' => $result]);
    }

    public function createPlan(Request $request): JsonResponse
    {
        $this->requirePlatformAdmin($request);
        $validated = $request->validate([
            'code' => ['required', 'string', 'alpha_dash', 'max:50', 'unique:subscription_plans,code'],
            'name' => ['required', 'string', 'max:120'], 'audience' => ['required', Rule::in(['all', 'institution', 'educator'])],
            'description' => ['nullable', 'string', 'max:2000'], 'amount_paise' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'billing_interval' => ['required', Rule::in(['month', 'year', 'custom'])],
            'entitlements' => ['required', 'array:max_students,max_courses,max_teachers,ai_requests_per_day,storage_mb'],
            'entitlements.max_students' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'entitlements.max_courses' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'entitlements.max_teachers' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'entitlements.ai_requests_per_day' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'entitlements.storage_mb' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);
        $plan = SubscriptionPlan::create([...$validated, 'is_active' => true]);
        $this->audit($request, 'plan.created', 'subscription_plan', $plan->id, ['code' => $plan->code]);
        return response()->json(['data' => $this->planData($plan)], 201);
    }

    public function updatePlan(Request $request, string $planId): JsonResponse
    {
        $this->requirePlatformAdmin($request);
        $plan = SubscriptionPlan::query()->whereKey($planId)->firstOrFail();
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'], 'audience' => ['sometimes', 'required', Rule::in(['all', 'institution', 'educator'])],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'], 'amount_paise' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000000'],
            'billing_interval' => ['sometimes', 'required', Rule::in(['month', 'year', 'custom'])],
            'entitlements' => ['sometimes', 'required', 'array:max_students,max_courses,max_teachers,ai_requests_per_day,storage_mb'],
            'entitlements.max_students' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'entitlements.max_courses' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'entitlements.max_teachers' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'entitlements.ai_requests_per_day' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'entitlements.storage_mb' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        abort_if($plan->code === 'free' && array_key_exists('is_active', $validated) && ! $validated['is_active'], 422, 'The Free plan must remain available to active workspaces.');
        $plan->fill($validated)->save();
        $this->audit($request, 'plan.updated', 'subscription_plan', $plan->id, ['fields' => array_keys($validated)]);
        return response()->json(['data' => $this->planData($plan->fresh())]);
    }

    public function pendingRequests(Request $request): JsonResponse
    {
        $this->requirePlatformAdmin($request);
        $items = OrganizationSubscription::query()->with(['plan', 'requestedPlan'])->where('status', 'change_pending')
            ->orderBy('requested_at')->get();
        $organizationNames = Organization::query()->whereIn('id', $items->pluck('organization_id'))->pluck('name', 'id');
        $data = $items->map(fn (OrganizationSubscription $subscription): array => [
            'organization_id' => $subscription->organization_id,
            'organization_name' => $organizationNames->get($subscription->organization_id),
            'plan' => $this->planData($subscription->plan), 'requested_plan' => $this->planData($subscription->requestedPlan),
            'requested_at' => $subscription->requested_at?->toIso8601String(),
        ]);
        return response()->json(['data' => $data]);
    }

    public function resolveRequest(Request $request, string $organizationId): JsonResponse
    {
        $this->requirePlatformAdmin($request);
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])], 'verification_reference' => ['required_if:decision,approve', 'nullable', 'string', 'max:180'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        DB::transaction(function () use ($request, $validated, $organizationId): void {
            $subscription = OrganizationSubscription::query()->where('organization_id', $organizationId)
                ->where('status', 'change_pending')->lockForUpdate()->firstOrFail();
            $requestedPlanId = $subscription->requested_plan_id;
            $requestedPlan = SubscriptionPlan::query()->whereKey($requestedPlanId)->firstOrFail();
            abort_if($validated['decision'] === 'approve' && $requestedPlan->amount_paise !== 0 && blank($validated['verification_reference'] ?? null), 422, 'A verified contract or payment reference is required for a paid or custom-priced plan.');
            if ($validated['decision'] === 'approve') {
                $subscription->forceFill(['plan_id' => $requestedPlanId, 'requested_plan_id' => null, 'status' => 'active', 'requested_at' => null])->save();
            } else {
                $subscription->forceFill(['requested_plan_id' => null, 'status' => 'active', 'requested_at' => null])->save();
            }
            DB::table('platform_audit_logs')->insert([
                'user_id' => $request->user()->id, 'event' => 'subscription.request_'.$validated['decision'],
                'subject_type' => 'organization', 'subject_id' => $organizationId,
                'details' => json_encode(['plan_id' => $requestedPlanId, 'verification_reference' => $validated['verification_reference'] ?? null, 'note' => $validated['note'] ?? null]),
                'created_at' => now(),
            ]);
        });
        return response()->json(['data' => ['organization_id' => $organizationId, 'status' => 'active', 'decision' => $validated['decision']]]);
    }

    private function requirePlatformAdmin(Request $request): void
    {
        abort_unless(in_array(strtolower($request->user()->email), config('platform.admin_emails', []), true), 403);
    }

    private function organization(Request $request, string $organizationId): Organization
    {
        return $request->user()->organizations()->wherePivot('status', 'active')->where('organizations.status', 'active')
            ->where('organizations.id', $organizationId)->firstOrFail();
    }

    private function planData(SubscriptionPlan $plan): array
    {
        return ['id' => $plan->id, 'code' => $plan->code, 'name' => $plan->name, 'audience' => $plan->audience,
            'description' => $plan->description, 'amount_paise' => $plan->amount_paise, 'billing_interval' => $plan->billing_interval,
            'entitlements' => $plan->entitlements ?? [], 'is_active' => $plan->is_active];
    }

    private function audit(Request $request, string $event, string $type, string $id, array $details): void
    {
        DB::table('platform_audit_logs')->insert(['user_id' => $request->user()->id, 'event' => $event,
            'subject_type' => $type, 'subject_id' => $id, 'details' => json_encode($details), 'created_at' => now()]);
    }
}
