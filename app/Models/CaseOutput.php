<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseOutput extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'case_id',
        'agent_number',
        'filename',
        'file_path',
        'content_type',
        'content',
        'content_json',
        'file_size',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'agent_number' => 'integer',
        'file_size' => 'integer',
        'content_json' => 'array',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class);
    }
}
