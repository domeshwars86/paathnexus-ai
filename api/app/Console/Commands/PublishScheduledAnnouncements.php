<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Models\Organization;
use App\Services\AnnouncementDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PublishScheduledAnnouncements extends Command
{
    protected $signature = 'announcements:publish-scheduled {--limit=100}';

    protected $description = 'Publish due workspace announcements and create in-app notifications';

    public function handle(AnnouncementDelivery $delivery): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $ids = Announcement::query()->where('status', 'scheduled')->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())->orderBy('scheduled_at')->limit($limit)->pluck('id');
        $publishedCount = 0;

        foreach ($ids as $id) {
            $published = DB::transaction(function () use ($id, $delivery): bool {
                $announcement = Announcement::query()->whereKey($id)->lockForUpdate()->first();
                if (! $announcement || $announcement->status !== 'scheduled' || ! $announcement->scheduled_at || $announcement->scheduled_at->isFuture()) {
                    return false;
                }

                $organization = Organization::query()->whereKey($announcement->organization_id)->firstOrFail();
                $announcement->forceFill(['status' => 'published', 'published_at' => now(), 'scheduled_at' => null])->save();
                $delivery->deliver($organization, $announcement);

                return true;
            });

            if ($published) $publishedCount++;
        }

        $this->info("Published {$publishedCount} scheduled announcement(s).");

        return self::SUCCESS;
    }
}
