<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LawSearchCache extends Model
{
    protected $table = 'law_search_cache';

    protected $fillable = [
        'query_hash',
        'query_text',
        'result_article_ids',
        'result_scores',
        'hit_count',
        'last_accessed_at',
    ];

    protected $casts = [
        'result_article_ids' => 'array',
        'result_scores'      => 'array',
        'hit_count'          => 'integer',
        'last_accessed_at'   => 'datetime',
    ];
}
