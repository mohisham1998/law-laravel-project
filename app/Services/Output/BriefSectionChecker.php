<?php

namespace App\Services\Output;

/**
 * Checks which required sections are present in an Arabic legal brief.
 * Required sections are modelled on the preferred output structure.
 */
class BriefSectionChecker
{
    /**
     * Section keys that must exist in the body of the brief.
     * Missing body sections trigger an LLM gap-fill pass.
     */
    public const BODY_SECTIONS = [
        'section_one',
        'section_two',
        'section_three',
        'section_four',
        'closing',
    ];

    /**
     * Human-readable labels for each section key.
     */
    public const SECTION_LABELS = [
        'basmalah'           => 'البسملة',
        'court_address'      => 'مخاطبة المحكمة',
        'case_header'        => 'بيانات القضية (رقم القضية + أطراف)',
        'section_one'        => 'أولاً: المقدمة والتأطير',
        'section_two'        => 'ثانياً: المبدأ النظامي / عبء الإثبات',
        'section_three'      => 'ثالثاً: تجريح الشهود',
        'section_four'       => 'رابعاً: الخلاصة',
        'requests'           => 'خامساً: الطلبات (أصلية + احتياطية + تبعية)',
        'closing'            => 'سادساً: الخاتمة',
        'signature'          => 'توقيع المحامي + الترخيص',
        'appendix1'          => 'ملحق (١): مسرد الوقائع الزمنية',
        'appendix2'          => 'ملحق (٢): المواد النظامية المستشهد بها',
    ];

    /**
     * Returns a list of section keys that are missing or incomplete.
     */
    public function getMissingSections(string $brief): array
    {
        $missing = [];

        // البسملة
        if (!$this->has($brief, ['بسم الله'])) {
            $missing[] = 'basmalah';
        }

        // مخاطبة المحكمة
        if (!$this->hasAny($brief, ['إلى أصحاب الفضيلة', 'إلى فضيلة'])) {
            $missing[] = 'court_address';
        }

        // بيانات القضية
        if (!($this->has($brief, ['القضية رقم']) && $this->hasAny($brief, ['المدعية', 'المدعى عليه']))) {
            $missing[] = 'case_header';
        }

        // أولاً: المقدمة
        if (!$this->has($brief, ['أولاً'])) {
            $missing[] = 'section_one';
        }

        // ثانياً: عبء الإثبات
        if (!$this->has($brief, ['ثانياً'])) {
            $missing[] = 'section_two';
        }

        // ثالثاً: تجريح الشهود (section must exist with testimony-related content)
        // We do not require multiple witnesses — the LLM addresses witnesses based on case facts.
        $hasThree = $this->has($brief, ['ثالثاً']) && $this->hasAny($brief, ['تجريح', 'الشاهد', 'الشاهدة', 'شهادة']);

        if (!$hasThree) {
            $missing[] = 'section_three';
        }

        // رابعاً: any fourth section (summary, conclusion of argument, lack of intent, etc.)
        if (!$this->has($brief, ['رابعاً'])) {
            $missing[] = 'section_four';
        }

        // خامساً: الطلبات (all three tiers required)
        if (
            !$this->has($brief, ['الطلبات الأصلية'])
            || !$this->has($brief, ['الطلبات الاحتياطية'])
            || !$this->has($brief, ['الطلبات التبعية'])
        ) {
            $missing[] = 'requests';
        }

        // سادساً: الخاتمة
        $hasClosingHeading = $this->hasAny($brief, ['سادساً', 'الخاتمة']);
        $hasClosingDua     = $this->hasAny($brief, ['والله يوفق', 'وصلى الله', 'يسدد خطاكم']);
        if (!$hasClosingHeading || !$hasClosingDua) {
            $missing[] = 'closing';
        }

        // توقيع المحامي + الترخيص
        if (!$this->has($brief, ['ترخيص']) || !$this->hasAny($brief, ['المحامي', 'مقدم المذكرة', 'وكيل'])) {
            $missing[] = 'signature';
        }

        // ملحق ١: الوقائع الزمنية
        $hasApp1 = $this->has($brief, ['ملحق']) &&
                   $this->hasAny($brief, ['مسرد الوقائع', 'الوقائع الزمنية', 'وقائع زمن', 'زمني']);
        if (!$hasApp1) {
            $missing[] = 'appendix1';
        }

        // ملحق ٢: المواد النظامية
        $hasApp2 = $this->has($brief, ['ملحق']) &&
                   $this->hasAny($brief, ['المواد النظامية', 'نظامية', 'مستشهد']);
        if (!$hasApp2) {
            $missing[] = 'appendix2';
        }

        return $missing;
    }

    /**
     * Returns human-readable labels for an array of section keys.
     */
    public function getLabels(array $sectionKeys): array
    {
        return array_map(fn ($k) => self::SECTION_LABELS[$k] ?? $k, $sectionKeys);
    }

    /**
     * Returns true if any of the given missing section keys are body sections
     * (require LLM to generate).
     */
    public function hasMissingBodySections(array $missingSections): bool
    {
        return !empty(array_intersect($missingSections, self::BODY_SECTIONS));
    }

    // -------------------------------------------------------------------------

    private function has(string $text, array $allOf): bool
    {
        foreach ($allOf as $needle) {
            if (mb_strpos($text, $needle) === false) {
                return false;
            }
        }
        return true;
    }

    private function hasAny(string $text, array $anyOf): bool
    {
        foreach ($anyOf as $needle) {
            if (mb_strpos($text, $needle) !== false) {
                return true;
            }
        }
        return false;
    }
}
