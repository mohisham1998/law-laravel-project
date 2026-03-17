<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LawEmbedding extends Model
{
    use HasFactory;

    protected $fillable = [
        'law_article_id',
        'embedding_model',
        'embedding_dimensions',
        'embedding_vector',
        'norm',
    ];

    protected $attributes = [
        'embedding_dimensions' => 1536,
    ];

    protected $casts = [
        'embedding_dimensions' => 'integer',
        'norm' => 'decimal:6',
    ];

    public function lawArticle(): BelongsTo
    {
        return $this->belongsTo(LawArticle::class);
    }

    public function getVectorArray(): array
    {
        if (!$this->embedding_vector) {
            return [];
        }
        
        // Unpack binary blob to float array
        $unpacked = unpack('f*', $this->embedding_vector);
        return array_values($unpacked);
    }

    public function setVectorArray(array $vector): void
    {
        // Pack float array to binary blob
        $this->embedding_vector = pack('f*', ...$vector);
        $this->embedding_dimensions = count($vector);
        
        // Calculate norm
        $sumSquares = array_reduce($vector, fn($carry, $val) => $carry + ($val * $val), 0);
        $this->norm = sqrt($sumSquares);
    }

    public static function cosineSimilarity(array $vector1, array $vector2): float
    {
        if (count($vector1) !== count($vector2)) {
            throw new \InvalidArgumentException('Vectors must have same dimensions');
        }

        $dotProduct = 0;
        $norm1 = 0;
        $norm2 = 0;

        for ($i = 0; $i < count($vector1); $i++) {
            $dotProduct += $vector1[$i] * $vector2[$i];
            $norm1 += $vector1[$i] * $vector1[$i];
            $norm2 += $vector2[$i] * $vector2[$i];
        }

        $norm1 = sqrt($norm1);
        $norm2 = sqrt($norm2);

        if ($norm1 == 0 || $norm2 == 0) {
            return 0;
        }

        return $dotProduct / ($norm1 * $norm2);
    }
}
