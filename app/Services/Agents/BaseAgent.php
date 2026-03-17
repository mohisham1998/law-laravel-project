<?php

namespace App\Services\Agents;

use App\Models\LegalCase;
use App\Services\Orchestration\GateValidator;
use App\Services\Orchestration\PromptBuilder;

abstract class BaseAgent
{
    public function __construct(
        protected PromptBuilder $promptBuilder,
        protected GateValidator $gateValidator,
    ) {
    }

    abstract public function agentNumber(): int;

    abstract public function agentName(): string;

    public function validateGate(LegalCase $case): bool
    {
        $missing = $this->gateValidator->validateGateForAgent($case, $this->agentNumber());
        return empty($missing);
    }

    abstract public function execute(LegalCase $case): array;
}
