<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseLaw extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'case_id',
        'required_law_id',
        'law_name',
        'filename',
        'file_path',
        'file_size',
        'encoding',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'file_size' => 'integer',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class);
    }

    public function requiredLaw(): BelongsTo
    {
        return $this->belongsTo(RequiredLaw::class);
    }
}
