<?php

namespace App\Services;

use App\Models\LegalCase;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;

/**
 * Generates professional Arabic RTL PDF legal briefs.
 *
 * Structure:
 *  Page 1  — Elegant cover header (no running header/footer)
 *  Page 2+ — Content pages with running header + footer
 *
 * Font: Cairo (static TTF, Google Fonts CDN)  useOTL=0xFF for full Arabic shaping
 * Design: Navy #1E3A8A / Gold #B45309 / Near-black #0F172A
 */
class PdfExportService
{
    // ── Design tokens ──────────────────────────────────────────────────────
    private const NAVY   = '#1E3A8A';
    private const BLUE   = '#1E40AF';
    private const GOLD   = '#B45309';
    private const TEXT   = '#0F172A';
    private const BORDER = '#CBD5E1';
    private const SHADE  = '#F1F5F9';

    // ── Font paths ──────────────────────────────────────────────────────────
    private function fontDir(): string
    {
        return storage_path('fonts/cairo');
    }

    private function fontData(): array
    {
        return [
            'cairo' => [
                'R'          => 'Cairo-Regular.ttf',
                'B'          => 'Cairo-Bold.ttf',
                'I'          => 'Cairo-Regular.ttf',
                'BI'         => 'Cairo-Bold.ttf',
                'useOTL'     => 0xFF,   // Full OpenType — correct Arabic shaping
                'useKashida' => 75,
            ],
        ];
    }

    // ── Public API ──────────────────────────────────────────────────────────

    public function generate(LegalCase $case): string
    {
        $content = $this->getFinalBriefContent($case);
        if ($content === null || trim($content) === '') {
            throw new \RuntimeException('لا يوجد مذكرة نهائية لهذه القضية بعد.');
        }

        $mpdf = new Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font_size' => 12,
            'default_font'      => 'cairo',
            'margin_left'       => 22,
            'margin_right'      => 22,
            'margin_top'        => 20,
            'margin_bottom'     => 20,
            'margin_header'     => 8,
            'margin_footer'     => 8,
            'fontDir'           => [$this->fontDir()],
            'fontdata'          => $this->fontData(),
        ]);

        $mpdf->SetDirectionality('rtl');
        $mpdf->SetTitle($case->title ?? 'مذكرة قانونية');
        $mpdf->SetAuthor('المستشار القانوني الذكي');
        $mpdf->SetCreator('Saudi Legal AI');

        // ── Page 1: Elegant cover (no running header/footer) ──────────────
        $mpdf->AddPage();
        $mpdf->WriteHTML($this->globalCss() . $this->buildCover($case));

        // ── Page 2+: Content with running header + footer ─────────────────
        $mpdf->AddPage();
        $mpdf->SetHTMLHeader($this->buildRunningHeader($case));
        $mpdf->SetHTMLFooter($this->buildRunningFooter());
        $mpdf->WriteHTML($this->globalCss() . '<div class="body-content">' . $this->convertToHtml($content) . '</div>');

        return $mpdf->Output('', 'S');
    }

    public function getFilename(LegalCase $case): string
    {
        $title = $case->title ?? 'مذكرة-قانونية';
        $safe  = preg_replace('/[^\p{Arabic}\w\s\-]/u', '', $title);
        $safe  = trim(preg_replace('/\s+/', '-', $safe));
        return "{$safe}-" . now()->format('Y-m-d') . '.pdf';
    }

    // ── Content Retrieval ──────────────────────────────────────────────────

    protected function getFinalBriefContent(LegalCase $case): ?string
    {
        $basePath   = "cases/{$case->id}/outputs";
        $candidates = [
            '14_final_brief_polished.md' => false,
            '13_final_brief_v3.md'       => true,
            '09_final_brief_v2.md'       => false,
        ];

        foreach ($candidates as $file => $needsExtraction) {
            $path = "{$basePath}/{$file}";
            if (Storage::disk('local')->exists($path)) {
                $raw = Storage::disk('local')->get($path);
                if (trim($raw) !== '') {
                    return $needsExtraction ? $this->extractFinalSection($raw) : $raw;
                }
            }

            $dbOutput = $case->outputs()
                ->where('filename', $file)
                ->latest('id')
                ->first();

            if ($dbOutput && is_string($dbOutput->content) && trim($dbOutput->content) !== '') {
                $raw = $dbOutput->content;
                return $needsExtraction ? $this->extractFinalSection($raw) : $raw;
            }
        }

        return null;
    }

    protected function extractFinalSection(string $content): string
    {
        if (preg_match('/---+\s*FINAL_BRIEF_V3\s*---+/i', $content, $m, PREG_OFFSET_CAPTURE)) {
            $extracted = substr($content, $m[0][1] + strlen($m[0][0]));
            $extracted = preg_replace('/---+\s*END\s*---+.*/is', '', $extracted);
            return trim($extracted);
        }
        return $content;
    }

    // ── Global CSS ─────────────────────────────────────────────────────────

    protected function globalCss(): string
    {
        $navy   = self::NAVY;
        $blue   = self::BLUE;
        $gold   = self::GOLD;
        $text   = self::TEXT;
        $border = self::BORDER;
        $shade  = self::SHADE;

        return <<<CSS
<style>
* { font-family: cairo, sans-serif; direction: rtl; }
body { font-size: 12.5px; line-height: 2.1; color: {$text}; background: #fff; text-align: right; }

/* ── Body content ── */
.body-content { padding: 0; }

/* ── Section headers — full-width navy banner ── */
h1 {
    font-size: 17px; font-weight: bold; color: #fff;
    background-color: {$navy};
    text-align: center;
    margin: 32px 0 18px 0;
    padding: 13px 20px;
    letter-spacing: 0.03em;
    page-break-after: avoid;
}

/* ── Sub-section headers — gold accent + tinted background ── */
h2 {
    font-size: 14px; font-weight: bold; color: {$navy};
    margin: 26px 0 12px 0;
    padding: 9px 16px 9px 10px;
    border-right: 5px solid {$gold};
    background-color: {$shade};
    border-top: 1px solid {$border};
    border-bottom: 1px solid {$border};
    page-break-after: avoid;
}

/* ── Minor headers — blue side rule ── */
h3 {
    font-size: 13px; font-weight: bold; color: {$blue};
    margin: 20px 0 8px 0;
    padding: 4px 14px 4px 0;
    border-right: 4px solid {$blue};
    page-break-after: avoid;
}

h4 {
    font-size: 12.5px; font-weight: bold; color: {$text};
    margin: 16px 0 6px 0;
    page-break-after: avoid;
}

/* ── Body text ── */
p { margin: 8px 0; line-height: 2.1; }

/* ── Lists ── */
ul { margin: 10px 0 14px 0; padding-right: 22px; list-style-type: disc; }
ul li { margin-bottom: 7px; line-height: 2.0; }
ol { margin: 10px 0 14px 0; padding-right: 22px; }
ol li { margin-bottom: 7px; line-height: 2.0; }

/* ── Statute / law citation block ── */
blockquote {
    margin: 14px 0;
    padding: 11px 16px;
    border-right: 5px solid {$navy};
    background-color: {$shade};
    font-size: 12px;
    line-height: 2.0;
    color: #1a2a5a;
}

/* ── Tables ── */
table {
    width: 100%;
    border-collapse: collapse;
    margin: 16px 0 20px 0;
    font-size: 11.5px;
    page-break-inside: avoid;
}
table th {
    background-color: {$navy};
    color: #ffffff;
    padding: 10px 12px;
    font-weight: bold;
    border: 1px solid {$navy};
    text-align: center;
    font-size: 11.5px;
    line-height: 1.7;
}
table td {
    border: 1px solid {$border};
    padding: 9px 12px;
    vertical-align: top;
    font-size: 11.5px;
    line-height: 1.9;
}
table tr:nth-child(even) td { background-color: {$shade}; }
table tr:nth-child(odd)  td { background-color: #ffffff; }

/* ── Dividers ── */
hr {
    border: none;
    border-top: 1px solid {$border};
    margin: 20px 0;
}

strong { font-weight: bold; }
em { font-style: normal; font-weight: bold; color: {$navy}; }
</style>
CSS;
    }

    // ── Cover Page — vertically centered, elegant ──────────────────────────

    protected function buildCover(LegalCase $case): string
    {
        $title  = htmlspecialchars($case->title ?? 'مذكرة قانونية', ENT_COMPAT, 'UTF-8');
        $hijri  = $this->getCurrentHijriDate();
        $gregor = now()->format('Y-m-d');
        $navy   = self::NAVY;
        $gold   = self::GOLD;
        $text   = self::TEXT;

        // A4 usable height with 20mm top/bottom margins = 257mm
        // border="0" cellspacing="0" cellpadding="0" are HTML attrs mPDF respects
        return <<<HTML
<table border="0" cellspacing="0" cellpadding="0"
       style="width:100%; height:257mm; border:none; border-collapse:collapse;">
  <tr style="border:none;">
    <td style="text-align:center; vertical-align:middle; border:none; padding:0;
                direction:rtl; font-family:cairo,sans-serif;">

      <p style="font-size:11px; color:#aaa; letter-spacing:0.06em; margin:0 0 8px 0;">
        المملكة العربية السعودية &nbsp;&nbsp;·&nbsp;&nbsp; وزارة العدل
      </p>

      <div style="width:38%; border-top:1px solid #d1d5db; margin:0 auto 18px auto;"></div>

      <p style="font-size:30px; font-weight:bold; color:{$navy};
                margin:0 0 6px 0; letter-spacing:0.05em; line-height:1.4;">
        بسم الله الرحمن الرحيم
      </p>

      <div style="width:22%; border-top:3px solid {$gold}; margin:16px auto;"></div>

      <p style="font-size:22px; font-weight:bold; color:{$text};
                margin:0 30px 14px 30px; line-height:1.6;">
        {$title}
      </p>

      <p style="font-size:12px; color:#6b7280; margin:0 0 24px 0; letter-spacing:0.02em;">
        مذكرة قانونية رسمية &nbsp;—&nbsp; للاستخدام أمام الجهات القضائية
      </p>

      <div style="width:22%; border-top:3px solid {$gold}; margin:0 auto 22px auto;"></div>

      <p style="font-size:12.5px; color:#374151; margin:0 0 4px 0;">
        التاريخ الهجري:&nbsp; <strong style="color:{$navy};">{$hijri} هـ</strong>
      </p>
      <p style="font-size:12.5px; color:#374151; margin:0;">
        التاريخ الميلادي:&nbsp; <strong style="color:{$navy};">{$gregor} م</strong>
      </p>

      <p style="font-size:9.5px; color:#ccc; margin-top:52px; letter-spacing:0.03em;">
        أُعِدَّت بواسطة المستشار القانوني الذكي &nbsp;—&nbsp; سري للاستخدام القانوني
      </p>

    </td>
  </tr>
</table>
HTML;
    }

    // ── Running Header ─────────────────────────────────────────────────────

    protected function buildRunningHeader(LegalCase $case): string
    {
        $rawTitle = $case->title ?? 'مذكرة قانونية';
        $title    = htmlspecialchars(
            mb_strlen($rawTitle) > 55 ? mb_substr($rawTitle, 0, 55) . '…' : $rawTitle,
            ENT_COMPAT, 'UTF-8'
        );
        $navy = self::NAVY;
        $gold = self::GOLD;

        return <<<HTML
<table width="100%" style="font-family:cairo,sans-serif; font-size:9px; color:{$navy}; border-bottom:2px solid {$gold}; padding-bottom:4px; direction:rtl;">
  <tr>
    <td width="65%" style="text-align:right; font-weight:bold;">{$title}</td>
    <td width="35%" style="text-align:left; color:#6b7280;">المستشار القانوني الذكي</td>
  </tr>
</table>
HTML;
    }

    // ── Running Footer ─────────────────────────────────────────────────────

    protected function buildRunningFooter(): string
    {
        $date   = now()->format('Y-m-d');
        $border = self::BORDER;

        return <<<HTML
<table width="100%" style="font-family:cairo,sans-serif; font-size:9px; color:#9ca3af; border-top:1px solid {$border}; padding-top:4px; direction:rtl;">
  <tr>
    <td width="33%" style="text-align:right;">{$date}</td>
    <td width="34%" style="text-align:center; color:#374151; font-weight:bold;">صفحة {PAGENO} من {nb}</td>
    <td width="33%" style="text-align:left;">سري — للاستخدام القانوني</td>
  </tr>
</table>
HTML;
    }

    // ── Hijri Date ─────────────────────────────────────────────────────────

    protected function getCurrentHijriDate(): string
    {
        if (class_exists('IntlCalendar')) {
            try {
                $cal   = \IntlCalendar::createInstance(null, '@calendar=islamic-civil');
                $cal->setTime(microtime(true) * 1000);
                $year  = $cal->get(\IntlCalendar::FIELD_YEAR);
                $month = $cal->get(\IntlCalendar::FIELD_MONTH) + 1;
                $day   = $cal->get(\IntlCalendar::FIELD_DAY_OF_MONTH);
                return "{$year}/{$month}/{$day}";
            } catch (\Throwable) {}
        }

        $y = (int) now()->format('Y');
        $m = (int) now()->format('m');
        $d = (int) now()->format('d');
        $jd = gregoriantojd($m, $d, $y);
        $n  = (int)(($jd - 1948440 + 10632 - 1) / 10631);
        $jd2 = $jd - 1948440 + 10632 - 10631 * $n;
        $j  = (int)(($jd2 - 10) / 354.367);
        $jd3 = $jd2 - (int)(354.367 * $j + 0.5);
        $hm = (int)(($jd3 + 9.5) / 29.5) + 1;
        $hd = $jd3 - (int)(29.5001 * ($hm - 1));
        $hy = 30 * $n + $j + 1;
        return "{$hy}/{$hm}/{$hd}";
    }

    // ── Markdown → HTML ────────────────────────────────────────────────────

    protected function convertToHtml(string $content): string
    {
        // Strip technical artifacts
        $content = preg_replace('/`?(CASE|LAW):[A-Z0-9_]+`?/', '', $content);
        $content = preg_replace('/⚠️\s*غير مُسنَّدة?/', '', $content);
        $content = preg_replace('/"confidence"\s*:\s*[\d.]+/', '', $content);
        $content = preg_replace('/\d{2}_\w+\.(jsonl|md|json)/u', '', $content);
        $content = preg_replace('/^(ملاحظة|ملحوظة)[^\n]*(CASE|Law|\.jsonl|\.json)[^\n]*$/mu', '', $content);
        $content = preg_replace('/---+[A-Z_]+---+/', '', $content);
        $content = str_replace('(quoted_text)', '', $content);
        $content = preg_replace('/```[\w]*\n?/', '', $content);
        $content = trim($content);

        $lines     = explode("\n", $content);
        $html      = '';
        $inUl      = false;
        $inOl      = false;
        $inTable   = false;
        $tableRows = [];

        $closeList = function () use (&$html, &$inUl, &$inOl) {
            if ($inUl) { $html .= '</ul>'; $inUl = false; }
            if ($inOl) { $html .= '</ol>'; $inOl = false; }
        };

        $flushTable = function () use (&$html, &$inTable, &$tableRows) {
            if (!$inTable || empty($tableRows)) return;
            $html .= '<table><thead>';
            $isHeader = true;
            foreach ($tableRows as $row) {
                $trimmed = trim($row);
                if (preg_match('/^\|[\s\-\|:]+\|$/', $trimmed)) continue; // separator

                $cells = array_values(array_filter(
                    array_map('trim', explode('|', trim($trimmed, '| '))),
                    fn ($c) => $c !== ''
                ));
                if (empty($cells)) continue;

                if ($isHeader) {
                    $html .= '<tr>';
                    foreach ($cells as $cell) {
                        $cell  = htmlspecialchars($cell, ENT_COMPAT, 'UTF-8');
                        $cell  = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $cell);
                        $html .= "<th>{$cell}</th>";
                    }
                    $html .= '</tr></thead><tbody>';
                    $isHeader = false;
                } else {
                    $html .= '<tr>';
                    foreach ($cells as $cell) {
                        $cell  = htmlspecialchars($cell, ENT_COMPAT, 'UTF-8');
                        $cell  = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $cell);
                        $html .= "<td>{$cell}</td>";
                    }
                    $html .= '</tr>';
                }
            }
            $html .= '</tbody></table>';
            $inTable  = false;
            $tableRows = [];
        };

        foreach ($lines as $line) {
            $trimmed = rtrim($line);

            // Table row
            if (str_starts_with($trimmed, '|') && str_ends_with($trimmed, '|')) {
                $closeList();
                if (!$inTable) { $inTable = true; $tableRows = []; }
                $tableRows[] = $trimmed;
                continue;
            } else {
                $flushTable();
            }

            // Horizontal rule
            if (preg_match('/^-{3,}$/', $trimmed) || preg_match('/^\*{3,}$/', $trimmed)) {
                $closeList();
                $html .= '<hr />';
                continue;
            }

            // Headings
            if (preg_match('/^#{4}\s+(.+)$/', $trimmed, $m)) {
                $closeList();
                $html .= '<h4>' . htmlspecialchars(trim($m[1]), ENT_COMPAT, 'UTF-8') . '</h4>';
                continue;
            }
            if (preg_match('/^#{3}\s+(.+)$/', $trimmed, $m)) {
                $closeList();
                $html .= '<h3>' . htmlspecialchars(trim($m[1]), ENT_COMPAT, 'UTF-8') . '</h3>';
                continue;
            }
            if (preg_match('/^#{2}\s+(.+)$/', $trimmed, $m)) {
                $closeList();
                $html .= '<h2>' . htmlspecialchars(trim($m[1]), ENT_COMPAT, 'UTF-8') . '</h2>';
                continue;
            }
            if (preg_match('/^#\s+(.+)$/', $trimmed, $m)) {
                $closeList();
                $html .= '<h1>' . htmlspecialchars(trim($m[1]), ENT_COMPAT, 'UTF-8') . '</h1>';
                continue;
            }

            // Blockquote
            if (preg_match('/^>\s+(.+)$/', $trimmed, $m)) {
                $closeList();
                $html .= '<blockquote>' . $this->inlineMarkdown(htmlspecialchars(trim($m[1]), ENT_COMPAT, 'UTF-8')) . '</blockquote>';
                continue;
            }

            // Unordered list
            if (preg_match('/^[-*•]\s+(.+)$/', $trimmed, $m)) {
                if ($inOl) { $html .= '</ol>'; $inOl = false; }
                if (!$inUl) { $html .= '<ul>'; $inUl = true; }
                $html .= '<li>' . $this->inlineMarkdown(htmlspecialchars(trim($m[1]), ENT_COMPAT, 'UTF-8')) . '</li>';
                continue;
            }

            // Ordered list
            if (preg_match('/^[\d١٢٣٤٥٦٧٨٩٠]+[.)]\s+(.+)$/u', $trimmed, $m)) {
                if ($inUl) { $html .= '</ul>'; $inUl = false; }
                if (!$inOl) { $html .= '<ol>'; $inOl = true; }
                $html .= '<li>' . $this->inlineMarkdown(htmlspecialchars(trim($m[1]), ENT_COMPAT, 'UTF-8')) . '</li>';
                continue;
            }

            // Blank line
            if (trim($trimmed) === '') {
                $closeList();
                continue; // skip blank lines — rely on paragraph spacing
            }

            // Paragraph
            $closeList();
            $html .= '<p>' . $this->inlineMarkdown(htmlspecialchars($trimmed, ENT_COMPAT, 'UTF-8')) . '</p>';
        }

        $closeList();
        $flushTable();

        return $html;
    }

    protected function inlineMarkdown(string $text): string
    {
        $text = preg_replace('/\*\*\*(.+?)\*\*\*/u', '<strong><em>$1</em></strong>', $text);
        $text = preg_replace('/\*\*(.+?)\*\*/u',     '<strong>$1</strong>', $text);
        $text = preg_replace('/\*(.+?)\*/u',          '<strong>$1</strong>', $text);
        $text = preg_replace('/`(.+?)`/u',            '$1', $text);
        return $text;
    }
}
