<?php

namespace App\Services\Orchestration;

use App\Models\LegalCase;
use Illuminate\Support\Facades\Storage;

class GateValidator
{
    public function validateGateForAgent(LegalCase $case, int $agentNumber): array
    {
        $requiredPaths = $this->getRequiredPathsForAgent($agentNumber);
        $missing = [];
        foreach ($requiredPaths as $relativePath) {
            $fullPath = "cases/{$case->id}/{$relativePath}";
            if (!Storage::disk('local')->exists($fullPath)) {
                $missing[] = $fullPath;
            }
        }
        return $missing;
    }

    protected function getRequiredPathsForAgent(int $agentNumber): array
    {
        $base = [];
        $outputFiles = [
            1 => '01_lead_plan.md',
            2 => '02_evidence_index.json',
            3 => '03_chain_of_custody.json',
            4 => '04_timeline.json',
            5 => '05_laws_summary.md',
            6 => '06_statutes_index.jsonl',
            7 => '07_defense_strategy.md',
            8 => '08_legal_brief_v2.md',
            9 => '09_qa_report.md',
        ];
        for ($i = 1; $i < $agentNumber && $i <= 9; $i++) {
            if (isset($outputFiles[$i])) {
                $base[] = 'outputs/' . $outputFiles[$i];
            }
        }
        if ($agentNumber >= 1 && $agentNumber <= 9) {
            $base[] = 'intake.txt';
            // Laws are supplied from the RAG law library (الأنظمة والقوانين), not per-case uploads
        }
        return array_unique($base);
    }

    public function validatePhase1Gate(LegalCase $case): bool
    {
        return !empty(trim($case->intake_text ?? ''));
    }

    public function allRequiredLawsUploaded(LegalCase $case): bool
    {
        return $case->requiredLaws()->where('is_uploaded', false)->doesntExist();
    }
}
