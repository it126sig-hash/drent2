---
name: compact-table-density
description: Make a PrimeVue DataTable (or similar table view) in this app compact/responsive on laptop screens between 769px and 1365px, by wiring its column text and column widths into the existing global font-size/spacing variable system instead of hardcoded Tailwind size classes or rem widths. Use when asked to "compact-kan tabel", "perkecil tabel di layar kecil", "redesign table for smaller screens", or when adding a new ListView table that should follow the same responsive density pattern as BookingListView.
---

# Compact table density

This app already has a global responsive scaling system:
- `--font-size-*` tokens defined once in `frontend/src/style.css` `:root` (e.g. `--font-size-12px: 12px;`).
- `--space-*` tokens for padding/gap.
- A breakpoint tier `@media (min-width: 769px) and (max-width: 1365px)` in `frontend/src/style.css` that overrides those tokens to ~0.8x, and a matching tier in `frontend/src/components/AppLayout.vue` for sidebar chrome.

Any element using `var(--font-size-*)` automatically shrinks in that breakpoint for free. The problem: many older view files style table columns with **Tailwind fixed-size utility classes** (`text-xs`, `text-sm`, `text-[10px]`) and **rem-based inline widths** (`style="min-width: 9rem"`) on PrimeVue `<Column>` — neither is wired into the token system, so those columns stay full-size at 769-1365px while the rest of the app shrinks, making the table look inconsistent.

Reference implementation: `frontend/src/views/bookings/BookingListView.vue` (desktop `<DataTable>` section + its `<style scoped>` block) was migrated using this exact checklist — read it as a worked example before applying this elsewhere.

## Checklist

1. **Find fixed-size text classes in the table's column templates.** Grep the view file for `text-xs|text-sm|text-base|text-\[[0-9]+px\]` inside the `<Column>` `#body` templates (ignore matches outside the table — page headers, dialogs, etc. are out of scope unless asked). Map each Tailwind size to the matching existing global token (don't invent new ones): `text-xs`→`var(--font-size-12px)`, `text-sm`→`var(--font-size-14px)`, `text-[10px]`→`var(--font-size-10px)`, `text-[11px]`→`var(--font-size-11px)`, etc. — Tailwind's px value tells you which token to reuse.

2. **Add small scoped utility classes that reuse those tokens**, then swap the Tailwind class for them in the template:
   ```css
   .cell-fs-10 { font-size: var(--font-size-10px); }
   .cell-fs-11 { font-size: var(--font-size-11px); }
   .cell-fs-12 { font-size: var(--font-size-12px); }
   .cell-fs-14 { font-size: var(--font-size-14px); }
   ```
   Only add the sizes actually used in that file. Keep other Tailwind classes on the same element untouched (color, weight, etc.) — only the size class changes.

3. **Find rem-based column widths.** Grep for `style="min-width: [0-9.]+rem"` on `<Column>` elements. `rem` is tied to the root `<html>` font-size, which the responsive tiers never touch — so these widths won't shrink. Group the distinct values (most tables reuse 2-4 widths across many columns) and define them as CSS variables scoped to that view's root container (e.g. `.page-container`), not in the global `:root` — they're view-specific:
   ```css
   .page-container {
     --col-w-sm: 9rem;
     --col-w-md: 10rem;
     --col-w-lg: 11rem;
     --col-w-xl: 17rem;
   }
   @media (min-width: 769px) and (max-width: 1365px) {
     .page-container {
       --col-w-sm: 7.2rem;
       --col-w-md: 8rem;
       --col-w-lg: 8.8rem;
       --col-w-xl: 13.6rem;
     }
   }
   ```
   Scale factor: ~0.8x, consistent with the rest of the app's compact tier. Then change each `<Column style="min-width: 9rem">` to `<Column :style="{ minWidth: 'var(--col-w-sm)' }">` (binding required — a CSS var inside a literal `style="..."` string won't be parsed as Vue-reactive, but more importantly stays inert either way; using `:style` with an object is the idiomatic Vue way and matches the existing codebase pattern). Reuse the SAME variable across every column that currently has the same rem value — don't create one variable per column.

4. **Reuse the existing breakpoint, don't invent a new one.** Always `@media (min-width: 769px) and (max-width: 1365px)` — this is the one established tier (`frontend/src/style.css:848` and `frontend/src/components/AppLayout.vue`). Never touch the global token *values* themselves from inside a view file — only add new view-scoped variables for things the global system doesn't already cover (column widths, view-specific fixed chrome).

5. **Verify.**
   - Re-grep the file for `text-xs|text-sm|text-\[[0-9]|min-width:\s*[0-9.]+rem` inside the table section — should be zero remaining (outside intentionally-skipped non-table areas).
   - Open the view, resize devtools to 1280px: every column's text and width should shrink together, none left oversized.
   - Resize to 1920px: table returns to original size (default token values, no override active).
   - Resize to <=768px: existing mobile card-list view (if present) is untouched — this tier only affects 769-1365px.
   - If the table is horizontally scrollable (`scrollable scrollHeight="flex"`), confirm no column overflows oddly after the width shrink.
