# KDNA Tables

A WordPress + Elementor plugin that ships a single widget capable of building
both general data tables and product or service comparison tables. Designed
to be lean: no third-party libraries on the front end, conditional asset
loading, and every visual element exposed through Elementor Style controls
or CSS variables.

- **Plugin slug:** kdna-tables
- **Widget:** KDNA Table (Elementor category: KDNA Tables)
- **Requires:** WordPress 6.0+, PHP 8.0+, Elementor

## Installation

1. Download the latest `kdna-tables.zip` release.
2. In WordPress, go to **Plugins, Add New, Upload Plugin** and choose the zip.
3. Click **Install Now**, then **Activate**.
4. Open any page or post with Elementor. Drag the **KDNA Table** widget from
   the **KDNA Tables** category into the canvas.
5. Pick **General Table** or **Comparison Table** in the Type Chooser. The
   Content and Style tabs configure themselves to suit that mode. A
   **Change table type** link at the bottom of the Content tab resets the
   chooser.

## Modes

### General Table

A clean, fully styleable table for any tabular content. Up to ten columns.

- Caption, first row is header, first column is header switchers.
- Columns repeater with label, alignment, and width percentage.
- Rows repeater with a nested Cells sub-repeater. Each cell renders as
  text, icon, image, or any mixed combination, with a per-cell alignment
  override.
- Style controls for the wrapper, header row, first column (when used as
  a header), body cells (including per-side cell borders and alternating
  row backgrounds), cell content (icon and image), and table layout
  (border-collapse, border-spacing).

### Comparison Table

A product or service comparison modelled on the kind shown in the Laseraid
V-Series example. Up to six items, unlimited feature rows.

- Items repeater with image, label, sublabel, and optional CTA per item.
- Highlighted item with badge text and badge position (top-left,
  top-centre, top-right).
- Global Cell Indicators: available icon, unavailable indicator (icon,
  text, or hidden).
- Feature Rows repeater with feature label, description, and tooltip. For
  each item slot (1 to 6) the row exposes an indicator control plus
  custom text, icon, image, and arrangement controls.
- Style controls for the wrapper, items header row, item card, highlighted
  item, feature rows, feature label column, available indicator,
  unavailable indicator, CTA button (with normal and hover state tabs and
  optional icon), and tooltip.

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
