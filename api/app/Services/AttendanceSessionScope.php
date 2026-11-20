<?php

namespace App\Services;

use App\Models\LiveSession;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Shares the attendance-history visibility rules between its index and search. */
class AttendanceSessionScope
{
    public function forMember(
        Organization $organization,
        User $user,
        TeacherClassScope $teacherClassScope,
        TeacherCourseScope $teacherCourseScope,
    ): HasMany {
        $query = $organization->attendanceSessions();
        $assignmentScopes = $teacherClassScope->assignmentScopes($user, $organization);
        if ($assignmentScopes === null) return $query;

        $wholeClassIds = $assignmentScopes->whereNull('section_id')->pluck('class_id')->unique();
        $sectionIds = $assignmentScopes->whereNotNull('section_id')->pluck('section_id')->unique();
        $courseSessionIds = LiveSession::query()->where('organization_id', $organization->id)
            ->whereIn('course_id', $teacherCourseScope->courses($organization, $user)->select('courses.id'))
            ->select('id');

        return $query->where(fn ($scope) => $scope->whereIn('class_id', $wholeClassIds)
            ->orWhereIn('section_id', $sectionIds)
            ->orWhere(fn ($legacy) => $legacy->whereNull('class_id')->where('recorded_by', $user->id))
            ->orWhereIn('live_session_id', $courseSessionIds));
    }
}
