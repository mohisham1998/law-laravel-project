<?php

namespace App\Services;

class AgentDefinitions
{
    /**
     * Static list of all 12 agents for dashboard UI and output chain.
     * Kept in sync with SKILL.md (see Portal Integration section).
     *
     * @return list<array{number: int, phase: int, name: string, name_en: string, outputs: list<string>, inputs: list<string>}>
     */
    public static function all(): array
    {
        return [
            ['number' => 0, 'phase' => 1, 'name' => 'تحليل القضية', 'name_en' => 'Case Analysis', 'outputs' => ['00_required_laws.md'], 'inputs' => ['intake.txt', 'docs/*']],
            ['number' => 1, 'phase' => 2, 'name' => 'قائد القضية', 'name_en' => 'Lead Counsel', 'outputs' => ['01_lead_plan.md', '01_acceptance_criteria.json'], 'inputs' => ['intake.txt', 'docs/*']],
            ['number' => 2, 'phase' => 2, 'name' => 'مدير الأدلة', 'name_en' => 'Evidence', 'outputs' => ['02_ingestion_report.md', '02_chunks.jsonl'], 'inputs' => ['docs/*', '01_acceptance_criteria.json']],
            ['number' => 3, 'phase' => 2, 'name' => 'النزاهة والفهرسة', 'name_en' => 'Indexing', 'outputs' => ['03_chain_of_custody.jsonl', '03_statutes_index.jsonl', '03_conflict_warnings.md'], 'inputs' => ['02_chunks.jsonl', 'laws/*']],
            ['number' => 4, 'phase' => 2, 'name' => 'الجدول الزمني', 'name_en' => 'Timeline', 'outputs' => ['04_timeline.json', '04_timeline.md', '04_entities_index.md'], 'inputs' => ['02_chunks.jsonl']],
            ['number' => 5, 'phase' => 2, 'name' => 'مدير القانون', 'name_en' => 'Law Lead', 'outputs' => ['05_issues_to_statutes.md', '05_procedural_notes.md', '05_matching_guidelines.json'], 'inputs' => ['02_chunks.jsonl', '04_timeline.json', '03_statutes_index.jsonl']],
            ['number' => 6, 'phase' => 2, 'name' => 'المطابقة النظامية', 'name_en' => 'Matcher', 'outputs' => ['06_statutes_map.jsonl', '06_accepted_matches.md', '06_gaps_and_todo.md'], 'inputs' => ['02_chunks.jsonl', '05_matching_guidelines.json', '03_statutes_index.jsonl']],
            ['number' => 7, 'phase' => 2, 'name' => 'فريق الدفاع', 'name_en' => 'Defense', 'outputs' => ['07_defense_skeleton.md'], 'inputs' => ['04_timeline.json', '05_issues_to_statutes.md', '06_statutes_map.jsonl']],
            ['number' => 8, 'phase' => 2, 'name' => 'صائغ المذكرة', 'name_en' => 'Drafter', 'outputs' => ['08_draft_brief.md'], 'inputs' => ['07_defense_skeleton.md', '06_accepted_matches.md', '04_timeline.md']],
            ['number' => 9, 'phase' => 2, 'name' => 'المراجعة النهائية', 'name_en' => 'Final', 'outputs' => ['09_final_brief_v2.md'], 'inputs' => ['08_draft_brief.md', '01_acceptance_criteria.json']],
            ['number' => 10, 'phase' => 3, 'name' => 'القاضي', 'name_en' => 'Judge', 'outputs' => ['10_judge_review.md'], 'inputs' => ['09_final_brief_v2.md']],
            ['number' => 11, 'phase' => 3, 'name' => 'محامي الشيطان', 'name_en' => "Devil's Advocate", 'outputs' => ['11_final_hardened_brief.md'], 'inputs' => ['09_final_brief_v2.md', '10_judge_review.md']],
        ];
    }
}
