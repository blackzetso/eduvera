<?php

namespace App\Support\FormBuilder;

class FormSubmissionSnapshot
{
    /**
     * @return array<string, mixed>
     */
    public static function fromRuntime(FormRuntimePayload $runtime): array
    {
        return [
            'form_id' => $runtime->formId(),
            'form_version' => (int) ($runtime->form['version'] ?? config('form-builder.version', 2)),
            'snapshot_hash' => $runtime->snapshotHash(),
            'captured_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function read(array $data): array
    {
        $meta = $data['_meta'] ?? [];

        return is_array($meta['snapshot'] ?? null) ? $meta['snapshot'] : [];
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public static function attach(array $values, array $snapshot, array $extraMeta = []): array
    {
        return array_merge($values, [
            '_meta' => array_merge([
                'snapshot' => $snapshot,
            ], $extraMeta),
        ]);
    }
}
