# Static Components Report - Smart Legal Advisor

## Overview
This document identifies all static/hardcoded components in the application that need to be made dynamic for production readiness.

---

## 1. AI Analysis Page (`/ai-analysis`)

**File:** `resources/views/pages/ai-analysis.blade.php`

### Static Elements Found:

| Element | Location | Hardcoded Value | Dynamic Replacement |
|---------|----------|-----------------|---------------------|
| Overall Progress | Line 35 | `65%` | `$case->progress_percentage` |
| Progress Bar Width | Line 38 | `width: 65%` | Computed from case data |
| Stage 1 Status | Line 54 | `مكتمل` (Completed) | From agent execution status |
| Stage 1 Documents Count | Line 61 | `١٢ مستند قانوني` | Dynamic count from documents |
| Stage 2 Status | Line 72 | `مكتمل` | From agent execution status |
| Stage 2 Facts Count | Line 79 | `٨ وقائع قانونية` | Dynamic count from extracted facts |
| Stage 3 Status | Line 90 | `جاري التنفيذ...` | Dynamic from current execution |
| Stage 3 Progress | Line 95 | `w-[65%]` | Computed percentage |
| Stage 3 Description | Line 97 | `جاري البحث في قاعدة الأنظمة السعودية` | Dynamic |
| AI Insights - Documents | Line 164 | `١٢` | Dynamic document count |
| AI Insights - Facts | Line 168 | `٨` | Dynamic facts count |
| AI Insights - Laws | Line 172 | `٢٤` | Dynamic law matches count |

### Buttons:
- "إيقاف مؤقت" (Pause) - Line 13 - No functionality, needs JavaScript handler
- Refresh button - Line 17 - No functionality

### Stage Names (Dynamic OK):
- تحليل المستندات (Document Analysis)
- استخراج الوقائع (Facts Extraction)  
- مطابقة الأنظمة (Law Matching)
- التحليل القانوني (Legal Analysis)
- صياغة المذكرة (Brief Generation)
- المراجعة النهائية (Final Review)

---

## 2. Dashboard Page (`/dashboard`)

**File:** `resources/views/pages/dashboard.blade.php`

### Static Elements Found:

#### Statistics Cards:
| Element | Location | Hardcoded Value | Dynamic Replacement |
|---------|----------|-----------------|---------------------|
| Active Cases Increase | Line 18 | `+١٢%` | Computed from trend |
| Analyzing Cases Label | Line 29 | `جارٍ` | Static label |
| Completed Briefs Label | Line 40 | `مكتمل` | Static label |

#### Monthly Bar Chart:
- **Lines 74-81** - Entire `$months` array is hardcoded:
```php
$months = [
    ['name' => 'يناير', 'value' => 45, 'height' => 40],
    ['name' => 'فبراير', 'value' => 72, 'height' => 65],
    ['name' => 'مارس', 'value' => 98, 'height' => 85],
    ['name' => 'أبريل', 'value' => 60, 'height' => 55],
    ['name' => 'مايو', 'value' => 110, 'height' => 95],
    ['name' => 'يونيو', 'value' => 38, 'height' => 35],
];
```
**Needs:** Dynamic data from database for last 6 months

#### Doughnut Chart:
| Element | Location | Hardcoded Value | Dynamic Replacement |
|---------|----------|-----------------|---------------------|
| Completed Cases Label | Line 111 | `قضايا منتهية` | Dynamic |
| Completed Percentage | Line 113 | `٨٥%` | Dynamic from case status |
| Pending Cases Label | Line 118 | `قضايا معلقة` | Dynamic |
| Pending Percentage | Line 120 | `١٥%` | Dynamic from case status |

#### AI Agents Progress Section:
| Agent | Location | Hardcoded Progress | Dynamic Replacement |
|-------|----------|-------------------|---------------------|
| Agent 1 (Text Analysis) | Line 152 | `٩٢%` | From agent execution |
| Agent 1 Description | Line 149 | `جاري مطابقة القوانين مع الوقائع` | Dynamic |
| Agent 2 (Gap Detection) | Line 171 | `٤٥%` | From agent execution |
| Agent 2 Description | Line 168 | `فحص السوابق القضائية المماثلة` | Dynamic |
| Agent 3 (Drafting) | Line 190 | `١٢%` | From agent execution |
| Agent 3 Description | Line 187 | `بانتظار اكتمال التحليل النصي` | Dynamic |

---

## 3. Cases Index Page (`/cases`)

**File:** `resources/views/pages/cases/index.blade.php`

### Static Elements Found:

| Element | Location | Hardcoded Value | Dynamic Replacement |
|---------|----------|-----------------|---------------------|
| View All Link | Line 75 | `عرض الكل` | Should link to filter |

---

## 4. Settings Page (`/settings`)

**File:** `resources/views/pages/settings.blade.php`

### Notes:
- Uses API endpoints to fetch models dynamically
- No hardcoded static values detected
- Model configuration loads from OpenRouter API

---

## 5. Case Detail Page (`/cases/{id}`)

**File:** `resources/views/pages/cases/show.blade.php`

### Potential Static Areas:
- Agent timeline progress indicators
- Status badges (should verify)
- Pipeline tracker display

---

## 6. Model Configuration Component

**File:** `resources/views/components/agent-model-config.blade.php`

### Model List (Lines 29-57):
The model groups are hardcoded but should remain as curated list:
```php
$modelGroups = [
    'Anthropic' => [...],
    'OpenAI' => [...],
    'Google' => [...],
    'Meta' => [...],
    'Mistral' => [...],
];
```

**Recommendation:** Consider loading from API to ensure model validity

---

## Recommendations Summary

### High Priority (Must Fix):
1. **AI Analysis Page** - All progress indicators and counts are static
2. **Dashboard Chart Data** - Monthly cases chart data is hardcoded
3. **Dashboard Agent Progress** - Agent progress percentages are static

### Medium Priority:
1. **Dashboard Statistics Trends** - Increase/decrease indicators
2. **Case Status Percentages** - Doughnut chart breakdown

### Low Priority:
1. **Model Configuration List** - Consider dynamic loading

---

## Implementation Notes

### For AI Analysis Page:
- Create a controller method to fetch case progress
- Link to actual agent execution data
- Make stage statuses dynamic based on agent execution state

### For Dashboard:
- Fetch monthly case data from database
- Calculate completion rates dynamically
- Pull agent progress from AgentExecution model

### Testing:
- Use Playwright to verify dynamic data loads correctly
- Test case creation flow to ensure new data appears in charts