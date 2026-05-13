---
trigger: always_on
---

# DrentDesign 

## Overview

Drent is a **light-first** design system, optimized for **HD desktop screens** (1366x768+). The foundation is a clean warm-white canvas that keeps the eye relaxed during long sessions. Status colors remain semantically clear and highly accessible: deep jewel tones for text paired with soft pastel backgrounds to ensure maximum contrast while maintaining an elegant, modern aesthetic. The system prioritizes **compact scannability** — tighter type scale, reduced spacing — so more financial data fits comfortably on-screen without feeling cramped.

---

## Colors

### Page & Surfaces
- **Page Background** (#F5F6FA): Warm off-white — primary page canvas
- **Surface Default** (#FFFFFF): Pure white — header bars, large surface regions
- **Card Background** (#F0F2F8): Light cool-grey — card and tile fill, transaction rows, KPI tiles, sidebar items
- **Card Background Hover** (#E4E8F3): Hover state for cards and rows
- **Surface Border** (#DDE1EE): Subtle dividers between surface layers

### Foreground / Text
- **Text Primary** (#1A1D2E): Near-black — headlines, primary labels, on-card text
- **Text Secondary** (#5A6070): Muted body, subtitles, "View All" links
- **Text Tertiary** (#8A92A6): Disabled states, very low-emphasis text (darkened slightly for better legibility)
- **Neutral 4** (#CDD2DF): Light dividers
- **Neutral 6** (#A8AEBB): Mid-grey on light surfaces

### Status / Badges (High Contrast & Elegant)
To ensure accessibility without sacrificing the clean aesthetic, statuses use specific deep-text on soft-background pairings rather than simple transparency.
- **Positive / Success**: Base (#27A858) | Badge Text (#147239) on Background (#E6F6EC)
- **Negative / Error**: Base (#E5534B) | Badge Text (#B02A24) on Background (#FCEAE9)
- **Warning / Pending**: Base (#D4A017) | Badge Text (#8C660A) on Background (#FDF4D9)
- **Info Cyan**: Base (#0B7A8A) | Badge Text (#085A66) on Background (#E1F4F6)
- **Neutral**: Base (#A8AEBB) | Badge Text (#4A5060) on Background (#E4E8F3)

### Account Card Gradients
Three named gradients for the horizontal account-card carousel. Each is a 135° linear gradient. Kept vibrant — they are the primary focal moments against the white canvas.
- **Card Turquoise**: linear-gradient(135deg, #0D8091 0%, #1AB8C7 100%) — Checking accounts
- **Card Pink**: linear-gradient(135deg, #B53D7F 0%, #E45D9A 100%) — Savings accounts
- **Card Purple**: linear-gradient(135deg, #5A2D8A 0%, #8B4FBF 100%) — Credit / charge accounts

### Financial-Product Card Gradients
Used for sidebar promotional cards (loans, retirement, goal CTAs).
- **Product Yellow**: linear-gradient(135deg, #D4A017 0%, #F2C94C 100%)
- **Product Pink Soft**: linear-gradient(135deg, #E64980 0%, #FF6B9D 100%)
- **Product Cyan Soft**: linear-gradient(135deg, #2D7DD2 0%, #4FA8E8 100%)

---

## Typography

> **HD-Compact scale**: All sizes reduced ~2px.
> Prioritize scannability: tight line heights.

- **Headline Font**: Sora
- **Body Font**: DM Sans
- **Mono Font**: JetBrains Mono (tabular monetary alignment)

| Role | Font | Size | Weight | Line Height | Usage |
|---|---|---|---|---|---|
| **Display** | Sora | 28px | 700 | 1.2 | Page-level totals, hero balance values |
| **H1 / Page Title** | Sora | 20px | 700 | 1.25 | KPI primary values, card balance numbers |
| **H2 / Section Title** | Sora | 14px | 600 | 1.3 | "Total Balance", "Last Transactions", section headers |
| **Card Title** | Sora | 14px | 600 | 1.3 | Gradient-card account names, goal/product titles |
| **Body** | DM Sans | 13px | 500 | 1.4 | Transaction merchant names, nav links, action labels |
| **Body Subtle** | DM Sans | 12px | 400 | 1.4 | Amount lines ("$543 of $1,000"), "View All" links |
| **Caption** | DM Sans | 11px | 400 | 1.4 | Transaction subtitles, KPI labels, dates, deltas |
| **Badge / Micro** | DM Sans | 11px | 600 | 1.3 | Status badges, small chips |
| **Mono Numeric** | JetBrains Mono | 13px | 500 | 1.4 | Tabular amounts, account numbers, last-4 digits |

---

## Spacing

Base unit: **8px** — tier ceiling compressed for HD density.

| Token | Value | Usage |
|---|---|---|
| **xs** | 4px | Inline icon gaps, small structural adjustments |
| **sm** | 8px | Tight component padding, gap between adjacent rows, badge padding |
| **md** | 12px | Pill button gap, action-row spacing |
| **lg** | 16px | Default card/tile padding, gap between cards in carousel |
| **xl** | 20px | Inside gradient cards |
| **2xl** | 24px | Outer page padding, gap between columns, gap between sidebar sections |
| **3xl** | 32px | Major section breaks within a column |

---

## Border Radius

- **xs** (4px): Small chips, progress bar tracks
- **sm** (6px): Status badges (softened from 4px for elegance), light dividers
- **DEFAULT** (10px): Tile and row backgrounds — transaction rows, KPI tiles, sidebar product cards
- **lg** (14px): Large gradient account cards in the carousel
- **full** (9999px): Pill buttons, avatar circles

---

## Elevation

Light-mode elevation uses soft drop shadows with warm-tinted umbra rather than the dark system's ambient glow.

- **Tile Shadow** (`0 1px 3px 0 rgba(26, 29, 46, 0.08)`): Transaction rows, KPI tiles, sidebar items — very subtle lift
- **Card Shadow Big** (`0 4px 24px 0 rgba(26, 29, 46, 0.12)`): Gradient account cards — crisp ambient lift against white canvas
- **Card Border** (`1px solid rgba(26, 29, 46, 0.07)`): Optional hairline on `--card-bg` tiles to aid separation on white backgrounds
- **Modal Shadow** (`0 8px 32px 0 rgba(26, 29, 46, 0.16)`): Modals, bottom sheets

> **Note**: On a light background, the color-step separation is: Page (#F5F6FA) → Surface (#FFFFFF) → Card (#F0F2F8). Add `--card-border` hairlines where color-step contrast alone is insufficient.

---

## Components

### Header Bar
- **Height**: 56px
- **Background**: `--surface-default` (#FFFFFF)
- **Bottom border**: `1px solid --surface-border`
- **Layout** (left → right): Wordmark logo, centered nav (5 links, 20px gap), right cluster (icon trio + greeting + avatar)
- **Wordmark**: Sora 18px bold, `--text-primary`
- **Nav links**: DM Sans 13px medium, `--text-secondary` (default) / `--text-primary` + bottom-border accent (active)
- **Right icons**: 18×18, `--text-secondary`, hoverable to `--text-primary`
- **Avatar**: 32×32 circle, background `--card-bg`, initials in `--text-primary` 12px semibold

### Gradient Account Card
- **Size**: ~300px wide × 160px tall
- **Background**: One of `--card-turquoise`, `--card-pink`, `--card-purple`
- **Padding**: 20px (`--xl`)
- **Radius**: 14px
- **Shadow**: Card Shadow Big
- **Top row**: Card title left (Sora 14px semibold, white), network wordmark top-right (white 80% alpha)
- **Bottom row**: Masked number left ("•••• 0001", JetBrains Mono 13px, white 80%), balance value top-right (Sora 20px bold, white)
- **Carousel layout**: 3 cards horizontal, 16px gap, no scrollbar on desktop

### Pill Action Button
- **Padding**: 8px 16px (compact) / 10px 20px (default)
- **Radius**: full (9999px)
- **Font**: DM Sans 12px semibold
- **Variants**:
  - **Primary**: `--text-primary` (#1A1D2E) fill, white text — "Transfer / Pay / Add"
  - **Secondary**: `--surface-default` (#FFFFFF) fill, `--text-primary` text, `1px solid --surface-border` — elevated secondary actions
  - **Toggle Active**: `--text-primary` fill, white text
  - **Toggle Inactive**: transparent fill, `--text-secondary` text, `1px solid --surface-border`
  - **Small View**: `--text-primary` fill, white text, smaller padding (6px 12px) — inside product cards

### Transaction Row
- **Background**: `--surface-default` (#FFFFFF)
- **Border**: `1px solid --surface-border`
- **Padding**: 12px 16px
- **Radius**: 10px
- **Margin between rows**: 8px
- **Layout** (left → right):
  - 32×32 circular icon — `--positive-bg` for Incoming, `--negative-bg` for Outgoing — with directional arrow icon
  - Stacked center — DisplayType (DM Sans 13px semibold, `--text-primary`) + MerchantName (DM Sans 11px regular, `--text-secondary`)
  - Stacked right — signed amount (JetBrains Mono 13px semibold, colored by direction base color) + date (DM Sans 11px regular, `--text-secondary`)

### KPI Tile
- **Background**: `--surface-default` (#FFFFFF)
- **Border**: `1px solid --surface-border`
- **Padding**: 16px
- **Radius**: 10px
- **Gap between tiles**: 8px
- **Anatomy**:
  - Small icon top-left (18×18, `--text-secondary`)
  - Label — DM Sans 12px medium, `--text-secondary`
  - Primary value — Sora 20px bold, `--text-primary`
  - Delta line — DM Sans 11px semibold, `--positive` or `--negative` base color (with leading "+" / "-")

### Goal / Product Card
- **Background**: `--card-bg` (default) or product gradients (loan/promo)
- **Border**: `1px solid --surface-border` (on `--card-bg` variant only)
- **Padding**: 16px
- **Radius**: 10px
- **Anatomy**:
  - Top row: icon left (18×18), title (DM Sans 13px semibold), action pill right (small variant)
  - Body line: DM Sans 12px, `--text-secondary` on `--card-bg`; white-80% on gradient
  - Optional progress bar (full-width, 4px tall)
  - Optional decorative illustration right at white 25% alpha

### Progress Bar
- **Height**: 4px
- **Track**: `--card-bg-hover` (#E4E8F3) on light cards; white-25% on gradient cards
- **Fill**: `--positive` (#27A858) for goals on track; white on gradient product cards
- **Radius**: full

### Status Badge
- **Padding**: 4px 8px
- **Radius**: 6px
- **Font**: DM Sans 11px semibold
- **Variants**:
  - **Neutral**: Background (#E4E8F3), Text (#4A5060) — "Draft", "Closed"
  - **Success**: Background (#E6F6EC), Text (#147239) — "Active", "On Track"
  - **Error**: Background (#FCEAE9), Text (#B02A24) — "Behind", "Failed"
  - **Warning**: Background (#FDF4D9), Text (#8C660A) — "Pending", "Under Approval"
  - **Info**: Background (#E1F4F6), Text (#085A66) — "New", "Achieved"

### Avatar
- **Size**: 32×32 (default) / 24×24 (compact)
- **Radius**: full
- **Background**: `--card-bg` (#F0F2F8)
- **Content**: Initials, DM Sans 12px semibold, `--text-primary`

### Section Header
- **Layout**: Title left, "View All" link right
- **Title**: Sora 14px semibold, `--text-primary`
- **Link**: DM Sans 12px, `--text-secondary`, underline on hover
- **Spacing**: 16px below header before content

---

## Do's and Don'ts

1. **Do** keep the page background as the lightest tone. Surfaces step slightly darker (not lighter) into cards — `#F5F6FA` → `#FFFFFF` → `#F0F2F8` is the layering order.
2. **Do** add `--card-border` hairlines on card tiles where background color-step alone isn't enough to separate elements on pure white surfaces.
3. **Don't** use gradient cards for transactional rows or list items. Reserve gradients for hero account cards, loan cards, and goal CTAs.
4. **Do** ensure contrast on badges by strictly using the defined deep-text and soft-background hex values, rather than adjusting opacity of a single color.
5. **Don't** use `--text-primary` (#1A1D2E) for body text — soften to `--text-secondary` (#5A6070) for all non-headline, non-primary content.
6. **Do** use JetBrains Mono for amount columns and account numbers. Tabular alignment is non-negotiable in financial UIs.
7. **Don't** introduce colored borders between tiles. Separation = background-color step + optional hairline + shadow. No colored strokes.
8. **Do** use Card Shadow Big only on gradient hero cards. All other tiles use Tile Shadow or Card Border — not both simultaneously.
9. **Don't** go below 10px border radius for main cards. Premium HD fintech reads crisp, not boxy.
10. **Do** ensure every interactive element has a hover state: `--card-bg-hover` (#E4E8F3) lift is sufficient; avoid hue-shifting hovers.
11. **Do** use Sora for headings and DM Sans for body.
12. **Don't** scale font sizes up to compensate for HD resolution. The compact type scale is intentional.