<?php

namespace Vizra\VizraADK\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\Vector;

class VectorMemory extends Model
{
    use HasUlids;

    protected $table = 'agent_vector_memories';

    protected $fillable = [
        'agent_name',
        'namespace',
        'content',
        'metadata',
        'source',
        'source_id',
        'chunk_index',
        'embedding_provider',
        'embedding_model',
        'embedding_dimensions',
        'embedding',
        'embedding_vector',
        'embedding_norm',
        'content_hash',
        'token_count',
    ];

    protected $casts = [
        'metadata' => 'array',
        'embedding_vector' => 'array',
        'embedding' => Vector::class,
        'embedding_norm' => 'float',
        'chunk_index' => 'integer',
        'embedding_dimensions' => 'integer',
        'token_count' => 'integer',
    ];

    /**
     * Scope to filter by agent name.
     */
    public function scopeForAgent($query, string $agentName)
    {
        return $query->where('agent_name', $agentName);
    }

    /**
     * Scope to filter by namespace.
     */
    public function scopeInNamespace($query, string $namespace = 'default')
    {
        return $query->where('namespace', $namespace);
    }

    /**
     * Scope to filter by source.
     */
    public function scopeFromSource($query, string $source)
    {
        return $query->where('source', $source);
    }

    /**
     * Calculate cosine similarity with another vector.
     * Note: For PostgreSQL with pgvector, use database functions instead.
     */
    public function cosineSimilarity(array $vector): float
    {
        $a = $this->embedding_vector;
        $b = $vector;

        if (empty($a) || empty($b) || count($a) !== count($b)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }

    /**
     * Generate content hash for deduplication.
     */
    public static function generateContentHash(string $content, string $agentName): string
    {
        return hash('sha256', $agentName.':'.trim($content));
    }

    /**
     * Calculate vector norm (magnitude).
     */
    public static function calculateNorm(array $vector): float
    {
        $sum = 0.0;
        foreach ($vector as $component) {
            $sum += $component * $component;
        }

        return sqrt($sum);
    }

    /**
     * Estimate token count from content.
     */
    public static function estimateTokenCount(string $content): int
    {
        // Rough estimation: ~4 characters per token for English text
        return (int) ceil(strlen($content) / 4);
    }
}
