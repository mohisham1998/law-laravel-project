<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidenceRepositoryEntry extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'case_id',
        'document_id',
        'evidence_type',
        'relevance_score',
        'notes',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'relevance_score' => 'decimal:2',
    ];

    public const EVIDENCE_TYPES = [
        'contract' => 'Contract',
        'correspondence' => 'Correspondence',
        'evidence' => 'Evidence',
        'statute' => 'Statute',
        'expert_report' => 'Expert Report',
        'witness_statement' => 'Witness Statement',
        'other' => 'Other',
    ];

    public const EVIDENCE_TYPES_AR = [
        'contract' => 'عقد',
        'correspondence' => 'مراسلات',
        'evidence' => 'دليل',
        'statute' => 'نظام',
        'expert_report' => 'تقرير خبير',
        'witness_statement' => 'شهادة شاهد',
        'other' => 'أخرى',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(CaseDocument::class, 'document_id');
    }

    public function getEvidenceTypeLabel(): string
    {
        return self::EVIDENCE_TYPES[$this->evidence_type] ?? 'Unknown';
    }

    public function getEvidenceTypeLabelAr(): string
    {
        return self::EVIDENCE_TYPES_AR[$this->evidence_type] ?? 'غير معروف';
    }

    public function isHighRelevance(): bool
    {
        return $this->relevance_score >= 0.70;
    }
}
