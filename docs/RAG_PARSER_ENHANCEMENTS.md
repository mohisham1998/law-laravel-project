# RAG Parser Enhancements

## Overview
Enhanced the law file parser to dynamically accept multiple article formats and provide user-friendly error messages when processing fails.

## Problems Solved

### 1. Files Stuck in "قيد المعالجة" (Processing)
**Issue:** Files showed "قيد المعالجة" indefinitely even when processing failed or completed with errors.

**Solution:**
- Clear status distinction: "معالج" (processed), "قيد المعالجة" (processing), "فشل" (failed)
- Failed files show the error message directly in the UI
- Added per-file retry button (⟳) to re-queue individual files
- User-friendly error messages in Arabic instead of technical exceptions

### 2. Limited Article Format Support
**Issue:** Parser only recognized "المادة ..." format, rejecting valid law documents that use different formatting.

**Solution:** Enhanced parser to accept **10+ article patterns**:

#### Supported Patterns

1. **Textual Arabic ordinals**: `المادة الأولى`, `المادة الثانية`, etc.
2. **Parentheses format**: `المادة (1)`, `المادة (الأولى)`
3. **المادة with space**: `المادة 1`, `المادة ١`
4. **المادة without space**: `المادة1`, `المادة١`
5. **Without ال prefix**: `مادة 1`, `مادة: ١`
6. **Arabic numeral X/Y**: `١/١`, `٢/٣`, `١/١٠` ← **This fixed the stuck file!**
7. **Western numeral X/Y**: `1/1`, `1.1`, `2/3`
8. **English**: `Article 1`, `Art. 1`, `Section 1`
9. **Long form textual**: `المادة الحادية والعشرون`
10. **Standalone numbers**: `1:`, `١-`, `2:`

### 3. Storage Path Configuration
**Issue:** `local` disk was pointing to `storage/app/private` but files were stored in `storage/app/law_library`.

**Solution:** Updated `config/filesystems.php` to use standard Laravel path: `storage/app`

### 4. Null Reference Errors
**Issue:** Embedding generation crashed when `lawRegistry` relation was null.

**Solution:** Added null-safe operator (`?->`) with fallback in `prepareTextForEmbedding()`

## User-Friendly Error Messages

### Processing Errors (Parsing)
| Technical Error | User Message |
|----------------|--------------|
| `Law file not found` | لم يتم العثور على ملف النظام في التخزين. تأكد من رفع الملف بشكل صحيح. |
| `No articles found` | لم يتم العثور على أي مواد قانونية في الملف. تأكد من أن الملف يحتوي على مواد بصيغة صحيحة (مثل: "المادة الأولى" أو "١/١" أو "مادة 1") |
| `encoding` / `charset` | خطأ في ترميز الملف. تأكد من أن الملف بصيغة UTF-8 أو نص عربي صحيح. |
| `timeout` / `time limit` | انتهت مهلة معالجة الملف. الملف قد يكون كبيراً جداً. حاول تقسيمه إلى ملفات أصغر. |
| `memory` | نفدت ذاكرة الخادم. حاول تقسيم الملف إلى ملفات أصغر. |

### Embedding Errors (Indexing)
| Technical Error | User Message |
|----------------|--------------|
| `Connection` / `cURL` / `OpenAI` | فشل الاتصال بخدمة الذكاء الاصطناعي. تأكد من الاتصال بالإنترنت وصحة مفاتيح OpenAI API. |
| `API key` / `authentication` | خطأ في مفتاح OpenAI API. تأكد من صحة المفتاح في ملف .env |
| `rate limit` / `quota` | تم تجاوز حد الاستخدام لخدمة OpenAI. انتظر قليلاً ثم أعد المحاولة. |
| `name` + `null` | خطأ في تحميل بيانات النظام. تأكد من أن النظام موجود في قاعدة البيانات. |

## Files Modified

### Core Logic
- `app/Services/RAG/LawParserService.php` - Enhanced article detection patterns
- `app/Services/RAG/LawProcessingService.php` - Added user-friendly error mapping
- `app/Jobs/ProcessLawFileJob.php` - Improved error handling and messages
- `app/Jobs/GenerateLawEmbeddingsJob.php` - Added error mapping for embedding failures

### UI
- `resources/views/pages/law-library/show.blade.php` - Show failed vs processing status, error messages, per-file retry button

### Routes & Controllers
- `app/Http/Controllers/LawLibraryController.php` - Added `reprocessFile()` method
- `routes/web.php` - Added route: `POST /law-library/{lawRegistry}/files/{lawFile}/reprocess`

### Configuration
- `config/filesystems.php` - Fixed `local` disk root path to `storage/app`

## New Features

### Per-File Retry
Users can now retry individual stuck/failed files:
- In law detail page, each unprocessed file shows a refresh button (⟳)
- Clicking it re-queues that specific file for processing
- Route: `POST /law-library/{lawRegistry}/files/{lawFile}/reprocess`

### Better Status Visibility
- **معالج** (green) - File fully processed
- **قيد المعالجة** (amber) - File is being processed or queued
- **فشل** (red) - Processing failed with clear error message shown
- Error message displayed below filename (truncated to 60 chars, full text in tooltip)

## Testing Results

Tested with 4 Saudi law files:
1. اللائحة التنفيذية لنظام الإجراءات الجزائية - **181 articles** ✓
2. اللوائح التنفيذية لنظام المرافعات الشرعية - **639 articles** ✓
3. نظام الإثبات - **129 articles** ✓
4. نظام المرافعات الشرعية - **242 articles** ✓

**Total: 1,372 articles** successfully extracted and ready for RAG semantic search.

## Usage

### For Users
1. Upload law files through UI (الأنظمة والقوانين → إضافة نظام جديد)
2. Files are automatically queued for processing
3. Status updates in real-time:
   - Amber badge = still processing
   - Green badge = done
   - Red badge = failed (with error message)
4. If a file fails or gets stuck, click the ⟳ button to retry

### For Developers
```bash
# Validate queue and see all file statuses
php artisan laws:validate-queue

# Retry all failed files
php artisan laws:validate-queue --retry

# Check specific law
php artisan laws:validate-queue --law="نظام الإثبات"
php artisan laws:validate-queue --law=3  # by ID
```

## Technical Details

### Parser Logic (`LawParserService`)
- Scans file line-by-line
- Detects article start using 10 regex patterns
- Groups consecutive lines into articles
- Tracks line numbers for reference
- Extracts legal keywords automatically

### Error Handling Flow
1. `ProcessLawFileJob` calls `LawProcessingService::processLawFile()`
2. Service catches exceptions and maps to user-friendly messages
3. Job stores error in `law_files.processing_error` column
4. UI displays error with retry option
5. User can retry → job re-dispatched → worker processes with enhanced patterns

### Why This Approach
- **Dynamic acceptance**: No need to modify code for each new law format
- **User empowerment**: Clear errors + self-service retry
- **Knowledge base maximization**: Accepts diverse document formats automatically
- **Production-ready**: Handles real Saudi legal documents with various formatting styles

## Next Steps

If you encounter a law file that still can't be parsed:
1. Check the error message in the UI
2. If it says "لم يتم العثور على أي مواد", inspect the file format
3. Add new pattern to `LawParserService::detectArticleStart()` if needed
4. Retry the file using the ⟳ button

Most Saudi legal documents should now work automatically with the 10 patterns we support.
