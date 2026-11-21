<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizationTaskController extends Controller
{
    public function index(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('viewTasks', $organization), 403);

        $tasks = $organization->tasks()->with('assignee:id,name')
            ->orderByRaw('CASE WHEN archived_at IS NULL THEN 0 ELSE 1 END')
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (OrganizationTask $task): array => $this->data($task));

        return response()->json(['data' => $tasks]);
    }

    public function assignees(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageTasks', $organization), 403);

        $members = $organization->users()
            ->wherePivot('status', 'active')
            ->wherePivotIn('role', ['owner', 'admin', 'educator', 'teacher'])
            ->where('users.account_status', 'active')
            ->orderBy('users.name')
            ->get(['users.id', 'users.name'])
            ->map(fn ($member): array => [
                'id' => (string) $member->id,
                'name' => $member->name,
                'role' => $member->pivot->role,
            ]);

        return response()->json(['data' => $members]);
    }

    public function store(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageTasks', $organization), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'min:1', $this->activeStaffAssigneeRule($organization)],
        ]);

        $task = $organization->tasks()->create([
            ...$validated,
            'created_by' => $request->user()->id,
            'status' => 'pending',
        ]);

        return response()->json(['data' => $this->data($task->load('assignee:id,name'))], 201);
    }

    public function complete(Request $request, string $organizationId, string $taskId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageTasks', $organization), 403);

        $task = DB::transaction(function () use ($organization, $taskId): OrganizationTask {
            $task = $organization->tasks()->whereKey($taskId)->lockForUpdate()->firstOrFail();
            abort_if($task->archived_at !== null, 422, 'Restore this task before completing it.');
            if ($task->status !== 'completed') {
                $task->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
            }

            return $task->refresh();
        });

        return response()->json(['data' => $this->data($task)]);
    }

    public function update(Request $request, string $organizationId, string $taskId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageTasks', $organization), 403);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'min:1', $this->activeStaffAssigneeRule($organization)],
        ]);

        abort_if($validated === [], 422, 'Provide at least one task field to update.');

        $task = DB::transaction(function () use ($organization, $taskId, $validated): OrganizationTask {
            $task = $organization->tasks()->whereKey($taskId)->lockForUpdate()->firstOrFail();
            abort_if($task->archived_at !== null, 422, 'Restore this task before editing it.');
            $task->fill($validated)->save();

            return $task->refresh()->load('assignee:id,name');
        });

        return response()->json(['data' => $this->data($task)]);
    }

    public function archive(Request $request, string $organizationId, string $taskId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageTasks', $organization), 403);

        $task = DB::transaction(function () use ($organization, $taskId): OrganizationTask {
            $task = $organization->tasks()->whereKey($taskId)->lockForUpdate()->firstOrFail();
            if ($task->archived_at === null) {
                $task->forceFill(['archived_at' => now()])->save();
            }

            return $task->refresh()->load('assignee:id,name');
        });

        return response()->json(['data' => $this->data($task)]);
    }

    public function restore(Request $request, string $organizationId, string $taskId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageTasks', $organization), 403);

        $task = DB::transaction(function () use ($organization, $taskId): OrganizationTask {
            $task = $organization->tasks()->whereKey($taskId)->lockForUpdate()->firstOrFail();
            if ($task->archived_at !== null) {
                $task->forceFill(['archived_at' => null])->save();
            }

            return $task->refresh()->load('assignee:id,name');
        });

        return response()->json(['data' => $this->data($task)]);
    }

    private function memberOrganization(Request $request, string $organizationId): Organization
    {
        return $request->user()->organizations()
            ->wherePivot('status', 'active')
            ->where('organizations.status', 'active')
            ->where('organizations.id', $organizationId)
            ->firstOrFail();
    }

    private function activeStaffAssigneeRule(Organization $organization): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($organization): void {
            if ($value === null) {
                return;
            }

            $isActiveStaffMember = $organization->users()
                ->whereKey($value)
                ->wherePivot('status', 'active')
                ->wherePivotIn('role', ['owner', 'admin', 'educator', 'teacher'])
                ->where('users.account_status', 'active')
                ->exists();

            if (! $isActiveStaffMember) {
                $fail('Select an active staff member from this workspace.');
            }
        };
    }

    private function data(OrganizationTask $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'due_at' => $task->due_at?->toIso8601String(),
            'assigned_to' => $task->assigned_to ? (string) $task->assigned_to : null,
            'assigned_to_name' => $task->assignee?->name,
            'status' => $task->status,
            'completed_at' => $task->completed_at?->toIso8601String(),
            'archived_at' => $task->archived_at?->toIso8601String(),
            'created_at' => $task->created_at?->toIso8601String(),
        ];
    }
}
