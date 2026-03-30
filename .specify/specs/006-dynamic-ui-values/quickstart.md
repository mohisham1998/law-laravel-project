# Quickstart Guide: Dynamic UI Values Feature

## Overview

This feature replaces all static/hardcoded UI values in the Smart Legal Advisor with dynamic, real-data-driven behavior.

## Prerequisites

- PHP 8.x
- Laravel 11
- MySQL or SQLite for development
- Docker (optional, for containerized setup)

## Quick Setup

1. **Ensure database is migrated**:
   ```bash
   php artisan migrate
   ```

2. **Seed test data** (optional):
   ```bash
   php artisan db:seed
   ```

3. **Start development server**:
   ```bash
   php artisan serve
   ```

## Key Changes

### 1. AI Analysis Page (`/ai-analysis`)

**Files Modified**:
- `resources/views/pages/ai-analysis.blade.php`

**Changes**:
- Replace hardcoded `65%` with `{{ $case->progress_percentage }}`
- Replace static stage statuses with data from `AgentExecution` model
- Replace document/fact/law counts with dynamic queries

### 2. Dashboard Page (`/dashboard`)

**Files Modified**:
- `resources/views/pages/dashboard.blade.php`
- `app/Http/Controllers/DashboardController.php`

**Changes**:
- Replace hardcoded monthly chart data with database queries
- Replace static percentages with calculated values
- Replace agent progress indicators with real `AgentExecution` data

### 3. Case Detail Page

**Files Modified**:
- `resources/views/pages/cases/show.blade.php`

**Changes**:
- Make timeline dynamic based on `AgentExecution` records
- Show real status badges instead of static labels

### 4. API Endpoints (New)

**Routes to add** (`routes/api.php`):
- `GET /api/cases/{id}/progress` - Case progress data
- `POST /api/cases/{id}/pause` - Pause case processing
- `GET /api/dashboard/stats` - Dashboard statistics

## Testing

### Manual Testing

1. **Create a test case**:
   Visit `/cases/create` and submit a new case

2. **Check AI Analysis page**:
   Visit `/ai-analysis?case_id={id}` and verify progress shows 0%

3. **Process the case**:
   Start case processing and verify progress updates

4. **Check dashboard**:
   Visit `/dashboard` and verify statistics reflect real counts

### Automated Testing

```bash
# Run PHPUnit tests
php artisan test

# Run specific feature tests
php artisan test --filter=DynamicUI
```

## Troubleshooting

### Progress shows 0%

Check that:
1. Case has `progress_percentage` set
2. Agent executions exist in database

### Dashboard shows wrong counts

Check that:
1. Database is properly seeded
2. DashboardController queries are correct

### Pause button doesn't work

Check that:
1. API endpoint exists
2. Route is properly registered
3. JavaScript is properly connected

## Implementation Order

1. Add API endpoints for case data
2. Update DashboardController for real statistics
3. Update ai-analysis.blade.php
4. Update dashboard.blade.php
5. Update cases pages
6. Test all changes

## Reference

- **Specification**: [spec.md](spec.md)
- **Plan**: [plan.md](plan.md)
- **Data Model**: [data-model.md](data-model.md)