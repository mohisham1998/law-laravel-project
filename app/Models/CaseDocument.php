<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaseDocument extends Model
{
    use HasFactory;
    use SoftDeletes;
    /** @var list<string> */
    protected $fillable = [
        'case_id',
        'filename',
        'file_path',
        'file_size',
        'mime_type',
        'encoding',
        'deleted_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'file_size' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function evidenceEntries(): HasMany
    {
        return $this->hasMany(EvidenceRepositoryEntry::class, 'document_id');
    }

    public function getHumanReadableSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function isImage(): bool
    {
        return in_array($this->mime_type, ['image/jpeg', 'image/png', 'image/gif']);
    }

    public function isText(): bool
    {
        return $this->mime_type === 'text/plain';
    }

    public function isDocx(): bool
    {
        return $this->mime_type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }
}
