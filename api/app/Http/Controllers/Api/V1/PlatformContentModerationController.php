<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Organization;
use App\Models\PlatformContentModeration;
use App\Models\TeacherProfile;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlatformContentModerationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->requirePlatformAdmin($request);
        $validated = $request->validate([
            'type' => ['required', Rule::in(['course', 'educator'])],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $type = $validated['type'];
        $term = trim((string) ($validated['q'] ?? ''));

        if ($type === 'course') {
            $query = Course::query()->where('courses.status', 'active')->where('courses.is_public', true)
                ->whereHas('organization', fn ($organizations) => $organizations->where('status', 'active'))
                ->with('organization:id,name')->orderByDesc('courses.created_at');
            if ($term !== '') {
                $query->where(function ($filter) use ($term): void {
                    $like = '%'.mb_strtolower($term).'%';
                    $filter->whereRaw('LOWER(courses.title) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(courses.code) LIKE ?', [$like])
                        ->orWhereHas('organization', fn ($organizations) => $organizations->whereRaw('LOWER(name) LIKE ?', [$like]));
                });
            }
            $page = $query->paginate(20);
            $moderations = PlatformContentModeration::query()->where('content_type', 'course')->whereIn('content_id', $page->getCollection()->pluck('id'))->get()->keyBy('content_id');
            $items = $page->getCollection()->map(fn (Course $course): array => $this->record('course', $course->id, $course->title, $course->organization_id, $course->organization?->name, $moderations->get($course->id)));
        } else {
            $query = TeacherProfile::query()->where('teacher_profiles.status', 'active')->where('teacher_profiles.is_public', true)
                ->whereNotNull('teacher_profiles.user_id')
                ->whereHas('organization', fn ($organizations) => $organizations->where('status', 'active'))
                ->whereHas('user', fn ($users) => $users->where('account_status', 'active'))
                ->whereExists(fn (Builder $members) => $members->selectRaw('1')->from('organization_user as educator_memberships')
                    ->whereColumn('educator_memberships.organization_id', 'teacher_profiles.organization_id')
                    ->whereColumn('educator_memberships.user_id', 'teacher_profiles.user_id')
                    ->where('educator_memberships.status', 'active')->where('educator_memberships.role', 'teacher'))
                ->with('organization:id,name')->orderBy('teacher_profiles.name');
            if ($term !== '') {
                $query->where(function ($filter) use ($term): void {
                    $like = '%'.mb_strtolower($term).'%';
                    $filter->whereRaw('LOWER(teacher_profiles.name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(teacher_profiles.public_title) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(teacher_profiles.public_bio) LIKE ?', [$like])
                        ->orWhereHas('organization', fn ($organizations) => $organizations->whereRaw('LOWER(name) LIKE ?', [$like]));
                });
            }
            $page = $query->paginate(20);
            $moderations = PlatformContentModeration::query()->where('content_type', 'educator')->whereIn('content_id', $page->getCollection()->pluck('id'))->get()->keyBy('content_id');
            $items = $page->getCollection()->map(fn (TeacherProfile $teacher): array => $this->record('educator', $teacher->id, $teacher->name, $teacher->organization_id, $teacher->organization?->name, $moderations->get($teacher->id)));
        }

        return response()->json(['data' => $items->values(), 'meta' => [
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(),
        ]]);
    }

    public function update(Request $request, string $type, string $contentId): JsonResponse
    {
        $this->requirePlatformAdmin($request);
        abort_unless(in_array($type, ['course', 'educator'], true), 404);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['hidden', 'visible'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $result = DB::transaction(function () use ($request, $type, $contentId, $validated): array {
            $initial = $type === 'course' ? Course::query()->whereKey($contentId)->firstOrFail() : TeacherProfile::query()->whereKey($contentId)->firstOrFail();
            $organization = Organization::query()->whereKey($initial->organization_id)->where('status', 'active')->lockForUpdate()->firstOrFail();
            $content = $type === 'course'
                ? Course::query()->whereKey($contentId)->where('organization_id', $organization->id)->where('status', 'active')->where('is_public', true)->lockForUpdate()->firstOrFail()
                : TeacherProfile::query()->whereKey($contentId)->where('organization_id', $organization->id)->where('status', 'active')->where('is_public', true)->lockForUpdate()->firstOrFail();
            $moderation = PlatformContentModeration::query()->updateOrCreate(
                ['content_type' => $type, 'content_id' => $content->getKey()],
                [
                    'organization_id' => $organization->id,
                    'status' => $validated['status'],
                    'reason' => $validated['status'] === 'hidden' ? ($validated['reason'] ?? null) : null,
                    'moderated_by' => $request->user()->id,
                    'moderated_at' => now(),
                ],
            );
            $details = ['content_type' => $type, 'status' => $moderation->status];
            if ($moderation->status === 'hidden' && $moderation->reason !== null) {
                $details['reason'] = $moderation->reason;
            }
            DB::table('platform_audit_logs')->insert([
                'user_id' => $request->user()->id, 'event' => 'platform.content_moderated',
                'subject_type' => $type, 'subject_id' => (string) $content->getKey(),
                'details' => json_encode($details), 'created_at' => now(),
            ]);

            return $this->record($type, $content->getKey(), $type === 'course' ? $content->title : $content->name, $organization->id, $organization->name, $moderation);
        });

        return response()->json(['data' => $result]);
    }

    private function record(string $type, string $id, string $title, string $organizationId, ?string $organizationName, ?PlatformContentModeration $moderation): array
    {
        return [
            'type' => $type, 'id' => $id, 'title' => $title, 'organization_id' => $organizationId,
            'organization_name' => $organizationName, 'status' => $moderation?->status ?? 'visible',
            'reason' => $moderation?->reason, 'moderated_at' => $moderation?->moderated_at?->toIso8601String(),
        ];
    }

    private function requirePlatformAdmin(Request $request): void
    {
        $emails = config('platform.admin_emails', []);
        abort_unless(in_array(strtolower((string) $request->user()?->email), $emails, true), 403);
    }
}
