<?php

namespace App\Services;

use App\Models\LegalCase;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;

class PdfExportService
{
    /**
     * Generate Arabic RTL PDF from the case's final brief (09_final_brief_v2.md or 11_final_hardened_brief.md).
     */
    public function generate(LegalCase $case): string
    {
        $content = $this->getFinalBriefContent($case);
        if ($content === null || $content === '') {
            throw new \RuntimeException('لا يوجد مذكرة نهائية لهذه القضية بعد.');
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font_size' => 12,
            'default_font' => 'dejavusans',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 20,
            'margin_bottom' => 20,
        ]);

        $mpdf->SetDirectionality('rtl');
        $mpdf->WriteHTML($this->wrapForRtl($content), \Mpdf\HTMLParserMode::HTML_BODY);
        $mpdf->SetFooter('صفحة {PAGENO} من {nb}| تاريخ التصدير: ' . now()->format('Y-m-d') . ' | القضية: ' . ($case->title ?? $case->id));

        return $mpdf->Output('', 'S');
    }

    protected function getFinalBriefContent(LegalCase $case): ?string
    {
        $basePath = "cases/{$case->id}/outputs";
        $candidates = ['11_final_hardened_brief.md', '09_final_brief_v2.md'];
        foreach ($candidates as $file) {
            $path = "{$basePath}/{$file}";
            if (Storage::disk('local')->exists($path)) {
                return Storage::disk('local')->get($path);
            }
        }

        return null;
    }

    /**
     * Wrap markdown-like content for RTL and basic HTML (mPDF).
     */
    protected function wrapForRtl(string $content): string
    {
        $html = '<div dir="rtl" style="font-family: dejavusans, sans-serif;">';
        $html .= nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'));
        $html .= '</div>';

        return $html;
    }
}
