<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Limits teachers to courses they created or were explicitly assigned to teach. */
class TeacherCourseScope
{
    public function courses(Organization $organization, User $user): HasMany
    {
        $courses = $organization->courses();
        $membership = $organization->users()->where('users.id', $user->id)->first();
        if ($membership?->pivot->role === 'teacher') {
            $courses->where(function ($query) use ($user, $organization): void {
                $query->where('created_by', $user->id)->orWhereHas('instructors', function ($instructors) use ($user, $organization): void {
                    $instructors->where('users.id', $user->id)->where('course_instructors.organization_id', $organization->id);
                });
            });
        }

        return $courses;
    }
}
