---
name: TokoKu POS Retail Engine
colors:
  surface: '#f8f9ff'
  surface-dim: '#cbdbf5'
  surface-bright: '#f8f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#eff4ff'
  surface-container: '#e5eeff'
  surface-container-high: '#dce9ff'
  surface-container-highest: '#d3e4fe'
  on-surface: '#0b1c30'
  on-surface-variant: '#584237'
  inverse-surface: '#213145'
  inverse-on-surface: '#eaf1ff'
  outline: '#8c7164'
  outline-variant: '#e0c0b1'
  surface-tint: '#9d4300'
  primary: '#9d4300'
  on-primary: '#ffffff'
  primary-container: '#f97316'
  on-primary-container: '#582200'
  inverse-primary: '#ffb690'
  secondary: '#565e74'
  on-secondary: '#ffffff'
  secondary-container: '#dae2fd'
  on-secondary-container: '#5c647a'
  tertiary: '#006c49'
  on-tertiary: '#ffffff'
  tertiary-container: '#00b07a'
  on-tertiary-container: '#003b26'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#ffdbca'
  primary-fixed-dim: '#ffb690'
  on-primary-fixed: '#341100'
  on-primary-fixed-variant: '#783200'
  secondary-fixed: '#dae2fd'
  secondary-fixed-dim: '#bec6e0'
  on-secondary-fixed: '#131b2e'
  on-secondary-fixed-variant: '#3f465c'
  tertiary-fixed: '#6ffbbe'
  tertiary-fixed-dim: '#4edea3'
  on-tertiary-fixed: '#002113'
  on-tertiary-fixed-variant: '#005236'
  background: '#f8f9ff'
  on-background: '#0b1c30'
  surface-variant: '#d3e4fe'
typography:
  headline-xl:
    fontFamily: Plus Jakarta Sans
    fontSize: 36px
    fontWeight: '700'
    lineHeight: 44px
    letterSpacing: -0.02em
  headline-xl-mobile:
    fontFamily: Plus Jakarta Sans
    fontSize: 28px
    fontWeight: '700'
    lineHeight: 36px
    letterSpacing: -0.01em
  headline-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 28px
    fontWeight: '700'
    lineHeight: 36px
    letterSpacing: -0.02em
  headline-lg-mobile:
    fontFamily: Plus Jakarta Sans
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
    letterSpacing: -0.01em
  headline-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 22px
    fontWeight: '600'
    lineHeight: 28px
    letterSpacing: -0.01em
  headline-sm:
    fontFamily: Plus Jakarta Sans
    fontSize: 18px
    fontWeight: '600'
    lineHeight: 24px
  title-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 16px
    fontWeight: '600'
    lineHeight: 22px
  body-lg:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  body-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '400'
    lineHeight: 16px
  label-lg:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 20px
  label-md:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '600'
    lineHeight: 16px
    letterSpacing: 0.02em
  label-sm:
    fontFamily: Inter
    fontSize: 11px
    fontWeight: '600'
    lineHeight: 14px
    letterSpacing: 0.04em
  numeric-display:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
    letterSpacing: -0.02em
  numeric-table:
    fontFamily: Inter
    fontSize: 13px
    fontWeight: '500'
    lineHeight: 18px
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  gutter: 1rem
  gutter-mobile: 0.75rem
  margin: 1.5rem
  margin-mobile: 1rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 1rem
  space-lg: 1.5rem
  space-xl: 2rem
---

## Brand & Style

This design system targets multi-outlet retail managers, cashier operators, and inventory directors across high-volume commerce environments. The brand personality is pragmatic, hyper-efficient, and structurally disciplined, counterbalancing the high-velocity chaos of physical checkout counters with tranquil visual clarity. 

The aesthetic executes an elevated Modern Enterprise SaaS standard, borrowing structural utility from operational cockpits and sleek polish from refined retail dashboards. It pairs high-density data management with immediate, glanceable legibility. High-contrast typography, strict grid boundaries, and deliberate chromatic hierarchy establish instant trust and high transactional velocity. The interface never feels decorative; every edge, surface, and hue serves transaction throughput, stock visibility, and cash-flow precision.

## Colors

The palette operates under a high-efficiency retail model where functional surfaces recede and vital transactional triggers advance:

- **Canvas & Structural Layers**: Background canvas rests on clean `#F8FAFC` (Slate 50), while interactive surfaces, cards, and data planes utilize `#FFFFFF`. Structural borders are governed by `#E2E8F0` (Slate 200) for standard containers and `#CBD5E1` (Slate 300) for active inputs and dividers.
- **Brand & Action (Vibrant Orange - `#F97316`)**: Reserved strictly for primary transactional intent—terminal checkouts, 'Complete Sale' triggers, master action floating bars, and active navigation badges. Hover state deepens to `#EA580C`.
- **Command Slate & Navy (`#0F172A`, `#1E293B`)**: Serves as the executive anchor across navigation drawers, terminal status bars, table headers, and high-impact key figures.
- **Transactional State Accents**:
  - *Success & Reconciliation (`#10B981` / `#059669`)*: Positive cash variance, completed transactions, paid invoices.
  - *Loyalty & Membership Tier (`#F59E0B` / `#D97706`)*: VIP loyalty badges, reward thresholds, partial settlements.
  - *Low Stock & Critical Audit (`#EF4444` / `#DC2626`)*: Stock-out alerts, cash discrepancy warnings, terminal offline status.
  - *Omnichannel & Shift Operations (`#0D9488` Teal, `#2563EB` Blue)*: Multi-store sync indicators, staff shift controls, online order intake badges.

## Typography

The type system implements a dual-typeface structure tailored to high-density SaaS and fiscal clarity:

- **Plus Jakarta Sans**: Drives all headlines, drawer titles, module headers, and primary KPI callouts. Its contemporary geometric geometry balances approachable retail friendliness with architectural rigor.
- **Inter**: Powers all transactional interfaces, data grids, tabular financial lists, and system labels.
- **Financial & Tabular Engine**: All currency displays (Indonesian Rupiah `Rp`), stock SKUs, barcode mappings, and ledger data must strictly activate `font-feature-settings: "tnum" 1, "cv05" 1` (tabular numbers with accessible punctuation). This prevents visual jitter when cashier carts refresh and stock ledgers update in real-time.
- **Caps & Hierarchy**: Section dividers, table header cells, and inventory state labels mandate `label-sm` or `label-md` with subtle uppercase tracking to delineate categorical metadata from editable numeric values.

## Layout & Spacing

The layout operates on a disciplined 8px base grid unit (with a 4px sub-grid for dense POS tables and tag components):

- **Terminal & Dashboard Architecture**: Employs a split fluid layout. The sidebar workspace adopts a fixed 240px width (collapsible to 72px icon rail), the central inventory catalogue/grid scales dynamically, and the active transactional register cart retains a rigid 380px or 420px operational rail.
- **Data Grids & Breakpoints**:
  - *Desktop (>1280px)*: 12-column layout, 24px margins, 16px gutters. Optimized for dual view: stock browsing + persistent cart terminal.
  - *Tablet (768px - 1279px)*: 8-column layout, 16px margins, 16px gutters. Terminal cart transforms into an off-canvas drawer or split-screen sheet with bottom bar toggles.
  - *Mobile (<767px)*: 4-column layout, 16px margins, 12px gutters. Cart docks as a bottom bar showing running item count and instant slide-up payment triggers.
- **Density Control**: Standard enterprise views use `space-md` (16px) internal card padding; dense cashier views compress table row heights to 40px and cell padding to `space-sm` (8px horizontal) to maximize vertical line-item visibility without scrolling.

## Elevation & Depth

Visual hierarchy uses flat structural containment backed by hairline borders and low-diffused, ambient drop shadows:

- **Base Card Elevation**: Ground surfaces rely primarily on 1px borders of `#E2E8F0` coupled with an ambient shadow: `0 1px 3px 0 rgba(15, 23, 42, 0.06), 0 1px 2px -1px rgba(15, 23, 42, 0.04)`. This creates a crisp delineation against the `#F8FAFC` canvas without muddying screens in bright retail environments.
- **Interactive Elevated Containers (Cart Drawer, Filter Menus, Modals)**: Hovering quick-action panels or floating summary ribbons step up to `0 4px 6px -1px rgba(15, 23, 42, 0.08), 0 2px 4px -2px rgba(15, 23, 42, 0.04)`.
- **Payment & Critical Modals**: Flyout payment drawers and critical error dialogs use high-elevation dampening: `0 20px 25px -5px rgba(15, 23, 42, 0.12), 0 8px 10px -6px rgba(15, 23, 42, 0.06)` combined with a frosted backdrop `#0F172A` at 40% opacity with `backdrop-filter: blur(4px)`.
- **Active State Rings**: Focused elements, editable table cells, and active barcode input triggers receive a 2px offset focus ring in Primary Vibrant Orange `#F97316` at 30% alpha.

## Shapes

The design system maintains controlled geometric stability with purposeful softening at outer boundaries:

- **Cards, Panels & Modal Shells**: Fixed at `rounded-xl` (12px / 0.75rem) to reflect modern SaaS polish while preserving tight grid alignment.
- **Buttons, Text Inputs, and Filter Dropdowns**: Standardized at `rounded-lg` (8px / 0.5rem), providing comfortable click and touch targets for touchscreen POS tablets.
- **Pills, Badges & Tonal Status Chips**: Fully rounded (`rounded-full` / 9999px) to establish immediate shape-level contrast against rectilinear table cells and card containers.
- **Table Outlines & Data Panels**: Inner cell borders maintain 0px internal radii, bound cleanly within an outer `rounded-xl` container with `overflow: hidden`.

## Components

### Buttons
- **Primary (POS Checkout / Save)**: `#F97316` background, `#FFFFFF` text, `rounded-lg` (8px), font weight 600. Hover: `#EA580C`. Active: scales down to 0.98. Focus: `ring-2 ring-offset-2 ring-[#F97316]`.
- **Secondary (Dark Command)**: `#0F172A` background, `#FFFFFF` text. Used for primary operational management (e.g., "Open Shift", "Print Z-Report").
- **Outline / Ghost**: 1px border `#E2E8F0`, `#0F172A` text, hover: `#F1F5F9`. For item modifications and table paging.
- **Touch-Bar Checkout**: Fixed height 52px on mobile/tablets with integrated numeric sum displays aligned left and trigger copy right.

### Chips & Status Badges
- **Style**: Fully pill-shaped (`rounded-full`), padding `2px 10px`, font size `11px`, font weight 600, tabular numeric compatibility.
- **Variants**:
  - *Paid / In-Stock*: Emerald-50 background (`#ECFDF5`), Emerald-700 text (`#047857`), Emerald-200 border (`#A7F3D0`).
  - *Low Stock / Alert*: Red-50 background (`#FEF2F2`), Red-700 text (`#B91C1C`), Red-200 border (`#FECACA`).
  - *Gold Tier Customer*: Amber-50 background (`#FFFBEB`), Amber-800 text (`#92400E`), Amber-200 border (`#FDE68A`).
  - *Shift / Omnichannel*: Slate-100 background (`#F1F5F9`), Slate-700 text (`#334155`), Slate-200 border (`#E2E8F0`).

### Tables & Data Grids
- **Header**: `#F8FAFC` background, 36px height, uppercase tracking `label-sm` (`#64748B`), bottom border 1px solid `#E2E8F0`.
- **Rows**: `#FFFFFF` background, 48px standard row height (38px dense mode), bottom border 1px solid `#F1F5F9`. Hover row: `#F8FAFC`.
- **Numeric Alignment**: Text columns left-aligned; quantities, unit prices, discounts, and total Rupiah columns strictly right-aligned with tabular figures (`Inter`).

### Input Fields & Search Bars
- **Default State**: Surface `#FFFFFF`, 1px solid `#CBD5E1`, height 40px, `rounded-lg`, typography `body-md`.
- **Focus State**: 1px solid `#F97316`, glowing ring: `0 0 0 3px rgba(249, 115, 22, 0.15)`.
- **Barcode & SKU Input**: Prefixed with a high-contrast icon, persistent trailing shortcut badge (e.g., `F2` or `Enter`), instantly clearable via inline touch target.

### Cards & POS Cart Items
- **Card**: Surface `#FFFFFF`, 1px solid `#E2E8F0`, `rounded-xl` (12px), ambient shadow.
- **Register Cart Row**: Flex layout with vertical division; item name and SKU in `Inter` 13px weight 500, stepper controls (+ / -) enclosed in 28px square buttons with border `#E2E8F0`, right-aligned total item calculation in `Inter` tabular nums.