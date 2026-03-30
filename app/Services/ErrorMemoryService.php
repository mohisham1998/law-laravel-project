<?php

namespace App\Services;

use App\Models\LegalCase;
use Illuminate\Support\Facades\Storage;

class ErrorMemoryService
{
    public function getMemoryPath(string $caseId): string
    {
        return "cases/{$caseId}/memory/errors_log.md";
    }

    public function read(string $caseId): string
    {
        $path = $this->getMemoryPath($caseId);
        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->get($path);
        }
        return '';
    }

    public function append(string $caseId, array $entry): void
    {
        $path = $this->getMemoryPath($caseId);
        $existing = $this->read($caseId);
        $count = substr_count($existing, '### Error #') + 1;

        $block = "\n---\n### Error #{$count} — " . now()->toDateTimeString() . "\n";
        $block .= "- **Discovering Agent**: Agent {$entry['discovering_agent_number']} ({$entry['discovering_agent_name']})\n";
        $block .= "- **Error Type**: {$entry['error_type']}\n";
        $block .= "- **Responsible Agent**: Agent " . ($entry['responsible_agent'] ?? $entry['discovering_agent_number']) . "\n";
        $block .= "- **Details**: {$entry['details']}\n";
        $block .= "- **Impact**: " . ($entry['impact'] ?? 'قد يؤثر على جودة المخرجات') . "\n";
        $block .= "- **Fix Applied**: " . ($entry['fix_applied'] ?? 'لم يتم التصحيح بعد') . "\n";
        $block .= "- **Lesson Learned**: " . ($entry['lesson_learned'] ?? 'يجب التحقق من هذا النوع من الأخطاء') . "\n";
        $block .= "---\n";

        if (empty($existing)) {
            $content = "# Error Memory — سجل الأخطاء\n\n" . $block;
        } else {
            $content = $existing . $block;
        }

        Storage::disk('local')->put($path, $content);
    }

    public function clear(string $caseId): void
    {
        $path = $this->getMemoryPath($caseId);
        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }
}
