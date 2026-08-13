<?php
/**
 * Comparison table render template.
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

$items = isset( $settings['items'] ) && is_array( $settings['items'] ) ? array_values( $settings['items'] ) : array();

// Cap items at 6 per the data model.
$items      = array_slice( $items, 0, 6 );
$item_count = count( $items );

if ( $item_count < 2 ) {
	?>
	<div class="kdna-table__placeholder">
		<span class="kdna-table__placeholder-message">
			<?php esc_html_e( 'Add at least two items to render the comparison table.', 'kdna-tables' ); ?>
		</span>
	</div>
	<?php
	return;
}

$feature_rows = isset( $settings['feature_rows'] ) && is_array( $settings['feature_rows'] ) ? array_values( $settings['feature_rows'] ) : array();

$highlight_raw   = isset( $settings['highlight_item'] ) ? (string) $settings['highlight_item'] : '';
$highlight_index = ( '' !== $highlight_raw ) ? (int) $highlight_raw : 0;
if ( $highlight_index < 1 || $highlight_index > $item_count ) {
	$highlight_index = 0;
}
$has_highlight = ( 0 !== $highlight_index );

$badge_text     = isset( $settings['highlight_badge_text'] ) ? (string) $settings['highlight_badge_text'] : '';
$badge_position = isset( $settings['highlight_badge_position'] ) ? $settings['highlight_badge_position'] : 'top-right';
if ( ! in_array( $badge_position, array( 'top-left', 'top-centre', 'top-right' ), true ) ) {
	$badge_position = 'top-right';
}

$has_cta_row = false;
foreach ( $items as $item ) {
	if ( ! empty( $item['cta_enable'] ) && 'yes' === $item['cta_enable'] ) {
		$has_cta_row = true;
		break;
	}
}

$tooltip_position = isset( $settings['tooltip_position'] ) ? $settings['tooltip_position'] : 'top';
if ( ! in_array( $tooltip_position, array( 'top', 'bottom', 'auto' ), true ) ) {
	$tooltip_position = 'top';
}

$cta_icon          = isset( $settings['cta_icon'] ) ? $settings['cta_icon'] : null;
$cta_icon_position = isset( $settings['cta_icon_position'] ) ? $settings['cta_icon_position'] : 'after';
$cta_has_icon      = ( ! empty( $cta_icon ) && ( ! empty( $cta_icon['value'] ) || ! empty( $cta_icon['library'] ) ) );
if ( ! in_array( $cta_icon_position, array( 'before', 'after' ), true ) ) {
	$cta_icon_position = 'after';
}

$table_classes = array( 'kdna-comparison' );
if ( $has_highlight ) {
	$table_classes[] = 'kdna-comparison--has-highlight';
}

$feature_count = count( $feature_rows );
$table_style   = sprintf( '--kdna-items-count: %d; --kdna-feature-count: %d;', (int) $item_count, (int) $feature_count );
$sticky        = ! empty( $settings['__sticky_first_column'] );
?>
<?php
/*
 * A comparison table collected a caption in the editor, saved it, and
 * dropped it here — this template never rendered one. Same partial as
 * the general table now, so the two behave alike.
 */
include KDNA_TABLES_PATH . 'templates/render-caption.php';
?>
<?php if ( $sticky ) : ?><div class="kdna-table__scroll"><?php endif; ?>
<table class="<?php echo esc_attr( implode( ' ', $table_classes ) ); ?>" data-item-count="<?php echo (int) $item_count; ?>" style="<?php echo esc_attr( $table_style ); ?>"<?php
	if ( ! empty( $kdna_caption_id ) ) {
		printf( ' aria-labelledby="%s"', esc_attr( $kdna_caption_id ) );
	}
?>>
	<colgroup>
		<col class="kdna-comparison__col kdna-comparison__col--label" />
		<?php for ( $col_index = 1; $col_index <= $item_count; $col_index++ ) : ?>
			<col class="kdna-comparison__col kdna-comparison__col--item kdna-comparison__col--item-<?php echo (int) $col_index; ?>" />
		<?php endfor; ?>
	</colgroup>
	<thead>
		<tr class="kdna-comparison__row kdna-comparison__row--head">
			<?php
			$features_heading = isset( $settings['features_heading_text'] ) ? trim( (string) $settings['features_heading_text'] ) : '';
			?>
			<th class="kdna-comparison__cell kdna-comparison__cell--label kdna-comparison__cell--head-label" scope="col">
				<?php if ( '' !== $features_heading ) : ?>
					<span class="kdna-comparison__features-heading"><?php echo esc_html( $features_heading ); ?></span>
				<?php else : ?>
					<span class="kdna-table__sr-only"><?php esc_html_e( 'Feature', 'kdna-tables' ); ?></span>
				<?php endif; ?>
			</th>
			<?php
			foreach ( $items as $i => $item ) :
				$slot = $i + 1;
				$is_highlighted = ( $slot === $highlight_index );

				$cell_classes = array(
					'kdna-comparison__cell',
					'kdna-comparison__cell--item',
					'kdna-comparison__cell--item-' . $slot,
				);
				if ( $is_highlighted ) {
					$cell_classes[] = 'kdna-comparison__cell--highlighted';
				}

				$item_classes = array(
					'kdna-comparison__item',
					'kdna-comparison__item--' . $slot,
				);
				if ( $is_highlighted ) {
					$item_classes[] = 'kdna-comparison__item--highlighted';
				}

				$has_image = ( ! empty( $item['item_image']['id'] ) || ! empty( $item['item_image']['url'] ) );
				$image_html = '';
				if ( $has_image ) {
					// Through the trait rather than the namespaced class directly:
					// the shortcode binds these templates to a plain renderer,
					// which has no page builder to call.
					$image_html = $this->kdna_render_attachment_image( $item, 'item_image_size', 'item_image' );
				}
				?>
				<th class="<?php echo esc_attr( implode( ' ', $cell_classes ) ); ?>" scope="col" data-slot="<?php echo (int) $slot; ?>" data-item-label="<?php echo esc_attr( isset( $item['item_label'] ) ? $item['item_label'] : '' ); ?>" style="--kdna-card-row: <?php echo (int) ( ( $slot - 1 ) * ( $feature_count + 1 ) + 1 ); ?>;">
					<div class="<?php echo esc_attr( implode( ' ', $item_classes ) ); ?>">
						<?php if ( $is_highlighted && '' !== $badge_text ) : ?>
							<span class="kdna-comparison__badge kdna-comparison__badge--<?php echo esc_attr( $badge_position ); ?>" aria-label="<?php echo esc_attr( $badge_text ); ?>">
								<?php echo esc_html( $badge_text ); ?>
							</span>
						<?php endif; ?>

						<?php if ( '' !== $image_html ) : ?>
							<span class="kdna-comparison__item-image"><?php echo $image_html; ?></span>
						<?php endif; ?>

						<?php if ( ! empty( $item['item_label'] ) ) : ?>
							<span class="kdna-comparison__item-label"><?php echo esc_html( $item['item_label'] ); ?></span>
						<?php endif; ?>

						<?php if ( ! empty( $item['item_sublabel'] ) ) : ?>
							<span class="kdna-comparison__item-sublabel"><?php echo esc_html( $item['item_sublabel'] ); ?></span>
						<?php endif; ?>
					</div>
				</th>
			<?php endforeach; ?>
		</tr>
	</thead>

	<tbody>
		<?php foreach ( $feature_rows as $row_index => $row ) :
			$row_parity = ( 0 === $row_index % 2 ) ? 'odd' : 'even';
			$tooltip    = isset( $row['feature_tooltip'] ) ? trim( (string) $row['feature_tooltip'] ) : '';
			$row_id     = isset( $row['_id'] ) ? sanitize_html_class( (string) $row['_id'] ) : (string) $row_index;
			$tooltip_id = 'kdna-tooltip-' . $row_id;
			?>
			<tr class="kdna-comparison__row kdna-comparison__row--body kdna-comparison__row--<?php echo esc_attr( $row_parity ); ?>">
				<td class="kdna-comparison__cell kdna-comparison__cell--label">
					<?php if ( ! empty( $row['feature_label'] ) ) : ?>
						<span class="kdna-comparison__feature-label"><?php echo esc_html( $row['feature_label'] ); ?></span>
					<?php endif; ?>

					<?php if ( ! empty( $row['feature_description'] ) ) : ?>
						<span class="kdna-comparison__feature-description"><?php echo esc_html( $row['feature_description'] ); ?></span>
					<?php endif; ?>

					<?php if ( '' !== $tooltip ) : ?>
						<span class="kdna-comparison__tooltip-wrap" data-tooltip-position="<?php echo esc_attr( $tooltip_position ); ?>">
							<button
								type="button"
								class="kdna-comparison__tooltip-trigger"
								aria-describedby="<?php echo esc_attr( $tooltip_id ); ?>"
								aria-label="<?php echo esc_attr__( 'More information', 'kdna-tables' ); ?>"
							>
								<i class="kdna-comparison__tooltip-icon eicon-info" aria-hidden="true"></i>
							</button>
							<span id="<?php echo esc_attr( $tooltip_id ); ?>" role="tooltip" class="kdna-comparison__tooltip">
								<?php echo wp_kses_post( $tooltip ); ?>
								<span class="kdna-comparison__tooltip-arrow" aria-hidden="true"></span>
							</span>
						</span>
					<?php endif; ?>
				</td>

				<?php for ( $slot = 1; $slot <= $item_count; $slot++ ) :
					$is_highlighted = ( $slot === $highlight_index );
					$indicator      = isset( $row[ 'cell_' . $slot . '_indicator' ] ) ? $row[ 'cell_' . $slot . '_indicator' ] : 'available';

					$cell_classes = array(
						'kdna-comparison__cell',
						'kdna-comparison__cell--value',
						'kdna-comparison__cell--item-' . $slot,
						'kdna-comparison__cell--indicator-' . sanitize_html_class( $indicator ),
					);
					if ( $is_highlighted ) {
						$cell_classes[] = 'kdna-comparison__cell--highlighted';
					}

					$item_label_for_cell = isset( $items[ $slot - 1 ]['item_label'] ) ? $items[ $slot - 1 ]['item_label'] : '';
					$grid_row_for_cell   = ( $slot - 1 ) * ( $feature_count + 1 ) + ( $row_index + 1 ) + 1;
					?>
					<td class="<?php echo esc_attr( implode( ' ', $cell_classes ) ); ?>" data-slot="<?php echo (int) $slot; ?>" data-item-label="<?php echo esc_attr( $item_label_for_cell ); ?>" data-feature-label="<?php echo esc_attr( isset( $row['feature_label'] ) ? $row['feature_label'] : '' ); ?>" style="--kdna-card-row: <?php echo (int) $grid_row_for_cell; ?>;">
						<?php echo $this->kdna_render_comparison_value( $row, $slot, $settings ); ?>
					</td>
				<?php endfor; ?>
			</tr>
		<?php endforeach; ?>

		<?php if ( $has_cta_row ) : ?>
			<tr class="kdna-comparison__row kdna-comparison__row--cta">
				<td class="kdna-comparison__cell kdna-comparison__cell--label kdna-comparison__cell--cta-label"></td>
				<?php foreach ( $items as $i => $item ) :
					$slot           = $i + 1;
					$is_highlighted = ( $slot === $highlight_index );

					$cell_classes = array(
						'kdna-comparison__cell',
						'kdna-comparison__cell--cta-cell',
						'kdna-comparison__cell--item-' . $slot,
					);
					if ( $is_highlighted ) {
						$cell_classes[] = 'kdna-comparison__cell--highlighted';
					}

					$cta_enabled = ! empty( $item['cta_enable'] ) && 'yes' === $item['cta_enable'];
					$cta_text    = isset( $item['cta_text'] ) ? (string) $item['cta_text'] : '';
					$cta_url     = isset( $item['cta_url']['url'] ) ? (string) $item['cta_url']['url'] : '';
					$cta_target  = ! empty( $item['cta_url']['is_external'] );
					$cta_nofol   = ! empty( $item['cta_url']['nofollow'] );

					$rel_parts = array();
					if ( $cta_target ) {
						$rel_parts[] = 'noopener';
					}
					if ( $cta_nofol ) {
						$rel_parts[] = 'nofollow';
					}
					$rel_attr    = ! empty( $rel_parts ) ? ' rel="' . esc_attr( implode( ' ', $rel_parts ) ) . '"' : '';
					$target_attr = $cta_target ? ' target="_blank"' : '';
					?>
					<td class="<?php echo esc_attr( implode( ' ', $cell_classes ) ); ?>">
						<?php
						$cta_has_text     = '' !== $cta_text;
						$cta_can_render   = $cta_enabled && '' !== $cta_url && ( $cta_has_text || $cta_has_icon );
						$cta_aria_label   = '';
						if ( $cta_can_render && ! $cta_has_text ) {
							/* translators: %s: item label. */
							$cta_aria_label = sprintf( esc_attr__( 'Learn more about %s', 'kdna-tables' ), isset( $item['item_label'] ) ? $item['item_label'] : '' );
						}
						?>
						<?php if ( $cta_can_render ) : ?>
							<a href="<?php echo esc_url( $cta_url ); ?>" class="kdna-comparison__cta kdna-comparison__cta--icon-<?php echo esc_attr( $cta_icon_position ); ?>"<?php echo $target_attr . $rel_attr; ?><?php if ( '' !== $cta_aria_label ) : ?> aria-label="<?php echo esc_attr( $cta_aria_label ); ?>"<?php endif; ?>>
								<?php if ( $cta_has_icon && 'before' === $cta_icon_position ) : ?>
									<span class="kdna-comparison__cta-icon" aria-hidden="true"><?php echo $this->kdna_render_icon( $cta_icon ); ?></span>
								<?php endif; ?>
								<?php if ( $cta_has_text ) : ?>
									<span class="kdna-comparison__cta-text"><?php echo esc_html( $cta_text ); ?></span>
								<?php endif; ?>
								<?php if ( $cta_has_icon && 'after' === $cta_icon_position ) : ?>
									<span class="kdna-comparison__cta-icon" aria-hidden="true"><?php echo $this->kdna_render_icon( $cta_icon ); ?></span>
								<?php endif; ?>
							</a>
						<?php endif; ?>
					</td>
				<?php endforeach; ?>
			</tr>
		<?php endif; ?>
	</tbody>
</table>
<?php if ( $sticky ) : ?></div><?php endif; ?>
