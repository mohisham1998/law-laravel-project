<?php

namespace App\Models;

use App\Enums\AgentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentExecution extends Model
{
    use HasFactory;
    /** @var list<string> */
    protected $fillable = [
        'case_id',
        'agent_number',
        'agent_name',
        'status',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'cost_usd',
        'duration_ms',
        'api_latency_ms',
        'error_message',
        'retry_count',
        'started_at',
        'completed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => AgentStatus::class,
        'agent_number' => 'integer',
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
        'cost_usd' => 'decimal:4',
        'duration_ms' => 'integer',
        'api_latency_ms' => 'integer',
        'retry_count' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class);
    }

    public function errorLogs(): HasMany
    {
        return $this->hasMany(ErrorLog::class, 'agent_execution_id');
    }
}
