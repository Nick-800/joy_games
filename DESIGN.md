---
name: Joy Games Lounge Management & Automation System
description: High-performance, local-first PS5 gaming lounge station grid, dynamic sliced billing, and hardware automation platform.
colors:
  primary: "#0284c7"
  primary-hover: "#0369a1"
  status-available: "#10b981"
  status-available-bg: "#064e3b"
  status-prepaid: "#0284c7"
  status-prepaid-bg: "#082f49"
  status-postpaid: "#8b5cf6"
  status-postpaid-bg: "#2e1065"
  status-warning: "#f59e0b"
  status-warning-bg: "#451a03"
  status-rogue: "#ef4444"
  status-rogue-bg: "#450a0a"
  status-paused: "#f97316"
  status-paused-bg: "#431407"
  status-maintenance: "#64748b"
  status-maintenance-bg: "#1e293b"
  surface-canvas: "#090d16"
  surface-card: "#0f172a"
  surface-elevated: "#1e293b"
  surface-overlay: "#020617"
  surface-border: "#334155"
  surface-border-subtle: "#1e293b"
  text-primary: "#f8fafc"
  text-secondary: "#94a3b8"
  text-muted: "#64748b"
typography:
  display:
    fontFamily: "Plus Jakarta Sans, Inter, system-ui, sans-serif"
    fontSize: "2.25rem"
    fontWeight: 700
    lineHeight: 1.15
    letterSpacing: "-0.03em"
  headline:
    fontFamily: "Plus Jakarta Sans, Inter, system-ui, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 600
    lineHeight: 1.25
    letterSpacing: "-0.02em"
  title:
    fontFamily: "Plus Jakarta Sans, Inter, system-ui, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 600
    lineHeight: 1.35
    letterSpacing: "-0.01em"
  body:
    fontFamily: "Inter, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.5
    letterSpacing: "normal"
  label:
    fontFamily: "Inter, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 600
    lineHeight: 1.4
    letterSpacing: "0.03em"
  mono-timer:
    fontFamily: "JetBrains Mono, Fira Code, ui-monospace, monospace"
    fontSize: "1.875rem"
    fontWeight: 700
    lineHeight: 1.1
    letterSpacing: "-0.02em"
rounded:
  sm: "6px"
  md: "10px"
  lg: "16px"
  full: "9999px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "16px"
  lg: "24px"
  xl: "32px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.text-primary}"
    rounded: "{rounded.md}"
    padding: "10px 18px"
  button-primary-hover:
    backgroundColor: "{colors.primary-hover}"
  button-danger:
    backgroundColor: "{colors.status-rogue}"
    textColor: "{colors.text-primary}"
    rounded: "{rounded.md}"
    padding: "10px 18px"
  card-station:
    backgroundColor: "{colors.surface-card}"
    textColor: "{colors.text-primary}"
    rounded: "{rounded.lg}"
    padding: "20px"
---

# Design System: Joy Games Lounge Management & Automation System (GLMAS)

## Overview

**Creative North Star: "The Neon Mission Control"**

The interface is an **Operate-mode** cockpit engineered for gaming lounge operators and cashiers working under ambient, low-light lounge lighting conditions. It balances high-velocity operational efficiency with crystal-clear station visibility across tablet screens and desktop monitors.

The visual posture is dark, high-contrast, structured, and tactile. Every station card serves as a dedicated command pod: operators can assess station status across the entire lounge in under 500 milliseconds without squinting, decode remaining playtime or running tabs at a glance, and execute session actions in 1–2 taps.

### Key Characteristics:
- **Scan-First Station Grid:** Distinct, unmissable semantic color anchoring for every station state (Green = Available, Blue = Prepaid, Purple = Postpaid, Yellow = Expiring, Red = Rogue Play Alert, Orange = Paused, Slate = Maintenance).
- **Tabular Precision:** Monospace tabular numbers for all countdown timers, active stopwatches, and Libyan Dinar (`LYD` / `د.ل`) financial sums to eliminate visual jitter.
- **Direct-Touch Density:** Generous touch targets (min 44px) on primary station actions tailored for busy cashier tablet touchscreens.
- **Fast-Action Command Modals:** Streamlined, keyboard/touch-friendly overlays for starting sessions, adding retail snacks/drinks, switching player tiers, and issuing itemized checkout invoices with cash change calculation.

---

## Colors

The palette is rooted in a deep obsidian foundation with dedicated semantic role accents that communicate operational state instantly.

### Primary & Brand
- **Electric Cobalt** (`#0284c7` / Hover: `#0369a1`): Primary interactive brand accent for primary confirmations, system headers, and active state highlights.

### Semantic Station States
- **Available Emerald** (`#10b981` / Bg: `#064e3b` / Border: `#059669`): Denotes an idle, clean station ready for immediate booking.
- **Prepaid Blue** (`#0284c7` / Bg: `#082f49` / Border: `#0284c7`): Denotes an active fixed-duration countdown session.
- **Postpaid Purple** (`#8b5cf6` / Bg: `#2e1065` / Border: `#7c3aed`): Denotes an open-ended running tab.
- **Expiring Amber** (`#f59e0b` / Bg: `#451a03` / Border: `#d97706`): Warns cashiers that prepaid time is under 5 minutes remaining.
- **Rogue Alert Crimson** (`#ef4444` / Bg: `#450a0a` / Border: `#dc2626`): High-priority flashing alert when unauthorized physical console power-on is detected.
- **Paused Tangerine** (`#f97316` / Bg: `#431407` / Border: `#ea580c`): Indicates a frozen billing clock during customer breaks or unexpected disconnects.
- **Maintenance Slate** (`#64748b` / Bg: `#1e293b` / Border: `#475569`): For staff downloads, system updates, and hardware checks.

### Neutrals & Surfaces
- **Canvas Base** (`#090d16`): The darkest neutral backdrop for minimum glare.
- **Card Surface** (`#0f172a`): Slightly raised container surface for station pods and panels.
- **Elevated Surface** (`#1e293b`): Raised surface for modals, popovers, and dropdown menus.
- **Borders & Dividers** (`#334155` / Subtle: `#1e293b`): Crisp boundaries defining structure.
- **Text Hierarchy**: Primary (`#f8fafc`), Secondary (`#94a3b8`), Muted (`#64748b`).

### Named Rules
**The Single-Meaning Color Rule.** Colors carry operational meaning strictly. Green always means Available/Ready, Blue always means Prepaid Active, Purple always means Postpaid Active, Amber always means Expiring Soon, and Red always means Rogue/Danger. Never use these semantic hues decoratively.

**The Currency Clarity Rule.** All monetary values are rendered in **Libyan Dinar (LYD)** with explicit formatting (`12.500 LYD` or `12.500 د.ل`), always displayed in tabular numerals with clear billable vs retail subtotal breakdowns.

---

## Typography

**Display / Headings:** Plus Jakarta Sans (Clean, modern, geometric grotesque with tight tracking)  
**Body & UI Controls:** Inter (Maximum legibility at small sizes and high DPI)  
**Timers & Monetary Data:** JetBrains Mono / ui-monospace (Strictly tabular numerals for zero jitter)

### Hierarchy
- **Display** (`700`, `2.25rem` / `36px`, `line-height: 1.15`, `tracking: -0.03em`): Top-level dashboard header, financial summaries.
- **Headline** (`600`, `1.5rem` / `24px`, `line-height: 1.25`, `tracking: -0.02em`): Station card names (e.g. `PS5 #01`), modal titles.
- **Title** (`600`, `1.125rem` / `18px`, `line-height: 1.35`): Section groupings, card subheadings.
- **Body** (`400`, `0.875rem` / `14px`, `line-height: 1.5`): Table rows, customer notes, status descriptions.
- **Label** (`600`, `0.75rem` / `12px`, `tracking: 0.03em`, uppercase where appropriate): Badges, tier tags, column headers.
- **Mono Timer** (`700`, `1.875rem` / `30px`, `line-height: 1.1`, `tabular-nums`): Station remaining/elapsed countdowns and running cost totals.

### Named Rules
**The Tabular Clock Rule.** Every live clock, countdown timer, running stopwatch, and currency amount MUST use tabular numerals (`font-variant-numeric: tabular-nums`). Clocks must never cause layout wobble or horizontal shifting as digits tick.

---

## Layout

- **Station Grid Matrix:** Responsive CSS Grid dynamically adapting from 1 column (mobile quick audit) to 2–3 columns (tablets) and 4–5 columns (wide cashier monitors).
- **Sticky Status Bar:** Fixed top bar displaying Active Shift information, Cash Drawer Float in LYD, Total Active Stations count, Live Clock, and Quick Staff Switcher.
- **Docked Hardware Simulation Drawer:** Collapsible side/bottom drawer for testing PS5 power, sleep, and rogue trigger events without cluttering standard operations.
- **Consistent Rhythm:** Base 8px spatial grid (4px, 8px, 16px, 24px, 32px, 48px).

---

## Elevation & Depth

GLMAS uses **structural tonal layering** combined with subtle ambient dark shadows to create depth without visual noise.

### Depth Vocabulary
- **Base Surface (`#090d16`):** Ground level.
- **Station Cards (`#0f172a`):** 1px border (`#334155` or state-tinted border) + `box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.5)`.
- **Modals & Drawers (`#1e293b`):** 1px border (`#475569`) + `box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.7), 0 8px 10px -6px rgba(0, 0, 0, 0.7)`.
- **Rogue Pulse Glow:** Soft, pulsing outer ring (`0 0 25px rgba(239, 68, 68, 0.35)`) on unauthorized active stations.

### Named Rules
**The State-Anchored Border Rule.** Station cards express their operational state through a 1.5px state-colored accent border and tinted header pill, never through heavy full-card saturated background floods that fatigue the cashier's eyes.

---

## Shapes

- **Cards & Modals:** Generous 16px rounded corners (`rounded-2xl` / `16px`) for friendly, modern containment.
- **Buttons & Inputs:** 10px rounded corners (`rounded-lg` / `10px`) for crisp clickability.
- **Badges & Status Pills:** Full pill radius (`rounded-full` / `9999px`) for quick visual tagging.
- **Hardware Status Dot:** 8px circular indicator with live ping status.

---

## Components

### 1. Station Card Pod
- **Header:** Station Name (e.g. `PS5 Station 04`), Station Type badge (`VIP` / `Standard`), Physical Hardware status indicator dot (`STANDBY`, `AWAKE`).
- **Body:** Prominent State Badge, Large Mono Timer (`42:15` or `01:15:30`), Active Controller Tier (`2 Players - 6.000 LYD/hr`), Running Cost tally (`12.500 LYD`).
- **Footer Action Bar:** Context-aware quick buttons:
  - *Available:* Prominent `[ ▶ Start Session ]`
  - *Prepaid:* `[ +15m ]`, `[ +30m ]`, `[ Switch Tier ]`, `[ End & Settle ]`
  - *Postpaid:* `[ + Retail Item ]`, `[ Switch Tier ]`, `[ Pause ]`, `[ End & Settle ]`
  - *Rogue Alert:* `[ Claim & Start Tab ]`, `[ Force Remote Sleep ]`
  - *Paused:* `[ ▶ Resume Clock ]`

### 2. Fast-Action Modals
- **Start Session Modal:** Toggle between Prepaid (preset pills: `30m`, `1h`, `2h`, `Custom`) and Postpaid, Controller Tier selector (1–2 vs 3–4 players), optional customer name, and auto-wake PS5 toggle.
- **Retail POS Quick Add:** Visual grid of beverages and snacks categorized with 1-tap `+` and `-` quantity controls and running item subtotal.
- **Payment & Invoice Modal:** Itemized breakdown of time slices + retail items, total in LYD, payment method selector (Cash with quick cash received presets and automatic change calculation, Card), and final settlement button.
- **Shift Drawer Modal:** Opening cash float entry, and closing blind-drop with discrepancy calculation.

### 3. Interactive Hardware Simulator Drawer
- Floating toggle in the header (`[ ⚙️ Dev Simulator ]`) expanding a panel with per-station triggers:
  - `[ Simulate PS5 Power On ]` (test rogue detection or session wake)
  - `[ Simulate PS5 Rest Mode ]` (test unexpected sleep or session end)
  - `[ Fast-Forward Clock +15m ]` (test timer transitions & sliced interval billing)

---

## Do's and Don'ts

### Do:
- **Do** format all currency explicitly in **Libyan Dinar (LYD / د.ل)** with 3-decimal precision where appropriate (`10.000 LYD`).
- **Do** enforce `tabular-nums` on all timers, elapsed stopwatches, and price displays to prevent visual jitter.
- **Do** provide instant visual feedback (hover, active, disabled states) on all touchscreen cashier buttons.
- **Do** calculate change automatically in the checkout modal when cashiers input cash amounts received.
- **Do** offer 1-tap quick time extension buttons (`+15m`, `+30m`, `+1h`) for active prepaid sessions.

### Don't:
- **Don't** use generic modals for simple inline actions.
- **Don't** use decorative gradients on text or un-anchored glowing borders.
- **Don't** allow timers to drift on client refresh: always compute elapsed/remaining time from authoritative server timestamps ($T_{\text{now}} - T_{\text{start}}$).
- **Don't** allow checkout settlement without recording the active cashier and shift ID.
