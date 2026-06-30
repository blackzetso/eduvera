<?php

namespace App\Services\Admission;

use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionStageHistory;
use App\Support\Admission\AdmissionStage;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AdmissionPipelineService
{
    public function recordInitialStage(
        AdmissionApplication $application,
        string $toStage,
        ?string $reason = null,
        ?string $notes = null,
        ?int $performedByUserId = null,
    ): AdmissionStageHistory {
        if (! AdmissionStage::isValid($toStage)) {
            throw new InvalidArgumentException("Invalid admission stage: {$toStage}");
        }

        return AdmissionStageHistory::create([
            'admission_application_id' => $application->id,
            'from_stage' => null,
            'to_stage' => $toStage,
            'reason' => $reason,
            'notes' => $notes,
            'performed_by_user_id' => $performedByUserId,
            'effective_at' => now(),
        ]);
    }

    public function transition(
        AdmissionApplication $application,
        string $toStage,
        ?string $reason = null,
        ?string $notes = null,
        ?int $performedByUserId = null,
    ): AdmissionApplication {
        if (! AdmissionStage::isValid($toStage)) {
            throw new InvalidArgumentException("Invalid admission stage: {$toStage}");
        }

        $fromStage = $application->pipeline_stage;

        if ($fromStage === $toStage) {
            return $application;
        }

        if ($fromStage && ! AdmissionStage::canTransition($fromStage, $toStage)) {
            throw new InvalidArgumentException(
                "Cannot transition from {$fromStage} to {$toStage}"
            );
        }

        return DB::transaction(function () use (
            $application,
            $fromStage,
            $toStage,
            $reason,
            $notes,
            $performedByUserId
        ) {
            $application->forceFill(['pipeline_stage' => $toStage])->save();

            AdmissionStageHistory::create([
                'admission_application_id' => $application->id,
                'from_stage' => $fromStage,
                'to_stage' => $toStage,
                'reason' => $reason,
                'notes' => $notes,
                'performed_by_user_id' => $performedByUserId,
                'effective_at' => now(),
            ]);

            return $application->fresh();
        });
    }
}
