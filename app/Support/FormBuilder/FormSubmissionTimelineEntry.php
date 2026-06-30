<?php

namespace App\Support\FormBuilder;

class FormSubmissionTimelineEntry
{
    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public static function make(
        string $event,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?string $workflowStage = null,
        ?FormRuntimeContext $context = null,
        ?string $comment = null,
        array $meta = [],
    ): array {
        return [
            'at' => now()->toIso8601String(),
            'event' => $event,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'workflow_stage' => $workflowStage,
            'actor' => self::resolveActor($context),
            'comment' => $comment,
            'meta' => $meta,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function resolveActor(?FormRuntimeContext $context): array
    {
        if ($context?->userId) {
            return [
                'type' => 'user',
                'id' => $context->userId,
            ];
        }

        return [
            'type' => 'anonymous',
            'id' => null,
        ];
    }
}
