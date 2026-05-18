---
name: Fleet Operational Pro
colors:
  surface: '#f8f9fa'
  surface-dim: '#d9dadb'
  surface-bright: '#f8f9fa'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f3f4f5'
  surface-container: '#edeeef'
  surface-container-high: '#e7e8e9'
  surface-container-highest: '#e1e3e4'
  on-surface: '#191c1d'
  on-surface-variant: '#46464c'
  inverse-surface: '#2e3132'
  inverse-on-surface: '#f0f1f2'
  outline: '#77767d'
  outline-variant: '#c7c5cd'
  surface-tint: '#595d73'
  primary: '#080c1e'
  on-primary: '#ffffff'
  primary-container: '#1e2235'
  on-primary-container: '#8689a0'
  inverse-primary: '#c2c5de'
  secondary: '#0059bb'
  on-secondary: '#ffffff'
  secondary-container: '#0070ea'
  on-secondary-container: '#fefcff'
  tertiary: '#001102'
  on-tertiary: '#ffffff'
  tertiary-container: '#002a09'
  on-tertiary-container: '#1c9f3e'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#dee1fb'
  primary-fixed-dim: '#c2c5de'
  on-primary-fixed: '#161b2d'
  on-primary-fixed-variant: '#42465a'
  secondary-fixed: '#d8e2ff'
  secondary-fixed-dim: '#adc7ff'
  on-secondary-fixed: '#001a41'
  on-secondary-fixed-variant: '#004493'
  tertiary-fixed: '#83fc8e'
  tertiary-fixed-dim: '#66df75'
  on-tertiary-fixed: '#002106'
  on-tertiary-fixed-variant: '#00531a'
  background: '#f8f9fa'
  on-background: '#191c1d'
  surface-variant: '#e1e3e4'
typography:
  headline-xl:
    fontFamily: Plus Jakarta Sans
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 32px
  headline-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  body-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  body-sm:
    fontFamily: Plus Jakarta Sans
    fontSize: 12px
    fontWeight: '400'
    lineHeight: 16px
  label-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 13px
    fontWeight: '600'
    lineHeight: 18px
  table-header:
    fontFamily: Plus Jakarta Sans
    fontSize: 12px
    fontWeight: '600'
    lineHeight: 16px
    letterSpacing: 0.02em
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  margin-sm: 16px
  margin-md: 24px
  gutter: 12px
  sidebar-width: 240px
  modal-padding: 32px
---

## Brand & Style

This design system is built for high-efficiency operational environments, specifically fleet management and logistics. The personality is **Professional, Systematic, and Efficient**. It balances a high-density data environment with a clear sense of focus through the use of strong contrast between the workspace and the navigational shell.

The design style is **Corporate Modern**, prioritizing information hierarchy and functional clarity. It utilizes a "dark-mode" navigation anchor to reduce visual noise while maintaining a "light-mode" content area for maximum legibility of complex data tables and financial records. The emotional response is one of control, reliability, and precision.

## Colors

The palette is anchored by a deep navy sidebar that provides a stable frame for the application. The operational interface uses a clean, white-based system with specific semantic accents:

- **Sidebar/Primary:** A deep, professional navy (#1E2235) used for navigation and high-level structure.
- **Action/Information:** A vibrant sky blue is used for primary actions, links, and informational tags (e.g., "Deposit OP").
- **Success/Status:** A bright emerald green is utilized for positive status indicators like "Selesai" or "Approved."
- **Surface/Background:** The workspace uses a very light grey (#F1F3F5) to differentiate between the white modal surfaces and the background application layer.
- **Inactive/Muted:** Subtle greys are used for borders and secondary text to maintain a low cognitive load in data-heavy views.

## Typography

The typography system utilizes **Plus Jakarta Sans** for its high legibility and modern, slightly technical feel. 

1. **Hierarchy:** Headlines use semi-bold and bold weights to anchor the eye.
2. **Data Tables:** Table headers are rendered in a slightly smaller, bold font with increased letter spacing to distinguish them from row content.
3. **Labels:** Small labels and status badges use a 12px or 13px size to maximize space efficiency without sacrificing readability.
4. **Contrast:** Darker text (#212529) is used for primary data, while secondary metadata (e.g., driver names, sub-codes) uses a lighter grey (#6C757D).

## Layout & Spacing

The system follows a **Fluid Grid** for the main dashboard, with a fixed sidebar. The spacing rhythm is compact to accommodate large data sets but uses generous internal padding for modals to create a "breathable" focused environment.

- **Sidebar:** Fixed width at 240px with condensed vertical spacing for navigation items.
- **Modals:** Centered overlays with wide 32px internal padding.
- **Tables:** High-density layout with 12px horizontal cell padding and 8px vertical padding.
- **Breakpoints:** On mobile, the sidebar collapses into a hamburger menu, and tables transition into card-based layouts.

## Elevation & Depth

Visual hierarchy is primarily established through **Tonal Layers** and crisp outlines:

- **Sidebar:** Lowest elevation, acting as a flat, dark foundation.
- **Main Surface:** Neutral grey background provides contrast for white cards and tables.
- **Modals:** Elevated with soft, ambient shadows (0px 10px 30px rgba(0,0,0,0.1)) to separate the active task from the background data.
- **Inputs & Fields:** Use subtle low-contrast outlines (1px solid #DEE2E6) to define interaction areas without adding visual clutter.

## Shapes

The shape language is consistently **Rounded (Level 2)**. This softens the industrial nature of the data:

- **Standard Containers:** Cards and input fields use a 0.5rem (8px) radius.
- **Modals:** Use a more pronounced 1rem (16px) radius to emphasize their role as a distinct "layer."
- **Status Badges:** Use a 4px (Soft) radius to keep them compact and distinct from buttons.
- **Action Buttons:** Use a standard 8px radius, unless they are specific "Pill" styled toggle buttons.

## Components

### Buttons
- **Primary:** Dark navy background with white text for high-priority actions (e.g., "Close Manual").
- **Secondary/Outline:** Thin borders with blue icons for utility actions (e.g., "Input Bon").
- **Ghost:** Background-less buttons with icons for "Detail" or "Reset" functions.

### Status Badges
- Small rectangular shapes with semi-transparent backgrounds of the status color (e.g., light green background for "Accepted" green text).
- Font weight should be bold for immediate recognition.

### Tables
- Rows should feature a "hover" state using a very light tint of blue or grey.
- Use zebra-striping or subtle dividers to maintain row tracking across wide screens.

### Form Inputs
- Grouped inputs (like "Total Anggaran") use a distinct grey background (#F1F3F5) with the label integrated into the field to save vertical space.
- Placeholder text is a soft grey to indicate it is editable.

### Sidebar Items
- Active state should use a light grey background with a blue accent bar or colored icon to highlight current location.