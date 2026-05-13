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
	$head_row  = array_shift( $body_rows );
}

$sticky = ! empty( $settings['__sticky_first_column'] );
?>
<?php if ( $sticky ) : ?><div class="kdna-table__scroll"><?php endif; ?>
<table class="kdna-table kdna-table--general">
	<?php if ( '' !== $caption ) : ?>
		<caption class="kdna-table__caption"><?php echo esc_html( $caption ); ?></caption>
	<?php endif; ?>

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
					$align         = $this->kdna_resolve_cell_alignment( $cell, $column );
					$modifier      = $this->kdna_cell_modifier_class( $cell );
					$cell_classes  = 'kdna-table__cell kdna-table__cell--header';
					if ( '' !== $modifier ) {
						$cell_classes .= ' ' . $modifier;
					}
					?>
					<th scope="col" class="<?php echo esc_attr( $cell_classes ); ?>" data-column-label="<?php echo esc_attr( isset( $column['column_label'] ) ? $column['column_label'] : '' ); ?>" style="--kdna-table-cell-text-align: <?php echo esc_attr( $align ); ?>;">
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
					if ( $is_row_header ) {
						$cell_classes .= ' kdna-table__cell--row-header';
					}
					?>
					<<?php echo $tag; ?><?php echo $is_row_header ? ' scope="row"' : ''; ?> class="<?php echo esc_attr( $cell_classes ); ?>" data-column-label="<?php echo esc_attr( isset( $column['column_label'] ) ? $column['column_label'] : '' ); ?>" style="--kdna-table-cell-text-align: <?php echo esc_attr( $align ); ?>;">
						<?php echo $this->kdna_render_cell_inner( $cell ); ?>
					</<?php echo $tag; ?>>
				<?php endfor; ?>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
<?php if ( $sticky ) : ?></div><?php endif; ?>
