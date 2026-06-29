# Design Guide — How the UI Is Built & How to Change It

---

## The One File That Controls Everything

**File:** `vu-react/src/index.css`

This file has CSS variables (also called design tokens) at the top inside `:root { }`. Every color, gradient, and border radius used across the entire app points back to these variables.

**To change the look of the whole app, you only edit this one file.**

```css
:root {
  /* ── Primary Blue (navbar, buttons, links, hover) ─── */
  --vu-primary:          #1976d2;   ← change this = navbar changes color
  --vu-primary-dark:     #1565c0;
  --vu-primary-darker:   #0d47a1;

  /* ── Status Colors ──────────────────────────────── */
  --vu-success:          #4caf50;   ← Valid cert card (green)
  --vu-warning:          #ff9800;   ← Expiring Soon card (orange)
  --vu-danger:           #f44336;   ← Expired card (red)
  --vu-info:             #2196f3;   ← Total certs card (blue)

  /* ── Gradients ───────────────────────────────────── */
  --vu-navbar-bg:        linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
  --vu-login-bg:         linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  --vu-csr-modal-bg:     linear-gradient(135deg, #673ab7 0%, #512da8 100%);
  --vu-success-gradient: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
  --vu-warning-gradient: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
  --vu-danger-gradient:  linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
  --vu-info-gradient:    linear-gradient(135deg, #2196f3 0%, #1976d2 100%);

  /* ── Page & Cards ────────────────────────────────── */
  --vu-page-bg:          #f5f5f5;   ← grey background behind all cards
  --vu-table-header-bg:  #f8f9fa;   ← table column header background

  /* ── Shape ───────────────────────────────────────── */
  --vu-radius-card:      12px;      ← rounded corners on all cards
  --vu-radius-btn:       8px;       ← rounded corners on buttons
}
```

---

## Common Changes — Exact Steps

### Change the Navbar Color
Open `vu-react/src/index.css` and change `--vu-navbar-bg`:
```css
/* Current — blue gradient */
--vu-navbar-bg: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);

/* Example — make it dark/black */
--vu-navbar-bg: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);

/* Example — solid color (no gradient) */
--vu-navbar-bg: #1976d2;
```
This also changes the header of the "Certificate Details" popup and the login page header automatically.

---

### Change the Login Page Background
```css
/* Current — purple gradient */
--vu-login-bg: linear-gradient(135deg, #667eea 0%, #764ba2 100%);

/* Example — make it teal */
--vu-login-bg: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
```

---

### Change the Valid Certificate Card Color (green → something else)
```css
/* Current */
--vu-success-gradient: linear-gradient(135deg, #4caf50 0%, #45a049 100%);

/* Example — teal */
--vu-success-gradient: linear-gradient(135deg, #00bcd4 0%, #0097a7 100%);
```

---

### Change Card Rounded Corners
```css
/* Current — slightly rounded */
--vu-radius-card: 12px;

/* Example — very rounded */
--vu-radius-card: 24px;

/* Example — sharp corners */
--vu-radius-card: 0px;
```

---

### Change Page Background
```css
/* Current — light grey */
--vu-page-bg: #f5f5f5;

/* Example — white */
--vu-page-bg: #ffffff;

/* Example — dark mode */
--vu-page-bg: #121212;
```

---

## What Is Material-UI (MUI)?

**Material-UI (MUI)** is the design framework we use. It gives us ready-made components like buttons, cards, tables, dialogs, chips, etc. so we don't have to build them from scratch.

Think of it like a box of LEGO pieces — MUI gives us the pieces, we arrange them.

MUI components we use:
| Component | What It Is |
|-----------|-----------|
| `AppBar` | The top blue navbar |
| `Toolbar` | The row inside the navbar |
| `Button` | Clickable buttons |
| `Card` | White boxes with shadows |
| `Chip` | Small rounded labels (like the LIVE badge) |
| `Dialog` | Popups/modals |
| `DataGrid` | The certificate table with sorting/filtering |
| `Alert` | Red/green/orange notification boxes |
| `CircularProgress` | The spinning loader |
| `Stepper` | The step-by-step form in CSR Generator |
| `Tabs` | The pending/approved/rejected tabs in CSR Management |

**MUI Version:** v7 (latest)

---

## Where Each Color Is Used (Map)

```
--vu-navbar-bg
    → Top navigation bar background
    → Certificate Details popup header
    → Login page card header
    → Login "Sign In" button

--vu-login-bg
    → Login page full-screen background (purple gradient)

--vu-csr-modal-bg
    → "Generate Certificate Signing Request" popup header (purple)

--vu-success-gradient
    → "Valid Certificates" stat card (top left green card)

--vu-warning-gradient
    → "Expiring Soon" stat card (orange card)

--vu-danger-gradient
    → "Expired" stat card (red card)

--vu-info-gradient
    → "Total Certificates" stat card (blue card)

--vu-page-bg
    → Grey background behind all content

--vu-radius-card
    → Rounded corners: stat cards, certificate table card, popups

--vu-shadow-card
    → Drop shadow on the certificate table card

--vu-shadow-modal
    → Deeper shadow on all popup dialogs
```

---

## File-by-File: Where to Find the Components

### Top Navbar
**File:** `vu-react/src/components/Dashboard.tsx` — lines ~455 to ~580

The `<AppBar>` and `<Toolbar>` components build the navbar.

**Mobile behaviour:**
- Phone (< 600px): Shows shortened title "CA Manager", hides both action buttons. They move into the three-dot menu instead.
- Tablet (600–900px): Shows icon-only buttons (no text), hides the role chip.
- Desktop (> 900px): Shows full text buttons and role chip.

---

### Stat Cards (Valid / Expiring / Expired / Total)
**File:** `vu-react/src/components/Dashboard.tsx` — lines ~580 to ~660

These are 4 `<Card>` components inside a CSS grid:
```tsx
gridTemplateColumns: { xs: '1fr', sm: 'repeat(2, 1fr)', lg: 'repeat(4, 1fr)' }
```
- Phone → 1 column (cards stack on top of each other)
- Tablet → 2 columns
- Desktop → 4 columns side by side

---

### Charts (Pie + Bar + Line)
**File:** `vu-react/src/components/CertificateCharts.tsx`

Three charts built with the `recharts` library:
- Pie chart — certificate status distribution
- Bar chart — expiry timeline (next 6 months)
- Area chart — issuance trend (last 6 months)

Grid:
```tsx
gridTemplateColumns: { xs: '1fr', md: 'repeat(2, 1fr)' }
```
- Phone → 1 column (charts stack)
- Desktop → 2 columns side by side

To change chart colours, find these lines in `CertificateCharts.tsx`:
```tsx
{ name: 'Valid',   color: '#4caf50' }   ← pie chart green slice
{ name: 'Expired', color: '#f44336' }   ← pie chart red slice
{ name: 'Revoked', color: '#ff9800' }   ← pie chart orange slice
<Bar dataKey="expiring" fill="#ff9800"  ← bar chart colour
<stop stopColor="#1976d2"               ← area chart colour
```

---

### Certificate Table
**File:** `vu-react/src/components/Dashboard.tsx` — lines ~700 onwards

Built with MUI `<DataGrid>`. Has built-in features:
- Column sorting (click any column header)
- Quick search filter
- Pagination (10/25/50/100 per page)
- Column show/hide
- Export to CSV

Table header colours:
```tsx
'& .MuiDataGrid-columnHeaders': {
  backgroundColor: 'var(--vu-table-header-bg)',  ← light grey header row
},
'& .MuiDataGrid-row:hover': {
  backgroundColor: 'rgba(25, 118, 210, 0.04)',   ← blue tint on hover
},
```

---

### Login Page
**File:** `vu-react/src/components/Login.tsx`

- Full page background: `var(--vu-login-bg)` — purple gradient
- Card header: `var(--vu-navbar-bg)` — blue gradient
- Sign In button: `var(--vu-navbar-bg)` — blue gradient
- Already fully mobile responsive (uses MUI `Container maxWidth="sm"`)

---

### CSR Generator (Request Certificate Form)
**File:** `vu-react/src/components/CSRGenerator.tsx`

- Modal header: `var(--vu-csr-modal-bg)` — purple gradient
- 3-step Stepper form
- To change the purple to another colour, change `--vu-csr-modal-bg` in `index.css`

---

### CSR Management (Admin Approve/Reject)
**File:** `vu-react/src/components/CSRManagement.tsx`

- Shows pending / approved / rejected tabs
- Table with approve and reject buttons
- This page is shown when admin clicks "CSR Management" from the three-dot menu

---

## Summary — Quick Reference

| I want to change... | Edit this variable in index.css |
|---------------------|--------------------------------|
| Navbar colour | `--vu-navbar-bg` |
| Login background | `--vu-login-bg` |
| CSR modal header | `--vu-csr-modal-bg` |
| Valid certs card | `--vu-success-gradient` |
| Expiring card | `--vu-warning-gradient` |
| Expired card | `--vu-danger-gradient` |
| Total certs card | `--vu-info-gradient` |
| Page background | `--vu-page-bg` |
| Card corner radius | `--vu-radius-card` |
| Button corner radius | `--vu-radius-btn` |
| Card shadow | `--vu-shadow-card` |

**One rule:** change the variable in `index.css` → save → the browser hot-reloads → all components update instantly. You never need to hunt through multiple files.
