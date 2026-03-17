# Design System: Saudi Legal Case Orchestrator

**Project**: Saudi Legal Case Orchestration System  
**Stitch Project ID**: 18254556907662508752  
**Atmosphere**: Professional, authoritative, trustworthy—legal domain with Arabic RTL support

---

## 1. Color Palette

| Role | Name | Hex | Usage |
|------|------|-----|-------|
| Primary | Deep Navy | #1e3a5f | Headers, primary buttons |
| Secondary | Muted Gold | #c9a227 | Accents, highlights, progress |
| Background | Warm White | #f8f6f3 | Page background |
| Surface | Card White | #ffffff | Cards, modals |
| Text Primary | Charcoal | #2d3748 | Body text |
| Text Secondary | Slate | #718096 | Labels, hints |
| Success | Forest | #276749 | Completed states |
| Warning | Amber | #d69e2e | Pending, in-progress |
| Error | Crimson | #c53030 | Failed states |
| Border | Light Gray | #e2e8f0 | Dividers, borders |

---

## 2. Typography

- **Font**: Inter (Latin), Tajawal (Arabic)
- **Headings**: Semibold 600, scale 1.25
- **Body**: Regular 400, 16px base
- **Labels**: Medium 500, 14px
- **Support RTL**: `dir="rtl"` for Arabic content, `font-family: Tajawal`

---

## 3. Layout & Spacing

- **Container**: max-width 1280px, padding 24px
- **Cards**: border-radius 12px, shadow sm, padding 20px
- **Buttons**: border-radius 8px, padding 12px 24px
- **Spacing scale**: 4, 8, 12, 16, 24, 32, 48

---

## 4. Components

- **Primary button**: Navy bg, white text, hover darker
- **Secondary button**: Outlined navy, transparent bg
- **Status badges**: Pill shape, colored by status (processing=amber, completed=green, failed=red)
- **Progress bar**: Rounded, gold fill, gray track
- **Cards**: White, subtle shadow, hover lift

---

## 5. Responsive

- Mobile-first; breakpoints 640, 768, 1024, 1280
- Stack on mobile; grid 2–3 cols on desktop

---

## 6. Creative Freedom (for Stitch prompts)

Use the palette above. Keep a clean, professional legal UI. Include:
- Dashboard stats (total, processing, completed, failed)
- Case list with status badges and progress
- Form for new case (title, intake text, document upload)
- Case detail with tabs (overview, outputs, errors)
- Laws upload modal with drag-drop
- Output viewer with markdown rendering
- Error log table with filters
- Agent timeline (9+2 agents, status per agent)
- Settings page (model selector, threshold, cost breakdown)
