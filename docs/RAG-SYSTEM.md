# RAG System Documentation

## Overview

The Legal Orchestrator now includes a comprehensive **Retrieval-Augmented Generation (RAG)** system for Saudi laws. This system enables semantic search across legal texts, improving the accuracy and relevance of AI-generated legal analysis.

## Architecture

### Components

1. **Law Library** (`law_registry`, `law_files`)
   - Central repository for all Saudi laws
   - Supports multiple files per law
   - Metadata: name, description, category, effective year, status

2. **Article Parser** (`LawParserService`)
   - Extracts individual articles from law texts
   - Supports multiple Arabic article numbering formats
   - Stores articles with context and line numbers

3. **Embedding Generator** (`EmbeddingService`)
   - Uses OpenRouter API with user's selected model (1536 dimensions)
   - Generates semantic embeddings for each article
   - Fallback to deterministic TF-IDF if API fails

4. **Vector Search** (`VectorSearchService`)
   - Cosine similarity-based semantic search
   - Configurable confidence thresholds (default: 0.70)
   - Multi-query search for complex cases

5. **Agent Integration**
   - **Agent 5 (Law Manager)**: Uses RAG to find relevant laws
   - **Agent 6 (Statute Matcher)**: Uses RAG to match specific articles to case facts

## Database Schema

```sql
-- Law registry (main law metadata)
law_registry (id, name, description, category, effective_year, status, supersedes, metadata)

-- Law files (multiple files per law)
law_files (id, law_registry_id, filename, file_path, file_size, is_processed, processed_at)

-- Parsed articles
law_articles (id, law_registry_id, law_file_id, article_number, article_text, article_context, keywords)

-- Vector embeddings (1536 dimensions)
law_embeddings (id, law_article_id, embedding_model, embedding_dimensions, embedding_vector, norm)

-- Search cache (for performance)
law_search_cache (id, query_hash, query_text, result_article_ids, result_scores, hit_count)
```

## Usage

### 1. Adding Laws to the Library

#### Via UI
1. Navigate to **مكتبة الأنظمة** (Law Library)
2. Click **إضافة نظام جديد** (Add New Law)
3. Fill in:
   - **Name**: e.g., "نظام الإثبات"
   - **Description**: Brief description
   - **Category**: civil, criminal, commercial, etc.
   - **Effective Year**: e.g., "1443"
   - **Files**: Upload TXT, PDF, DOC, or DOCX files (max 50MB each)
4. Click **حفظ ومعالجة** (Save and Process)

The system will:
- Parse the files to extract articles
- Generate embeddings for each article
- Make the law searchable via RAG

#### Via Seeder
```bash
php artisan db:seed --class=LawLibrarySeeder
```

This imports all laws from the `laws/` directory.

### 2. Searching Laws

#### Programmatic Search
```php
use App\Services\RAG\VectorSearchService;

$vectorSearch = app(VectorSearchService::class);

// Single query
$results = $vectorSearch->search('إثبات الشهادة', topK: 10, minSimilarity: 0.70);

// Multiple queries (for complex cases)
$queries = ['إثبات الشهادة', 'شروط قبول الشهادة'];
$results = $vectorSearch->searchMultiple($queries, topKPerQuery: 10);

// Search within a specific law
$results = $vectorSearch->searchInLaw($lawRegistryId, 'الشهادة', topK: 10);
```

#### Result Format
```php
[
    [
        'article' => LawArticle, // Full article model with relationships
        'similarity' => 0.85,    // Cosine similarity (0-1)
        'confidence' => 0.85,    // Same as similarity
    ],
    // ... more results
]
```

### 3. Agent Integration

The RAG system is automatically used by Agents 5 and 6:

**Agent 5 (Law Manager)**:
- Generates search queries from case facts
- Uses RAG to find relevant laws
- Provides context for Agent 6

**Agent 6 (Statute Matcher)**:
- Receives RAG candidates from Agent 5
- Performs semantic search for specific articles
- Applies confidence thresholds (≥ 0.70)
- Verifies abrogation status

## Configuration

### Environment Variables

```env
# OpenRouter API (for ALL AI operations including embeddings)
OPENROUTER_API_KEY=sk-or-v1-...
OPENROUTER_DEFAULT_MODEL=anthropic/claude-3.5-sonnet

# Confidence threshold (0.0 - 1.0)
CONFIDENCE_THRESHOLD=0.70
```

**Note**: The system uses the user's selected model from their profile settings for all AI operations, including embedding generation.

### Embedding Generation

The system uses **OpenRouter API** with the user's selected model to generate semantic embeddings:

- **Model**: User's selected model from profile (e.g., `anthropic/claude-3.5-sonnet`)
- **Dimensions**: 1536 (fixed)
- **Fallback**: Deterministic TF-IDF-based embeddings if API fails
- **Cost**: Same as regular AI agent usage (per user's model)

## Performance

### Optimization Strategies

1. **Search Cache**: Frequently used queries are cached
2. **Batch Embeddings**: Multiple articles processed in single API call
3. **Background Jobs**: File processing runs asynchronously via Horizon
4. **Confidence Filtering**: Only articles above threshold are returned

### Scaling Considerations

For production with large law libraries (>10,000 articles):

1. **Use a Vector Database**:
   - Pinecone, Weaviate, or Qdrant
   - Sub-millisecond search times
   - Horizontal scaling

2. **Implement Hybrid Search**:
   - Combine semantic (vector) + keyword (BM25) search
   - Better recall and precision

3. **Add Caching Layer**:
   - Redis for search results
   - Reduce embedding API calls

## Workflow

```
┌─────────────────┐
│  Upload Law     │
│  (UI/Seeder)    │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Parse Articles │ ← LawParserService
│  (Background)   │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Generate        │ ← EmbeddingService
│ Embeddings      │   (OpenRouter API)
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Store in DB     │ ← law_embeddings
│ (Vector Blob)   │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Available for   │ ← VectorSearchService
│ Semantic Search │
└─────────────────┘
         │
         ▼
┌─────────────────┐
│ Used by Agents  │ ← Agent 5/6
│ (Case Analysis) │
└─────────────────┘
```

## Monitoring

### Check Processing Status

```bash
# View Horizon dashboard
php artisan horizon

# Check law processing status
php artisan tinker
>>> LawRegistry::with('files')->get()->map(fn($l) => [
    'name' => $l->name,
    'files' => $l->files->count(),
    'processed' => $l->files->where('is_processed', true)->count(),
    'articles' => $l->articles->count(),
    'embedded' => $l->articles->whereHas('embedding')->count(),
]);
```

### Logs

```bash
# Processing logs
tail -f storage/logs/laravel.log | grep -i "law\|embedding\|rag"
```

## Troubleshooting

### Issue: No embeddings generated

**Cause**: OpenRouter API key not configured or invalid

**Solution**:
```bash
# Check .env
grep OPENROUTER_API_KEY .env

# Test API key
php artisan tinker
>>> app(\App\Services\RAG\EmbeddingService::class)->generateEmbedding('test');
```

### Issue: Search returns no results

**Cause**: No laws processed yet, or confidence threshold too high

**Solution**:
```bash
# Check if laws are processed
php artisan tinker
>>> LawArticle::whereHas('embedding')->count();

# Lower confidence threshold temporarily
>>> app(\App\Services\RAG\VectorSearchService::class)->search('query', 10, 0.50);
```

### Issue: Processing job failed

**Cause**: File encoding issues, parsing errors, or API limits

**Solution**:
```bash
# Check failed jobs
php artisan horizon:failed

# Retry failed job
php artisan queue:retry <job-id>

# Reprocess a specific law
php artisan tinker
>>> $law = LawRegistry::find(1);
>>> app(\App\Services\RAG\LawProcessingService::class)->processLawRegistry($law);
```

## Future Enhancements

1. **Hybrid Search**: Combine semantic + keyword search
2. **Vector Database**: Migrate to Pinecone/Weaviate for scale
3. **Multi-modal**: Support PDF images, scanned documents
4. **Cross-referencing**: Detect and link related articles
5. **Versioning**: Track law amendments and supersessions
6. **Arabic NLP**: Better keyword extraction and entity recognition

## API Reference

See inline documentation in:
- `app/Services/RAG/EmbeddingService.php`
- `app/Services/RAG/VectorSearchService.php`
- `app/Services/RAG/LawProcessingService.php`
- `app/Services/RAG/LawParserService.php`

## License

This RAG system is part of the Saudi Legal Orchestrator project.
