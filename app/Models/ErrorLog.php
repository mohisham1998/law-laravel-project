<?php

namespace App\Models;

use App\Enums\ErrorType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErrorLog extends Model
{
    use HasFactory;
    /** @var list<string> */
    protected $fillable = [
        'case_id',
        'agent_execution_id',
        'agent_number',
        'error_type',
        'error_details',
        'fix_applied',
        'lesson_learned',
        'confidence_score',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'error_type' => ErrorType::class,
        'agent_number' => 'integer',
        'confidence_score' => 'decimal:3',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class);
    }

    public function agentExecution(): BelongsTo
    {
        return $this->belongsTo(AgentExecution::class);
    }
}
