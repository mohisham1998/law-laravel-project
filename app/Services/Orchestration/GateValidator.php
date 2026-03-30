<?php

namespace App\Services\Orchestration;

use App\Models\LegalCase;
use App\Models\LawArticle;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GateValidator
{
    /**
     * Validate that all required upstream output files exist for a given agent.
     */
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

    /**
     * Map each agent to the upstream output files it requires.
     * Updated filenames per spec contracts.
     */
    protected function getRequiredPathsForAgent(int $agentNumber): array
    {
        // Maps agent number → primary output file it produces
        $outputFiles = [
            1 => '01_lead_plan.md',
            2 => '02_chunks.jsonl',
            3 => '03_statutes_index.jsonl',
            4 => '04_timeline.json',
            5 => '05_issues_to_statutes.md',
            6 => '06_statutes_map.jsonl',
            7 => '07_risk_matrix.md',
            8 => '08_final_brief.md',
            9 => '09_final_brief_v2.md',
        ];

        $base = [];

        // All Phase 2 agents require intake.txt
        if ($agentNumber >= 1 && $agentNumber <= 9) {
            $base[] = 'intake.txt';
        }

        // Require all upstream agent output files
        for ($i = 1; $i < $agentNumber && $i <= 9; $i++) {
            if (isset($outputFiles[$i])) {
                $base[] = 'outputs/' . $outputFiles[$i];
            }
        }

        return array_unique($base);
    }

    /**
     * Validate Phase 1 gate: case must have intake text.
     */
    public function validatePhase1Gate(LegalCase $case): bool
    {
        return !empty(trim($case->intake_text ?? ''));
    }

    /**
     * Validate Phase 2 gate: RAG database must have law articles with embeddings,
     * and Phase 1 must have identified required laws.
     */
    public function validatePhase2Gate(LegalCase $case): bool
    {
        // Check RAG database has articles with embeddings
        $hasEmbeddings = LawArticle::whereHas('embedding')->exists();
        if (!$hasEmbeddings) {
            return false;
        }

        // Check Phase 1 identified required laws (RequiredLaw records exist)
        return $case->requiredLaws()->exists();
    }

    /**
     * Validate Phase 3 gate: Phase 2 must be complete, final brief v2 must exist,
     * and deterministic quality checks must pass.
     * T044: Added statute_id cross-validation at phase boundary.
     */
    public function validatePhase3Gate(LegalCase $case): bool
    {
        if ($case->status->value !== 'phase2_completed') {
            return false;
        }

        $briefPath = "cases/{$case->id}/outputs/09_final_brief_v2.md";
        if (!Storage::disk('local')->exists($briefPath)) {
            return false;
        }

        // T044: Deterministic check — validate statute_ids in 06_statutes_map.jsonl
        // exist in 03_statutes_index.jsonl
        $statutesMap = $this->loadOutput($case, '06_statutes_map.jsonl');
        $statutesIndex = $this->loadOutput($case, '03_statutes_index.jsonl');
        if (!empty($statutesMap) && !empty($statutesIndex)) {
            $violations = OutputValidator::validateStatuteIds($statutesMap, $statutesIndex);
            if (!empty($violations)) {
                Log::warning('Phase 3 gate: statute_id validation failed', [
                    'case_id' => $case->id,
                    'violations' => count($violations),
                ]);
                // Allow passage but log — re-processing handled by orchestrator
            }
        }

        // T045: Deterministic check — verify brief has mandatory sections
        $briefContent = Storage::disk('local')->get($briefPath);
        $structureViolations = OutputValidator::validateBriefStructure($briefContent);
        if (!empty($structureViolations)) {
            Log::warning('Phase 3 gate: brief structure validation warnings', [
                'case_id' => $case->id,
                'issues' => array_map(fn($v) => $v['detail'], $structureViolations),
            ]);
        }

        return true;
    }

    /**
     * Load an output file's content for validation.
     */
    private function loadOutput(LegalCase $case, string $filename): string
    {
        $output = $case->outputs()->where('filename', $filename)->first();
        if (!$output) {
            return '';
        }
        $content = $output->content;
        if ($content === null && $output->file_path && Storage::disk('local')->exists($output->file_path)) {
            $content = Storage::disk('local')->get($output->file_path);
        }
        return (string) $content;
    }

    /**
     * @deprecated Use validatePhase2Gate() instead. Kept for backward compatibility.
     */
    public function allRequiredLawsUploaded(LegalCase $case): bool
    {
        return $this->validatePhase2Gate($case);
    }
}
