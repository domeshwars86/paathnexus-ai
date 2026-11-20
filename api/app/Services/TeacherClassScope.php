<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resolves active-year whole-class and section assignments for a linked teacher.
 * Null means the caller is not a teacher and should use its broader capability scope.
 */
class TeacherClassScope
{
    public function classIds(User $user, Organization $organization): ?Collection
    {
        $scopes = $this->assignmentScopes($user, $organization);
        if ($scopes === null) return null;
        return $scopes->whereNull('section_id')->pluck('class_id')->unique()->values();
    }

    public function assignedClassIds(User $user, Organization $organization): ?Collection
    {
        $scopes = $this->assignmentScopes($user, $organization);
        return $scopes === null ? null : $scopes->pluck('class_id')->unique()->values();
    }

    public function assignmentScopes(User $user, Organization $organization): ?Collection
    {
        $membership = $organization->users()->where('users.id', $user->id)->first();
        if ($membership?->pivot->role !== 'teacher') return null;
        $profile = $organization->teacherProfiles()->where('user_id', $user->id)->where('status', 'active')->first();
        $yearId = $organization->academicYears()->where('status', 'active')->value('id');
        if (! $profile || ! $yearId) return collect();

        return DB::table('class_teacher_assignments')->where('organization_id', $organization->id)
            ->where('teacher_profile_id', $profile->id)->where('academic_year_id', $yearId)
            ->whereNull('ended_at')
            ->where(function ($query) use ($organization): void {
                $query->whereNull('section_id')->orWhereIn('section_id', DB::table('organization_sections')
                    ->where('organization_id', $organization->id)->where('status', 'active')->select('id'));
            })
            ->whereIn('class_id', $organization->classes()->where('status', 'active')->select('id'))
            ->get(['class_id', 'section_id']);
    }

    public function studentIds(Organization $organization, Collection $classIds): Collection
    {
        if ($classIds->isEmpty()) return collect();
        return DB::table('enrollments')->join('student_profiles', function ($join): void {
                $join->on('student_profiles.id', '=', 'enrollments.student_id')
                    ->on('student_profiles.organization_id', '=', 'enrollments.organization_id');
            })->where('enrollments.organization_id', $organization->id)->where('enrollments.status', 'active')
            ->where('student_profiles.status', 'active')->whereIn('enrollments.class_id', $classIds)
            ->pluck('enrollments.student_id')->unique()->values();
    }

    public function assignedStudentIds(User $user, Organization $organization): ?Collection
    {
        $scopes = $this->assignmentScopes($user, $organization);
        if ($scopes === null) return null;
        if ($scopes->isEmpty()) return collect();

        $query = DB::table('enrollments')->join('student_profiles', function ($join): void {
            $join->on('student_profiles.id', '=', 'enrollments.student_id')
                ->on('student_profiles.organization_id', '=', 'enrollments.organization_id');
        })->where('enrollments.organization_id', $organization->id)
            ->where('enrollments.status', 'active')->where('student_profiles.status', 'active')
            ->where(function ($scopeQuery) use ($scopes): void {
                foreach ($scopes as $assignment) {
                    $scopeQuery->orWhere(function ($assignmentQuery) use ($assignment): void {
                        $assignmentQuery->where('enrollments.class_id', $assignment->class_id);
                        if ($assignment->section_id !== null) {
                            $assignmentQuery->where('enrollments.section_id', $assignment->section_id);
                        }
                    });
                }
            });

        return $query->pluck('enrollments.student_id')->unique()->values();
    }
}
