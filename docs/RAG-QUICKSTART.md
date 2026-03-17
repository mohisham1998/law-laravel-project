# RAG System - Quick Start Guide

## 🚀 5-Minute Setup

### Step 1: Verify OpenRouter API Key

Check `.env` (should already be configured):

```env
OPENROUTER_API_KEY=sk-or-v1-...
OPENROUTER_DEFAULT_MODEL=anthropic/claude-3.5-sonnet
```

**Note**: The system uses OpenRouter for ALL AI operations, including embeddings. No separate API key needed!

### Step 2: Run Migrations

```bash
docker compose exec app php artisan migrate
```

### Step 3: Seed Law Library

```bash
docker compose exec app php artisan db:seed --class=LawLibrarySeeder
```

This imports all laws from the `laws/` directory.

### Step 4: Start Horizon

```bash
docker compose exec app php artisan horizon
```

Monitor at: http://localhost:8000/horizon

### Step 5: Verify

Open the app: http://localhost:8000

Navigate to: **مكتبة الأنظمة** (Law Library)

You should see your laws being processed!

---

## 📖 Using the Law Library

### Upload a New Law

1. Click **إضافة نظام جديد** (Add New Law)
2. Fill in:
   - **Name**: e.g., "نظام الإثبات"
   - **Description**: Brief description
   - **Category**: Select from dropdown
   - **Year**: e.g., "1443"
   - **Files**: Upload TXT/PDF/DOC/DOCX (max 50MB each)
3. Click **حفظ ومعالجة** (Save and Process)

The system will automatically:
- ✅ Parse articles
- ✅ Generate embeddings
- ✅ Make searchable

### View Law Details

Click on any law to see:
- 📄 Files uploaded
- 📋 Articles extracted
- 🔍 Embeddings generated
- 📊 Processing status

### Add More Files to Existing Law

1. Open law details page
2. Click **إضافة ملفات** (Add Files)
3. Upload additional files
4. System processes them automatically

---

## 🧪 Test RAG Search

```bash
docker compose exec app php artisan tinker
```

```php
// Get search service
$search = app(\App\Services\RAG\VectorSearchService::class);

// Search for articles about witness testimony
$results = $search->search('شروط قبول شهادة الشهود', 5);

// Display results
foreach ($results as $result) {
    echo "Law: {$result['article']->lawRegistry->name}\n";
    echo "Article: {$result['article']->article_number}\n";
    echo "Confidence: " . number_format($result['confidence'], 2) . "\n";
    echo "Text: " . Str::limit($result['article']->article_text, 100) . "\n\n";
}
```

---

## 🎯 How It Works with AI Agents

### When You Create a Case:

1. **Phase 1**: Agent 1 analyzes intake → identifies required laws
2. **Phase 2 - Agent 5**: 
   - Generates search queries from case facts
   - **Uses RAG** to find relevant articles
   - Provides context for Agent 6
3. **Phase 2 - Agent 6**:
   - Receives RAG candidates
   - **Uses RAG** to match specific articles
   - Applies confidence threshold (≥ 0.70)
   - Outputs final statute map

### Example Flow:

```
Case: "رفض موظف تنفيذ أمر مشروع"
       ↓
Agent 5: Generates queries → ["رفض تنفيذ الأوامر", "واجبات الموظف"]
       ↓
RAG Search: Returns relevant articles
       ↓
Agent 6: Validates & matches → نظام العمل المادة 80 (confidence: 0.89)
       ↓
Final Brief: Includes accurate article citations
```

---

## 📊 Monitor Processing

### Horizon Dashboard

http://localhost:8000/horizon

- View active jobs
- Check failed jobs
- Monitor throughput
- Retry failed jobs

### Check Stats

```bash
docker compose exec app php artisan tinker
```

```php
// Total laws
LawRegistry::count();

// Total articles
LawArticle::count();

// Articles with embeddings
LawArticle::whereHas('embedding')->count();

// Processing status
LawRegistry::with('files')->get()->map(function($law) {
    return [
        'name' => $law->name,
        'files' => $law->files->count(),
        'processed' => $law->files->where('is_processed', true)->count(),
        'articles' => $law->articles->count(),
        'embedded' => $law->articles->whereHas('embedding')->count(),
    ];
});
```

---

## ⚙️ Configuration

### Confidence Threshold

In `.env`:

```env
CONFIDENCE_THRESHOLD=0.70
```

- `0.70` = 70% similarity (recommended)
- Higher = more strict (fewer results)
- Lower = more permissive (more results)

### Model Selection

The system uses **your selected model** from your user profile settings for ALL AI operations, including embeddings.

To change the model:
1. Go to **Settings** (الإعدادات)
2. Select your preferred model
3. All AI operations (agents + embeddings) will use this model

**Note**: Embeddings are always 1536 dimensions regardless of model.

---

## 🐛 Common Issues

### "No embeddings generated"

**Cause**: OpenRouter API key missing or invalid

**Fix**:
```bash
# Check .env
grep OPENROUTER_API_KEY .env

# Should show: OPENROUTER_API_KEY=sk-or-v1-...
```

### "Search returns no results"

**Cause**: Laws not processed yet

**Fix**:
```bash
# Check Horizon dashboard
http://localhost:8000/horizon

# Wait for jobs to complete
# Or check manually:
docker compose exec app php artisan tinker
>>> LawArticle::whereHas('embedding')->count();
```

### "Processing job failed"

**Cause**: File encoding issues or API rate limits

**Fix**:
```bash
# Check Horizon failed jobs
http://localhost:8000/horizon

# Retry job
docker compose exec app php artisan queue:retry <job-id>

# Or reprocess law:
docker compose exec app php artisan tinker
>>> $law = LawRegistry::find(1);
>>> app(\App\Services\RAG\LawProcessingService::class)->processLawRegistry($law);
```

---

## 📚 Learn More

- **Full Documentation**: `docs/RAG-SYSTEM.md`
- **Implementation Summary**: `RAG-IMPLEMENTATION-SUMMARY.md`
- **Code Examples**: See service classes in `app/Services/RAG/`

---

## 🎉 You're Ready!

Your RAG system is now fully operational. The AI agents will automatically use semantic search to find relevant laws when analyzing cases.

**Next Steps**:
1. ✅ Verify OpenRouter API key is configured (already done!)
2. ✅ Upload your laws via UI
3. ✅ Create a test case
4. ✅ Watch AI use RAG to find relevant articles
5. ✅ Review the generated legal brief

**Important**: The system uses your selected model from profile settings for ALL AI operations. No separate API keys needed! 🚀

Happy lawyering! ⚖️
