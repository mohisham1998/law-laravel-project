<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LawRegistry extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'law_registry';

    protected $fillable = [
        'name',
        'description',
        'category',
        'effective_year',
        'status',
        'supersedes',
        'metadata',
    ];

    protected $casts = [
        'supersedes' => 'array',
        'metadata' => 'array',
    ];

    public function files(): HasMany
    {
        return $this->hasMany(LawFile::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(LawArticle::class);
    }

    public function isProcessed(): bool
    {
        return $this->files()->where('is_processed', true)->exists();
    }

    public function getTotalArticlesCount(): int
    {
        return $this->articles()->count();
    }

    public function getProcessedFilesCount(): int
    {
        return $this->files()->where('is_processed', true)->count();
    }
}
