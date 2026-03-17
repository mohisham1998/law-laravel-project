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
     * Extract articles from law text
     */
    protected function extractArticles(array $lines, LawFile $lawFile): array
    {
        $articles = [];
        $currentArticle = null;
        $currentText = [];
        $currentStartLine = null;

        foreach ($lines as $lineNum => $line) {
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
     */
    protected function detectArticleStart(string $line): ?string
    {
        // Pattern 1: "المادة الأولى" or "المادة الثانية"
        if (preg_match('/^المادة\s+(الأولى|الثانية|الثالثة|الرابعة|الخامسة|السادسة|السابعة|الثامنة|التاسعة|العاشرة|الحادية عشرة|الثانية عشرة|الثالثة عشرة|الرابعة عشرة|الخامسة عشرة|السادسة عشرة|السابعة عشرة|الثامنة عشرة|التاسعة عشرة|العشرون)/u', $line, $matches)) {
            return $matches[1];
        }

        // Pattern 2: "المادة (1)" or "المادة (الأولى)"
        if (preg_match('/^المادة\s*\(([^\)]+)\)/u', $line, $matches)) {
            return trim($matches[1]);
        }

        // Pattern 3: "المادة 1" or "المادة ١"
        if (preg_match('/^المادة\s+(\d+|[٠-٩]+)/u', $line, $matches)) {
            return $matches[1];
        }

        // Pattern 4: Numbered articles with text (e.g., "المادة الحادية والعشرون")
        if (preg_match('/^المادة\s+(.+?)$/u', $line, $matches)) {
            $articleName = trim($matches[1]);
            // Only accept if it looks like an article number (not too long)
            if (mb_strlen($articleName) < 50) {
                return $articleName;
            }
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
