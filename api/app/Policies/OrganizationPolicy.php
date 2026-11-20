<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        return $this->allows($user, $organization, 'organization.view');
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->allows($user, $organization, 'organization.update');
    }

    public function viewMembers(User $user, Organization $organization): bool
    {
        return $this->allows($user, $organization, 'members.view');
    }

    public function viewStudents(User $user, Organization $organization): bool
    {
        return $this->allows($user, $organization, 'students.view');
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        return $this->allows($user, $organization, 'members.manage');
    }

    public function viewTasks(User $user, Organization $organization): bool
    {
        return $this->allows($user, $organization, 'tasks.view');
    }

    public function manageTasks(User $user, Organization $organization): bool
    {
        return $this->allows($user, $organization, 'tasks.manage');
    }

    public function viewAttendance(User $user, Organization $organization): bool
    {
        return $this->allows($user, $organization, 'attendance.read');
    }

    public function manageAttendance(User $user, Organization $organization): bool
    {
        return $this->allows($user, $organization, 'attendance.write');
    }

    public function viewAcademic(User $user, Organization $organization): bool
    {
        return $this->allows($user, $organization, 'academic.view');
    }

    public function manageAcademic(User $user, Organization $organization): bool
    {
        return $this->allows($user, $organization, 'academic.manage');
    }

    public function viewCourses(User $user, Organization $organization): bool
    {
        return $this->allows($user, $organization, 'courses.view');
    }

    public function manageCourses(User $user, Organization $organization): bool
    {
        return $this->allows($user, $organization, 'courses.manage');
    }

    public function manageAssessments(User $user, Organization $organization): bool
    {
        return $this->allows($user, $organization, 'assessments.manage');
    }

    public function manageLiveSessions(User $user, Organization $organization): bool
    {
        return $this->allows($user, $organization, 'live_sessions.manage');
    }

    public function viewFeeInvoices(User $user, Organization $organization): bool
    {
        return $this->allows($user, $organization, 'fees.view');
    }

    public function viewResults(User $user, Organization $organization): bool
    {
        return $this->allows($user, $organization, 'results.view');
    }

    public function viewReports(User $user, Organization $organization): bool
    {
        return $this->allows($user, $organization, 'reports.view');
    }

    public function takeAssessments(User $user, Organization $organization): bool
    {
        return $this->allows($user, $organization, 'assessments.take');
    }

    public function viewLiveSessions(User $user, Organization $organization): bool
    {
        return $this->allows($user, $organization, 'live_sessions.view');
    }

    private function allows(User $user, Organization $organization, string $permission): bool
    {
        if ($user->account_status !== 'active') {
            return false;
        }

        $membership = $organization->users()
            ->where('users.id', $user->getKey())
            ->where('users.account_status', 'active')
            ->wherePivot('status', 'active')
            ->first();

        if (! $membership) {
            return false;
        }

        $customRoleId = $membership->pivot->custom_role_id ?? null;
        if ($customRoleId !== null) {
            $customRole = $organization->customRoles()->whereKey($customRoleId)->first();
            if (! $customRole || $customRole->base_role !== $membership->pivot->role) return false;
            $permissions = $customRole->permissions;
            if (! is_array($permissions)) return false;
        } else {
            $permissions = config('organization_permissions.roles.'.$membership->pivot->role, []);
        }

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }
}
