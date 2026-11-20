<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationSubscription;

class PlanEntitlements
{
    public function limit(Organization $organization, string $key): ?int
    {
        $subscription = OrganizationSubscription::query()->with('plan')
            ->where('organization_id', $organization->id)->first();
        $entitlements = $subscription?->plan?->entitlements ?? [];
        $limit = $entitlements[$key] ?? null;
        return is_numeric($limit) ? (int) $limit : null;
    }

    public function assertBelowLimit(Organization $organization, string $key, int $currentCount): void
    {
        $limit = $this->limit($organization, $key);
        abort_if($limit !== null && $currentCount >= $limit, 422, 'The workspace has reached its current plan limit. Contact your workspace owner to review the plan.');
    }
}
