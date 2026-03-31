<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'selected_model',
        'confidence_threshold',
        'total_tokens_consumed',
        'total_cost_usd',
        'llm_provider',
        'puter_model',
        'puter_disclosure_acknowledged',
        'notifications_enabled',
        'openrouter_api_key',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'openrouter_api_key',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'confidence_threshold' => 'decimal:2',
            'total_tokens_consumed' => 'integer',
            'total_cost_usd' => 'decimal:4',
            'puter_disclosure_acknowledged' => 'boolean',
            'notifications_enabled' => 'boolean',
            'openrouter_api_key' => 'encrypted',
        ];
    }

    public function cases(): HasMany
    {
        return $this->hasMany(LegalCase::class);
    }
}
