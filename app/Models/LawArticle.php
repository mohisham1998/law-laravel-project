<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LawArticle extends Model
{
    use HasFactory;

    protected $fillable = [
        'law_registry_id',
        'law_file_id',
        'article_number',
        'article_text',
        'article_context',
        'start_line',
        'end_line',
        'keywords',
        'metadata',
    ];

    protected $casts = [
        'start_line' => 'integer',
        'end_line' => 'integer',
        'keywords' => 'array',
        'metadata' => 'array',
    ];

    public function lawRegistry(): BelongsTo
    {
        return $this->belongsTo(LawRegistry::class);
    }

    public function lawFile(): BelongsTo
    {
        return $this->belongsTo(LawFile::class);
    }

    public function embedding(): HasOne
    {
        return $this->hasOne(LawEmbedding::class);
    }

    public function hasEmbedding(): bool
    {
        return $this->embedding()->exists();
    }

    public function getFullReferenceAttribute(): string
    {
        return "{$this->lawRegistry->name} - المادة {$this->article_number}";
    }
}
