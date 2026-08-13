<?php
/**
 * Bridges the kdna_table CPT data into the legacy v1.x $settings array
 * shape that templates/render-general.php and templates/render-comparison.php
 * already consume. The render templates do not know about the CPT at all,
 * which is the entire point: every Style control keeps working untouched.
 *
 * @package KDNA_Tables
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KDNA_Tables_Data {

	/**
	 * Build the flat $settings array a render template expects.
	 *
	 * Returns an empty array when the table does not exist or is not
	 * published, so the caller can fall back to the placeholder template.
	 *
	 * @param int   $table_id        Selected kdna_table post ID.
	 * @param array $widget_settings Display settings from the Elementor widget.
	 * @return array
	 */
	public static function get_settings_for_render( $table_id, $widget_settings ) {
		$table_id = (int) $table_id;
		if ( $table_id <= 0 ) {
			return array();
		}

		$post = get_post( $table_id );
		if ( ! $post || KDNA_Tables_CPT::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return array();
		}

		$type = KDNA_Tables_CPT::get_type( $table_id );
		if ( 'general' !== $type && 'comparison' !== $type ) {
			return array();
		}

		$caption = (string) get_post_meta( $table_id, KDNA_Tables_CPT::META_CAPTION, true );
		$ws      = is_array( $widget_settings ) ? $widget_settings : array();

		if ( 'general' === $type ) {
			$data     = get_post_meta( $table_id, KDNA_Tables_CPT::META_GENERAL, true );
			$mapped   = self::map_general( is_array( $data ) ? $data : array() );
			$settings = array_merge(
				array(
					'table_type' => 'general',
					'caption'    => $caption,
				),
				$mapped
			);
		} else {
			$data     = get_post_meta( $table_id, KDNA_Tables_CPT::META_COMPARISON, true );
			$mapped   = self::map_comparison( is_array( $data ) ? $data : array() );
			$settings = array_merge(
				array(
					'table_type' => 'comparison',
					'caption'    => $caption,
				),
				$mapped
			);
		}

		return self::merge_widget_display_settings( $settings, $ws );
	}

	/**
	 * Map the CPT general data structure into the legacy shape consumed by
	 * templates/render-general.php.
	 */
	private static function map_general( array $data ) {
		$first_row_header = ! empty( $data['first_row_is_header'] );
		$first_col_header = ! empty( $data['first_column_is_header'] );

		$columns = array();
		if ( ! empty( $data['columns'] ) && is_array( $data['columns'] ) ) {
			foreach ( $data['columns'] as $col ) {
				if ( ! is_array( $col ) ) {
					continue;
				}
				$width      = isset( $col['width'] ) ? (float) $col['width'] : 0.0;
				$width_unit = ( isset( $col['width_unit'] ) && 'px' === $col['width_unit'] ) ? 'px' : '%';
				// render-general.php hardcodes '%' as the CSS unit. Until that
				// template is updated to honour { unit }, emit size=0 for px
				// so px-width columns gracefully fall back to auto-sized
				// rather than being misrendered as percent.
				$emit_size = ( '%' === $width_unit ) ? $width : 0.0;
				// Header alignment is optional, empty in meta means "use the
				// table default" which render-general.php resolves to centre.
				$header_align_raw = isset( $col['header_alignment'] ) ? (string) $col['header_alignment'] : '';
				$columns[] = array(
					'_id'                     => isset( $col['id'] ) ? (string) $col['id'] : '',
					'column_label'            => isset( $col['label'] ) ? (string) $col['label'] : '',
					'column_alignment'        => self::legacy_alignment( $col['alignment'] ?? 'left', 'left' ),
					'column_header_alignment' => '' === $header_align_raw ? '' : self::legacy_alignment( $header_align_raw, 'center' ),
					'column_width'            => array(
						'size' => $emit_size,
						'unit' => $width_unit,
					),
				);
			}
		}

		$rows = array();
		if ( ! empty( $data['rows'] ) && is_array( $data['rows'] ) ) {
			foreach ( $data['rows'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$cells = array();
				if ( ! empty( $row['cells'] ) && is_array( $row['cells'] ) ) {
					foreach ( $row['cells'] as $cell ) {
						$cells[] = self::map_general_cell( is_array( $cell ) ? $cell : array() );
					}
				}
				$rows[] = array(
					'_id'       => isset( $row['id'] ) ? (string) $row['id'] : '',
					'row_label' => isset( $row['label'] ) ? (string) $row['label'] : '',
					'cells'     => $cells,
				);
			}
		}

		return array(
			'first_row_is_header'    => $first_row_header ? 'yes' : '',
			'first_column_is_header' => $first_col_header ? 'yes' : '',
			'columns'                => $columns,
			'rows'                   => $rows,
		);
	}

	/**
	 * Map one general-table cell from the new content_types structure into
	 * the legacy cell_type/cell_text/cell_icon/cell_image shape.
	 */
	private static function map_general_cell( array $cell ) {
		$content_types = isset( $cell['content_types'] ) && is_array( $cell['content_types'] )
			? array_values( $cell['content_types'] )
			: array( 'text' );
		if ( empty( $content_types ) ) {
			$content_types = array( 'text' );
		}

		$type        = self::resolve_cell_type( $content_types );
		$arrangement = isset( $cell['arrangement'] ) ? (string) $cell['arrangement'] : 'icon-text';
		if ( 'mixed' === $type ) {
			$arrangement = self::resolve_legacy_arrangement( $arrangement, $content_types );
		}

		return array(
			'_id'                     => isset( $cell['id'] ) ? (string) $cell['id'] : '',
			'cell_type'               => $type,
			'cell_text'               => isset( $cell['text'] ) ? (string) $cell['text'] : '',
			'cell_icon'               => self::cpt_icon_to_legacy( $cell['icon'] ?? array() ),
			'cell_image'              => self::cpt_image_to_legacy( $cell['image'] ?? array() ),
			'cell_image_size'         => 'full',
			'cell_arrangement'        => $arrangement,
			'cell_alignment_override' => self::legacy_alignment_override( $cell['alignment'] ?? '' ),
		);
	}

	/**
	 * Map the CPT comparison data structure into the legacy shape consumed
	 * by templates/render-comparison.php.
	 */
	private static function map_comparison( array $data ) {
		$items = array();
		if ( ! empty( $data['items'] ) && is_array( $data['items'] ) ) {
			foreach ( $data['items'] as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$cta = is_array( $item['cta'] ?? null ) ? $item['cta'] : array();
				$items[] = array(
					'_id'           => isset( $item['id'] ) ? (string) $item['id'] : '',
					'item_image'    => self::cpt_image_to_legacy( $item['image'] ?? array() ),
					'item_image_size' => 'full',
					'item_label'    => isset( $item['label'] ) ? (string) $item['label'] : '',
					'item_sublabel' => isset( $item['sublabel'] ) ? (string) $item['sublabel'] : '',
					'cta_enable'    => ! empty( $cta['enabled'] ) ? 'yes' : '',
					'cta_text'      => isset( $cta['text'] ) ? (string) $cta['text'] : '',
					'cta_url'       => array(
						'url'         => isset( $cta['url'] ) ? (string) $cta['url'] : '',
						'is_external' => '',
						'nofollow'    => '',
					),
				);
			}
		}

		$item_count   = count( $items );
		$highlight_ix = isset( $data['highlighted_item_index'] ) ? (int) $data['highlighted_item_index'] : -1;
		$highlight    = ( $highlight_ix >= 0 && $highlight_ix < $item_count ) ? (string) ( $highlight_ix + 1 ) : '';

		$badge_position = isset( $data['badge_position'] ) ? (string) $data['badge_position'] : 'top-centre';
		if ( ! in_array( $badge_position, array( 'top-left', 'top-centre', 'top-right' ), true ) ) {
			$badge_position = 'top-centre';
		}

		$feature_rows = array();
		if ( ! empty( $data['feature_rows'] ) && is_array( $data['feature_rows'] ) ) {
			foreach ( $data['feature_rows'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$feature_rows[] = self::map_feature_row( $row );
			}
		}

		return array(
			'items'                    => $items,
			'item_count'               => (string) $item_count,
			'highlight_item'           => $highlight,
			'highlight_badge_text'     => isset( $data['badge_text'] ) ? (string) $data['badge_text'] : '',
			'highlight_badge_position' => $badge_position,
			'feature_rows'             => $feature_rows,
		);
	}

	private static function map_feature_row( array $row ) {
		$out = array(
			'_id'                 => isset( $row['id'] ) ? (string) $row['id'] : '',
			'feature_label'       => isset( $row['label'] ) ? (string) $row['label'] : '',
			'feature_description' => isset( $row['description'] ) ? (string) $row['description'] : '',
			'feature_tooltip'     => isset( $row['tooltip'] ) ? (string) $row['tooltip'] : '',
		);

		$cells = isset( $row['cells'] ) && is_array( $row['cells'] ) ? array_values( $row['cells'] ) : array();
		foreach ( $cells as $i => $cell ) {
			if ( ! is_array( $cell ) ) {
				continue;
			}
			$slot     = $i + 1;
			$state    = isset( $cell['state'] ) ? (string) $cell['state'] : 'available';
			$state    = in_array( $state, array( 'available', 'unavailable', 'custom' ), true ) ? $state : 'available';
			$out[ 'cell_' . $slot . '_indicator' ] = $state;

			if ( 'custom' === $state ) {
				$custom        = is_array( $cell['custom'] ?? null ) ? $cell['custom'] : array();
				$content_types = isset( $custom['content_types'] ) && is_array( $custom['content_types'] )
					? array_values( $custom['content_types'] )
					: array( 'text' );
				if ( empty( $content_types ) ) {
					$content_types = array( 'text' );
				}
				$custom_type        = self::resolve_cell_type( $content_types );
				$custom_arrangement = isset( $custom['arrangement'] ) ? (string) $custom['arrangement'] : 'icon-text';
				if ( 'mixed' === $custom_type ) {
					$custom_arrangement = self::resolve_legacy_arrangement( $custom_arrangement, $content_types );
				}

				$out[ 'cell_' . $slot . '_custom_type' ] = $custom_type;
				$out[ 'cell_' . $slot . '_text' ]        = isset( $custom['text'] ) ? (string) $custom['text'] : '';
				$out[ 'cell_' . $slot . '_icon' ]        = self::cpt_icon_to_legacy( $custom['icon'] ?? array() );
				$out[ 'cell_' . $slot . '_image' ]       = self::cpt_image_to_legacy( $custom['image'] ?? array() );
				$out[ 'cell_' . $slot . '_image_size' ]  = 'full';
				$out[ 'cell_' . $slot . '_arrangement' ] = $custom_arrangement;
			}
		}

		return $out;
	}

	/**
	 * Merge widget display settings (the ones still controlled by Elementor:
	 * sticky, responsive, cell indicators, tooltip / CTA style settings)
	 * into the data array. Widget settings always win for display concerns;
	 * data layer only owns content. We never let the data layer overwrite
	 * a display-side setting.
	 */
	private static function merge_widget_display_settings( array $settings, array $ws ) {
		// Selected table id is useful downstream for debugging and analytics.
		$settings['selected_table_id'] = isset( $ws['selected_table_id'] ) ? (int) $ws['selected_table_id'] : 0;

		// Sticky first column.
		$settings['sticky_first_column'] = isset( $ws['sticky_first_column'] ) ? (string) $ws['sticky_first_column'] : '';

		// Responsive controls.
		$pass_through = array(
			// Carried so anything reading $settings sees the same answer
			// the widget's render does. The widget sets 'show_caption'
			// from this; without it here the key is absent downstream.
			'caption_show',
			'responsive_mode',
			'responsive_breakpoint',
			'pivot_label_position',
			'picker_default_items',
			'picker_max_select',
			'picker_label_text',
		);
		foreach ( $pass_through as $key ) {
			if ( array_key_exists( $key, $ws ) ) {
				$settings[ $key ] = $ws[ $key ];
			}
		}

		// Cell indicators and comparison style controls only matter for
		// comparison tables, but we copy them unconditionally so the data
		// layer stays type-agnostic. Render templates ignore the keys they
		// do not need.
		$comparison_passthrough = array(
			'available_icon',
			'unavailable_mode',
			'unavailable_icon',
			'unavailable_text',
			'tooltip_position',
			'cta_icon',
			'cta_icon_position',
			'features_heading_text',
		);
		foreach ( $comparison_passthrough as $key ) {
			if ( array_key_exists( $key, $ws ) ) {
				$settings[ $key ] = $ws[ $key ];
			}
		}

		return $settings;
	}

	/*
	 * --------------------------------------------------------------------
	 * Helpers
	 * --------------------------------------------------------------------
	 */

	private static function resolve_cell_type( array $content_types ) {
		$has_text  = in_array( 'text', $content_types, true );
		$has_icon  = in_array( 'icon', $content_types, true );
		$has_image = in_array( 'image', $content_types, true );
		$count     = (int) $has_text + (int) $has_icon + (int) $has_image;

		if ( $count >= 2 ) {
			return 'mixed';
		}
		if ( $has_icon ) {
			return 'icon';
		}
		if ( $has_image ) {
			return 'image';
		}
		return 'text';
	}

	/**
	 * Normalise the arrangement value into one of the four arrangements
	 * the legacy renderer recognises (icon-text, text-icon, icon-text-image,
	 * image-text-icon).
	 *
	 * For new two-piece combos that legacy does not natively express
	 * (text+image, icon+image), we map to a three-piece arrangement and
	 * rely on the legacy piece-renderers returning empty strings for the
	 * absent piece. That way the visible pieces stay in the order the
	 * user picked without touching the locked render template.
	 */
	private static function resolve_legacy_arrangement( $arrangement, array $content_types ) {
		$has_text  = in_array( 'text', $content_types, true );
		$has_icon  = in_array( 'icon', $content_types, true );
		$has_image = in_array( 'image', $content_types, true );
		$count     = (int) $has_text + (int) $has_icon + (int) $has_image;

		// Three pieces.
		if ( $count >= 3 ) {
			// Legacy supports icon-text-image and image-text-icon. Map the
			// six modern three-piece arrangements onto these two by checking
			// the first piece (image-first => image-text-icon, else icon-text-image).
			$first = is_string( $arrangement ) && false !== strpos( $arrangement, '-' )
				? substr( $arrangement, 0, strpos( $arrangement, '-' ) )
				: '';
			return 'image' === $first ? 'image-text-icon' : 'icon-text-image';
		}

		// Two pieces.
		if ( $count === 2 ) {
			$parts = is_string( $arrangement ) ? explode( '-', $arrangement ) : array();
			$first = isset( $parts[0] ) ? $parts[0] : '';

			// text + icon, legacy expresses this natively.
			if ( $has_text && $has_icon && ! $has_image ) {
				return 'text' === $first ? 'text-icon' : 'icon-text';
			}
			// text + image, map onto a three-piece, icon piece will render empty.
			if ( $has_text && $has_image && ! $has_icon ) {
				return 'image' === $first ? 'image-text-icon' : 'icon-text-image';
			}
			// icon + image, same trick.
			if ( $has_icon && $has_image && ! $has_text ) {
				return 'image' === $first ? 'image-text-icon' : 'icon-text-image';
			}
		}

		// Single piece or unknown. The icon-text default is harmless because
		// only the present piece will render.
		return 'icon-text';
	}

	private static function legacy_alignment( $value, $default ) {
		$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';
		if ( 'centre' === $value ) {
			$value = 'center';
		}
		return in_array( $value, array( 'left', 'center', 'right' ), true ) ? $value : $default;
	}

	private static function legacy_alignment_override( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return 'inherit';
		}
		$value = strtolower( trim( $value ) );
		if ( 'centre' === $value ) {
			$value = 'center';
		}
		return in_array( $value, array( 'left', 'center', 'right' ), true ) ? $value : 'inherit';
	}

	private static function cpt_icon_to_legacy( $icon ) {
		if ( ! is_array( $icon ) ) {
			return array( 'value' => '', 'library' => '' );
		}
		return array(
			'value'   => isset( $icon['value'] ) ? (string) $icon['value'] : '',
			'library' => isset( $icon['library'] ) ? (string) $icon['library'] : '',
		);
	}

	private static function cpt_image_to_legacy( $image ) {
		if ( ! is_array( $image ) ) {
			return array( 'id' => 0, 'url' => '', 'alt' => '' );
		}
		return array(
			'id'  => isset( $image['id'] ) ? (int) $image['id'] : 0,
			'url' => isset( $image['url'] ) ? (string) $image['url'] : '',
			'alt' => isset( $image['alt'] ) ? (string) $image['alt'] : '',
		);
	}
}
