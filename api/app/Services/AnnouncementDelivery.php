<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Organization;
use App\Models\WorkspaceNotification;
use Illuminate\Support\Str;

class AnnouncementDelivery
{
    public function deliver(Organization $organization, Announcement $announcement): int
    {
        $recipients = $organization->users()->wherePivot('status', 'active')
            ->where('users.account_status', 'active')->orderBy('users.id');
        $targetRole = match ($announcement->audience) {
            'students' => 'student', 'guardians' => 'guardian', 'teachers' => 'teacher', default => null,
        };
        if ($targetRole) $recipients->wherePivot('role', $targetRole);

        $now = now();
        $rows = $recipients->pluck('users.id')->map(fn ($userId): array => [
            'id' => (string) Str::uuid(), 'organization_id' => $organization->id,
            'announcement_id' => $announcement->id, 'user_id' => $userId,
            'read_at' => null, 'created_at' => $now,
        ])->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            WorkspaceNotification::query()->insertOrIgnore($chunk);
        }

        return count($rows);
    }
}
