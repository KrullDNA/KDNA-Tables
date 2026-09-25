#!/usr/bin/env python3
"""
Validate a KDNA Tables import file before handing it over.

Applies the same rules the plugin applies on import, so a file that
passes here imports cleanly there. The two are kept in step by a parity
test in the plugin repository that runs both over the same fixtures and
requires the same verdict.

    python3 validate.py table.json

Exit code 0 when the file would import, 1 when the plugin would refuse
it. Warnings do not fail the run: they are things the plugin accepts and
changes on the way in, and they need a human to decide whether the change
was intended.
"""

import json
import sys

KNOWN_KEYS = ["version", "type", "title", "caption", "data", "styles"]

MAX_GENERAL_COLUMNS = 10
MAX_COMPARISON_ITEMS = 6

VALID_ALIGNMENTS = ["left", "centre", "center", "right"]
VALID_CONTENT_TYPES = ["text", "icon", "image"]
VALID_BADGE_POSITIONS = ["top-left", "top-centre", "top-right"]
VALID_STATES = ["available", "unavailable", "custom"]

# Every ordered permutation of the three piece types, two and three deep,
# matching KDNA_Tables_CPT::VALID_ARRANGEMENTS.
VALID_ARRANGEMENTS = [
    "icon-text", "text-icon", "image-text", "text-image", "icon-image", "image-icon",
    "icon-text-image", "icon-image-text", "text-icon-image",
    "text-image-icon", "image-icon-text", "image-text-icon",
]


def validate(payload):
    """Return (errors, warnings) for a decoded payload."""
    errors = []
    warnings = []

    if not isinstance(payload, dict):
        return (["The import must be a JSON object."], [])

    for key in payload:
        if key not in KNOWN_KEYS:
            warnings.append(
                '"%s" is not part of the format and will be ignored. '
                "Expected: %s." % (key, ", ".join(KNOWN_KEYS))
            )

    table_type = payload.get("type")
    table_type = table_type.strip().lower() if isinstance(table_type, str) else ""
    if table_type not in ("general", "comparison"):
        if table_type == "":
            errors.append('"type" is missing. It must be "general" or "comparison".')
        else:
            errors.append(
                '"type" is "%s". It must be "general" or "comparison".' % payload.get("type")
            )

    title = payload.get("title")
    title = title.strip() if isinstance(title, str) else ""
    if not title:
        errors.append('"title" is missing. It names the table in the library.')

    data = payload.get("data")
    if not isinstance(data, dict):
        errors.append('"data" is missing, or is not an object.')
        data = None

    if data is not None and table_type == "general":
        check_general(data, errors, warnings)
    elif data is not None and table_type == "comparison":
        check_comparison(data, errors, warnings)

    if "styles" in payload and not isinstance(payload["styles"], dict):
        warnings.append('"styles" is not an object and will be ignored.')

    return (errors, warnings)


def check_general(data, errors, warnings):
    columns = data.get("columns")
    if not isinstance(columns, list) or not columns:
        errors.append(
            "A general table needs \"data.columns\" with at least one column. "
            "Rows are matched to the columns, so with none, every cell would be discarded."
        )
        return

    count = len(columns)
    if count > MAX_GENERAL_COLUMNS:
        warnings.append(
            "data.columns has %d columns; the maximum is %d, so the extras will be dropped."
            % (count, MAX_GENERAL_COLUMNS)
        )
        count = MAX_GENERAL_COLUMNS

    for i, col in enumerate(columns):
        if not isinstance(col, dict):
            warnings.append("data.columns[%d] is not an object and will become an empty column." % i)
            continue
        check_alignment(col, "alignment", "data.columns[%d]" % i, warnings)
        check_alignment(col, "header_alignment", "data.columns[%d]" % i, warnings)
        unit = col.get("width_unit")
        if unit is not None and unit not in ("%", "px"):
            warnings.append(
                'data.columns[%d].width_unit is "%s"; only "%%" and "px" are accepted.' % (i, unit)
            )

    # first_row_is_header does not mean "the columns are headings". It
    # promotes the first row of DATA to the header, and when it is on the
    # column labels are not rendered at all. A file written the obvious
    # way, with labels and a full set of rows, silently loses its first
    # row into the header.
    labelled = any(
        isinstance(c, dict) and str(c.get("label", "")).strip()
        for c in columns
    )
    if data.get("first_row_is_header") and labelled:
        warnings.append(
            "data.first_row_is_header is true, so the first row of data becomes the header "
            "and the column labels are not shown. If the labels are your headings, set it to false."
        )

    rows = data.get("rows")
    rows = rows if isinstance(rows, list) else []
    if not rows:
        warnings.append("data.rows is empty, so the table will import with its columns but no content.")
        return

    for i, row in enumerate(rows):
        if not isinstance(row, dict):
            warnings.append("data.rows[%d] is not an object and will become an empty row." % i)
            continue
        cells = row.get("cells")
        cells = cells if isinstance(cells, list) else []
        if len(cells) != count:
            warnings.append(
                "data.rows[%d] has %d cells for %d columns. It will be padded or trimmed to match."
                % (i, len(cells), count)
            )
        for j, cell in enumerate(cells):
            check_cell(cell, "data.rows[%d].cells[%d]" % (i, j), warnings)


def check_comparison(data, errors, warnings):
    items = data.get("items")
    items = items if isinstance(items, list) else []
    count = len(items)

    if count < 2:
        errors.append(
            "A comparison table needs at least two items in \"data.items\"; this has %d. "
            "Fewer than two renders a placeholder rather than a table." % count
        )
        return

    if count > MAX_COMPARISON_ITEMS:
        warnings.append(
            "data.items has %d items; the maximum is %d, so the extras will be dropped."
            % (count, MAX_COMPARISON_ITEMS)
        )
        count = MAX_COMPARISON_ITEMS

    for i, item in enumerate(items):
        if isinstance(item, dict) and "image" in item:
            check_image(item["image"], "data.items[%d].image" % i, warnings)

    if "highlighted_item_index" in data:
        try:
            highlight = int(data["highlighted_item_index"])
        except (TypeError, ValueError):
            highlight = -1
        if highlight >= count:
            warnings.append(
                "data.highlighted_item_index is %d but there are only %d items, "
                "so no item will be highlighted. It is zero based; -1 means none."
                % (highlight, count)
            )

    badge = data.get("badge_position")
    if badge is not None and badge not in VALID_BADGE_POSITIONS and badge != "top-center":
        warnings.append(
            'data.badge_position is "%s"; accepted values are %s.'
            % (badge, ", ".join(VALID_BADGE_POSITIONS))
        )

    rows = data.get("feature_rows")
    rows = rows if isinstance(rows, list) else []
    if not rows:
        warnings.append(
            "data.feature_rows is empty, so the table will import with its items "
            "but no rows to compare them on."
        )
        return

    for i, row in enumerate(rows):
        if not isinstance(row, dict):
            warnings.append("data.feature_rows[%d] is not an object and will become an empty row." % i)
            continue
        cells = row.get("cells")
        cells = cells if isinstance(cells, list) else []
        if len(cells) != count:
            warnings.append(
                "data.feature_rows[%d] has %d cells for %d items. It will be padded or trimmed to match."
                % (i, len(cells), count)
            )
        for j, cell in enumerate(cells):
            if not isinstance(cell, dict):
                continue
            path = "data.feature_rows[%d].cells[%d]" % (i, j)
            state = cell.get("state")
            if state is not None and state not in VALID_STATES:
                warnings.append(
                    '%s.state is "%s"; accepted values are %s.'
                    % (path, state, ", ".join(VALID_STATES))
                )
            if isinstance(cell.get("custom"), dict):
                check_cell(cell["custom"], path + ".custom", warnings)


def check_cell(cell, path, warnings):
    if not isinstance(cell, dict):
        warnings.append("%s is not an object and will become an empty cell." % path)
        return

    if "content_types" in cell:
        types = cell["content_types"]
        types = types if isinstance(types, list) else [types]
        for t in types:
            if not isinstance(t, str) or t not in VALID_CONTENT_TYPES:
                warnings.append(
                    '%s.content_types contains "%s"; accepted values are %s.'
                    % (path, t, ", ".join(VALID_CONTENT_TYPES))
                )

    arrangement = cell.get("arrangement")
    if arrangement is not None and arrangement not in VALID_ARRANGEMENTS:
        warnings.append(
            '%s.arrangement is "%s", which is not one of the accepted arrangements.'
            % (path, arrangement)
        )

    check_alignment(cell, "alignment", path, warnings)

    if "image" in cell:
        check_image(cell["image"], path + ".image", warnings)


def check_alignment(holder, key, path, warnings):
    value = holder.get(key)
    if value is None or value == "":
        return
    normalised = value.strip().lower() if isinstance(value, str) else ""
    if normalised not in VALID_ALIGNMENTS:
        warnings.append(
            '%s.%s is "%s"; accepted values are left, centre, right.' % (path, key, value)
        )


def check_image(image, path, warnings):
    if not isinstance(image, dict):
        return
    try:
        attachment_id = int(image.get("id", 0) or 0)
    except (TypeError, ValueError):
        attachment_id = 0
    if attachment_id:
        warnings.append(
            "%s.id is %d. Attachment ids do not carry between sites, so this will point at "
            "whatever image has that id on the destination. Leave it at 0 and use a url, or "
            "pick the image in the editor after importing." % (path, attachment_id)
        )


def main():
    if len(sys.argv) != 2:
        print("usage: validate.py table.json", file=sys.stderr)
        return 2

    path = sys.argv[1]
    try:
        with open(path, "r", encoding="utf-8-sig") as handle:
            payload = json.load(handle)
    except FileNotFoundError:
        print("No such file: %s" % path, file=sys.stderr)
        return 2
    except json.JSONDecodeError as exc:
        print("That is not valid JSON: %s (line %d, column %d)" % (exc.msg, exc.lineno, exc.colno))
        return 1

    errors, warnings = validate(payload)

    for error in errors:
        print("ERROR   %s" % error)
    for warning in warnings:
        print("WARN    %s" % warning)

    if errors:
        print("\nThe plugin would refuse this file. %d error(s)." % len(errors))
        return 1

    if warnings:
        print("\nWould import, with %d change(s) made on the way in. Check each is intended." % len(warnings))
    else:
        print("Clean. This file will import as written.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
