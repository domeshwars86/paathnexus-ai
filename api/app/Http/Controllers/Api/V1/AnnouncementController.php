<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Organization;
use App\Models\WorkspaceNotification;
use App\Models\WorkspaceFeedbackNotification;
use App\Services\AnnouncementDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AnnouncementController extends Controller
{
    public function index(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        abort_unless($request->user()->can('view', $organization), 403);
        $canManage = $request->user()->can('manageAcademic', $organization);
        $role = $organization->users()->where('users.id', $request->user()->id)->firstOrFail()->pivot->role;
        $query = Announcement::query()->where('organization_id', $organization->id)
            ->with('creator:id,name')->orderByRaw('COALESCE(published_at, scheduled_at) DESC')->limit(100);
        if ($canManage) {
            $query->whereIn('status', ['published', 'scheduled', 'withdrawn']);
        } else {
            $query->where('status', 'published')
                ->where(fn ($audiences) => $audiences->where('audience', 'all_members')->orWhere('audience', $this->audienceForRole($role)));
        }
        return response()->json(['data' => $query->get()->map(fn (Announcement $announcement): array => $this->announcementData($announcement))]);
    }

    public function store(Request $request, string $organizationId, AnnouncementDelivery $delivery): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:10000'],
            'audience' => ['required', Rule::in(['all_members', 'students', 'guardians', 'teachers'])],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ]);

        $announcement = DB::transaction(function () use ($organization, $request, $validated, $delivery): Announcement {
            $scheduledAt = isset($validated['scheduled_at']) ? \Illuminate\Support\Carbon::parse($validated['scheduled_at']) : null;
            $announcement = Announcement::create([
                'organization_id' => $organization->id, 'title' => $validated['title'],
                'body' => $validated['body'], 'audience' => $validated['audience'],
                'status' => $scheduledAt ? 'scheduled' : 'published', 'created_by' => $request->user()->id,
                'published_at' => $scheduledAt ? null : now(), 'scheduled_at' => $scheduledAt,
            ]);
            if (! $scheduledAt) $delivery->deliver($organization, $announcement);
            return $announcement->load('creator:id,name');
        });

        return response()->json(['data' => [
            ...$this->announcementData($announcement), 'recipient_count' => $announcement->status === 'scheduled' ? null : ($announcement->audience === 'all_members'
                ? $organization->users()->wherePivot('status', 'active')->where('users.account_status', 'active')->count()
                : $organization->users()->wherePivot('status', 'active')->wherePivot('role', $this->roleForAudience($announcement->audience))->where('users.account_status', 'active')->count()),
        ]], 201);
    }

    public function update(Request $request, string $organizationId, string $announcementId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);
        $announcement = Announcement::query()->where('organization_id', $organization->id)
            ->whereIn('status', ['published', 'scheduled'])->whereKey($announcementId)->firstOrFail();
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:10000'],
            'audience' => ['prohibited'],
        ]);

        $announcement->update(['title' => $validated['title'], 'body' => $validated['body']]);

        return response()->json(['data' => $this->announcementData($announcement->fresh()->load('creator:id,name'))]);
    }

    public function withdraw(Request $request, string $organizationId, string $announcementId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);
        $announcement = Announcement::query()->where('organization_id', $organization->id)
            ->whereIn('status', ['published', 'scheduled'])->whereKey($announcementId)->firstOrFail();
        $previousStatus = $announcement->status;
        $announcement->forceFill(['status' => 'withdrawn', 'withdrawn_status' => $previousStatus])->save();

        return response()->json(['data' => $this->announcementData($announcement->fresh()->load('creator:id,name'))]);
    }

    public function restore(Request $request, string $organizationId, string $announcementId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        abort_unless($request->user()->can('manageAcademic', $organization), 403);
        $announcement = Announcement::query()->where('organization_id', $organization->id)
            ->where('status', 'withdrawn')->whereIn('withdrawn_status', ['published', 'scheduled'])
            ->whereKey($announcementId)->firstOrFail();
        $announcement->forceFill(['status' => $announcement->withdrawn_status, 'withdrawn_status' => null])->save();

        return response()->json(['data' => $this->announcementData($announcement->fresh()->load('creator:id,name'))]);
    }

    public function notifications(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        abort_unless($request->user()->can('view', $organization), 403);
        $baseQuery = WorkspaceNotification::query()->where('organization_id', $organization->id)->where('user_id', $request->user()->id)
            ->whereHas('announcement', fn ($announcements) => $announcements->where('status', 'published'));
        $announcementUnread = (clone $baseQuery)->whereNull('read_at')->count();
        $announcementItems = (clone $baseQuery)
            ->with('announcement:id,title,body,audience,published_at')->orderByDesc('created_at')->limit(100)->get();
        $feedbackQuery = WorkspaceFeedbackNotification::query()->where('organization_id', $organization->id)
            ->where('user_id', $request->user()->id);
        $feedbackUnread = (clone $feedbackQuery)->whereNull('read_at')->count();
        $feedbackItems = (clone $feedbackQuery)->with('feedback:id,category,status,response')->orderByDesc('created_at')->limit(100)->get();
        $items = $announcementItems->map(fn (WorkspaceNotification $item): array => [
            'id' => $item->id, 'type' => 'announcement', 'announcement_id' => $item->announcement_id,
            'feedback_id' => null, 'title' => $item->announcement?->title, 'body' => $item->announcement?->body,
            'created_at' => $item->created_at?->toIso8601String(), 'read_at' => $item->read_at?->toIso8601String(),
        ])->concat($feedbackItems->map(fn (WorkspaceFeedbackNotification $item): array => [
            'id' => $item->id, 'type' => 'feedback', 'announcement_id' => null, 'feedback_id' => $item->feedback_id,
            'title' => 'Response to your feedback',
            'body' => $item->feedback?->response
                ? 'Status: '.ucfirst($item->feedback->status).'. '.Str::limit($item->feedback->response, 220)
                : 'A manager updated your feedback status to '.ucfirst($item->feedback?->status ?? 'updated').'.',
            'created_at' => $item->created_at?->toIso8601String(), 'read_at' => $item->read_at?->toIso8601String(),
        ]))->sortByDesc('created_at')->take(100)->values();
        return response()->json(['data' => [
            'unread_count' => $announcementUnread + $feedbackUnread,
            'items' => $items,
        ]]);
    }

    public function markRead(Request $request, string $organizationId, string $notificationId): JsonResponse
    {
        $organization = $this->organization($request, $organizationId);
        abort_unless($request->user()->can('view', $organization), 403);
        $notification = WorkspaceNotification::query()->where('organization_id', $organization->id)
            ->where('user_id', $request->user()->id)->whereKey($notificationId)->first();
        if ($notification) {
            $notification->forceFill(['read_at' => $notification->read_at ?? now()])->save();
        } else {
            $notification = WorkspaceFeedbackNotification::query()->where('organization_id', $organization->id)
                ->where('user_id', $request->user()->id)->whereKey($notificationId)->firstOrFail();
            $notification->forceFill(['read_at' => $notification->read_at ?? now()])->save();
        }
        return response()->json(['data' => ['id' => $notification->id, 'read_at' => $notification->read_at?->toIso8601String()]]);
    }

    private function organization(Request $request, string $organizationId): Organization
    {
        return $request->user()->organizations()->wherePivot('status', 'active')->where('organizations.status', 'active')
            ->where('organizations.id', $organizationId)->firstOrFail();
    }

    private function audienceForRole(string $role): string
    {
        return match ($role) { 'student' => 'students', 'guardian' => 'guardians', 'teacher' => 'teachers', default => 'none' };
    }

    private function roleForAudience(string $audience): ?string
    {
        return match ($audience) { 'students' => 'student', 'guardians' => 'guardian', 'teachers' => 'teacher', default => null };
    }

    private function announcementData(Announcement $announcement): array
    {
        return [
            'id' => $announcement->id, 'title' => $announcement->title, 'body' => $announcement->body,
            'audience' => $announcement->audience, 'status' => $announcement->status,
            'published_at' => $announcement->published_at?->toIso8601String(),
            'scheduled_at' => $announcement->scheduled_at?->toIso8601String(),
            'author_name' => $announcement->creator?->name,
        ];
    }
}
