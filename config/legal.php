<?php

return [
    'skill_version' => env('SKILL_VERSION', 'v2.4.0'),
    'skill_path' => env('SKILL_PATH', base_path('.agent/skills/legal-counsel/SKILL.md')),
    'confidence_threshold' => (float) env('CONFIDENCE_THRESHOLD', 0.70),
    'rate_limit_cases_per_hour' => (int) env('RATE_LIMIT_CASES_PER_HOUR', 10),
    'max_file_size_mb' => (int) env('MAX_FILE_SIZE_MB', 10),
    'allowed_file_extensions' => ['txt', 'md'],
];
