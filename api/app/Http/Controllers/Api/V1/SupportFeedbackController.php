<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\SupportFeedback;
use App\Models\WorkspaceFeedbackNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SupportFeedbackController extends Controller
{
    public function index(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageMembers', $organization), 403);

        $items = SupportFeedback::query()->with(['user:id,name', 'responder:id,name'])->where('organization_id', $organization->id)
            ->latest()->limit(100)->get()
            ->map(fn (SupportFeedback $item): array => $this->data($item, true));

        return response()->json(['data' => $items]);
    }

    public function mine(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        $items = SupportFeedback::query()->where('organization_id', $organization->id)
            ->where('user_id', $request->user()->id)->latest()->limit(100)->get()
            ->map(fn (SupportFeedback $item): array => $this->data($item));

        return response()->json(['data' => $items]);
    }

    public function store(Request $request, string $organizationId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        $request->merge(['message' => trim((string) $request->input('message', ''))]);
        $validated = $request->validate([
            'category' => ['required', Rule::in(['bug', 'idea', 'support', 'other'])],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $feedback = SupportFeedback::create([
            'organization_id' => $organization->id,
            'user_id' => $request->user()->id,
            ...$validated,
            'status' => 'open',
            'response' => null,
            'responded_by' => null,
            'responded_at' => null,
        ]);

        return response()->json(['data' => $this->data($feedback)], 201);
    }

    public function update(Request $request, string $organizationId, string $feedbackId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageMembers', $organization), 403);
        $feedback = SupportFeedback::query()->where('organization_id', $organization->id)->whereKey($feedbackId)->firstOrFail();
        $validated = $request->validate([
            'status' => ['required', Rule::in(['open', 'resolved'])],
            'response' => ['nullable', 'string', 'max:5000'],
        ]);
        $response = array_key_exists('response', $validated)
            ? (trim((string) $validated['response']) ?: null)
            : $feedback->response;
        $statusChanged = $feedback->status !== $validated['status'];
        $responseChanged = $feedback->response !== $response;

        DB::transaction(function () use ($feedback, $request, $validated, $response, $statusChanged, $responseChanged): void {
            $attributes = ['status' => $validated['status'], 'response' => $response];
            if ($responseChanged) {
                $attributes['responded_by'] = $response === null ? null : $request->user()->id;
                $attributes['responded_at'] = $response === null ? null : Carbon::now();
            }
            $feedback->forceFill($attributes)->save();

            if ($statusChanged || $responseChanged) {
                WorkspaceFeedbackNotification::query()->updateOrCreate(
                    ['organization_id' => $feedback->organization_id, 'feedback_id' => $feedback->id, 'user_id' => $feedback->user_id],
                    ['read_at' => null, 'created_at' => now()],
                );
            }
        });

        return response()->json(['data' => $this->data($feedback->fresh()->load('responder:id,name'), true)]);
    }

    private function memberOrganization(Request $request, string $organizationId): Organization
    {
        return $request->user()->organizations()
            ->wherePivot('status', 'active')
            ->where('organizations.status', 'active')
            ->where('organizations.id', $organizationId)
            ->firstOrFail();
    }

    private function data(SupportFeedback $feedback, bool $includeSubmitter = false): array
    {
        return [
            'id' => $feedback->id,
            'category' => $feedback->category,
            'message' => $feedback->message,
            'status' => $feedback->status,
            'response' => $feedback->response,
            'created_at' => $feedback->created_at?->toIso8601String(),
            'responded_at' => $feedback->responded_at?->toIso8601String(),
            ...($includeSubmitter ? [
                'submitter_name' => $feedback->user?->name,
                'responder_name' => $feedback->responder?->name,
            ] : []),
        ];
    }
}
