<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RequiredLaw extends Model
{
    use HasFactory;
    /** @var list<string> */
    protected $fillable = [
        'case_id',
        'law_name',
        'reason',
        'is_uploaded',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_uploaded' => 'boolean',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class);
    }

    public function uploadedLaw(): HasOne
    {
        return $this->hasOne(CaseLaw::class, 'required_law_id');
    }
}
