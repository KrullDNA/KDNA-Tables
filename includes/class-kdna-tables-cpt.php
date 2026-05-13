<?php
/**
 * Custom post type registration for kdna_table, the table library backing
 * the v2.0.0 widget. Data lives here. The Elementor widget reads from these
 * entries and applies its own styling on top.
 *
 * @package KDNA_Tables
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KDNA_Tables_CPT {

	const POST_TYPE      = 'kdna_table';
	const META_TYPE      = '_kdna_table_type';
	const META_CAPTION   = '_kdna_table_caption';
	const META_GENERAL   = '_kdna_table_general';
	const META_COMPARISON = '_kdna_table_comparison';
	const META_SCHEMA    = '_kdna_table_schema_version';
	const META_CONTENT_HASH = '_kdna_table_content_hash';

	const SCHEMA_VERSION = 2;

	const MAX_GENERAL_COLUMNS    = 10;
	const MAX_COMPARISON_ITEMS   = 6;
	const VALID_ALIGNMENTS       = array( 'left', 'centre', 'center', 'right' );
	const VALID_BADGE_POSITIONS  = array( 'top-left', 'top-centre', 'top-right' );
	const VALID_CONTENT_TYPES    = array( 'text', 'icon', 'image' );
	const VALID_ARRANGEMENTS     = array(
		// Two-piece, every ordered permutation of the three piece types.
		'icon-text',
		'text-icon',
		'image-text',
		'text-image',
		'icon-image',
		'image-icon',
		// Three-piece, every ordered permutation.
		'icon-text-image',
		'image-text-icon',
		'text-icon-image',
		'icon-image-text',
		'text-image-icon',
		'image-icon-text',
	);

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
	}

	public static function register_post_type() {
		$labels = array(
			'name'               => _x( 'KDNA Tables', 'post type general name', 'kdna-tables' ),
			'singular_name'      => _x( 'KDNA Table', 'post type singular name', 'kdna-tables' ),
			'menu_name'          => _x( 'KDNA Tables', 'admin menu', 'kdna-tables' ),
			'name_admin_bar'     => _x( 'KDNA Table', 'add new on admin bar', 'kdna-tables' ),
			'add_new'            => _x( 'Add New', 'kdna_table', 'kdna-tables' ),
			'add_new_item'       => __( 'Add New Table', 'kdna-tables' ),
			'new_item'           => __( 'New Table', 'kdna-tables' ),
			'edit_item'          => __( 'Edit Table', 'kdna-tables' ),
			'view_item'          => __( 'View Table', 'kdna-tables' ),
			'all_items'          => __( 'All Tables', 'kdna-tables' ),
			'search_items'       => __( 'Search Tables', 'kdna-tables' ),
			'not_found'          => __( 'No tables found.', 'kdna-tables' ),
			'not_found_in_trash' => __( 'No tables found in Trash.', 'kdna-tables' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => false,
			'exclude_from_search' => true,
			'hierarchical'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'supports'            => array( 'title' ),
			'menu_icon'           => 'dashicons-grid-view',
		);

		register_post_type( self::POST_TYPE, $args );
	}

	public static function register_meta() {
		$auth = array( __CLASS__, 'meta_auth' );

		register_post_meta(
			self::POST_TYPE,
			self::META_TYPE,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => array( __CLASS__, 'sanitize_type' ),
				'auth_callback'     => $auth,
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META_CAPTION,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $auth,
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META_GENERAL,
			array(
				'type'              => 'array',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => array( __CLASS__, 'sanitize_general_meta' ),
				'auth_callback'     => $auth,
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META_COMPARISON,
			array(
				'type'              => 'array',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => array( __CLASS__, 'sanitize_comparison_meta' ),
				'auth_callback'     => $auth,
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META_SCHEMA,
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'absint',
				'auth_callback'     => $auth,
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META_CONTENT_HASH,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $auth,
			)
		);
	}

	public static function meta_auth( $allowed, $meta_key, $post_id ) {
		return current_user_can( 'edit_post', $post_id );
	}

	public static function sanitize_type( $value ) {
		return in_array( $value, array( 'general', 'comparison' ), true ) ? $value : '';
	}

	public static function sanitize_general_meta( $value ) {
		return self::sanitize_table_data( $value, 'general' );
	}

	public static function sanitize_comparison_meta( $value ) {
		return self::sanitize_table_data( $value, 'comparison' );
	}

	/**
	 * Recursively sanitises a table data structure. The shape depends on $type.
	 * Returns a fully sanitised array, never null. Unknown keys are dropped.
	 *
	 * @param mixed  $value Raw data, typically from $_POST after JSON decode.
	 * @param string $type  'general' or 'comparison'.
	 * @return array
	 */
	public static function sanitize_table_data( $value, $type ) {
		if ( is_string( $value ) ) {
			$decoded = json_decode( wp_unslash( $value ), true );
			$value   = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $value ) ) {
			return array();
		}
		if ( 'general' === $type ) {
			return self::sanitize_general_data( $value );
		}
		if ( 'comparison' === $type ) {
			return self::sanitize_comparison_data( $value );
		}
		return array();
	}

	private static function sanitize_general_data( array $value ) {
		$out = array(
			'first_row_is_header'    => ! empty( $value['first_row_is_header'] ),
			'first_column_is_header' => ! empty( $value['first_column_is_header'] ),
			'columns'                => array(),
			'rows'                   => array(),
		);

		if ( ! empty( $value['columns'] ) && is_array( $value['columns'] ) ) {
			$i = 0;
			foreach ( $value['columns'] as $col ) {
				if ( $i++ >= self::MAX_GENERAL_COLUMNS ) {
					break;
				}
				$out['columns'][] = self::sanitize_general_column( $col );
			}
		}

		$column_count = count( $out['columns'] );

		if ( ! empty( $value['rows'] ) && is_array( $value['rows'] ) ) {
			foreach ( $value['rows'] as $row ) {
				$out['rows'][] = self::sanitize_general_row( $row, $column_count );
			}
		}

		return $out;
	}

	private static function sanitize_general_column( $col ) {
		if ( ! is_array( $col ) ) {
			$col = array();
		}
		$width_unit = isset( $col['width_unit'] ) && in_array( $col['width_unit'], array( '%', 'px' ), true )
			? $col['width_unit']
			: '%';
		return array(
			'id'         => self::sanitize_id( $col['id'] ?? '', 'col' ),
			'label'      => sanitize_text_field( (string) ( $col['label'] ?? '' ) ),
			'alignment'  => self::sanitize_alignment( $col['alignment'] ?? 'left', false ),
			'width'      => max( 0, (float) ( $col['width'] ?? 0 ) ),
			'width_unit' => $width_unit,
		);
	}

	private static function sanitize_general_row( $row, $column_count ) {
		if ( ! is_array( $row ) ) {
			$row = array();
		}
		$cells = array();
		if ( ! empty( $row['cells'] ) && is_array( $row['cells'] ) ) {
			foreach ( $row['cells'] as $cell ) {
				$cells[] = self::sanitize_content_cell( $cell );
			}
		}
		if ( $column_count > 0 ) {
			while ( count( $cells ) < $column_count ) {
				$cells[] = self::sanitize_content_cell( array() );
			}
			$cells = array_slice( $cells, 0, $column_count );
		}
		return array(
			'id'    => self::sanitize_id( $row['id'] ?? '', 'row' ),
			'cells' => $cells,
		);
	}

	private static function sanitize_content_cell( $cell ) {
		if ( ! is_array( $cell ) ) {
			$cell = array();
		}
		$content_types = self::sanitize_content_types( $cell['content_types'] ?? array( 'text' ) );
		return array(
			'id'            => self::sanitize_id( $cell['id'] ?? '', 'cell' ),
			'content_types' => $content_types,
			'text'          => wp_kses_post( (string) ( $cell['text'] ?? '' ) ),
			'icon'          => self::sanitize_icon( $cell['icon'] ?? array() ),
			'image'         => self::sanitize_image( $cell['image'] ?? array() ),
			'arrangement'   => self::sanitize_arrangement( $cell['arrangement'] ?? 'icon-text' ),
			'alignment'     => self::sanitize_alignment( $cell['alignment'] ?? '', true ),
		);
	}

	private static function sanitize_comparison_data( array $value ) {
		$out = array(
			'highlighted_item_index' => isset( $value['highlighted_item_index'] ) ? max( -1, (int) $value['highlighted_item_index'] ) : -1,
			'badge_text'             => sanitize_text_field( (string) ( $value['badge_text'] ?? '' ) ),
			'badge_position'         => self::sanitize_badge_position( $value['badge_position'] ?? 'top-centre' ),
			'items'                  => array(),
			'feature_rows'           => array(),
		);

		if ( ! empty( $value['items'] ) && is_array( $value['items'] ) ) {
			$i = 0;
			foreach ( $value['items'] as $item ) {
				if ( $i++ >= self::MAX_COMPARISON_ITEMS ) {
					break;
				}
				$out['items'][] = self::sanitize_comparison_item( $item );
			}
		}

		$item_count = count( $out['items'] );
		if ( $out['highlighted_item_index'] >= $item_count ) {
			$out['highlighted_item_index'] = -1;
		}

		if ( ! empty( $value['feature_rows'] ) && is_array( $value['feature_rows'] ) ) {
			foreach ( $value['feature_rows'] as $row ) {
				$out['feature_rows'][] = self::sanitize_feature_row( $row, $item_count );
			}
		}

		return $out;
	}

	private static function sanitize_comparison_item( $item ) {
		if ( ! is_array( $item ) ) {
			$item = array();
		}
		$cta = is_array( $item['cta'] ?? null ) ? $item['cta'] : array();
		return array(
			'id'       => self::sanitize_id( $item['id'] ?? '', 'item' ),
			'image'    => self::sanitize_image( $item['image'] ?? array() ),
			'label'    => sanitize_text_field( (string) ( $item['label'] ?? '' ) ),
			'sublabel' => sanitize_text_field( (string) ( $item['sublabel'] ?? '' ) ),
			'cta'      => array(
				'enabled' => ! empty( $cta['enabled'] ),
				'text'    => sanitize_text_field( (string) ( $cta['text'] ?? '' ) ),
				'url'     => esc_url_raw( (string) ( $cta['url'] ?? '' ) ),
			),
		);
	}

	private static function sanitize_feature_row( $row, $item_count ) {
		if ( ! is_array( $row ) ) {
			$row = array();
		}
		$cells = array();
		if ( ! empty( $row['cells'] ) && is_array( $row['cells'] ) ) {
			foreach ( $row['cells'] as $cell ) {
				$cells[] = self::sanitize_comparison_cell( $cell );
			}
		}
		if ( $item_count > 0 ) {
			while ( count( $cells ) < $item_count ) {
				$cells[] = self::sanitize_comparison_cell( array() );
			}
			$cells = array_slice( $cells, 0, $item_count );
		}
		return array(
			'id'          => self::sanitize_id( $row['id'] ?? '', 'fr' ),
			'label'       => sanitize_text_field( (string) ( $row['label'] ?? '' ) ),
			'description' => sanitize_text_field( (string) ( $row['description'] ?? '' ) ),
			'tooltip'     => sanitize_textarea_field( (string) ( $row['tooltip'] ?? '' ) ),
			'cells'       => $cells,
		);
	}

	private static function sanitize_comparison_cell( $cell ) {
		if ( ! is_array( $cell ) ) {
			$cell = array();
		}
		$state  = in_array( $cell['state'] ?? 'available', array( 'available', 'unavailable', 'custom' ), true )
			? $cell['state']
			: 'available';
		$custom = is_array( $cell['custom'] ?? null ) ? $cell['custom'] : array();
		$content_types = self::sanitize_content_types( $custom['content_types'] ?? array( 'text' ) );
		return array(
			'state'  => $state,
			'custom' => array(
				'content_types' => $content_types,
				'text'          => wp_kses_post( (string) ( $custom['text'] ?? '' ) ),
				'icon'          => self::sanitize_icon( $custom['icon'] ?? array() ),
				'image'         => self::sanitize_image( $custom['image'] ?? array() ),
				'arrangement'   => self::sanitize_arrangement( $custom['arrangement'] ?? 'icon-text' ),
			),
		);
	}

	private static function sanitize_content_types( $value ) {
		if ( ! is_array( $value ) ) {
			$value = array( $value );
		}
		$out = array();
		foreach ( $value as $t ) {
			if ( is_string( $t ) && in_array( $t, self::VALID_CONTENT_TYPES, true ) && ! in_array( $t, $out, true ) ) {
				$out[] = $t;
			}
		}
		if ( empty( $out ) ) {
			$out = array( 'text' );
		}
		return $out;
	}

	private static function sanitize_icon( $icon ) {
		if ( ! is_array( $icon ) ) {
			$icon = array();
		}
		return array(
			'value'   => sanitize_text_field( (string) ( $icon['value'] ?? '' ) ),
			'library' => sanitize_text_field( (string) ( $icon['library'] ?? '' ) ),
		);
	}

	private static function sanitize_image( $image ) {
		if ( ! is_array( $image ) ) {
			$image = array();
		}
		return array(
			'id'  => absint( $image['id'] ?? 0 ),
			'url' => esc_url_raw( (string) ( $image['url'] ?? '' ) ),
			'alt' => sanitize_text_field( (string) ( $image['alt'] ?? '' ) ),
		);
	}

	private static function sanitize_alignment( $value, $allow_empty ) {
		$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';
		if ( '' === $value && $allow_empty ) {
			return '';
		}
		// Accept both UK 'centre' and US 'center'; normalise to 'centre'.
		if ( 'center' === $value ) {
			$value = 'centre';
		}
		return in_array( $value, array( 'left', 'centre', 'right' ), true ) ? $value : ( $allow_empty ? '' : 'left' );
	}

	private static function sanitize_arrangement( $value ) {
		$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';
		return in_array( $value, self::VALID_ARRANGEMENTS, true ) ? $value : 'icon-text';
	}

	private static function sanitize_badge_position( $value ) {
		$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';
		if ( 'top-center' === $value ) {
			$value = 'top-centre';
		}
		return in_array( $value, self::VALID_BADGE_POSITIONS, true ) ? $value : 'top-centre';
	}

	private static function sanitize_id( $value, $prefix ) {
		$value = sanitize_key( (string) $value );
		if ( '' === $value ) {
			$value = $prefix . '_' . wp_generate_uuid4();
		}
		return $value;
	}

	/**
	 * Starter data emitted into a freshly created general kdna_table.
	 */
	public static function default_general_data() {
		return self::sanitize_general_data(
			array(
				'first_row_is_header'    => true,
				'first_column_is_header' => false,
				'columns'                => array(
					array(
						'id'         => 'col_1',
						'label'      => __( 'Column 1', 'kdna-tables' ),
						'alignment'  => 'left',
						'width'      => 0,
						'width_unit' => '%',
					),
				),
				'rows'                   => array(
					array(
						'id'    => 'row_1',
						'cells' => array(
							array(
								'id'            => 'cell_1_1',
								'content_types' => array( 'text' ),
								'text'          => '',
							),
						),
					),
				),
			)
		);
	}

	/**
	 * Starter data emitted into a freshly created comparison kdna_table.
	 */
	public static function default_comparison_data() {
		$blank_cells = array(
			array( 'state' => 'available' ),
			array( 'state' => 'available' ),
		);
		return self::sanitize_comparison_data(
			array(
				'highlighted_item_index' => -1,
				'badge_text'             => __( 'Recommended', 'kdna-tables' ),
				'badge_position'         => 'top-centre',
				'items'                  => array(
					array(
						'id'       => 'item_1',
						'label'    => __( 'Plan 1', 'kdna-tables' ),
						'sublabel' => '',
						'cta'      => array( 'enabled' => false, 'text' => __( 'Choose', 'kdna-tables' ), 'url' => '' ),
					),
					array(
						'id'       => 'item_2',
						'label'    => __( 'Plan 2', 'kdna-tables' ),
						'sublabel' => '',
						'cta'      => array( 'enabled' => false, 'text' => __( 'Choose', 'kdna-tables' ), 'url' => '' ),
					),
				),
				'feature_rows'           => array(
					array(
						'id'          => 'fr_1',
						'label'       => __( 'Feature one', 'kdna-tables' ),
						'description' => '',
						'tooltip'     => '',
						'cells'       => $blank_cells,
					),
					array(
						'id'          => 'fr_2',
						'label'       => __( 'Feature two', 'kdna-tables' ),
						'description' => '',
						'tooltip'     => '',
						'cells'       => $blank_cells,
					),
					array(
						'id'          => 'fr_3',
						'label'       => __( 'Feature three', 'kdna-tables' ),
						'description' => '',
						'tooltip'     => '',
						'cells'       => $blank_cells,
					),
				),
			)
		);
	}

	/**
	 * Returns 'general' or 'comparison' for a given post id, or '' if not set
	 * or the post is not a kdna_table.
	 */
	public static function get_type( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return '';
		}
		if ( get_post_type( $post_id ) !== self::POST_TYPE ) {
			return '';
		}
		$type = (string) get_post_meta( $post_id, self::META_TYPE, true );
		return self::sanitize_type( $type );
	}

	/**
	 * Returns the raw data array for the post, keyed by 'type', 'caption',
	 * and either 'general' or 'comparison'.
	 */
	public static function get_data( $post_id ) {
		$post_id = (int) $post_id;
		$type    = self::get_type( $post_id );
		if ( '' === $type ) {
			return array();
		}
		$caption = (string) get_post_meta( $post_id, self::META_CAPTION, true );
		if ( 'general' === $type ) {
			$data = get_post_meta( $post_id, self::META_GENERAL, true );
			$data = is_array( $data ) ? $data : array();
			return array(
				'type'    => 'general',
				'caption' => $caption,
				'general' => $data,
			);
		}
		$data = get_post_meta( $post_id, self::META_COMPARISON, true );
		$data = is_array( $data ) ? $data : array();
		return array(
			'type'        => 'comparison',
			'caption'     => $caption,
			'comparison'  => $data,
		);
	}
}
