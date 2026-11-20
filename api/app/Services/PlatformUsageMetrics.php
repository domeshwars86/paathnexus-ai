<?php

namespace App\Services;

use App\Models\PlatformUsageSnapshot;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PlatformUsageMetrics
{
    /** @return array<string, int> */
    public function current(): array
    {
        $activeOrganizations = DB::table('organizations')->where('status', 'active')->select('id');

        return [
            'organization_count' => DB::table('organizations')->count(),
            'active_organization_count' => DB::table('organizations')->where('status', 'active')->count(),
            'suspended_organization_count' => DB::table('organizations')->where('status', 'suspended')->count(),
            'active_membership_count' => DB::table('organization_user as membership')->join('organizations', 'organizations.id', '=', 'membership.organization_id')
                ->where('organizations.status', 'active')->where('membership.status', 'active')->count(),
            'active_student_count' => DB::table('student_profiles')->where('status', 'active')->whereIn('organization_id', $activeOrganizations)->count(),
            'active_course_count' => DB::table('courses')->where('status', 'active')->whereIn('organization_id', $activeOrganizations)->count(),
            'active_subscription_count' => DB::table('organization_subscriptions')->where('status', 'active')->whereIn('organization_id', $activeOrganizations)->count(),
        ];
    }

    public function capture(?Carbon $date = null): PlatformUsageSnapshot
    {
        return PlatformUsageSnapshot::query()->updateOrCreate(
            ['snapshot_date' => ($date ?? Carbon::now('UTC'))->toDateString()],
            $this->current(),
        );
    }

    /** @return Collection<int, PlatformUsageSnapshot> */
    public function history(int $days): Collection
    {
        $from = Carbon::now('UTC')->subDays($days - 1)->toDateString();

        return PlatformUsageSnapshot::query()->whereDate('snapshot_date', '>=', $from)
            ->orderBy('snapshot_date')->get();
    }
}
