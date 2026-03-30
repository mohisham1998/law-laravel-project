<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LawFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'law_registry_id',
        'uploaded_by_user_id',
        'filename',
        'file_path',
        'file_size',
        'mime_type',
        'encoding',
        'total_articles',
        'is_processed',
        'processed_at',
        'processing_error',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'total_articles' => 'integer',
        'is_processed' => 'boolean',
        'processed_at' => 'datetime',
    ];

    public function lawRegistry(): BelongsTo
    {
        return $this->belongsTo(LawRegistry::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(LawArticle::class);
    }

    /**
     * Derived status for UI: completed, processing, or failed
     */
    public function getProcessingStatusAttribute(): string
    {
        if ($this->is_processed) {
            return 'completed';
        }
        if ($this->processing_error) {
            return 'failed';
        }
        return 'processing';
    }

    public function getHumanReadableSizeAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
