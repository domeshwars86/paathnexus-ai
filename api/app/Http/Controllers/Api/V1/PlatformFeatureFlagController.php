<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PlatformFeatureFlags;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformFeatureFlagController extends Controller
{
    public function index(Request $request, PlatformFeatureFlags $flags): JsonResponse
    {
        $this->requirePlatformAdmin($request);

        return response()->json(['data' => $flags->all()]);
    }

    public function update(Request $request, string $flag, PlatformFeatureFlags $flags): JsonResponse
    {
        $this->requirePlatformAdmin($request);
        abort_unless(array_key_exists($flag, config('features.flags', [])), 404);
        $validated = $request->validate(['enabled' => ['required', 'boolean']]);

        return response()->json(['data' => $flags->set($flag, (bool) $validated['enabled'], (int) $request->user()->id)]);
    }

    private function requirePlatformAdmin(Request $request): void
    {
        $emails = config('platform.admin_emails', []);
        abort_unless(in_array(strtolower((string) $request->user()?->email), $emails, true), 403);
    }
}
