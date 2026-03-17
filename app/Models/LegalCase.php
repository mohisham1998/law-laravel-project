<?php

namespace App\Models;

use App\Enums\CaseStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class LegalCase extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected static function newFactory()
    {
        return \Database\Factories\CaseFactory::new();
    }

    protected $table = 'cases';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'title',
        'intake_text',
        'status',
        'phase',
        'current_agent',
        'progress_percentage',
        'skill_version',
        'skill_hash',
        'model_used',
        'total_tokens',
        'total_cost_usd',
        'started_at',
        'completed_at',
        'last_failed_phase',
        'last_error_message',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => CaseStatus::class,
        'phase' => 'integer',
        'current_agent' => 'integer',
        'progress_percentage' => 'integer',
        'total_tokens' => 'integer',
        'total_cost_usd' => 'decimal:4',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CaseDocument::class, 'case_id');
    }

    public function laws(): HasMany
    {
        return $this->hasMany(CaseLaw::class, 'case_id');
    }

    public function requiredLaws(): HasMany
    {
        return $this->hasMany(RequiredLaw::class, 'case_id');
    }

    public function outputs(): HasMany
    {
        return $this->hasMany(CaseOutput::class, 'case_id');
    }

    public function agentExecutions(): HasMany
    {
        return $this->hasMany(AgentExecution::class, 'case_id');
    }

    public function metrics(): HasOne
    {
        return $this->hasOne(CaseMetrics::class, 'case_id');
    }

    public function errorLogs(): HasMany
    {
        return $this->hasMany(ErrorLog::class, 'case_id');
    }

    public function evidenceEntries(): HasMany
    {
        return $this->hasMany(EvidenceRepositoryEntry::class, 'case_id');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', CaseStatus::Failed);
    }

    public function scopeRetryable($query)
    {
        return $query->where('status', CaseStatus::Failed)->whereNotNull('last_failed_phase');
    }

    public function canRetry(): bool
    {
        return $this->status === CaseStatus::Failed && !is_null($this->last_failed_phase);
    }

    public function markAsFailed(string $phase, string $errorMessage): void
    {
        $this->update([
            'status' => CaseStatus::Failed,
            'last_failed_phase' => $phase,
            'last_error_message' => $errorMessage,
        ]);
    }

    public function clearFailure(): void
    {
        $this->update([
            'last_failed_phase' => null,
            'last_error_message' => null,
        ]);
    }
}
