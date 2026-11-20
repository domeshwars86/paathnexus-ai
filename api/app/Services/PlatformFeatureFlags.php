<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PlatformFeatureFlags
{
    /**
     * @return array<int, array{key: string, label: string, description: string, enabled: bool, default_enabled: bool, updated_at: ?string, updated_by_id: ?int}>
     */
    public function all(): array
    {
        $definitions = config('features.flags', []);
        $stored = DB::table('feature_flags')->whereIn('key', array_keys($definitions))->get()->keyBy('key');

        return collect($definitions)->map(function (array $definition, string $key) use ($stored): array {
            $record = $stored->get($key);

            return [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'enabled' => $record === null ? (bool) $definition['default_enabled'] : (bool) $record->enabled,
                'default_enabled' => (bool) $definition['default_enabled'],
                'updated_at' => $record?->updated_at,
                'updated_by_id' => $record?->updated_by_id === null ? null : (int) $record->updated_by_id,
            ];
        })->values()->all();
    }

    public function isEnabled(string $key): bool
    {
        $definition = config('features.flags.'.$key);
        if (! is_array($definition)) return false;

        $enabled = DB::table('feature_flags')->where('key', $key)->value('enabled');

        return $enabled === null ? (bool) $definition['default_enabled'] : (bool) $enabled;
    }

    /**
     * @return array{key: string, label: string, description: string, enabled: bool, default_enabled: bool, updated_at: ?string, updated_by_id: ?int}
     */
    public function set(string $key, bool $enabled, int $actorId): array
    {
        $definition = config('features.flags.'.$key);
        abort_unless(is_array($definition), 404);

        return DB::transaction(function () use ($key, $enabled, $actorId, $definition): array {
            $existing = DB::table('feature_flags')->where('key', $key)->lockForUpdate()->first();
            $previous = $existing === null ? (bool) $definition['default_enabled'] : (bool) $existing->enabled;

            if ($previous !== $enabled) {
                $now = now();
                DB::table('feature_flags')->updateOrInsert(
                    ['key' => $key],
                    ['enabled' => $enabled, 'updated_by_id' => $actorId, 'created_at' => $existing?->created_at ?? $now, 'updated_at' => $now],
                );
                DB::table('platform_audit_logs')->insert([
                    'user_id' => $actorId,
                    'event' => $enabled ? 'feature_flag.enabled' : 'feature_flag.disabled',
                    'subject_type' => 'feature_flag',
                    'subject_id' => $key,
                    'details' => json_encode(['from' => $previous, 'to' => $enabled], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                ]);
            }

            return [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'enabled' => $enabled,
                'default_enabled' => (bool) $definition['default_enabled'],
                'updated_at' => $previous === $enabled ? $existing?->updated_at : $now->toDateTimeString(),
                'updated_by_id' => $previous === $enabled ? ($existing?->updated_by_id === null ? null : (int) $existing->updated_by_id) : $actorId,
            ];
        });
    }
}
