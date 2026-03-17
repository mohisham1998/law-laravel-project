<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'intake_text' => $this->when($request->routeIs('*.show'), $this->intake_text),
            'status' => $this->status->value ?? $this->status,
            'phase' => $this->phase,
            'current_agent' => $this->current_agent,
            'current_agent_name' => $this->when($this->current_agent, fn () => $this->agentName($this->current_agent)),
            'progress_percentage' => $this->progress_percentage,
            'model_used' => $this->model_used,
            'total_tokens' => $this->total_tokens,
            'total_cost_usd' => $this->total_cost_usd ? (float) $this->total_cost_usd : 0,
            'skill_version' => $this->skill_version,
            'skill_hash' => $this->when($request->routeIs('*.show'), $this->skill_hash),
            'documents_count' => $this->whenLoaded('documents', fn () => $this->documents->count(), $this->documents_count ?? null),
            'documents' => $this->when($request->routeIs('*.show'), fn () => $this->documents->map(fn ($d) => [
                'id' => $d->id,
                'filename' => $d->filename,
                'file_size' => $d->file_size,
                'created_at' => $d->created_at?->toIso8601String(),
            ])),
            'required_laws' => $this->when($request->routeIs('*.show'), fn () => $this->requiredLaws->map(fn ($r) => [
                'id' => $r->id,
                'law_name' => $r->law_name,
                'reason' => $r->reason,
                'is_uploaded' => $r->is_uploaded,
            ])),
            'laws' => $this->when($request->routeIs('*.show'), fn () => $this->laws->map(fn ($l) => [
                'id' => $l->id,
                'law_name' => $l->law_name,
                'filename' => $l->filename,
                'file_size' => $l->file_size,
                'created_at' => $l->created_at?->toIso8601String(),
            ])),
            'agent_executions' => $this->when($request->routeIs('*.show'), fn () => $this->agentExecutions->map(fn ($e) => [
                'agent_number' => $e->agent_number,
                'agent_name' => $e->agent_name,
                'status' => $e->status->value ?? $e->status,
                'total_tokens' => $e->total_tokens,
                'cost_usd' => $e->cost_usd ? (float) $e->cost_usd : 0,
                'duration_ms' => $e->duration_ms,
                'started_at' => $e->started_at?->toIso8601String(),
                'completed_at' => $e->completed_at?->toIso8601String(),
            ])),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    protected function agentName(int $n): string
    {
        $names = [
            1 => 'Lead Counsel',
            2 => 'Evidence Manager',
            3 => 'Chain of Custody',
            4 => 'Timeline Extractor',
            5 => 'Law Manager',
            6 => 'Statute Matcher',
            7 => 'Defense Strategist',
            8 => 'Legal Drafter',
            9 => 'Quality Assurance',
            10 => 'Judge',
            11 => "Devil's Advocate",
        ];
        return $names[$n] ?? "Agent {$n}";
    }
}
