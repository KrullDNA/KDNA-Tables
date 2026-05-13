# KDNA Tables

A WordPress + Elementor plugin that splits table content from table display.
Tables live in a reusable library (a custom post type), and a single
Elementor widget picks a table and renders it with full Style control.
Edit a table once, every widget instance using it updates instantly. The
same table can be styled differently in different widget instances on
different pages.

- **Plugin slug:** kdna-tables
- **Version:** 2.0.0
- **Widget:** KDNA Table (Elementor category: KDNA Tables)
- **Shortcode:** `[kdna_table id="123"]` (non-Elementor contexts)
- **Custom post type:** `kdna_table` (admin only, not public)
- **Requires:** WordPress 6.0+, PHP 8.0+, Elementor

## Installation

1. Download the latest `kdna-tables.zip` release.
2. In WordPress, go to **Plugins, Add New, Upload Plugin** and choose the zip.
3. Click **Install Now**, then **Activate**.
4. A new top-level **KDNA Tables** menu appears in WP Admin (with submenus
   **All Tables**, **Add New**, and **Tools**).

## Workflow

### 1. Create a table in the library

1. Go to **KDNA Tables, Add New** in WP Admin.
2. Pick **General Table** or **Comparison Table** in the Type Chooser. Type
   is permanent for that entry. To convert a table to the other type later,
   duplicate it from the All Tables list and pick the other type.
3. The custom matrix editor opens. Add columns/rows (general) or items and
   feature rows (comparison). Each cell can carry text, icon, image, or any
   combination in any arrangement.
4. The **Structural preview** meta box below the editor shows a low-fidelity
   HTML preview that updates live as you edit. Visual styling lives on the
   widget, the preview only confirms structure.
5. Publish.

### 2. Render the table

**With Elementor:** Drag the **KDNA Table** widget onto a page. In the
Content tab, pick a table from the **Source Table** dropdown. Use the Style
tab to apply per-instance colours, typography, borders, responsive mode,
sticky column, and so on.

**Without Elementor (classic editor, theme template, Gutenberg shortcode
block):** Use the shortcode

```
[kdna_table id="123"]
```

The shortcode renders the table at desktop layout with default cell
indicator icons and no per-instance styling. Frontend CSS auto-enqueues on
the first shortcode render on a page.

## Migration from v1.x

v2.0.0 moves table data out of widget instances into the CPT library. Two
paths are provided:

### Lazy migration on edit

Open an existing v1.x widget instance in the Elementor editor. The widget
panel shows a yellow notice:

> KDNA Tables: legacy data detected. Click Migrate to convert it into a
> reusable table in your library.

Click **Migrate**. The server creates a new `kdna_table` entry from the
legacy settings (or reuses an existing entry if the same data was migrated
before, by content hash), the widget instance updates to reference the new
entry, and the page is marked dirty. Save the Elementor page to persist.
The widget renders identically because the data layer maps the new shape
back onto the same render template contract.

### Bulk migration tool

Run **KDNA Tables, Tools, Migrate v1.x widgets** as an administrator.

1. Click **Scan for legacy widgets**. The scanner reports the list of posts
   that still carry inline v1.x widget data, with an instance count per post.
2. Click **Migrate all**. The tool processes 10 posts per AJAX chunk with a
   progress bar. Before mutating each post, the original `_elementor_data`
   blob is backed up to `_kdna_tables_pre_migration_backup` meta so the
   change can be reversed.
3. The log table shows per-post results, downloadable as JSON.
4. Per-row **Rollback** buttons restore the original Elementor data for
   that post if the migration result needs reverting.

Identical legacy payloads collapse onto a single CPT entry via content-hash
dedupe.

## Table types

### General Table

A clean, fully styleable table for any tabular content. Up to ten columns.

- Caption, first row is header, first column is header switchers (data).
- Columns with label, alignment, width (`%` or `px`, data).
- Rows with cells. Each cell renders as text, icon, image, or any
  combination, with arrangement order and per-cell alignment override
  (data).
- Style controls for the wrapper, header row, first column (when used as
  a header), body cells (including per-side cell borders and alternating
  row backgrounds), cell content (icon and image), and table layout
  (border-collapse, border-spacing). (Widget.)

### Comparison Table

A product or service comparison table with up to six items and unlimited
feature rows.

- Items with image, label, sublabel, and optional CTA per item (data).
- Highlighted item with badge text and badge position (top-left,
  top-centre, top-right) (data).
- Global Cell Indicators on the widget: available icon, unavailable
  indicator (icon, text, or hidden).
- Feature rows with label, description, and tooltip. Per-item per-row
  cell state: available, unavailable, or custom (with text + icon +
  image + arrangement). (Data.)
- Style controls for the wrapper, items header row, item card, highlighted
  item, feature rows, feature label column, available indicator,
  unavailable indicator, CTA button (with normal and hover state tabs and
  optional icon), and tooltip. (Widget.)

## Responsive Modes

Each widget instance picks one of four responsive modes. The breakpoint at
which the mode activates is configurable (Mobile only, Tablet and Mobile).

- **None.** The table stays in its desktop layout at every viewport.
- **Card Stack.** The comparison table reflows so each item column becomes
  a vertical card showing all feature rows as stacked label/value pairs.
  In the General Table each row becomes a card with column labels rendered
  before each cell value. CSS Grid handles the reflow; no duplicate DOM.
- **Pivot Rows.** Each feature row reflows into a block. For the
  comparison table the feature label sits at the top with a horizontal
  set of mini cards (one per item) below it. For the general table each
  cell shows the column label above or inline with the value (selectable
  per widget).
- **Column Picker.** The comparison table hides all item columns by
  default and shows a picker above the table. The picker lets the user
  select up to two items and reveals only those columns. Picker UI is
  built by JS, fully keyboard accessible, and re-runs on breakpoint
  resize events.

## Tooltips

Feature label tooltips show on hover for desktop pointer devices and on
tap for touch devices. Tooltip triggers are focusable, support
Enter/Space to open and Escape to close, and carry aria-describedby
pointing at the tooltip span (role="tooltip"). When tooltip position is
set to **Auto**, the script flips the tooltip from top to bottom if there
is not enough room above the trigger.

## Sticky First Column

A switcher in the Content tab pins the first column to the left edge of
a horizontal scroller on desktop. The Sticky Column Style section
controls the column background, right-edge shadow colour and size, and
z-index. The responsive mode takes precedence at its breakpoint.

## CSS Variables (Theme Integration)

Every visual token is exposed as a CSS variable scoped to the widget
wrapper. Theme stylesheets or per-instance Custom CSS (via the
`selector` keyword) can override these without rewriting selectors.

### General Table

| Variable | Default | Purpose |
| --- | --- | --- |
| `--kdna-table-bg` | `transparent` | Table wrapper background |
| `--kdna-table-border-color` | `#e5e7eb` | Cell border colour |
| `--kdna-table-border-width` | `1px` | Cell border width |
| `--kdna-table-border-radius` | `12px` | Outer wrapper radius |
| `--kdna-table-header-bg` | `#000000` | Header row background |
| `--kdna-table-header-color` | `#ffffff` | Header row text colour |
| `--kdna-table-header-padding` | `14px 16px` | Header cell padding |
| `--kdna-table-body-bg-odd` | `#ffffff` | Body odd-row background |
| `--kdna-table-body-bg-even` | `#f7f7f8` | Body even-row background |
| `--kdna-table-body-color` | `#1f2937` | Body text colour |
| `--kdna-table-body-padding` | `12px 16px` | Body cell padding |
| `--kdna-table-first-col-bg` | `transparent` | First column background (when used as header) |
| `--kdna-table-first-col-color` | `inherit` | First column text colour |
| `--kdna-table-first-col-weight` | `700` | First column font weight |
| `--kdna-table-icon-color` | `#3362dd` | Cell icon colour |
| `--kdna-table-icon-size` | `1.25em` | Cell icon size |
| `--kdna-table-cell-gap` | `8px` | Gap between cell pieces (icon, text, image) |
| `--kdna-table-row-hover-transition` | `200ms` | Hover transition duration |

### Comparison Table

| Variable | Default | Purpose |
| --- | --- | --- |
| `--kdna-comparison-bg` | `#ffffff` | Comparison wrapper background |
| `--kdna-comparison-border-color` | `#e5e7eb` | Row divider colour |
| `--kdna-comparison-border-radius` | `12px` | Wrapper radius |
| `--kdna-comparison-header-bg` | `#000000` | Items header bar background |
| `--kdna-comparison-header-color` | `#ffffff` | Items header text |
| `--kdna-comparison-header-padding` | `24px 16px` | Items header padding |
| `--kdna-comparison-body-bg-odd` | `#ffffff` | Body odd-row background |
| `--kdna-comparison-body-bg-even` | `#f7f7f8` | Body even-row background |
| `--kdna-comparison-body-color` | `#1f2937` | Body text colour |
| `--kdna-comparison-body-padding` | `16px` | Body cell padding |
| `--kdna-comparison-label-color` | `#1f2937` | Feature label colour |
| `--kdna-comparison-label-description-color` | `#6b7280` | Feature description colour |
| `--kdna-comparison-item-image-size` | `96px` | Item image width |
| `--kdna-comparison-item-image-spacing` | `12px` | Gap below item image |
| `--kdna-comparison-item-label-color` | `#ffffff` | Item label colour |
| `--kdna-comparison-item-sublabel-color` | `rgba(255,255,255,0.8)` | Item sublabel colour |
| `--kdna-comparison-highlight-bg` | `#f0f4ff` | Highlighted column body background |
| `--kdna-comparison-highlight-border` | `#3362dd` | Highlighted accent colour |
| `--kdna-comparison-highlight-scale` | `1` | Slight scale on the highlighted item card |
| `--kdna-comparison-badge-bg` | `#3362dd` | Highlighted badge background |
| `--kdna-comparison-badge-color` | `#ffffff` | Badge text colour |
| `--kdna-comparison-badge-offset-y` | `-14px` | Badge vertical offset |
| `--kdna-comparison-cta-bg` | `#000000` | CTA background |
| `--kdna-comparison-cta-color` | `#ffffff` | CTA text colour |
| `--kdna-comparison-cta-hover-bg` | `#3362dd` | CTA hover background |
| `--kdna-comparison-cta-hover-color` | `#ffffff` | CTA hover text colour |
| `--kdna-comparison-cta-icon-spacing` | `8px` | Gap between CTA icon and text |
| `--kdna-comparison-cta-transition` | `150ms` | CTA transition duration |
| `--kdna-comparison-available-color` | `#ffffff` | Available indicator icon colour |
| `--kdna-comparison-available-bg` | `#3362dd` | Available indicator shape colour |
| `--kdna-comparison-available-icon-size` | `16px` | Available indicator icon size |
| `--kdna-comparison-available-shape-size` | `32px` | Available indicator shape size |
| `--kdna-comparison-available-shape-radius` | `50%` | Available indicator shape radius |
| `--kdna-comparison-unavailable-color` | `#9ca3af` | Unavailable indicator colour |
| `--kdna-comparison-unavailable-size` | `18px` | Unavailable indicator size |
| `--kdna-comparison-row-hover-transition` | `200ms` | Row hover transition duration |

### Tooltip

| Variable | Default | Purpose |
| --- | --- | --- |
| `--kdna-tooltip-bg` | `#1f2937` | Tooltip background |
| `--kdna-tooltip-color` | `#ffffff` | Tooltip text |
| `--kdna-tooltip-radius` | `8px` | Tooltip border radius |
| `--kdna-tooltip-padding` | `8px 12px` | Tooltip padding |
| `--kdna-tooltip-max-width` | `240px` | Tooltip maximum width |
| `--kdna-tooltip-arrow-size` | `6px` | Tooltip arrow size |
| `--kdna-tooltip-shadow` | `0 10px 24px rgba(0,0,0,0.12)` | Tooltip shadow |

### Responsive modes

| Variable | Default | Purpose |
| --- | --- | --- |
| `--kdna-card-bg` | `#ffffff` | Card stack card background |
| `--kdna-card-radius` | `12px` | Card stack card radius |
| `--kdna-card-padding` | `16px` | Card stack card padding |
| `--kdna-card-spacing` | `16px` | Gap between stacked cards |
| `--kdna-pivot-label-color` | `inherit` | Pivot rows label colour |
| `--kdna-pivot-label-width` | `30%` | Pivot rows inline label width |
| `--kdna-pivot-row-spacing` | `16px` | Pivot rows row spacing |
| `--kdna-picker-bg` | `#ffffff` | Column picker background |
| `--kdna-picker-padding` | `12px` | Column picker padding |
| `--kdna-picker-radius` | `12px` | Column picker radius |
| `--kdna-picker-label-color` | `inherit` | Column picker label colour |
| `--kdna-picker-dropdown-bg` | `#ffffff` | Picker dropdown background |
| `--kdna-picker-dropdown-color` | `#1f2937` | Picker dropdown text colour |
| `--kdna-picker-chip-bg` | `#3362dd` | Selected chip background |
| `--kdna-picker-chip-color` | `#ffffff` | Selected chip text colour |
| `--kdna-picker-chip-radius` | `999px` | Selected chip radius |

### Sticky first column

| Variable | Default | Purpose |
| --- | --- | --- |
| `--kdna-sticky-bg` | `#ffffff` | Sticky column background |
| `--kdna-sticky-shadow-color` | `rgba(0,0,0,0.08)` | Right-edge shadow colour |
| `--kdna-sticky-shadow-size` | `8px` | Right-edge shadow size |
| `--kdna-sticky-z-index` | `2` | Sticky column z-index |

## Custom CSS

Elementor's per-instance Custom CSS field uses the `selector` keyword as
the widget-wrapper prefix, so the variables above can be overridden for
one instance with:

```css
selector { --kdna-comparison-highlight-bg: #fff7ed; }
```

## Asset Loading

- `kdna-tables.css` registers as a style dependency of the widget and is
  pulled in only on pages that render at least one KDNA Table.
- `kdna-comparison.css` loads only when an instance is configured as a
  comparison table.
- `kdna-responsive.css` loads only when a responsive mode is selected.
- `kdna-tables.js` loads only on pages with the widget.

## Conventions

- Atomic Elementor architecture: `has_widget_inner_wrapper()` returns
  false when the `e_optimized_markup` experiment is active. No CSS
  targets `.elementor-widget-container`.
- All PHP classes prefixed `KDNA_Tables_`. All filter and action names
  prefixed `kdna_tables_`. All option keys prefixed `kdna_tables_`. All
  translatable strings use the `kdna-tables` text domain.
- All CSS classes prefixed `.kdna-table__` or `.kdna-comparison__`. CSS
  variables prefixed `--kdna-table-`, `--kdna-comparison-`, `--kdna-`.
- UK English throughout code, labels, and documentation.

## Changelog

### 2.0.0

Major release. Data and display split.

- **Tables live in a reusable library.** A new `kdna_table` custom post
  type stores every table. The Elementor widget becomes a thin display
  layer that picks an entry from the library and renders it with full
  Style control. Edit a table once, every widget instance using it
  updates instantly. The same table can be styled differently in
  different widget instances on different pages.
- **Top-level KDNA Tables admin menu** (position 25, `dashicons-grid-view`)
  with **All Tables**, **Add New**, and **Tools** submenus. The All Tables
  list shows the table type, row or item counts, feature row count
  (comparison), a Used in count (cached), and the `[kdna_table id="..."]`
  shortcode with a one-click Copy button. Per-row Duplicate action.
- **Type Chooser on Add New.** Two cards (General Table or Comparison
  Table) decide which custom matrix editor opens. Type is permanent for
  that entry, duplicate to convert.
- **Alpine.js admin editor.** CSS Grid matrix editor for general tables
  with per-cell text contenteditable, icon picker (181-icon bundled set
  across Font Awesome 6 solid, regular, brands and Elementor's eicon),
  image picker via `wp.media`, mixed-content arrangement, per-cell
  alignment override, per-column alignment + width (`%` or `px`).
  Comparison editor with items header strip (image, label, sublabel,
  CTA, highlight), inline badge controls, feature rows grid with
  per-cell three-state controls (available / unavailable / custom) and
  a custom sub-editor that mirrors the general cell editor.
- **Structural preview meta box** below the editor renders a low-fidelity
  HTML view of the current Alpine state, live-updates as you type, no
  save required.
- **Shortcode `[kdna_table id="123"]`** renders a table outside Elementor
  with default cell indicator icons and no per-instance styling.
  Frontend CSS auto-enqueues on first render. Empty id or missing post
  renders nothing.
- **Source Table dropdown** on the widget Content tab picks a table from
  the library. All Style sections type-condition on a hidden mirror of
  the selected table's type that updates via AJAX. Elementor editor JS
  refactored to drop the v1.x type chooser code in favour of the
  source-table-driven flow.
- **Conditional asset loading preserved.** Comparison CSS only loads when
  a comparison-type table is selected. Responsive CSS only loads when a
  responsive mode is set.
- **Migration from v1.x.** Lazy migration in the Elementor editor on
  widget open, plus a bulk Tools page that scans every Elementor post
  for legacy data, migrates in chunks of 10 with a progress bar,
  deduplicates identical payloads by content hash, and writes a
  per-post backup of `_elementor_data` for one-click rollback.
- **Inline help.** Tooltip badges on the First row is header, First
  column is header, Highlight item, and Tooltip text controls.
- **All Style controls preserved.** Every Style section in the v1.x
  widget keeps its exact controls, CSS variable bindings, selectors,
  and conditional asset loading. The data layer is the only seam that
  changed.

### 1.1.0

- **Style controls now actually apply.** CSS variable defaults were
  declared on the inner `.kdna-table__wrapper` element, which shadowed
  any override Elementor wrote to `{{WRAPPER}}` (the outer Elementor
  element). Defaults are now scoped to
  `:where(.elementor-widget-kdna-table)` with zero specificity, so
  every Style control's override wins the cascade as designed.
- **Table Type is a SELECT dropdown.** The card-based Type Chooser
  and the separate Change Table Type section are replaced by a
  single SELECT control at the top of the Content tab. Switching is
  now driven by Elementor's native live-render path, so the canvas
  updates immediately. Per-type settings are still preserved when
  you switch back.
- **Cell indicator buttons.** Inside each Feature Row, the per-item
  indicator is now a CHOOSE control with a tick / cross / pencil
  icon for Available / Unavailable / Custom, instead of a dropdown.
- **Feature label column width.** A new Style control in *Feature
  Label Column* sets the width of the first column in px, % or vw.
  The remaining item columns share the leftover width equally via
  `table-layout: fixed` and a `<colgroup>`.
- **Generic default copy.** The Comparison Table defaults no longer
  reference the Laseraid V-Series example. Items default to
  Plan 1 / Plan 2 / Plan 3 and feature rows to Feature one through
  Feature five with placeholder descriptions.

### 1.0.4

- The **Change Table Type** section now shows the two type cards
  directly. Clicking a card switches the widget to that type without
  the extra "Change table type" link step. Existing content for each
  type is preserved when you switch back.

### 1.0.3

- Fix canvas placeholder showing "Choose a table type to begin" even
  after a type was selected. The HIDDEN `table_type` control was
  registered inside a section conditioned on `table_type=''`; once a
  type was picked the section deactivated and recent Elementor builds
  stripped its controls from the settings payload sent to the canvas
  renderer. The Type Chooser section is now always active and the
  individual chooser cards condition on `table_type` instead, so the
  hidden value is preserved.

### 1.0.2

- Fix the same `sanitize_settings()` `TypeError` reported against
  1.0.1. Recent Elementor builds also route
  `Controls_Stack::get_data('settings')` through the strict sanitiser,
  so the previous workaround still crashed on instances whose stored
  settings were `null`. `get_style_depends()` no longer inspects
  instance settings at all and instead returns the full set of style
  handles. They still only load on pages that render the widget.

### 1.0.1

- Initial attempt to fix `sanitize_settings()` `TypeError` by reading
  raw settings via `get_data('settings')`. Superseded by 1.0.2.

### 1.0.0

Initial release.
