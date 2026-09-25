---
name: kdna-table-json
description: "Build KDNA Tables import JSON for the KDNA Tables WordPress plugin. Use whenever Nick wants a table built from article text, data or a spreadsheet, or says import, table JSON or build me a table. Not for the plugin's own code."
---

# KDNA Tables import JSON

Turns content into a JSON file the KDNA Tables plugin imports as a new
table. Upload it at **KDNA Tables > Import** in wp-admin, or paste it
into the box on that page.

## When this applies

Use it whenever Nick wants a table for a post and the content already
exists somewhere: an article draft, a set of notes, a spreadsheet, a
comparison he has described in chat. Casual phrasings count: "make this
into a table", "build me a comparison of these three", "turn that
section into a table I can import".

Not for changing the plugin itself. That is `wp-plugin-builder`.

## Rule zero: validate before handing anything over

Run the validator on every file before presenting it:

```bash
python3 scripts/validate.py path/to/table.json
```

It applies the same rules the plugin applies on import. If it reports an
error the plugin will refuse the file. If it reports a warning the plugin
will import it and change something on the way in, so read the warning
and decide whether that was intended.

Never hand over a file that has not been through it. A table that imports
into an empty grid wastes more time than writing it by hand.

## Pick the type first

Two table types, and they are not interchangeable.

| Use | When |
|---|---|
| `general` | A grid. Any number of columns, any number of rows, free text in every cell. The default choice. |
| `comparison` | Two to six things compared on a list of features, with tick and cross indicators, optional highlight badge and call to action buttons. |

If the content is "here are some products and which features each one
has", use `comparison`. Otherwise use `general`.

## The shape

One JSON object. Two required keys plus `data`.

```json
{
  "version": 1,
  "type": "general",
  "title": "Vitamin C forms",
  "caption": "Vitamin C forms & properties",
  "data": { }
}
```

| Key | Required | Notes |
|---|---|---|
| `type` | yes | `general` or `comparison` |
| `title` | yes | Names the table in the library and in the Elementor table picker. Never leave it out |
| `data` | yes | The table itself. Shape depends on `type` |
| `caption` | no | Renders as a heading above the table on shortcodes. Off by default on the Elementor widget |
| `styles` | no | Per table style overrides. Only use when asked |
| `version` | no | Write `1` |

### General

```json
"data": {
  "first_row_is_header": false,
  "first_column_is_header": false,
  "columns": [
    { "label": "Form", "width": 30, "width_unit": "%" },
    { "label": "Stable?", "alignment": "centre" }
  ],
  "rows": [
    { "cells": [ { "text": "L-ascorbic acid" }, { "text": "Poorly" } ] },
    { "cells": [ { "text": "MAP" }, { "text": "Very" } ] }
  ]
}
```

At least one column, maximum ten. Give every row exactly as many cells as
there are columns, in the same order.

**Leave `first_row_is_header` as `false` when the headings are the column
labels**, which is the normal case. It does not mean "this table has a
header row". It means "promote the first row of data into the header",
and when it is `true` the column labels are not rendered at all. Set it
`true` only when the heading text lives in the first entry of `rows` and
the columns have no labels. Getting this wrong silently swallows a row of
data, and the table looks fine.

### Comparison

```json
"data": {
  "highlighted_item_index": 0,
  "badge_text": "Best value",
  "badge_position": "top-centre",
  "items": [
    { "label": "Serum A", "sublabel": "20% LAA" },
    { "label": "Serum B", "sublabel": "10% MAP" }
  ],
  "feature_rows": [
    {
      "label": "Vitamin C",
      "cells": [ { "state": "available" }, { "state": "available" } ]
    },
    {
      "label": "Fragrance free",
      "cells": [ { "state": "unavailable" }, { "state": "available" } ]
    }
  ]
}
```

Two to six items. Give every feature row exactly as many cells as there
are items, in the same order. `highlighted_item_index` is zero based, and
`-1` means none.

Full field reference, including icons, images, cell arrangements and
call to action buttons: `references/format.md`. Read it when the table
needs anything beyond plain text cells.

## Rules that cause a refusal

The plugin refuses the whole file for any of these. The validator catches
them all.

- `type` missing, or not `general` or `comparison`
- `title` missing or empty
- `data` missing
- A general table with no columns
- A comparison table with fewer than two items

## Things that import but get changed

Worth avoiding rather than relying on:

- `first_row_is_header` set `true` alongside column labels eats the first
  row of data into the header and hides the labels
- A row with the wrong number of cells is padded or trimmed to match
- More than ten columns, or more than six items, and the extras are dropped
- An unrecognised alignment, arrangement or state falls back to a default
- An unknown key anywhere is ignored

## Writing good cells

- Keep cell text short. A table is scanned, not read
- Plain text is the default. Do not reach for icons or images unless Nick
  asks for them
- Basic HTML is allowed in `text`, so `<strong>` and `<a href>` work.
  Block level tags do not belong in a cell
- Use UK English in the content, matching the article it belongs to
- Write `centre`, not `center`, though both are accepted

## Delivering

1. Write the JSON to a `.json` file, named after the table:
   `vitamin-c-forms.json`
2. Run the validator
3. Fix anything it reports, and run it again
4. Present the file and say in one line what to do with it: upload it at
   KDNA Tables > Import

## Self-check

1. Validator run, and clean?
2. `type`, `title` and `data` all present?
3. Every row's cell count equal to the column count, or every feature
   row's cell count equal to the item count?
4. `first_row_is_header` false, unless the headings really are in the
   first row of data rather than in the column labels?
5. Ten columns or fewer, six items or fewer?
6. Delivered as a `.json` file rather than pasted into chat?
