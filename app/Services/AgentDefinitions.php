<?php

namespace App\Services;

class AgentDefinitions
{
    /**
     * Static list of all 14 agents for dashboard UI and output chain.
     * Kept in sync with SKILL.md (see Portal Integration section).
     *
     * @return list<array{number: int, phase: int, name: string, name_en: string, outputs: list<string>, inputs: list<string>}>
     */
    public static function all(): array
    {
        return [
            ['number' => 0, 'phase' => 1, 'name' => 'تحليل القضية', 'name_en' => 'Case Analysis', 'outputs' => ['00_required_laws.md'], 'inputs' => ['intake.txt', 'docs/*']],
            ['number' => 1, 'phase' => 2, 'name' => 'القائد القانوني', 'name_en' => 'Lead Counsel', 'outputs' => ['01_lead_plan.md', '01_acceptance_criteria.json'], 'inputs' => ['intake.txt', 'docs/*', 'memory/errors_log.md']],
            ['number' => 2, 'phase' => 2, 'name' => 'مدير الأدلة', 'name_en' => 'Evidence Manager', 'outputs' => ['02_chunks.jsonl', '02_ingestion_report.md'], 'inputs' => ['docs/*']],
            ['number' => 3, 'phase' => 2, 'name' => 'سلسلة الحفظ', 'name_en' => 'Chain of Custody', 'outputs' => ['03_statutes_index.jsonl', '03_conflict_warnings.md', '03_chain_of_custody_summary.md'], 'inputs' => ['docs/*', 'RAG database']],
            ['number' => 4, 'phase' => 2, 'name' => 'الجدول الزمني', 'name_en' => 'Timeline Extractor', 'outputs' => ['04_timeline.json', '04_timeline.md', '04_entities_index.md'], 'inputs' => ['02_chunks.jsonl']],
            ['number' => 5, 'phase' => 2, 'name' => 'مدير القانون', 'name_en' => 'Law Manager', 'outputs' => ['05_issues_to_statutes.md', '05_procedural_notes.md', '05_adversary_evidence_analysis.md', '05_matching_guidelines.json'], 'inputs' => ['02_chunks.jsonl', '04_timeline.json', '03_statutes_index.jsonl']],
            ['number' => 6, 'phase' => 2, 'name' => 'مطابق الأنظمة', 'name_en' => 'Statute Matcher', 'outputs' => ['06_statutes_map.jsonl', '06_accepted_matches.md', '06_rejections.md', '06_gaps_and_todo.md'], 'inputs' => ['02_chunks.jsonl', '05_matching_guidelines.json', '03_statutes_index.jsonl']],
            ['number' => 7, 'phase' => 2, 'name' => 'الاستراتيجي', 'name_en' => 'Defense Strategist', 'outputs' => ['07_risk_matrix.md', '07_defense_layers.md', '07_charges_scenarios.json', '07_mitigation_opportunities.md'], 'inputs' => ['06_statutes_map.jsonl', '04_timeline.json', '05_procedural_notes.md']],
            ['number' => 8, 'phase' => 2, 'name' => 'الصائغ القانوني', 'name_en' => 'Legal Drafter', 'outputs' => ['08_final_brief.md', '08_defense_arguments.md', '08_arguments_index.json'], 'inputs' => ['01-07 outputs', 'memory/errors_log.md']],
            ['number' => 9, 'phase' => 2, 'name' => 'ضبط الجودة', 'name_en' => 'Quality Assurance', 'outputs' => ['09_QA_summary.md', '09_final_brief_v2.md'], 'inputs' => ['08_final_brief.md', 'all upstream']],
            ['number' => 10, 'phase' => 3, 'name' => 'القاضي', 'name_en' => 'Judge', 'outputs' => ['10_judge_notes.md'], 'inputs' => ['09_final_brief_v2.md', 'all upstream']],
            ['number' => 11, 'phase' => 3, 'name' => 'محامي الخصم', 'name_en' => "Devil's Advocate", 'outputs' => ['11_devils_advocate_notes.md'], 'inputs' => ['09_final_brief_v2.md', 'all upstream']],
            ['number' => 12, 'phase' => 3, 'name' => 'وكيل التحصين', 'name_en' => 'Fortification Agent', 'outputs' => ['12_fortification_plan.md', '13_final_brief_v3.md'], 'inputs' => ['09_final_brief_v2.md', '10_judge_notes.md', '11_devils_advocate_notes.md']],
            ['number' => 13, 'phase' => 3, 'name' => 'مدقق اللغة العربية', 'name_en' => 'Arabic Language Polisher', 'outputs' => ['14_final_brief_polished.md'], 'inputs' => ['13_final_brief_v3.md']],
        ];
    }
}
