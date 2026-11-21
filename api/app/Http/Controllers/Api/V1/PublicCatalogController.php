<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseOrder;
use App\Models\Organization;
use App\Models\StudentProfile;
use App\Models\SubscriptionPlan;
use App\Models\TeacherProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicCatalogController extends Controller
{
    public function courses(Request $request): JsonResponse
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);
        $query = Course::query()->where('status', 'active')->where('is_public', true)
            ->whereNotExists(fn ($moderations) => $moderations->selectRaw('1')->from('platform_content_moderations')
                ->whereColumn('platform_content_moderations.content_id', 'courses.id')
                ->where('platform_content_moderations.content_type', 'course')->where('platform_content_moderations.status', 'hidden'))
            ->whereHas('organization', fn ($organizations) => $organizations->where('status', 'active'))
            ->with(['organization:id,name,slug,type', 'subject:id,name,code', 'instructors:id,name'])
            ->withCount(['lessons' => fn ($lessons) => $lessons->where('status', 'active')])->orderByDesc('created_at');
        if (! empty($validated['q'])) {
            $term = mb_strtolower($validated['q']);
            $query->where(function ($filter) use ($term): void {
                $filter->whereRaw('LOWER(title) LIKE ?', ['%'.$term.'%'])
                    ->orWhereRaw('LOWER(code) LIKE ?', ['%'.$term.'%'])
                    ->orWhereHas('organization', fn ($organizations) => $organizations->whereRaw('LOWER(name) LIKE ?', ['%'.$term.'%']))
                    ->orWhereHas('subject', fn ($subjects) => $subjects->whereRaw('LOWER(name) LIKE ?', ['%'.$term.'%']));
            });
        }
        $page = $query->paginate(12);

        return response()->json(['data' => $page->getCollection()->map(function (Course $course): array {
            $instructors = $course->organization->teacherProfiles()->where('status', 'active')->where('is_public', true)
                ->whereNotExists(fn ($moderations) => $moderations->selectRaw('1')->from('platform_content_moderations')
                    ->whereColumn('platform_content_moderations.content_id', 'teacher_profiles.id')
                    ->where('platform_content_moderations.content_type', 'educator')->where('platform_content_moderations.status', 'hidden'))
                ->whereIn('user_id', $course->instructors->pluck('id'))->orderBy('name')->get(['name', 'public_title', 'public_bio'])
                ->map(fn ($teacher): array => ['name' => $teacher->name, 'title' => $teacher->public_title, 'bio' => $teacher->public_bio])->values();

            return [
                'id' => $course->id, 'code' => $course->code, 'title' => $course->title,
                'description' => $course->description, 'organization_name' => $course->organization?->name,
                'amount_paise' => (int) $course->price_paise,
                'organization_slug' => $course->organization?->slug, 'organization_type' => $course->organization?->type,
                'subject' => $course->subject?->name, 'lesson_count' => (int) $course->lessons_count,
                'instructors' => $instructors,
            ];
        })->values(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]]);
    }

    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::query()->where('is_active', true)->orderBy('amount_paise')->get();
        return response()->json(['data' => $plans->map(fn (SubscriptionPlan $plan): array => [
            'id' => $plan->id, 'code' => $plan->code, 'name' => $plan->name, 'audience' => $plan->audience,
            'description' => $plan->description, 'amount_paise' => $plan->amount_paise,
            'billing_interval' => $plan->billing_interval, 'entitlements' => $plan->entitlements ?? [],
        ])]);
    }

    public function educators(Request $request): JsonResponse
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);
        $query = TeacherProfile::query()->where('status', 'active')->where('is_public', true)->whereNotNull('user_id')
            ->whereNotExists(fn ($moderations) => $moderations->selectRaw('1')->from('platform_content_moderations')
                ->whereColumn('platform_content_moderations.content_id', 'teacher_profiles.id')
                ->where('platform_content_moderations.content_type', 'educator')->where('platform_content_moderations.status', 'hidden'))
            ->whereHas('organization', fn ($organizations) => $organizations->where('status', 'active'))
            ->whereHas('user', fn ($users) => $users->where('account_status', 'active'))
            ->whereExists(fn ($members) => $members->selectRaw('1')->from('organization_user as educator_memberships')
                ->whereColumn('educator_memberships.organization_id', 'teacher_profiles.organization_id')
                ->whereColumn('educator_memberships.user_id', 'teacher_profiles.user_id')
                ->where('educator_memberships.status', 'active')->where('educator_memberships.role', 'teacher'))
            ->with('organization:id,name,slug,type')->orderBy('name');

        if (! empty($validated['q'])) {
            $term = mb_strtolower($validated['q']);
            $query->where(function ($filter) use ($term): void {
                $filter->whereRaw('LOWER(name) LIKE ?', ['%'.$term.'%'])
                    ->orWhereRaw('LOWER(public_title) LIKE ?', ['%'.$term.'%'])
                    ->orWhereRaw('LOWER(public_bio) LIKE ?', ['%'.$term.'%'])
                    ->orWhereHas('organization', fn ($organizations) => $organizations->whereRaw('LOWER(name) LIKE ?', ['%'.$term.'%']));
            });
        }

        $page = $query->paginate(12);

        return response()->json(['data' => $page->getCollection()->map(fn (TeacherProfile $teacher): array => [
            'id' => $teacher->id, 'name' => $teacher->name, 'title' => $teacher->public_title,
            'bio' => $teacher->public_bio, 'organization_name' => $teacher->organization?->name,
            'organization_slug' => $teacher->organization?->slug, 'organization_type' => $teacher->organization?->type,
        ])->values(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]]);
    }

    public function educator(string $educatorId): JsonResponse
    {
        $teacher = TeacherProfile::query()->whereKey($educatorId)->where('status', 'active')->where('is_public', true)
            ->whereNotNull('user_id')
            ->whereNotExists(fn ($moderations) => $moderations->selectRaw('1')->from('platform_content_moderations')
                ->whereColumn('platform_content_moderations.content_id', 'teacher_profiles.id')
                ->where('platform_content_moderations.content_type', 'educator')->where('platform_content_moderations.status', 'hidden'))
            ->whereHas('organization', fn ($organizations) => $organizations->where('status', 'active'))
            ->whereHas('user', fn ($users) => $users->where('account_status', 'active'))
            ->whereExists(fn ($members) => $members->selectRaw('1')->from('organization_user as educator_memberships')
                ->whereColumn('educator_memberships.organization_id', 'teacher_profiles.organization_id')
                ->whereColumn('educator_memberships.user_id', 'teacher_profiles.user_id')
                ->where('educator_memberships.status', 'active')->where('educator_memberships.role', 'teacher'))
            ->with('organization:id,name,slug,type')->firstOrFail();

        return response()->json(['data' => [
            'id' => $teacher->id, 'name' => $teacher->name, 'title' => $teacher->public_title,
            'bio' => $teacher->public_bio, 'organization_name' => $teacher->organization?->name,
            'organization_slug' => $teacher->organization?->slug, 'organization_type' => $teacher->organization?->type,
        ]]);
    }

    public function enroll(Request $request, string $courseId): JsonResponse
    {
        $course = Course::query()->whereKey($courseId)->where('status', 'active')->where('is_public', true)
            ->whereNotExists(fn ($moderations) => $moderations->selectRaw('1')->from('platform_content_moderations')
                ->whereColumn('platform_content_moderations.content_id', 'courses.id')
                ->where('platform_content_moderations.content_type', 'course')->where('platform_content_moderations.status', 'hidden'))
            ->whereHas('organization', fn ($organizations) => $organizations->where('status', 'active'))
            ->with('organization')->firstOrFail();
        abort_if($course->price_paise > 0, 402, 'This course requires payment. Checkout is not available yet.');
        $result = DB::transaction(function () use ($course, $request): array {
            $organization = Organization::query()->whereKey($course->organization_id)->where('status', 'active')->lockForUpdate()->firstOrFail();
            $isActiveStudentMember = $organization->users()->where('users.id', $request->user()->id)
                ->wherePivot('status', 'active')->wherePivot('role', 'student')->lockForUpdate()->exists();
            abort_unless($isActiveStudentMember, 403, 'An active student membership in this workspace is required.');
            $student = StudentProfile::query()->where('organization_id', $organization->id)->where('user_id', $request->user()->id)
                ->where('status', 'active')->lockForUpdate()->firstOrFail();
            $lockedCourse = Course::query()->whereKey($course->id)->where('organization_id', $organization->id)
                ->where('status', 'active')->where('is_public', true)
                ->whereNotExists(fn ($moderations) => $moderations->selectRaw('1')->from('platform_content_moderations')
                    ->whereColumn('platform_content_moderations.content_id', 'courses.id')
                    ->where('platform_content_moderations.content_type', 'course')->where('platform_content_moderations.status', 'hidden'))
                ->lockForUpdate()->firstOrFail();
            abort_if($lockedCourse->price_paise > 0, 402, 'This course requires payment. Checkout is not available yet.');
            $existing = CourseEnrollment::query()->where('organization_id', $lockedCourse->organization_id)
                ->where('course_id', $lockedCourse->id)->where('student_id', $student->id)->lockForUpdate()->first();
            if ($existing?->status === 'active') return compact('organization', 'lockedCourse', 'existing');
            if ($existing) {
                $existing->forceFill(['status' => 'active', 'enrolled_by' => $request->user()->id, 'enrolled_at' => now()])->save();
                return ['organization' => $organization, 'lockedCourse' => $lockedCourse, 'existing' => $existing->refresh()];
            }
            $enrollment = CourseEnrollment::create([
                'organization_id' => $lockedCourse->organization_id, 'course_id' => $lockedCourse->id,
                'student_id' => $student->id, 'status' => 'active',
                'enrolled_by' => $request->user()->id, 'enrolled_at' => now(),
            ]);
            return ['organization' => $organization, 'lockedCourse' => $lockedCourse, 'existing' => $enrollment];
        });
        $organization = $result['organization'];
        $course = $result['lockedCourse'];
        $enrollment = $result['existing'];

        return response()->json(['data' => [
            'id' => $enrollment->id, 'course_id' => $course->id, 'course_title' => $course->title,
            'organization_id' => $organization->id, 'organization_name' => $organization->name,
            'status' => $enrollment->status, 'enrolled_at' => $enrollment->enrolled_at?->toIso8601String(),
        ]]);
    }

    public function createCourseOrder(Request $request, string $courseId): JsonResponse
    {
        abort_unless(config('services.payments.gateway') === 'sandbox', 503, 'Course checkout is not enabled.');
        $course = Course::query()->whereKey($courseId)->where('status', 'active')->where('is_public', true)
            ->whereNotExists(fn ($moderations) => $moderations->selectRaw('1')->from('platform_content_moderations')
                ->whereColumn('platform_content_moderations.content_id', 'courses.id')
                ->where('platform_content_moderations.content_type', 'course')->where('platform_content_moderations.status', 'hidden'))
            ->whereHas('organization', fn ($organizations) => $organizations->where('status', 'active'))
            ->firstOrFail();
        $result = DB::transaction(function () use ($course, $request): array {
            $organization = Organization::query()->whereKey($course->organization_id)->where('status', 'active')->lockForUpdate()->firstOrFail();
            $isStudent = $organization->users()->where('users.id', $request->user()->id)
                ->wherePivot('status', 'active')->wherePivot('role', 'student')->lockForUpdate()->exists();
            abort_unless($isStudent, 403, 'An active student membership in this workspace is required.');
            $student = StudentProfile::query()->where('organization_id', $organization->id)->where('user_id', $request->user()->id)
                ->where('status', 'active')->lockForUpdate()->firstOrFail();
            $lockedCourse = Course::query()->whereKey($course->id)->where('organization_id', $organization->id)
                ->where('status', 'active')->where('is_public', true)
                ->whereNotExists(fn ($moderations) => $moderations->selectRaw('1')->from('platform_content_moderations')
                    ->whereColumn('platform_content_moderations.content_id', 'courses.id')
                    ->where('platform_content_moderations.content_type', 'course')->where('platform_content_moderations.status', 'hidden'))
                ->lockForUpdate()->firstOrFail();
            abort_if($lockedCourse->price_paise < 1, 422, 'Free courses do not need a checkout order.');
            abort_if(CourseEnrollment::query()->where('organization_id', $organization->id)->where('course_id', $lockedCourse->id)
                ->where('student_id', $student->id)->where('status', 'active')->exists(), 409, 'This learner is already enrolled in the course.');

            $pending = CourseOrder::query()->where('organization_id', $organization->id)->where('course_id', $lockedCourse->id)
                ->where('student_id', $student->id)->where('status', 'pending')->lockForUpdate()->latest('created_at')->first();
            if ($pending && $pending->expires_at->isFuture()) return ['order' => $pending, 'created' => false];
            if ($pending) $pending->forceFill(['status' => 'expired'])->save();

            return ['order' => CourseOrder::create([
                'organization_id' => $organization->id, 'course_id' => $lockedCourse->id,
                'student_id' => $student->id, 'buyer_user_id' => $request->user()->id,
                'amount_paise' => $lockedCourse->price_paise, 'status' => 'pending',
                'provider' => 'sandbox', 'expires_at' => now()->addMinutes(30),
            ]), 'created' => true];
        });
        return response()->json(['data' => $this->courseOrderData($result['order']), 'checkout_mode' => 'sandbox'], $result['created'] ? 201 : 200);
    }

    public function showCourseOrder(Request $request, string $orderId): JsonResponse
    {
        $order = CourseOrder::query()->where('buyer_user_id', $request->user()->id)->whereKey($orderId)->firstOrFail();
        return response()->json(['data' => $this->courseOrderData($order)]);
    }

    private function courseOrderData(CourseOrder $order): array
    {
        return [
            'id' => $order->id, 'course_id' => $order->course_id, 'organization_id' => $order->organization_id,
            'amount_paise' => (int) $order->amount_paise, 'status' => $order->status,
            'expires_at' => $order->expires_at?->toIso8601String(), 'paid_at' => $order->paid_at?->toIso8601String(),
        ];
    }
}
