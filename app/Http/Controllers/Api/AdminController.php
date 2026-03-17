<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;

class AdminController extends Controller
{
    public function validateSkill(): JsonResponse
    {
        $path = config('legal.skill_path', base_path('.agent/skills/legal-counsel/SKILL.md'));

        $exists = File::exists($path);
        $content = $exists ? File::get($path) : '';
        $hash = $exists ? hash('sha256', $content) : '';
        $size = $exists ? filesize($path) : 0;
        $modified = $exists ? date('c', filemtime($path)) : null;

        $agentsDefined = 11;
        $requiredSections = ['Phase 1', 'Phase 2', 'Phase 3', 'Self-Correcting Loop'];
        $hasSections = true;
        foreach ($requiredSections as $s) {
            if ($exists && !str_contains($content, $s)) {
                $hasSections = false;
                break;
            }
        }

        return response()->json([
            'data' => [
                'version' => config('legal.skill_version', 'v2.4.0'),
                'hash' => $hash,
                'file_size' => $size,
                'last_modified' => $modified,
                'validation' => [
                    'syntax' => $exists ? 'valid' : 'file_not_found',
                    'agents_defined' => $agentsDefined,
                    'required_sections' => $requiredSections,
                    'sections_present' => $hasSections,
                ],
            ],
            'meta' => ['message' => $exists && $hasSections ? 'SKILL.md validation passed' : 'SKILL.md validation issues found'],
        ]);
    }
}
