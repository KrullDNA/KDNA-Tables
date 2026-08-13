# KDNA Tables

A WordPress + Elementor plugin that splits table content from table display.
Tables live in a reusable library (a custom post type), and a single
Elementor widget picks a table and renders it with full Style control.
Edit a table once, every widget instance using it updates instantly. The
same table can be styled differently in different widget instances on
different pages.

- **Plugin slug:** kdna-tables
- **Version:** 3.1.2
- **Widget:** KDNA Table (Elementor category: KDNA Tables)
- **Shortcode:** `[kdna_table id="123"]` (non-Elementor contexts)
- **Custom post type:** `kdna_table` (admin only, not public)
- **Requires:** WordPress 6.0+, PHP 8.0+. Elementor is required for the
  widget; since 3.0.0 the shortcode renders without it.

## Installation

1. Download the latest `kdna-tables-vX.Y.Z.zip` from the repository root
   (currently `kdna-tables-v3.1.2.zip`).
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
block, JetEngine or ACF field, widget, term description):** Use the
shortcode. Since 3.0.0 this works whether Elementor is installed or not —
the render templates no longer depend on it.

```
[kdna_table id="123"]
```

Since 3.0.0 the shortcode has full styling parity with the widget. It is
styled from **KDNA Tables → Shortcode Styles**, which sets the global
defaults every shortcode renders with, and each table can override those
on its own edit screen. See [Shortcode Styles](#shortcode-styles).

### Shortcode attributes

| Attribute | Values | Default | What it does |
| --- | --- | --- | --- |
| `id` | table post id | — | **Required.** The table to render. An id that is not a published `kdna_table` renders nothing. |
| `responsive` | `none`, `card_stack`, `pivot_rows`, `column_picker` | the **Responsive Mode** setting | Layout below the breakpoint. See [Responsive Modes](#responsive-modes). |
| `breakpoint` | `mobile`, `tablet_and_mobile` | the **Applies At** setting | Where the responsive mode starts applying. `mobile` is ≤767px; `tablet_and_mobile` is ≤1024px. |
| `sticky` | `yes`, `no` | `no` | Pins the first column to the left edge of a horizontal scroller. |
| `style_id` | table post id | — | Borrow another table's style overrides instead of using this table's own. Useful for keeping a set of tables visually identical. |

Every attribute is validated against an allow-list, and anything
unrecognised falls back to its default rather than failing — a shortcode
is hand-typed, and a typo should not blank the table. `yes`, `y`, `true`,
`1` and `on` are all accepted for `sticky`.

`responsive` and `breakpoint` are the two attributes whose default is not
a constant. Written, the attribute wins. Left off, the shortcode takes
the **Responsive Mode** and **Applies At** settings from Shortcode
Styles, overridable per table like any other style. That is what makes
the live preview trustworthy: before, the mode existed only as an
attribute defaulting to card stack, so a settings page previewing pivot
rows and a page rendering card stack were both behaving correctly and
nothing on screen explained the difference.

```
[kdna_table id="123" responsive="pivot_rows" breakpoint="tablet_and_mobile" sticky="yes"]
```

Frontend CSS is enqueued in the header on every page by default. That is
deliberate: `has_shortcode()` only ever reads `post_content`, so a
shortcode inside a JetEngine meta field, an ACF field, a page-builder
template or a term description is invisible to it — and those are the
contexts this path exists for. The fallback when the shortcode is found
late is a footer enqueue, which paints the table unstyled first. See
[Asset Loading](#asset-loading) for how to turn the eager load off.

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
- Style controls for the wrapper, header row, first column, body cells
  (alternating row backgrounds and the rule lines), cell content (icon
  and image), and table layout (border-collapse, border-spacing).
  (Widget.)
- Rule lines are split by axis and location, each with its own Style /
  Width / Colour and a **None** option that removes just that set:
  Body Cells → Horizontal Lines (between rows) and Vertical Lines
  (between columns); Header Row → Bottom Divider and Vertical Lines;
  First Column → Right Edge Line. The table itself never draws an outer
  frame — the Table Wrapper border owns the outside edge — so no
  combination of these can produce a stray line at the top of the table.

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

Every mode except None centres its cell text at its breakpoint, whatever
the column, per-cell or Style-tab alignment says for desktop. Left and
right alignment belongs to the desktop column grid; once the table has
reflowed into cards or stacked rows there is nothing left for it to line
up against. None keeps the desktop layout, so it keeps the desktop
alignment.

- **None.** The table stays in its desktop layout at every viewport.
- **Card Stack.** The comparison table reflows so each item column becomes
  a vertical card showing all feature rows as stacked label/value pairs.
  In the General Table each row becomes a card with column labels rendered
  before each cell value. CSS Grid handles the reflow; no duplicate DOM.
- **Pivot Rows.** Each feature row reflows into a block. For the
  comparison table the feature label sits at the top with a horizontal
  set of mini cards (one per item) below it. For the general table each
  cell shows the column label above or inline with the value (selectable
  per widget). That label is the column heading at this breakpoint — the
  real header row is hidden — so it carries the Header Row background and
  text colour by default, and the Pivot Rows Mode section restyles it for
  mobile (background, typography, colour, padding, radius, spacing)
  without touching the desktop header.
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

## Shortcode Styles

The `[kdna_table]` shortcode is styled from **KDNA Tables → Shortcode
Styles**, which sets the global defaults every shortcode renders with.
Values resolve schema default → global option → per-table override, and
every control writes a CSS custom property onto the wrapper as an inline
style attribute, so a shortcode is styled wherever it lands — including
inside a JetEngine repeater, which `has_shortcode()` cannot see.

Each control maps one to one onto a widget Style control. The settings
page groups them slightly differently from the widget: the caption gets
its own section rather than sitting inside Table Wrapper, and all five
sets of rule lines are gathered into one Rule Lines section rather than
being spread across Header Row, First Column and Body Cells.

| Widget control | Schema key | Custom property |
| --- | --- | --- |
| Table Wrapper → Background | `wrapper_background` | `--kdna-table-wrapper-bg` |
| Table Wrapper → Border | `wrapper_border` | `--kdna-table-wrapper-border-style` / `-width` / `-color` |
| Table Wrapper → Border Radius | `wrapper_border_radius` | `--kdna-table-border-radius` |
| Table Wrapper → Max Width | `wrapper_max_width` | `--kdna-table-wrapper-max-width` |
| Table Wrapper → Alignment | `wrapper_alignment` | `--kdna-table-wrapper-margin` |
| Table Wrapper → Padding | `wrapper_padding` | `--kdna-table-wrapper-padding` |
| Table Wrapper → Space Above Table | `wrapper_margin_top` | `--kdna-table-wrapper-margin-top` |
| Table Wrapper → Space Below Table | `wrapper_margin_bottom` | `--kdna-table-wrapper-margin-bottom` |
| Table Layout → Border Collapse | `border_collapse` | `--kdna-table-border-collapse` |
| Table Layout → Border Spacing | `border_spacing` | `--kdna-table-border-spacing` |
| Table Wrapper → Caption Typography | `caption_typography` | `--kdna-table-caption-font-*` |
| Caption → Padding | `caption_padding` | `--kdna-table-caption-padding` |
| Caption → Background | `caption_background` | `--kdna-table-caption-bg` |
| Caption → Border | `caption_border` | `--kdna-table-caption-border-style` / `-width` / `-color` |
| Caption → Border Radius | `caption_radius` | `--kdna-table-caption-radius` |
| Caption → Space Above | `caption_margin_top` | `--kdna-table-caption-margin-top` |
| Caption → Heading Level | `caption_tag` | *(none — chooses the element)* |
| Table Wrapper → Caption Colour | `caption_color` | `--kdna-table-caption-color` |
| Table Wrapper → Caption Alignment | `caption_alignment` | `--kdna-table-caption-align` |
| Table Wrapper → Caption Spacing | `caption_spacing` | `--kdna-table-caption-spacing` |
| Header Row → Background | `header_background` | `--kdna-table-header-bg` |
| Header Row → Text Colour | `header_text_color` | `--kdna-table-header-color` |
| Header Row → Typography | `header_typography` | `--kdna-table-header-font-*` |
| Header Row → Padding | `header_padding` | `--kdna-table-header-padding` |
| Header Row → Text Alignment | `header_text_align` | `--kdna-table-header-text-align` |
| Header Row → Bottom Divider | `header_divider_style` / `_width` / `_color` | `--kdna-table-header-divider-*` |
| Header Row → Vertical Lines | `header_v_line_style` / `_width` / `_color` | `--kdna-table-header-v-line-*` |
| First Column → Background Colour | `first_col_bg` | `--kdna-table-first-col-bg` |
| First Column → Text Colour | `first_col_text_color` | `--kdna-table-first-col-color` |
| First Column → Typography | `first_col_typography` | `--kdna-table-first-col-font-*` |
| First Column → Padding | `first_col_padding` | `--kdna-table-first-col-padding` |
| First Column → Right Edge Line | `first_col_edge_style` / `_width` / `_color` | `--kdna-table-first-col-edge-*` |
| Body Cells → Odd Row Background | `body_bg_odd` | `--kdna-table-body-bg-odd` |
| Body Cells → Even Row Background | `body_bg_even` | `--kdna-table-body-bg-even` |
| Body Cells → Typography | `body_typography` | `--kdna-table-body-font-*` |
| Body Cells → Text Colour | `body_text_color` | `--kdna-table-body-color` |
| Body Cells → Padding | `body_padding` | `--kdna-table-body-padding` |
| Body Cells → Text Alignment | `body_text_align` | `--kdna-table-body-text-align` |
| Body Cells → Horizontal Lines | `body_h_line_style` / `_width` / `_color` | `--kdna-table-h-line-*` |
| Body Cells → Vertical Lines | `body_v_line_style` / `_width` / `_color` | `--kdna-table-v-line-*` |
| Body Cells → Row Hover Background | `row_hover_bg` | `--kdna-table-row-hover-bg` |
| Body Cells → Hover Transition | `row_hover_transition_duration` | `--kdna-table-row-hover-transition` |
| Cell Content → Icon Colour | `icon_color` | `--kdna-table-icon-color` |
| Cell Content → Icon Hover Colour | `icon_color_hover` | `--kdna-table-icon-hover-color` |
| Cell Content → Icon Size | `icon_size` | `--kdna-table-icon-size` |
| Cell Content → Icon Spacing | `icon_spacing` | `--kdna-table-cell-gap` |
| Cell Content → Image Width | `image_width` | `--kdna-table-image-width` |
| Cell Content → Image Height | `image_height` | `--kdna-table-image-height` |
| Cell Content → Image Border Radius | `image_border_radius` | `--kdna-table-image-radius` |
| Cell Content → Image Fit | `image_object_fit` | `--kdna-table-image-object-fit` |
| Card Stack Mode → Card Background | `card_bg` | `--kdna-card-bg` |
| Card Stack Mode → Border | `card_border` | `--kdna-card-border-style` / `-width` / `-color` |
| Card Stack Mode → Card Border Radius | `card_border_radius` | `--kdna-card-radius` |
| Card Stack Mode → Card Padding | `card_padding` | `--kdna-card-padding` |
| Card Stack Mode → Card Spacing | `card_spacing` | `--kdna-card-spacing` |
| Pivot Rows Mode → Typography | `pivot_label_typography` | `--kdna-pivot-label-font-*` |
| Pivot Rows Mode → Text Colour | `pivot_label_color` | `--kdna-pivot-label-color` |
| Pivot Rows Mode → Background Colour | `pivot_heading_bg` | `--kdna-pivot-heading-bg` |
| Pivot Rows Mode → Padding | `pivot_heading_padding` | `--kdna-pivot-heading-padding` |
| Pivot Rows Mode → Border Radius | `pivot_heading_radius` | `--kdna-pivot-heading-radius` |
| Pivot Rows Mode → Spacing Below Heading | `pivot_heading_spacing` | `--kdna-pivot-heading-spacing` |
| Pivot Rows Mode → Label Width | `pivot_label_width` | `--kdna-pivot-label-width` |
| Pivot Rows Mode → Row Spacing | `pivot_row_spacing` | `--kdna-pivot-row-spacing` |
| Pivot Rows Mode → Row Divider | `pivot_divider_style` / `_width` / `_color` | `--kdna-pivot-divider-*` |
| *(new in 3.1.0)* Pivot Rows Mode → Row Background | `pivot_row_bg` | `--kdna-pivot-row-bg` |
| *(new in 3.1.0)* Pivot Rows Mode → Row Border | `pivot_row_border_style` / `_width` / `_color` | `--kdna-pivot-row-border-*` |
| *(new in 3.1.0)* Pivot Rows Mode → Row Padding | `pivot_row_padding` | `--kdna-pivot-row-padding` |
| *(new in 3.1.0)* Pivot Rows Mode → Row Border Radius | `pivot_row_radius` | `--kdna-pivot-row-radius` |
| Sticky Column → Background | `sticky_bg` | `--kdna-sticky-bg` |
| Sticky Column → Right Edge Shadow Colour | `sticky_shadow_color` | `--kdna-sticky-shadow-color` |
| Sticky Column → Right Edge Shadow Size | `sticky_shadow_size` | `--kdna-sticky-shadow-size` |
| Sticky Column → Z-Index | `sticky_z_index` | `--kdna-sticky-z-index` |

### Widget controls not ported

| Widget control | Reason |
| --- | --- |
| Table Wrapper → Box Shadow | No box-shadow control type in the schema. The stylesheet reads `--kdna-table-wrapper-box-shadow`, so adding one later is a schema entry plus a renderer, not a stylesheet change. |
| Card Stack Mode → Box Shadow | Same. |
| Card Stack Mode → Sticky Card Header | A switcher, and one aimed at the comparison table's item header. There is no switcher control type, and nothing in the general card layout it would pin. |
| Pivot Rows Mode → Label Position | A layout switch, not a style value: the stylesheet keys it off a `data-pivot-label-position` attribute the shortcode does not take. The shortcode always uses the Above position. |
| Column Picker Mode → all 13 controls | The column picker hides and shows columns by their `data-slot` attribute, which only the comparison render emits, and its script only initialises inside Elementor. A general-table shortcode in `column_picker` mode renders normally with no picker, so the chrome has nothing to style. The stylesheet already carries the `--kdna-picker-*` variables for when that changes. |
| Cell Content → Icon Colour (hover) on the comparison indicators | Comparison-only, and comparison styling is out of scope for this build. |

### The settings page

**KDNA Tables → Shortcode Styles** is one screen holding the whole global
layer. The controls are grouped into ten sections down the left, matching
the widget's Style tab but for two deliberate regroupings: the caption
gets its own section rather than sitting inside Table Wrapper, and all
five sets of rule lines are gathered into one Rule Lines section rather
than being spread across three.

Every control has three states. **Set** means it writes a value. **Unset**
— blank, or `— Default —` in a select — means inherit, and the layer
beneath shows through. Each field shows the value it would inherit as
placeholder text, so blank reads as *"1px, unless I say otherwise"*
rather than as *"nothing"*. A responsive control renders one input bound
to whichever breakpoint its switcher has selected, with a dot on any
breakpoint that already carries a value. Typography, border and
background collapse to a single row carrying a live summary of what is
set inside them.

Above the controls sit the preset tools and the live preview:

| Action | What it does |
| --- | --- |
| **Export preset** | Downloads the **saved** global styles as `kdna-tables-styles.json`. If there are unsaved edits it says so rather than folding them in — a preset nobody can reproduce is not a preset. |
| **Import preset** | Paste a preset or choose an exported file. Import **replaces** rather than merges, so the same preset produces the same result on any site. Anything the schema does not accept is dropped and named, with a reason, instead of failing silently. A bare map of control keys is accepted as well as a full export. |
| **Reset all global styles to plugin defaults** | Confirms, then clears the stored set. An empty stored set *is* the plugin defaults, because the resolver falls through to the schema — which also means a later change to a schema default reaches a site that has been reset. Tables with their own overrides keep them. |

Saving is a REST call, not a form post: `kdna-tables/v1/styles`, behind
`manage_options` and an explicit `wp_rest` nonce check. The page re-seeds
itself from what was actually stored rather than from what was typed, so
anything the sanitiser rejected disappears from the form instead of
sitting there looking saved.

### Caching

The resolver runs once per shortcode on a page, walking seventy-odd
control definitions, merging three layers leaf by leaf and formatting a
hundred-odd CSS values. The result only changes when someone saves, so
the finished style attribute is cached in a transient per table.

Invalidation is by **generation counter**: the counter is part of every
transient key, so saving the globals — which can affect any table — moves
it on and invalidates every table at once, with no LIKE sweep across the
options table and nothing to miss on a site using an object cache. A
per-table save deletes just that table's key, since one table's overrides
cannot change what another resolves to.

The **plugin version is also part of the key**, and for a reason that is
easy to miss: what is cached is the *output* of the schema, with every
default baked into a string. An update that changes a default, adds a
control or fixes which variable a control writes makes every cached
string wrong — and no save has happened, so the generation has not moved.
Without the version in the key, an updated site kept serving the previous
version's CSS for up to a week while the settings page, which resolves
from the schema live, showed the new one. Both were correct and they
disagreed. With it, an update misses every entry and each table rebuilds
once on first view; the stale entries expire on their own TTL rather than
being swept.

While debugging a site whose styles look stale, `add_filter(
'kdna_tables_cache_styles', '__return_false' )` turns the cache off
entirely.

Writes this plugin did not make are covered too: `update_option` on the
global key and `updated_post_meta` on `_kdna_table_style_overrides` both
invalidate, so WP-CLI, an importer or another plugin cannot leave a site
rendering a stale string with no way to clear it from the admin.

Because the variables are written into the markup, a page cache keeps the
old styling until the page regenerates. Saving therefore calls
`rocket_clean_domain()` and `rocket_clean_minify()` when WP Rocket is
present — both behind `function_exists`, since a fatal there would take
down the save that had just succeeded — and fires
`kdna_tables_styles_changed` for anything else.

| Filter | Default | Purpose |
| --- | --- | --- |
| `kdna_tables_cache_styles` | `true` | Turn the transient cache off, for debugging a site whose styles look stale. |
| `kdna_tables_flush_page_cache` | `true` | Stop a style save flushing page caches. |
| `kdna_tables_style_properties` | — | Filter the resolved properties before they are rendered. Receives the property map and the table id. |
| `kdna_tables_styles_changed` | — | Action fired after a style save, for other page caches. |

### Live preview

The settings page carries a preview pane above the controls. A dropdown
picks any published table, defaulting to the most recently modified one,
and a device toggle sets the preview width to 1200px, 900px or 390px.

The preview is an **iframe**, not an inline block, and that is a
deliberate constraint rather than an implementation detail. The
responsive modes are viewport media queries; only a document with a real
390px viewport makes the mobile query fire. Previewing inline would mean
restating every breakpoint rule as a container query or a class
override — a second copy of the responsive layer to keep in step with
the first for ever.

The frame carries no `src`, so its document is `about:blank` and
therefore same-origin. It loads `kdna-tables.css` and
`kdna-shortcode.css` by URL, and the markup comes from
`kdna-tables/v1/preview/<id>` — the render templates' own output,
deliberately stripped of its resolved style attribute so an unset control
reads as absent in the preview exactly as it does on the front end.

Everything after that is a DOM write through `contentDocument`. Because
every visual property reads from a custom property, updating the preview
is writing the resolved variables onto the wrapper, so editing repaints
with no re-fetch and no `postMessage` plumbing. The markup is re-fetched
only when the chosen table changes, or when the sticky toggle does —
sticky wraps the table in a scroll container, which is structure rather
than style. Responsive mode and breakpoint are wrapper data attributes,
so they are rewritten in place like the variables are.

Resolving the variables in the browser means there are two
implementations of the resolver, one in PHP and one in
`assets/js/kdna-style-admin.js`. Both are driven by the same schema
object, so anything expressible as a schema entry needs no code in
either, and the pair is held together by a parity test that runs both
over the same value sets and compares the property maps.

The pane is on the global settings page only. A per-table panel
previewing itself would want the table's own overrides folded into the
preview's variable maths, which is a change to what the pane resolves
rather than to where it is rendered.

### Pivot rows are cards

A pivoted table is one card per desktop row, its cells stacked inside it:
each row's facts in a box of their own, one box under the next with a gap
between them.

| Control | Default | What it does |
| --- | --- | --- |
| **Card Background** | `#ffffff` | The card's ground |
| **Card Border** | `1px solid #e5e7eb` | Style, width and colour, one width for all four sides |
| **Card Border Radius** | `12px` | Rounds the whole card; the cells inside stay square |
| **Card Padding** | `16px` | Even inset between the card edge and its contents |
| **Spacing Between Cells** | `0` | *Extra* gap between cells inside a card, on top of the Body Cells padding |
| **Space Between Cards** | `16px` | The gap between one card and the next. Not applied under the last card |

The spacing inside a card comes from three controls that stack, and each
does exactly what its name says:

- **Body Cells → Padding** pads each cell, all four sides. This is the
  base spacing between one heading-and-value pair and the next, and it is
  also what insets them from the card's sides. Set it to `0` left/right
  for headings that run the full width of the card.
- **Card Padding** insets the whole stack from the card edge.
- **Spacing Between Cells** adds more space between cells and nothing
  else. Zero by default, so it never contributes a gap the padding
  control cannot account for.

The space around the *whole table* is separate, in **Table Wrapper**:
**Space Above Table** and **Space Below Table**, both responsive, both
applying in every mode. Between the cards themselves is **Space Between
Cards** here. The last card gets no bottom gap, so raising the card
spacing does not also add dead space under the table and leave the two
controls fighting.

**The whole Header Row section reaches the injected column headings.**
Pivoted or stacked, the real `<thead>` is hidden and the heading is a
`::before` on each cell, so it inherits the header row's background,
colour, typography, padding and alignment. The **Pivot Rows: Column
Heading** controls override any of it where the two should differ.

Alignment is centred by default in every responsive mode — a stack of
mixed left, centre and right reads as broken in one narrow strip — but it
is a default, not a lock: set Text Alignment and it wins.

These carry the card's own defaults rather than defaulting to nothing. A
mode called Pivot Rows that renders as one undifferentiated column of
text until four separate controls are found and set looks broken, not
neutral.

Two details are load-bearing:

- **The radius is on the row, not the cells.** A radius per cell rounds
  every band inside the card and leaves the card itself square, which is
  the opposite of what a card is.
- **The cell gap is a flex gap, not cell padding.** The row is a column
  flex box, so the gap falls between cells and nowhere else. Padding on
  each cell would double up between neighbours and leave a half gap
  against the card's own padding at the two ends.

Card Border and Row Divider both want the bottom edge. A divider that is
set wins it; with no divider, the border closes the box. The Card Border
is a style/width/colour trio rather than a four-sided border group, so
its single width can be handed to `border-bottom-width` — a four-sided
value there is invalid, and an invalid longhand resets to `medium`
rather than falling through to the shorthand above it.

### Hover states on touch devices

Every front-end `:hover` rule sits inside `@media (hover: hover)`. A
phone has no pointer, but it still fires `:hover` on tap and leaves it
stuck there until something else is tapped — so the last row touched kept
a highlight it never earned, reading as a selection the table had not
made.

The query asks whether a pointing device exists rather than inferring it
from viewport width, which a laptop can also have. Where a rule paired
`:hover` with `:focus`, `:focus-within` or `.is-open` — the comparison
table's tooltips — only the hover half moved. Those are the tap and
keyboard paths, and gating them would have taken tooltips away from
exactly the devices that need the tap path.

### Per-table overrides

Every table gets a **Styles** panel on its own edit screen, with the same
controls as the settings page. Each control there has a third state on
top of set and unset: **inherit**, which is the default and means the
table follows the global value. An inherited control shows the value it
is inheriting, greyed, next to an **Override** button that takes it off
inherit — seeded with the value it was showing, so editing starts from
what was on screen rather than from blank. Once overridden it offers
**Revert to global**, and each section header offers **Reset all in this
section to inherit**. The save bar carries a confirmed **Reset entire
table to inherit** for the whole panel.

Overrides are stored in the `_kdna_table_style_overrides` post meta,
merged leaf by leaf over the global option, so overriding a table's
mobile padding leaves its desktop padding following the global. Nothing
is written for an inherited control: absent is what inherit means to the
resolver, and a control reverted back to inherit is deleted from the meta
rather than stored empty. A table with no overrides at all deletes the
meta key entirely.

The panel is visible to users with `manage_options`, and saves through
`kdna-tables/v1/styles/<id>` — the same nonce check, the same capability
check and the same schema-driven sanitiser as the global route, plus a
check that the id is a table this user can edit.

## CSS Variables (Theme Integration)

Every visual token is exposed as a CSS variable scoped to the widget
wrapper. Theme stylesheets or per-instance Custom CSS (via the
`selector` keyword) can override these without rewriting selectors.

### General Table

| Variable | Default | Purpose |
| --- | --- | --- |
| `--kdna-table-bg` | `transparent` | Table wrapper background |
| `--kdna-table-border-color` | `#e5e7eb` | Legacy border colour, kept for custom CSS |
| `--kdna-table-border-width` | `1px` | Legacy border width, kept for custom CSS |
| `--kdna-table-border-radius` | `12px` | Outer wrapper radius |
| `--kdna-table-h-line-style` | `solid` | Horizontal line between body rows (`none` hides) |
| `--kdna-table-h-line-width` | `1px` | Horizontal line width |
| `--kdna-table-h-line-color` | `#e5e7eb` | Horizontal line colour |
| `--kdna-table-v-line-style` | `solid` | Vertical line between columns (`none` hides) |
| `--kdna-table-v-line-width` | `1px` | Vertical line width |
| `--kdna-table-v-line-color` | `#e5e7eb` | Vertical line colour |
| `--kdna-table-header-divider-style` | `solid` | Line under the header row (`none` hides) |
| `--kdna-table-header-divider-width` | `1px` | Header divider width |
| `--kdna-table-header-divider-color` | `#e5e7eb` | Header divider colour |
| `--kdna-table-header-v-line-style` | falls back to `--kdna-table-v-line-style` | Vertical lines between header cells |
| `--kdna-table-header-v-line-width` | falls back to `--kdna-table-v-line-width` | Header vertical line width |
| `--kdna-table-header-v-line-color` | falls back to `--kdna-table-v-line-color` | Header vertical line colour |
| `--kdna-table-first-col-edge-style` | falls back to `--kdna-table-v-line-style` | Line to the right of the first column |
| `--kdna-table-first-col-edge-width` | falls back to `--kdna-table-v-line-width` | First column right edge width |
| `--kdna-table-first-col-edge-color` | falls back to `--kdna-table-v-line-color` | First column right edge colour |
| `--kdna-table-header-bg` | `#000000` | Header row background |
| `--kdna-table-header-color` | `#ffffff` | Header row text colour |
| `--kdna-table-header-padding` | `14px 16px` | Header cell padding |
| `--kdna-table-body-bg-odd` | `#ffffff` | Body odd-row background |
| `--kdna-table-body-bg-even` | `#f7f7f8` | Body even-row background |
| `--kdna-table-body-color` | `#1f2937` | Body text colour |
| `--kdna-table-body-padding` | `12px 16px` | Body cell padding |
| `--kdna-table-first-col-weight` | `700` | First column font weight (row-header columns only) |
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
| `--kdna-pivot-label-color` | undeclared | Pivot rows label colour. Unset, the general table's column headings take `--kdna-table-header-color` and the comparison item labels inherit |
| `--kdna-pivot-label-width` | `30%` | Pivot rows inline label width |
| `--kdna-pivot-row-spacing` | `16px` | Gap between one pivot card and the next |
| `--kdna-pivot-row-bg` | `#ffffff` | Pivot card background |
| `--kdna-pivot-row-padding` | `16px` | Pivot card padding |
| `--kdna-pivot-row-radius` | `12px` | Pivot card radius, on the whole card |
| `--kdna-pivot-row-border-style` | `solid` | Pivot card border style |
| `--kdna-pivot-row-border-width` | `1px` | Pivot card border width, one value for all four sides |
| `--kdna-pivot-row-border-color` | `#e5e7eb` | Pivot card border colour |
| `--kdna-pivot-cell-spacing` | `0` | Extra gap between cells inside a pivot card, on top of the body padding |
| `--kdna-pivot-heading-padding` | undeclared | Pivot rows column heading padding. Unset, headings take `--kdna-table-header-padding` |
| `--kdna-pivot-heading-radius` | `0` | Pivot rows column heading radius |
| `--kdna-pivot-heading-spacing` | `2px` | Gap under a pivot rows column heading |
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

### 3.4.2

**A black box appeared around the table with no setting changed.** The
caption-and-table unit added in 3.4.0 is a new, unadorned `<div>` inside
post content, and a theme rule as ordinary as `.entry-content div {
border: 3px solid }` decorates it on sight. That selector is `(0,1,1)`
and beat the single class keeping it plain.

The unit is unconditionally inert now — doubled class and `!important` on
margin, padding, border, radius, background and shadow. Not the usual
answer, and the right one here: the element exists only to hold the
caption and the table together, it has no design of its own to lose, and
losing that argument puts a black box round a table on a live page. The
frame stays on the wrapper inside, where every control that draws one
already points.

**Two things it turned up.** The unit rule lived only in
`kdna-shortcode.css`, which the Elementor widget does not load — so it
reached shortcodes and missed widgets entirely; it is in
`kdna-tables.css` now, which both paths load. And the widget's four
caption controls still targeted `{{WRAPPER}} .kdna-table--general
.kdna-table__caption`, a descendant of the table the caption no longer
sits in, so Caption Typography, Colour, Alignment and Spacing had
silently stopped working on the widget. They point at the moved element
now.

### 3.4.1

**The Show Caption toggle did nothing.** The widget read
`$settings['caption_show']`, but `$settings` is not the Elementor
settings array — `KDNA_Tables_Data` rebuilds it from the table's meta
plus an *explicit pass-through list* of widget controls, and a control
missing from that list simply is not in it. The toggle stored `yes` and
the render read null.

The widget reads `$widget_settings` now, which is the real thing, and
`caption_show` is in the pass-through list so anything downstream sees
the same answer.

Worth naming, because it is the fourth time this shape has appeared here:
every earlier caption test drove the render partial directly with
`show_caption` already set. They proved the partial works and said
nothing about whether anything sets it. The new checks drive the toggle
the way the widget does — Elementor settings in, markup out.

### 3.4.0

- **The caption is outside the table frame now.** It was the first child
  of the wrapper, which carries the background, border, radius and an
  `overflow: hidden` that clips the header row's corners to that radius —
  so the caption was clipped by the same rule, the first letter sliced
  off by the corner, and painted over the radius on the way past. Caption
  and wrapper are siblings inside a `.kdna-table__unit` that draws
  nothing. The style attribute moved to the unit: custom properties
  inherit, so the wrapper resolves exactly as before and the caption can
  see the variables too.
- **New Elementor control: Show Caption**, in Source Table. **Off by
  default**, because an Elementor page usually has its own Heading widget
  above the table and a second one appearing on update would be a
  regression on every existing instance. Shortcodes always show the
  caption when the table has one.

### 3.3.3

- **The "editor did not load" notice now diagnoses itself.** It reports
  whether `kdna-admin.js` is on the page, whether it executed, whether
  Alpine is present and started, and whether the seed arrived. Those are
  five different faults with one symptom, and reading them off a console
  dump was costing a round trip each. The check is inline and
  dependency-free, because it has to run in exactly the situation where
  the plugin's own scripts do not.
- **Alpine injection is deferred to `DOMContentLoaded`.** 3.3.2 had each
  script inject Alpine as soon as it ran, which left a narrower version
  of the same race: on the table edit screen two of our scripts are on
  the page, and an injected script can execute in the gap between two
  classic ones. By `DOMContentLoaded` every classic script has run, so
  every component is registered before Alpine can look for one. Tested
  by driving the worst order — styles script first, editor second.

### 3.3.2

**The table editor was dead from 3.0.0 onward, and this is why.**

Symptoms: no rows, no columns, + Row and + Column doing nothing, the
caption never saving. Console: `kdnaTablesGeneralEditor is not defined`.

3.0.0 is when the per-table Shortcode Styles panel started loading on the
table edit screen. From then on **two** of our screens enqueued there,
and both pinned Alpine's load order the documented way — register Alpine
with their own script as its dependency.

But `wp_enqueue_script` ignores the `src` and `deps` of a handle that is
already registered. So whichever of the two ran second silently inherited
the other's dependency list. On the ordering where the styles panel
registered `kdna-tables-alpine` first, Alpine did not depend on
`kdna-admin.js` at all — it printed first, walked the DOM before any
component was registered, and every `x-data` expression threw. The editor
then rendered as an empty one-cell table.

The caption "not saving" was the same fault: `x-model="state.caption"`
never bound, so what was typed never reached the form.

**The fix is not a better dependency list — it is not having the race.**
Neither screen enqueues Alpine now. Each script injects it itself, after
registering its components, so `alpine:init` cannot be missed and no
script optimiser, deferral or combination setting can reorder the two.
If the script does not load at all then neither does Alpine, which is the
honest outcome: the 3.3.1 boot warning stays up and nothing
half-initialises.

Checked under both hook orders, because the old arrangement passed under
one of them — which is exactly why it survived four releases.

### 3.3.1

**A table editor that fails to load can no longer destroy the table.**

Everything in the Table data box is Alpine. If its scripts do not run —
blocked, reordered by an optimiser, 404ing behind a CDN — the markup
still renders, but Alpine falls back to `defaultGeneralState()`: one
empty column, one empty row, both header checkboxes off. That is
indistinguishable from a brand new table. Pressing Update on it wrote the
blank over the real one, silently and completely.

Two changes, either of which is enough on its own:

- **The save refuses a state the editor never seeded.** The seed carries
  the post id; the fallback state carries `0`. A mismatch is an exact
  statement that this state did not come from this post's data, so
  nothing is written and the refusal is recorded in a transient. It is
  deliberately not a "did the table shrink?" heuristic — deleting rows is
  something people do on purpose, and a guard that second-guesses that
  loses edits instead of saving them. Emptying a table on purpose still
  works; so does a state arriving for the wrong post id, which is refused
  for the same reason.
- **The editor says when it has not loaded.** A notice sits in the page
  from the start, along with the row and column counts read straight from
  the database, and the editor removes it only on a boot that actually
  received the seed. The failure state is now the one that talks.

If you see that notice: your data is safe and still in the database, and
the screen simply cannot show it. Hard-refresh first. If it persists,
look in the browser console for an error on `kdna-admin.js` — the
signature is `kdnaTablesGeneralEditor is not defined` — and disable
JavaScript optimisation, deferral, delay or minification for the admin.

### 3.3.0

**The caption is a heading above the table.** It was a `<caption>` element
inside the `<table>`, which is very hard to style and very easy to lose:
it cannot take a margin separating it from the frame, it sits inside the
wrapper's overflow clipping, and in the responsive modes the table itself
becomes `display: block` or a grid — at which point the caption's layout
is whatever the browser decides.

**It was also dropped entirely on comparison tables.** The comparison
editor collected a caption, saved it, and `render-comparison.php` never
output one. Typed, stored and invisible — which is exactly what "the
caption doesn't save" looks like from the outside. Both table types now
render the same partial.

**Full style controls**, all responsive except the heading level:
Typography, Colour, Alignment, Padding, Background, Border, Border
Radius, Space Above, Space Below, and **Heading Level** — `h2` to `h6`,
`p`, or a plain block. The level is a content decision rather than a
style one: a table under an `<h2>` wants an `<h3>` here, and getting it
wrong is an outline error a screen reader and a search engine both
notice.

An empty caption renders no markup at all — not an empty heading with a
margin, which would leave an unexplained gap above the table.

**Where it renders.** Outside the wrapper, as a sibling of it inside a
`.kdna-table__unit`. Inside the wrapper it was clipped by the frame's own
`overflow: hidden`. Shortcodes always render it when the table has a
caption; the Elementor widget has a **Show Caption** toggle, off by
default.

**Accessibility.** Moving the caption out of the table would leave the
table unnamed, so the heading carries an id and the table is
`aria-labelledby` it. A screen reader announces the same text it did
before, once. The stored heading level is allow-listed again at render,
because it is interpolated into a tag name and a sanitiser on the way in
is not the right place to rely on for that.

**On the caption not saving:** the save path itself checks out. The
editor writes the caption into the state input on every keystroke and
again on submit, and `save_post` reads it, sanitises it and stores it — a
round trip through the real save handler and the real seed builder keeps
its value, changes it, clears it, and survives a quick-edit save with no
editor state. If it still goes missing on a general table after
installing this, that is a different fault from the one fixed here and
worth reporting with the table type.

### 3.2.4

- **The last pivot card no longer keeps its bottom gap.** The space
  between cards is a margin under each one, and the last card has nothing
  to clear — so raising it to 40px also put 40px of dead space under the
  table. Space Below Table then read as adding more than it was set to,
  and the two controls looked like they were fighting each other.
- **"Row Spacing" is now "Space Between Cards".** The old name described
  the markup rather than what is on screen: pivoted, a row *is* a card,
  and nobody looking for the gap between two cards found it under a
  heading about rows. It also now points at Table Wrapper for the space
  around the whole table, which is where the two page-level margins live.

Both controls this names already existed and both worked — verified on a
page with real siblings above and below, since a margin's effect on
screen depends on what it collapses against, not on what the declaration
says. What did not work was the two of them together.

### 3.2.3

**The front end kept serving the previous version's CSS after an update.**
The resolved style attribute is cached per table for a week, and what is
cached is the *output* of the schema — every default baked into a string.
The key was the invalidation generation plus the table id, and the
generation only moves when someone saves on the settings page. So an
update that changed a default, added a control or fixed which variable a
control writes left every cached string wrong, and nothing said so.

That is why an updated site could show a live table that did not match
its own preview: the preview resolves from the schema on every keystroke,
the front end was serving a week-old string built by the schema of the
version before. Both halves were behaving correctly, which is why it was
invisible from either side.

The plugin version is now part of the cache key, so an update misses
every entry and each table rebuilds once on first view. Saving still
moves the generation within a version, exactly as before. If you ever
need to rule the cache out while debugging, `add_filter(
'kdna_tables_cache_styles', '__return_false' )` turns it off.

**The preview now says when it is showing unsaved changes.** It renders
from the editor's live values, so an edit appears the moment it is made
while the front end keeps the saved ones — correct, and previously
silent. The only hint was the words "Unsaved changes" beside the save
button, well below the fold on a long section, which reads as a save
reminder rather than as the explanation for why the live table looks
different. There is a notice above the frame now.

### 3.2.2

**Header Row → Typography did nothing in the responsive modes.** Pivoted
or stacked, the real `<thead>` is hidden and the column name is a
`::before` on each cell. That pseudo-element's font chain ended at
literals — `inherit`, `700`, `normal` — so setting the header size to
100px moved nothing and nothing on the page said why. Every field now
falls back to the Header Row's own value: family, size, weight, style,
transform, decoration, line height, letter spacing, word spacing.

Pivot Rows: Column Heading Typography no longer pre-sets a weight of
`700`, which was overriding Header Row → Weight. Unset, it inherits;
set, it still wins.

**Text Alignment did nothing in any responsive mode.** Both the cells and
the injected headings carried a hardcoded `text-align: center`. Centring
is still the default — a stack of mixed left, centre and right reads as
broken in one narrow strip — but it is now the *fallback*, so Header Row
→ Text Alignment and Body Cells → Text Alignment work when set.

**Card Stack had the same two faults as Pivot Rows.** In the tablet band
the injected heading had no background and no padding of its own, so
Header Row → Background and → Padding were inert. At mobile the card
padding overwrote the heading cell's own padding. Both fixed.

#### The audit that let all of this through

`control-audit.js` sets each control's variable and requires the target
element to move. It tried each responsive mode only until it found one
where the selector matched, checked the control *there*, and moved on —
which in practice meant desktop. A control verified at desktop was
reported as working even where a responsive-mode rule shadowed it.

It now checks **every** mode in which the selector matches, and each
result is one of three things:

- **passed** — the named element moves;
- **relocated** — that element does not move, but something else in the
  table does. The effect legitimately moves between modes: the heading is
  a `::before` when pivoted, the card background is on the `<tr>` at
  tablet and on the cell at mobile;
- **inert** — nothing in the table moves at all. That is the bug, and it
  fails the audit unless the pair is listed in `EXPECTED_INERT` with a
  written reason. There are 32 such entries — no columns to rule a line
  between once every cell is a full-width block, alternate shading
  stripped by design in Pivot Rows, and so on.

633 checks, 0 unexpectedly inert.

### 3.2.1

Three faults introduced by the card work in 3.2.0, all in Pivot Rows.

- **Body Cells → Padding did nothing.** The pivot cell rule set
  `padding: 0`, on the reasoning that the card's padding and the card's
  gap between cells were together enough. That silently disabled a
  control: setting it moved nothing and the page gave no hint why. Cells
  take the Body Cells padding again, all four sides. The first cell of
  each card takes the same padding as its siblings rather than the First
  Column gutter, which is a gutter for a column that has stopped existing
  in this layout.
- **Alternate shading was still visible** on the first cell of every odd
  card. The rule that strips it excluded first-column cells so that a
  First Column background could still paint — but the First Column
  odd/even backgrounds *are* alternate shading, and they were the half
  left switched on. Every cell in a card is cleared now; the card is the
  ground.
- **Header Row → Padding never reached the column headings.** Pivoted,
  the real `<thead>` is hidden and the heading is a `::before` carrying a
  pivot-specific padding that defaulted to a hard zero and always won.
  That control is unset by default now, so the heading inherits the
  header row's padding exactly as it already inherits that row's
  background and colour, and the pivot-specific control still overrides
  it where the two should differ.

**Spacing Between Cells** now defaults to `0` rather than `12px`. With
the Body Cells padding restored it is extra space on top of that padding,
and a non-zero default would be a second gap the padding control could
not account for.

### 3.2.0

- **The responsive mode is a setting.** It used to exist only as a
  `responsive="…"` shortcode attribute defaulting to card stack, while
  the settings page offered a *Responsive Mode* dropdown in the preview
  bar that flipped an attribute on the preview iframe and nothing else.
  Both halves behaved correctly and they disagreed: the pane showed pivot
  rows, the page showed cards, and nothing on screen explained why.
  **Responsive Mode** and **Applies At** are now saved controls in
  Responsive Modes, overridable per table, and the preview reads them
  back as a readout rather than offering a second place to set them. A
  shortcode attribute still wins wherever one is written, so existing
  markup is untouched.
- **Pivot Rows renders as cards out of the box.** Card Background, Card
  Border, Card Border Radius and Card Padding carry the card's own
  defaults instead of defaulting to nothing — a mode that renders as one
  undifferentiated column of text until four controls are found and set
  looks broken, not neutral. The radius is on the card, not on its cells,
  so the corner belongs to the whole box.
- New **Spacing Between Cells** control for how tightly the facts inside
  one card are packed, separate from Row Spacing, which is the gap
  between whole cards. Implemented as a flex gap on the row: padding on
  each cell would double up between neighbours and leave a half gap
  against the card's own padding at the two ends.
- New **Space Above Table** and **Space Below Table** controls, on every
  device. They are two controls rather than one margin box because
  Alignment already owns the margin shorthand — centring is `auto` on the
  two sides — so these take the vertical edges on variables of their own
  and are applied as longhands after it. Neither can erase the other.
- **Hover states no longer apply on touch devices.** Every front-end
  `:hover` rule now sits inside `@media (hover: hover)`. A phone has no
  pointer but still fires `:hover` on tap and leaves it stuck there, so
  the last row touched kept a highlight it never earned. Where a rule
  paired `:hover` with `:focus`, `:focus-within` or `.is-open` — the
  comparison tooltips — only the hover half moved; those are the tap and
  keyboard paths.

### 3.1.2

- Every control in **Responsive Modes** now says which mode it belongs
  to: "Card Stack: Card Background", "Pivot Rows: Row Background". The
  section holds two independent layouts and twenty-two controls, and
  nothing said that Card Background does nothing in Pivot Rows while Row
  Background does nothing in Card Stack.
- Pivot Rows no longer inherits the **alternate row shading**. Odd/even
  stripes are how you follow a row across a wide table; pivoted, each row
  is its own block, so the stripe only showed up as one group being grey
  and the next white, fighting the Row Background.
- Fixed the **first column's injected heading sitting inset** on both
  sides in Pivot Rows while every other heading ran full width. The First
  Column padding rule was still winning in a layout where the first
  column has stopped being a column.

### 3.1.1

- Fixed the preview pane's **Responsive Mode** and **Applies at**
  dropdowns showing their first option regardless of what the preview was
  actually rendering — the mode select read "No responsive mode" over a
  card-stack preview, so styling Card Stack or Pivot Rows looked like it
  did nothing, and choosing the mode the dropdown already claimed to be
  on changed nothing because the value had not moved. Alpine applies
  `x-model` to a select before an `x-for` inside it has created the
  options, so the select fell to its first option and never re-synced.
  These lists are fixed and known to PHP, so they are rendered there
  instead.
- The preview's table, responsive mode, breakpoint, width and sticky
  toggle are now remembered between page loads, in `localStorage`. They
  say what you are looking at rather than what the site renders, so they
  are not part of the saved styles. A remembered value that no longer
  exists — a deleted table, a mode from a later version — is discarded
  and the default used.

### 3.1.0

Everything below shipped after 3.0.0 went out. The version bump matters
beyond bookkeeping: `KDNA_TABLES_VERSION` is the `?ver=` on every
stylesheet, so a fix shipped under the same number leaves browsers and
CDNs serving the cached old CSS.

- **Pivot Rows gains Row Background, Row Border, Row Padding and Row
  Border Radius**, so each pivoted row reads as a separate box rather
  than one continuous column of text. All four default to nothing. A Row
  Divider that is set still wins the bottom edge; with no divider, the
  border closes the box.
- Fixed **Line Height** doing nothing to the space between wrapped lines.
  The cell text sits in a span that hard-coded `line-height: 1.5`, which
  beat the value inherited from the cell — so the control grew the cell's
  line box, padding the text above and below, while the lines inside
  stayed where they were. The default now lives on the cell and the span
  inherits it. Affects the header row, first column and body cells, on
  the widget as well as the shortcode.
- Fixed the **Shortcode Styles menu link** pointing at
  `/wp-admin/kdna-tables-styles`, which is not an admin URL and landed on
  the theme's 404. The submenu registered before its parent menu existed,
  so WordPress derived the wrong load hook — which also stopped the
  page's own CSS and JS loading. It now registers at a later priority.
- **The shortcode no longer needs Elementor.** The cell-render helpers
  moved out of the widget class into `KDNA_Tables_Cell_Renderer_Trait`,
  so the shortcode binds the render templates to a plain object instead
  of to a widget. Previously, rendering with Elementor deactivated was a
  fatal error, and the guard against it made the shortcode render nothing
  at all. It now renders identically with or without Elementor.
- Fixed the **Card Border Radius** control squaring the top corners of a
  card. The control writes four values and the stylesheet fed them to
  `border-top-left-radius`, which accepts at most two — so the
  declaration was invalid and silently dropped. Affected the Elementor
  widget's card-stack layout for both table types.

### 3.0.0

**The Shortcode Style Engine.** `[kdna_table]` gains full styling parity
with the Elementor widget, plus responsive modes the shortcode could not
previously reach.

- **Shortcode Styles settings page** (KDNA Tables → Shortcode Styles):
  71 controls writing 117 CSS custom properties, covering the wrapper,
  caption, header row, first column, body cells, all five sets of rule
  lines, cell content, both responsive modes and the sticky column.
- **Per-table overrides.** Every table gets a Styles panel on its own
  edit screen. Each control inherits from the global defaults until
  explicitly overridden, with per-control revert, per-section reset and a
  whole-table reset.
- **Live preview** on the settings page: any published table rendered in
  a same-origin iframe at 1200px, 900px or 390px, repainting as controls
  are edited. The iframe is what makes the responsive modes previewable —
  only a real 390px viewport fires the mobile media query.
- **Presets.** Export the global styles as JSON, import them back.
  Import replaces rather than merges, and names anything it discarded.
- **Reset all global styles to plugin defaults**, with confirmation.
- **Caching.** The resolved style attribute is cached per table in a
  transient, invalidated by a generation counter on a global save and by
  key on a per-table save. WP Rocket's cache is flushed on save when its
  API is present.
- New shortcode attributes: `responsive`, `breakpoint`, `sticky`,
  `style_id`.

### 2.3.1

- Responsive modes: text is centred at every breakpoint the mode applies
  to, regardless of the desktop alignment set in the backend.
- Pivot Rows: the column heading row is back, with its own background,
  padding, typography, radius and spacing controls.

### 2.3.0

- General tables no longer draw an outer frame. The Table Wrapper border
  owns the outside edge, so no combination of rule-line settings can
  produce a stray line at the top of the table.
- First Column style controls now target general tables correctly.
- Rule lines split by axis and location, each with its own Style / Width
  / Colour and a **None** option: Body Cells → Horizontal Lines and
  Vertical Lines, Header Row → Bottom Divider and Vertical Lines, First
  Column → Right Edge Line.

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
