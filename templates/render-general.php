<?php
/**
 * General table render template.
 *
 * Receives $settings and $this (KDNA_Tables_Widget) via include() from
 * the widget's render() method.
 *
 * @package KDNA_Tables
 *
 * @var array               $settings
 * @var KDNA_Tables_Widget  $this
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$caption           = isset( $settings['caption'] ) ? (string) $settings['caption'] : '';
$first_row_header  = ! empty( $settings['first_row_is_header'] );
$first_col_header  = ! empty( $settings['first_column_is_header'] );
$columns           = isset( $settings['columns'] ) && is_array( $settings['columns'] ) ? $settings['columns'] : array();
$rows              = isset( $settings['rows'] ) && is_array( $settings['rows'] ) ? $settings['rows'] : array();

// Cap columns at 10 per the data model.
$columns      = array_slice( array_values( $columns ), 0, 10 );
$column_count = count( $columns );

if ( 0 === $column_count ) {
	?>
	<div class="kdna-table__placeholder">
		<span class="kdna-table__placeholder-message">
			<?php esc_html_e( 'Add at least one column to render the table.', 'kdna-tables' ); ?>
		</span>
	</div>
	<?php
	return;
}

$rows      = array_values( $rows );
$head_row  = null;
$body_rows = $rows;

if ( $first_row_header && ! empty( $body_rows ) ) {
	$head_row = array_shift( $body_rows );
} else {
	// No row-based header. Fall back to the column labels so the table
	// still has a semantic <thead> when at least one column is named.
	// Each synthesized head cell is a plain text cell that carries the
	// column's label; the per-column alignment still cascades through
	// kdna_resolve_cell_alignment because the column is passed alongside.
	$any_label   = false;
	$synth_cells = array();
	foreach ( $columns as $column ) {
		$label = isset( $column['column_label'] ) ? trim( (string) $column['column_label'] ) : '';
		if ( '' !== $label ) {
			$any_label = true;
		}
		$synth_cells[] = array(
			'cell_type' => 'text',
			'cell_text' => $label,
		);
	}
	if ( $any_label ) {
		$head_row = array( 'cells' => $synth_cells );
	}
}

$sticky = ! empty( $settings['__sticky_first_column'] );

// Card-stack on mobile lays the table out as one card per column,
// with the column heading at the top of each card and that column's
// body cells stacked underneath. Each cell carries a --kdna-card-row
// value the responsive CSS uses to place it in the right grid row.
$body_row_count = count( $body_rows );
$rows_per_card  = $body_row_count + 1; // header cell + body rows

// Row-label injection for the per-column card layout: when the user
// has the first column as the row-label column, each body cell of
// subsequent cards needs to know "which row" it represents so the
// label can be shown alongside the value.
$row_labels = array();
if ( $first_col_header ) {
	foreach ( $body_rows as $body_row ) {
		$row_cells = isset( $body_row['cells'] ) && is_array( $body_row['cells'] ) ? array_values( $body_row['cells'] ) : array();
		$first     = isset( $row_cells[0] ) ? $row_cells[0] : array();
		$row_labels[] = isset( $first['cell_text'] ) ? (string) $first['cell_text'] : '';
	}
}
?>
<?php
/*
 * The caption is rendered by the CALLER, outside this wrapper entirely —
 * the wrapper clips to its own radius and was cutting the first letter
 * off. All that is left here is the id to point aria-labelledby at.
 */
$kdna_caption_id = isset( $settings['__caption_id'] ) ? (string) $settings['__caption_id'] : '';
?>
<?php if ( $sticky ) : ?><div class="kdna-table__scroll"><?php endif; ?>
<table class="kdna-table kdna-table--general"<?php
	// The caption used to be a <caption> inside this table, which is what
	// named it. It is a heading above the table now, so the table points
	// at it instead — same announcement, once.
	if ( ! empty( $kdna_caption_id ) ) {
		printf( ' aria-labelledby="%s"', esc_attr( $kdna_caption_id ) );
	}
?>>

	<colgroup>
		<?php foreach ( $columns as $column ) :
			$width = isset( $column['column_width']['size'] ) ? (float) $column['column_width']['size'] : 0.0;
			?>
			<?php if ( $width > 0 ) : ?>
				<col style="width: <?php echo esc_attr( $width ); ?>%;" />
			<?php else : ?>
				<col />
			<?php endif; ?>
		<?php endforeach; ?>
	</colgroup>

	<?php if ( null !== $head_row ) :
		$head_cells = isset( $head_row['cells'] ) && is_array( $head_row['cells'] ) ? array_values( $head_row['cells'] ) : array();
		?>
		<thead>
			<tr class="kdna-table__row kdna-table__row--head">
				<?php for ( $c = 0; $c < $column_count; $c++ ) :
					$column        = $columns[ $c ];
					$cell          = isset( $head_cells[ $c ] ) ? $head_cells[ $c ] : array();
					// Heading cells follow:
					//   1. per-cell alignment override if set (advanced)
					//   2. per-column header alignment (top L/C/R toolbar)
					//   3. fall back to centre when neither is set
					//
					// The resolved value is emitted only as the custom property
					// --kdna-table-cell-text-align, never as an inline
					// text-align. kdna-tables.css turns the property into the
					// real declaration at zero-ish specificity, which is what
					// lets the responsive modes re-centre every cell without
					// resorting to !important against an inline style.
					$cell_override = isset( $cell['cell_alignment_override'] ) ? (string) $cell['cell_alignment_override'] : 'inherit';
					$col_header    = isset( $column['column_header_alignment'] ) ? (string) $column['column_header_alignment'] : '';
					if ( in_array( $cell_override, array( 'left', 'center', 'right' ), true ) ) {
						$align = $cell_override;
					} elseif ( in_array( $col_header, array( 'left', 'center', 'right' ), true ) ) {
						$align = $col_header;
					} else {
						$align = 'center';
					}
					$modifier      = $this->kdna_cell_modifier_class( $cell );
					$cell_classes  = 'kdna-table__cell kdna-table__cell--header';
					if ( '' !== $modifier ) {
						$cell_classes .= ' ' . $modifier;
					}
					?>
					<th scope="col" class="<?php echo esc_attr( $cell_classes ); ?>" data-column-label="<?php echo esc_attr( isset( $column['column_label'] ) ? $column['column_label'] : '' ); ?>" style="--kdna-table-cell-text-align: <?php echo esc_attr( $align ); ?>; --kdna-card-row: <?php echo (int) ( $c * $rows_per_card + 1 ); ?>;">
						<?php
						$inner = $this->kdna_render_cell_inner( $cell );
						echo '' !== $inner ? $inner : '&nbsp;';
						?>
					</th>
				<?php endfor; ?>
			</tr>
		</thead>
	<?php endif; ?>

	<tbody>
		<?php foreach ( $body_rows as $row_index => $row ) :
			$cells = isset( $row['cells'] ) && is_array( $row['cells'] ) ? array_values( $row['cells'] ) : array();
			?>
			<tr class="kdna-table__row kdna-table__row--body kdna-table__row--<?php echo ( 0 === $row_index % 2 ) ? 'odd' : 'even'; ?>">
				<?php for ( $c = 0; $c < $column_count; $c++ ) :
					$column        = $columns[ $c ];
					$cell          = isset( $cells[ $c ] ) ? $cells[ $c ] : array();
					$align         = $this->kdna_resolve_cell_alignment( $cell, $column );
					$modifier      = $this->kdna_cell_modifier_class( $cell );
					$is_row_header = ( $first_col_header && 0 === $c );
					$tag           = $is_row_header ? 'th' : 'td';

					$cell_classes = 'kdna-table__cell';
					if ( '' !== $modifier ) {
						$cell_classes .= ' ' . $modifier;
					}
					// Every first-column body cell carries --first-col, whether or
					// not the table marks that column as a header. The First Column
					// Style controls hang off this class, so they apply to any
					// general table rather than silently doing nothing when the
					// "first column is header" flag happens to be off.
					if ( 0 === $c ) {
						$cell_classes .= ' kdna-table__cell--first-col';
					}
					if ( $is_row_header ) {
						$cell_classes .= ' kdna-table__cell--row-header';
					}
					?>
					<?php
					$grid_row_for_cell = $c * $rows_per_card + $row_index + 2;
					$row_label_for_card = isset( $row_labels[ $row_index ] ) ? (string) $row_labels[ $row_index ] : '';
					?>
					<<?php echo $tag; ?><?php echo $is_row_header ? ' scope="row"' : ''; ?> class="<?php echo esc_attr( $cell_classes ); ?>" data-column-label="<?php echo esc_attr( isset( $column['column_label'] ) ? $column['column_label'] : '' ); ?>"<?php if ( $first_col_header && '' !== $row_label_for_card ) : ?> data-row-label="<?php echo esc_attr( $row_label_for_card ); ?>"<?php endif; ?> style="--kdna-table-cell-text-align: <?php echo esc_attr( $align ); ?>; --kdna-card-row: <?php echo (int) $grid_row_for_cell; ?>;">
						<?php echo $this->kdna_render_cell_inner( $cell ); ?>
					</<?php echo $tag; ?>>
				<?php endfor; ?>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
<?php if ( $sticky ) : ?></div><?php endif; ?>
