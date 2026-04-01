<?php

namespace App\Services\RAG;

use App\Models\LawArticle;
use App\Models\LawFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LawParserService
{
    /**
     * Parse a law file and extract articles
     */
    public function parseFile(LawFile $lawFile): array
    {
        $path = Storage::disk('local')->path($lawFile->file_path);

        if (!file_exists($path)) {
            throw new \RuntimeException("Law file not found: {$lawFile->file_path}");
        }

        $content = file_get_contents($path);
        $lines = explode("\n", $content);

        $articles = $this->extractArticles($lines, $lawFile);

        return $articles;
    }

    /**
     * Parse the structured header section of a law file.
     *
     * Every law .txt file has a header before the "── النصوص ──" delimiter that
     * contains metadata fields (الاسم, التصنيف, الحالة, etc.) and an optional
     * summary between "── الملخص ──" and "── النصوص ──".
     *
     * @return array{name:string, category:string, status:string, issue_date:string, decree_ref:string, summary:string}
     */
    public function parseHeader(string $content): array
    {
        $result = [
            'name'       => '',
            'category'   => '',
            'status'     => '',
            'issue_date' => '',
            'decree_ref' => '',
            'summary'    => '',
        ];

        // Everything before "── النصوص ──" is the header block
        $textsDelimiter = '── النصوص ──';
        $textsPos = mb_strpos($content, $textsDelimiter);
        $headerBlock = $textsPos !== false
            ? mb_substr($content, 0, $textsPos)
            : $content;

        // --- Extract summary between "── الملخص ──" and "── النصوص ──" ---
        $summaryDelimiter = '── الملخص ──';
        $summaryPos = mb_strpos($headerBlock, $summaryDelimiter);
        if ($summaryPos !== false) {
            $summaryStart = $summaryPos + mb_strlen($summaryDelimiter);
            $result['summary'] = trim(mb_substr($headerBlock, $summaryStart));
            // Trim the summary section from the metadata block
            $headerBlock = mb_substr($headerBlock, 0, $summaryPos);
        }

        // --- Parse individual metadata lines ---
        $lines = explode("\n", $headerBlock);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            if (preg_match('/^الاسم:\s*(.+)$/u', $line, $m)) {
                $result['name'] = trim($m[1]);
            } elseif (preg_match('/^التصنيف:\s*(.+)$/u', $line, $m)) {
                $result['category'] = trim($m[1]);
            } elseif (preg_match('/^الحالة:\s*(.+)$/u', $line, $m)) {
                $result['status'] = trim($m[1]);
            } elseif (preg_match('/^تاريخ الإصدار:\s*(.+)$/u', $line, $m)) {
                // Extract Hijri date only (before "الموافق" or end of string)
                $dateStr = trim($m[1]);
                if (preg_match('/([\d٠-٩]+\/[\d٠-٩]+\/[\d٠-٩]+)/u', $dateStr, $dm)) {
                    $result['issue_date'] = $dm[1];
                } else {
                    $result['issue_date'] = $dateStr;
                }
            } elseif (preg_match('/^أداة الإصدار:\s*(.+)$/u', $line, $m)) {
                $result['decree_ref'] = trim($m[1]);
            }
        }

        return $result;
    }

    /**
     * Extract articles from law text
     */
    protected function extractArticles(array $lines, LawFile $lawFile): array
    {
        $articles = [];
        $currentArticle = null;
        $currentText = [];
        $currentStartLine = null;

        // Find the "── النصوص ──" delimiter and start parsing only from there
        $textsDelimiter = '── النصوص ──';
        $startFromLine = 0;
        foreach ($lines as $idx => $rawLine) {
            if (mb_strpos(trim($rawLine), $textsDelimiter) !== false) {
                $startFromLine = $idx + 1;
                break;
            }
        }

        foreach ($lines as $lineNum => $line) {
            // Skip header lines before "── النصوص ──"
            if ($lineNum < $startFromLine) {
                continue;
            }

            $line = trim($line);
            
            // Skip empty lines
            if (empty($line)) {
                if ($currentArticle !== null) {
                    $currentText[] = '';
                }
                continue;
            }

            // Check if this line starts a new article
            $articleMatch = $this->detectArticleStart($line);
            
            if ($articleMatch) {
                // Save previous article if exists
                if ($currentArticle !== null && !empty($currentText)) {
                    $articles[] = [
                        'article_number' => $currentArticle,
                        'article_text' => implode("\n", $currentText),
                        'start_line' => $currentStartLine,
                        'end_line' => $lineNum - 1,
                    ];
                }
                
                // Start new article
                $currentArticle = $articleMatch;
                $currentText = [$line];
                $currentStartLine = $lineNum + 1;
            } else {
                // Continue current article
                if ($currentArticle !== null) {
                    $currentText[] = $line;
                }
            }
        }

        // Save last article
        if ($currentArticle !== null && !empty($currentText)) {
            $articles[] = [
                'article_number' => $currentArticle,
                'article_text' => implode("\n", $currentText),
                'start_line' => $currentStartLine,
                'end_line' => count($lines),
            ];
        }

        Log::info("Parsed {$lawFile->filename}: found " . count($articles) . " articles");

        return $articles;
    }

    /**
     * Detect if a line starts a new article
     * Supports multiple formats to maximize knowledge base coverage
     */
    protected function detectArticleStart(string $line): ?string
    {
        // Pattern 1: "المادة الأولى" or "المادة الثانية" (textual Arabic ordinals)
        if (preg_match('/^المادة\s+(الأولى|الثانية|الثالثة|الرابعة|الخامسة|السادسة|السابعة|الثامنة|التاسعة|العاشرة|الحادية عشرة|الثانية عشرة|الثالثة عشرة|الرابعة عشرة|الخامسة عشرة|السادسة عشرة|السابعة عشرة|الثامنة عشرة|التاسعة عشرة|العشرون)/u', $line, $matches)) {
            return $matches[1];
        }

        // Pattern 2: "المادة (1)" or "المادة (الأولى)"
        if (preg_match('/^المادة\s*\(([^\)]+)\)/u', $line, $matches)) {
            return trim($matches[1]);
        }

        // Pattern 3: "المادة 1" or "المادة ١" (with space)
        if (preg_match('/^المادة\s+(\d+|[٠-٩]+)/u', $line, $matches)) {
            return $matches[1];
        }

        // Pattern 4: "المادة1" or "المادة١" (no space)
        if (preg_match('/^المادة(\d+|[٠-٩]+)/u', $line, $matches)) {
            return $matches[1];
        }

        // Pattern 5: "مادة 1" or "مادة ١" (without "ال")
        if (preg_match('/^مادة\s*:?\s*(\d+|[٠-٩]+)/u', $line, $matches)) {
            return $matches[1];
        }

        // Pattern 6: Arabic numeral formats like "١/١" or "١/٣" (section/article format)
        if (preg_match('/^([٠-٩]+)\s*\/\s*([٠-٩]+)$/u', $line, $matches)) {
            return $matches[1] . '/' . $matches[2];
        }

        // Pattern 7: Western numeral formats like "1/1" or "1.1" (section/article format)
        if (preg_match('/^(\d+)\s*[\/\.]\s*(\d+)$/u', $line, $matches)) {
            return $matches[1] . '/' . $matches[2];
        }

        // Pattern 8: "Article 1" or "Art. 1" (English)
        if (preg_match('/^(Article|Art\.?|Section|Sec\.?)\s+(\d+)/i', $line, $matches)) {
            return $matches[2];
        }

        // Pattern 9: Numbered articles with text (e.g., "المادة الحادية والعشرون")
        if (preg_match('/^المادة\s+(.+?)[:：]?\s*$/u', $line, $matches)) {
            $articleName = trim($matches[1]);
            if (mb_strlen($articleName) < 50) {
                return $articleName;
            }
        }

        // Pattern 10: Standalone numbers with colon/dash at start (e.g., "1:" or "1-")
        if (preg_match('/^(\d+|[٠-٩]+)\s*[:：\-]\s*$/u', $line, $matches)) {
            return trim($matches[1]);
        }

        // Pattern 11: Arabic ordinal headings (أولاً, ثانياً, ... عاشراً)
        if (preg_match('/^(أولاً|ثانياً|ثالثاً|رابعاً|خامساً|سادساً|سابعاً|ثامناً|تاسعاً|عاشراً)\s*[:\-]?\s*$/u', $line, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract keywords from article text
     */
    public function extractKeywords(string $text): array
    {
        // Common legal terms in Arabic
        $legalTerms = [
            'إثبات', 'شهادة', 'يمين', 'إقرار', 'استجواب', 'محرر', 'رسمي', 'عادي',
            'بينة', 'دليل', 'قرينة', 'خبير', 'معاينة', 'دعوى', 'حكم', 'قاضي',
            'محكمة', 'خصم', 'مدعي', 'مدعى عليه', 'حق', 'التزام', 'عقد', 'ضرر',
            'تعويض', 'بطلان', 'نفاذ', 'تقادم', 'اختصاص', 'صفة', 'مصلحة',
        ];

        $keywords = [];
        
        foreach ($legalTerms as $term) {
            if (mb_stripos($text, $term) !== false) {
                $keywords[] = $term;
            }
        }

        return array_unique($keywords);
    }

    /**
     * Get context around an article (previous and next articles)
     */
    public function getArticleContext(LawArticle $article, int $contextLines = 5): string
    {
        $lawFile = $article->lawFile;
        
        if (!$lawFile) {
            return '';
        }

        $path = Storage::disk('local')->path($lawFile->file_path);
        
        if (!file_exists($path)) {
            return '';
        }

        $lines = file($path);
        $startLine = max(0, $article->start_line - $contextLines - 1);
        $endLine = min(count($lines), $article->end_line + $contextLines);
        
        $contextLines = array_slice($lines, $startLine, $endLine - $startLine);
        
        return implode('', $contextLines);
    }
}
