# UI Function Contracts: 011-formatted-output-modal

## Global JavaScript Functions

Defined inside `case-output-modal.blade.php`, accessible globally.

### `openOutputModal()`
- Collects all agent Markdown outputs from `dbOutputsByAgent` (agents 1–9)
- Joins with `---` dividers, parses via `marked.parse()`
- Injects HTML into `#outputModalContent`
- Removes `hidden` class from `#caseOutputModal`
- Locks body scroll (`document.body.style.overflow = 'hidden'`)

### `closeOutputModal()`
- Adds `hidden` class to `#caseOutputModal`
- Restores body scroll (`document.body.style.overflow = ''`)

### `activateOutputButton()`
- Finds `#outputModalBtn` (the "عرض النتائج" button container)
- Replaces disabled span with enabled button (same pattern as `activatePdfExportButton`)

## DOM IDs

| ID | Element | Purpose |
|---|---|---|
| `caseOutputModal` | `<div>` — modal overlay | Shown/hidden by JS |
| `outputModalContent` | `<div>` — modal body | marked.js HTML injected here |
| `outputModalBtn` | `<div>` — button container | Replaced on activation |
