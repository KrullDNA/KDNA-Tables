# Full field reference

Every field the importer accepts. Read this when a table needs more than
plain text cells.

Anything not listed here is ignored, and the importer says so.

---

## Top level

| Key | Type | Notes |
|---|---|---|
| `version` | number | Write `1`. Reserved for future format changes |
| `type` | string | `general` or `comparison`. Required |
| `title` | string | Required. Names the table in the library |
| `caption` | string | Optional. Renders as a heading above the table |
| `data` | object | Required. Shape depends on `type` |
| `styles` | object | Optional. Per table style overrides, checked against the plugin's style schema. Anything unrecognised is dropped with a warning |

---

## General tables

### `data`

| Key | Type | Default | Notes |
|---|---|---|---|
| `first_row_is_header` | boolean | `false` | **Promotes the first row of `rows` into the header, and hides the column labels.** Leave it `false` when the headings are the column labels, which is the normal case. See the warning below |
| `first_column_is_header` | boolean | `false` | Renders the leftmost cell of each row as a row header. Use for spec tables with a label column |
| `columns` | array | required | One to ten. The spine of the table |
| `rows` | array | `[]` | Any number |

> **The one that bites.** `first_row_is_header` does not mean "this table
> has a header row". Set it `true` alongside column labels and the labels
> are never rendered, while the first entry in `rows` disappears into the
> header. The table still looks right, so it is only caught by counting
> the rows. Use `true` only when the heading text is genuinely the first
> entry of `rows` and the columns carry no labels.

### A column

| Key | Type | Default | Notes |
|---|---|---|---|
| `id` | string | generated | Leave it out and the plugin generates one |
| `label` | string | `""` | The heading text |
| `alignment` | string | `left` | `left`, `centre` or `right`. Aligns the body cells in this column |
| `header_alignment` | string | `""` | Same values. Empty means follow the global header alignment |
| `width` | number | `0` | `0` means auto |
| `width_unit` | string | `%` | `%` or `px` |

### A row

```json
{ "id": "optional", "cells": [ { }, { } ] }
```

Cells are positional. The first cell is the first column.

### A cell

| Key | Type | Default | Notes |
|---|---|---|---|
| `id` | string | generated | Leave it out |
| `content_types` | array | `["text"]` | Any of `text`, `icon`, `image`. Controls what the cell shows |
| `text` | string | `""` | Inline HTML allowed |
| `icon` | object | empty | `{ "value": "fas fa-check", "library": "fa-solid" }` |
| `image` | object | empty | `{ "id": 0, "url": "", "alt": "" }` |
| `arrangement` | string | `icon-text` | Order of the pieces. See below |
| `alignment` | string | `""` | `left`, `centre`, `right`. Empty means follow the column |

Plain text cell:

```json
{ "text": "L-ascorbic acid" }
```

Icon and text:

```json
{
  "content_types": ["icon", "text"],
  "icon": { "value": "fas fa-check", "library": "fa-solid" },
  "text": "Included",
  "arrangement": "icon-text"
}
```

### Arrangements

Two piece: `icon-text`, `text-icon`, `image-text`, `text-image`,
`icon-image`, `image-icon`.

Three piece: every ordered permutation of icon, text and image, for
example `icon-text-image`.

### Images

`image.id` is a WordPress attachment id **on the site doing the
importing**. An id copied from another site points at whatever happens to
be that id here, which is usually somebody else's picture. The importer
warns about every id it sees for exactly this reason.

Safest: leave `id` at `0` and set `url` to a full URL, or leave the image
out and pick it in the editor afterwards.

---

## Comparison tables

### `data`

| Key | Type | Default | Notes |
|---|---|---|---|
| `items` | array | required | Two to six. The things being compared, one per column |
| `feature_rows` | array | `[]` | The rows they are compared on |
| `highlighted_item_index` | number | `-1` | Zero based. `-1` for none |
| `badge_text` | string | `""` | Shown on the highlighted item |
| `badge_position` | string | `top-centre` | `top-left`, `top-centre` or `top-right` |

### An item

| Key | Type | Notes |
|---|---|---|
| `id` | string | Leave it out |
| `label` | string | The product or option name |
| `sublabel` | string | A short qualifier under the name |
| `image` | object | Same shape as a cell image |
| `cta` | object | `{ "enabled": true, "text": "Buy", "url": "https://…" }` |

### A feature row

| Key | Type | Notes |
|---|---|---|
| `id` | string | Leave it out |
| `label` | string | The feature name, in the left column |
| `description` | string | Smaller text under the label |
| `tooltip` | string | Shown on a hover or tap target beside the label |
| `cells` | array | One per item, in item order |

### A comparison cell

| Key | Type | Default | Notes |
|---|---|---|---|
| `state` | string | `available` | `available`, `unavailable` or `custom` |
| `custom` | object | empty | Only read when `state` is `custom` |

`available` and `unavailable` render the plugin's tick and cross. Use
`custom` when the answer is a value rather than a yes or no:

```json
{
  "state": "custom",
  "custom": {
    "content_types": ["text"],
    "text": "20%"
  }
}
```

The `custom` object takes the same `content_types`, `text`, `icon`,
`image` and `arrangement` keys as a general cell.

---

## Styles

Optional, and only when asked for. Control keys map to the Shortcode
Styles settings page, for example:

```json
"styles": {
  "header_background": { "color": "#bfff00" },
  "header_text_color": "#000000",
  "body_padding": { "top": 14, "right": 16, "bottom": 14, "left": 16, "unit": "px" }
}
```

Anything the style schema does not recognise is dropped, and the importer
names it in a warning. If Nick has not asked for styling, leave the whole
key out and let the table inherit the site's global styles, which is
almost always what he wants.
