<?php

namespace App\Services\Pdf;

use Mpdf\Mpdf;

class PdfGenerator
{
    public function generateFromMarkdown(string $markdown, string $title = 'Legal Brief'): string
    {
        $html = $this->markdownToHtml($markdown);
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'default_font' => 'dejavusans',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 16,
            'margin_bottom' => 16,
        ]);

        $mpdf->SetDirectionality('rtl');
        $mpdf->SetDocTemplateValue('title', $title);
        $mpdf->WriteHTML($html);
        return $mpdf->Output('', 'S');
    }

    protected function markdownToHtml(string $md): string
    {
        $lines = explode("\n", $md);
        $html = [];
        $inCode = false;
        $inList = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '```')) {
                $inCode = !$inCode;
                $html[] = $inCode ? '<pre>' : '</pre>';
                continue;
            }
            if ($inCode) {
                $html[] = htmlspecialchars($line);
                continue;
            }
            if (preg_match('/^#{1,6}\s+(.+)/', $trimmed, $m)) {
                $level = min(6, strlen($trimmed) - strlen(ltrim($trimmed, '#')));
                $html[] = "<h{$level}>" . htmlspecialchars(trim($m[1])) . "</h{$level}>";
                continue;
            }
            if (preg_match('/^[-*]\s+(.+)/', $trimmed, $m)) {
                if (!$inList) {
                    $html[] = '<ul>';
                    $inList = true;
                }
                $html[] = '<li>' . htmlspecialchars($m[1]) . '</li>';
                continue;
            }
            if ($inList) {
                $html[] = '</ul>';
                $inList = false;
            }
            if ($trimmed !== '') {
                $html[] = '<p>' . nl2br(htmlspecialchars($trimmed)) . '</p>';
            } else {
                $html[] = '<br>';
            }
        }
        if ($inList) {
            $html[] = '</ul>';
        }

        $body = implode("\n", $html);
        return "<!DOCTYPE html><html dir='rtl'><head><meta charset='UTF-8'></head><body>{$body}</body></html>";
    }
}
