<?php

namespace App\Services\Orchestration;

use Illuminate\Support\Facades\File;

class PromptBuilder
{
    public function __construct(protected string $skillPath)
    {
    }

    public static function fromConfig(): self
    {
        $path = config('legal.skill_path', base_path('.agent/skills/legal-counsel/SKILL.md'));
        return new self($path);
    }

    public function getSkillContent(): string
    {
        if (!File::exists($this->skillPath)) {
            return "# Legal Counsel Skill\n\nSKILL.md not found at configured path.";
        }
        return File::get($this->skillPath);
    }

    public function getSkillHash(): string
    {
        return hash('sha256', $this->getSkillContent());
    }

    public function buildPromptForAgent(int $agentNumber, string $context): string
    {
        $skill = $this->getSkillContent();
        $base = $skill . "\n\n---\n## Context for Agent {$agentNumber}\n\n{$context}\n\n---\n";
        if ($agentNumber === 0) {
            $base .= "Execute Phase 1 analysis. At the end of your response, include a JSON block with the required laws:\n```json\n{\"required_laws\": [{\"law_name\": \"نظام الإثبات\", \"reason\": \"...\"}]}\n```";
        } else {
            $base .= "Please execute the task for Agent {$agentNumber} according to the SKILL instructions above.";
        }
        return $base;
    }
}
