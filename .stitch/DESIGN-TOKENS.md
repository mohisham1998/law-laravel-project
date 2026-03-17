# Stitch Design Tokens - Legal Advisor System

**Project ID**: 18254556907662508752  
**Project Title**: الصفحة الرئيسية - المستشار القانوني الذكي  
**Device Type**: Desktop  
**Direction**: RTL (Right-to-Left)

## Color Palette

### Primary Colors
```css
--primary: #006b34;           /* Saudi green - primary brand color */
--primary-light: #006b3410;   /* 10% opacity for backgrounds */
--primary-dark: #0f2319;      /* Dark mode background */
```

### Background Colors
```css
--background-light: #f5f8f7;  /* Light mode background */
--background-dark: #0f2319;   /* Dark mode background */
--card-bg: #ffffff;           /* Card background */
```

### Semantic Colors
```css
--success: #10b981;           /* Green for completed states */
--warning: #f59e0b;           /* Orange for in-progress */
--danger: #ef4444;            /* Red for errors */
--info: #3b82f6;              /* Blue for informational */
```

### Neutral Colors
```css
--slate-50: #f8fafc;
--slate-100: #f1f5f9;
--slate-200: #e2e8f0;
--slate-400: #94a3b8;
--slate-500: #64748b;
--slate-600: #475569;
--slate-900: #0f172a;
```

## Typography

### Font Families
```css
--font-display: 'Cairo', sans-serif;  /* Arabic-optimized font */
--font-body: 'Cairo', sans-serif;
--font-mono: 'Courier New', monospace;
```

### Font Sizes
```css
--text-xs: 0.75rem;      /* 12px */
--text-sm: 0.875rem;     /* 14px */
--text-base: 1rem;       /* 16px */
--text-lg: 1.125rem;     /* 18px */
--text-xl: 1.25rem;      /* 20px */
--text-2xl: 1.5rem;      /* 24px */
--text-3xl: 1.875rem;    /* 30px */
```

### Font Weights
```css
--font-normal: 400;
--font-medium: 500;
--font-semibold: 600;
--font-bold: 700;
--font-extrabold: 800;
--font-black: 900;
```

## Spacing & Layout

### Border Radius
```css
--radius-default: 0.5rem;    /* 8px - default */
--radius-lg: 1rem;           /* 16px - cards */
--radius-xl: 1.5rem;         /* 24px - large cards */
--radius-full: 9999px;       /* fully rounded */
```

### Spacing Scale
```css
--space-1: 0.25rem;   /* 4px */
--space-2: 0.5rem;    /* 8px */
--space-3: 0.75rem;   /* 12px */
--space-4: 1rem;      /* 16px */
--space-6: 1.5rem;    /* 24px */
--space-8: 2rem;      /* 32px */
```

## Components

### Cards
```css
.notion-card {
    background: white;
    border: 1px solid rgba(0, 107, 52, 0.1);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
    border-radius: 1rem;
}
```

### Buttons
```css
.btn-primary {
    background: #006b34;
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.75rem;
    font-weight: 700;
}

.btn-primary:hover {
    background: #005527;
}
```

### Navigation
```css
.nav-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border-radius: 0.75rem;
    transition: all 0.2s;
}

.nav-item.active {
    background: rgba(0, 107, 52, 0.1);
    color: #006b34;
    font-weight: 700;
}

.nav-item:hover {
    background: rgba(0, 0, 0, 0.03);
}
```

## Icons

**Icon Library**: Material Symbols Outlined  
**CDN**: `https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined`

### Common Icons
- Dashboard: `dashboard`
- Cases: `work`
- Documents: `description`
- Analysis: `psychology`
- Reports: `assessment`
- Settings: `settings`
- Notifications: `notifications`
- Search: `search`
- Upload: `upload`
- Download: `download`

## Effects & Animations

### Shadows
```css
--shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
--shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
--shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
```

### Transitions
```css
--transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
--transition-base: 200ms cubic-bezier(0.4, 0, 0.2, 1);
--transition-slow: 300ms cubic-bezier(0.4, 0, 0.2, 1);
```

### Hover Effects
```css
.hover-lift:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}
```

## Filament Integration Mapping

### Color Mapping
- Filament Primary → `#006b34`
- Filament Success → `#10b981`
- Filament Warning → `#f59e0b`
- Filament Danger → `#ef4444`
- Filament Info → `#3b82f6`

### Component Mapping
- Stitch Cards → Filament `Section` with custom styling
- Stitch Buttons → Filament `Action` with primary color
- Stitch Tables → Filament `Table` with custom columns
- Stitch Forms → Filament `Schema` with Arabic labels
- Stitch Stats → Filament `StatsOverviewWidget`
- Stitch Charts → Filament `ChartWidget`

## Implementation Notes

1. **Font Loading**: Add Cairo font via Google Fonts in Filament theme
2. **RTL Support**: Filament 5.x handles RTL automatically with proper configuration
3. **Icons**: Material Symbols can be used alongside Heroicons
4. **Custom CSS**: Create `resources/css/filament/admin/theme.css` for custom styling
5. **Color Consistency**: Update AdminPanelProvider colors to match Stitch palette
