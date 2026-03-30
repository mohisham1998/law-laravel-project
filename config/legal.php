<?php

return [
    'skill_version' => env('SKILL_VERSION', 'v2.4.0'),
    'skill_path' => env('SKILL_PATH', base_path('.agent/skills/legal-counsel/SKILL.md')),
    'confidence_threshold' => (float) env('CONFIDENCE_THRESHOLD', 0.70),
    'rate_limit_cases_per_hour' => (int) env('RATE_LIMIT_CASES_PER_HOUR', 10),
    'max_file_size_mb' => (int) env('MAX_FILE_SIZE_MB', 10),
    'allowed_file_extensions' => ['txt', 'md'],
    'agent_timeout_seconds' => (int) env('AGENT_TIMEOUT_SECONDS', 180), // 3 minutes per agent
    'agent_max_retries' => (int) env('AGENT_MAX_RETRIES', 3),
    'audit_passing_threshold' => (int) env('AUDIT_PASSING_THRESHOLD', 70),
    'audit_soft_timeout_seconds' => (int) env('AUDIT_SOFT_TIMEOUT_SECONDS', 10),
    'audit_hard_timeout_seconds' => (int) env('AUDIT_HARD_TIMEOUT_SECONDS', 30),
    'pipeline_timeout_minutes' => (int) env('PIPELINE_TIMEOUT_MINUTES', 30),
    'retry_budget_per_case' => (int) env('RETRY_BUDGET_PER_CASE', 5),
    'job_retries' => (int) env('JOB_RETRIES', 2),

    /*
    |--------------------------------------------------------------------------
    | Per-Agent Configuration
    |--------------------------------------------------------------------------
    | Temperature and max_tokens calibrated per agent role.
    | Keyed by agent number (0-12). Values sourced from SKILL.md analysis.
    */
    'agents' => [
        0  => ['temperature' => 0.3, 'max_tokens' => 4096],   // Phase 1 Analysis
        1  => ['temperature' => 0.3, 'max_tokens' => 8192],   // Lead Counsel
        2  => ['temperature' => 0.2, 'max_tokens' => 8192],   // Evidence Manager
        3  => ['temperature' => 0.2, 'max_tokens' => 8192],   // Chain of Custody
        4  => ['temperature' => 0.3, 'max_tokens' => 8192],   // Timeline Extractor
        5  => ['temperature' => 0.3, 'max_tokens' => 8192],   // Law Manager
        6  => ['temperature' => 0.3, 'max_tokens' => 8192],   // Statute Matcher
        7  => ['temperature' => 0.3, 'max_tokens' => 8192],   // Defense Strategist
        8  => ['temperature' => 0.3, 'max_tokens' => 16384],  // Legal Drafter
        9  => ['temperature' => 0.2, 'max_tokens' => 16384],  // Quality Assurance
        10 => ['temperature' => 0.3, 'max_tokens' => 8192],   // Judge
        11 => ['temperature' => 0.3, 'max_tokens' => 8192],   // Devil's Advocate
        12 => ['temperature' => 0.3, 'max_tokens' => 16384],  // Fortification
    ],
];
