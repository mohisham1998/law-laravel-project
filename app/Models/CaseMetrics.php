<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseMetrics extends Model
{
    use HasUuids;

    protected $table = 'case_metrics';

    /** @var list<string> */
    protected $fillable = [
        'case_id',
        'total_duration_seconds',
        'total_tokens',
        'statutes_matched',
        'average_confidence',
        'corrections_count',
        'items_for_review',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'total_duration_seconds' => 'integer',
        'total_tokens' => 'integer',
        'statutes_matched' => 'integer',
        'average_confidence' => 'decimal:2',
        'corrections_count' => 'integer',
        'items_for_review' => 'array',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public static function upsertForCase(LegalCase $case): self
    {
        $case->load(['agentExecutions']);
        $executions = $case->agentExecutions;
        $totalDuration = $executions->sum('duration_ms');
        $totalTokens = $executions->sum('total_tokens');
        return self::updateOrCreate(
            ['case_id' => $case->id],
            [
                'total_duration_seconds' => (int) round($totalDuration / 1000),
                'total_tokens' => (int) $totalTokens,
                'statutes_matched' => 0,
                'average_confidence' => 0,
                'corrections_count' => 0,
                'items_for_review' => null,
            ]
        );
    }
}
