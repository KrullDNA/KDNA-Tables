<?php
/**
 * Shortcode Styles settings page: submenu registration, asset loading,
 * and the REST route the page saves through.
 *
 * The page renders from KDNA_Tables_Style_Schema and saves back into the
 * single kdna_tables_style_defaults option. One option rather than sixty
 * keeps the options table clean, means the whole set loads in one
 * autoloaded read, and makes the Stage 10 export a single json_encode.
 *
 * ── Sanitising ────────────────────────────────────────────────────────
 *
 * Everything arriving on the REST route is validated against the schema
 * rather than against a hand-written list, so a control added at Stage 7
 * is saveable the moment its schema entry exists. A key with no schema
 * entry is discarded outright; each type has its own sanitiser; and a
 * value that sanitises to nothing is dropped from the stored array
 * rather than written as an empty string.
 *
 * That last point matters beyond tidiness. Absent means inherit
 * everywhere in this system: the resolver skips absent values so the
 * layer beneath shows through, and the stylesheet's var() fallback
 * chains only fall through on a property that was never emitted. An
 * empty string stored here would travel all the way to the front end as
 * an empty custom property and break both.
 *
 * @package KDNA_Tables
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KDNA_Tables_Style_Admin {

	const MENU_SLUG      = 'kdna-tables-styles';
	const SCRIPT_HANDLE  = 'kdna-tables-style-admin';
	const STYLE_HANDLE   = 'kdna-tables-style-admin';
	/* Same handle the table editor uses, so the two screens never load
	 * two copies of Alpine. */
	const ALPINE_HANDLE  = 'kdna-tables-alpine';
	const REST_NAMESPACE = 'kdna-tables/v1';
	const REST_ROUTE     = '/styles';
	/* Per-table overrides. Same sanitiser, same permission callback, one
	 * extra check that the id really is a table this user may edit. */
	const REST_ROUTE_TABLE = '/styles/(?P<id>\d+)';
	const META_BOX_ID      = 'kdna_table_styles';

	/** Hook suffix returned by add_submenu_page, for the asset check. */
	private static $hook_suffix = '';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
	}

	/* ─── Menu ──────────────────────────────────────────────────────── */

	public static function register_menu() {
		self::$hook_suffix = add_submenu_page(
			KDNA_Tables_Admin::MENU_SLUG_LIST,
			__( 'Shortcode Styles', 'kdna-tables' ),
			__( 'Shortcode Styles', 'kdna-tables' ),
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to edit these settings.', 'kdna-tables' ) );
		}

		$sections = KDNA_Tables_Style_Schema::get_sections();
		$grouped  = KDNA_Tables_Style_Schema::get_by_section();
		$devices  = self::device_labels();

		include KDNA_TABLES_PATH . 'templates/admin-style-settings.php';
	}

	/* ─── Per-table panel ───────────────────────────────────────────── */

	/**
	 * The Styles meta box on the table edit screen.
	 *
	 * Gated on manage_options rather than on edit_post, deliberately: the
	 * save route it posts to is the global route's twin and carries the
	 * same capability check, so showing the panel to an editor who could
	 * not save from it would be a worse experience than not showing it.
	 */
	public static function register_meta_box() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		add_meta_box(
			self::META_BOX_ID,
			__( 'Styles', 'kdna-tables' ),
			array( __CLASS__, 'render_overrides_panel' ),
			KDNA_Tables_CPT::POST_TYPE,
			'normal',
			'low'
		);
	}

	public static function render_overrides_panel( $post ) {
		$table_id = $post instanceof WP_Post ? (int) $post->ID : 0;
		$sections = KDNA_Tables_Style_Schema::get_sections();
		$grouped  = KDNA_Tables_Style_Schema::get_by_section();
		$devices  = self::device_labels();

		include KDNA_TABLES_PATH . 'templates/admin-style-overrides.php';
	}

	/**
	 * The overrides stored against one table, always an array.
	 */
	public static function stored_overrides( $table_id ) {
		$values = get_post_meta( (int) $table_id, KDNA_Tables_Style_Resolver::META_KEY, true );
		return is_array( $values ) ? $values : array();
	}

	/* ─── Assets ────────────────────────────────────────────────────── */

	public static function enqueue_assets( $hook ) {
		$table_id = self::edited_table_id( $hook );
		$on_page  = ( '' !== self::$hook_suffix && $hook === self::$hook_suffix );

		if ( ! $on_page && 0 === $table_id ) {
			return;
		}

		wp_enqueue_style(
			self::STYLE_HANDLE,
			KDNA_TABLES_URL . 'assets/css/kdna-style-admin.css',
			array(),
			KDNA_TABLES_VERSION
		);

		/*
		 * Alpine boots the moment its script tag is parsed in the footer,
		 * so the component factory has to be registered before then.
		 * Declaring our script as a dependency of Alpine pins that order,
		 * since WordPress always prints dependencies first. Same pattern
		 * as the table editor.
		 */
		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			KDNA_TABLES_URL . 'assets/js/kdna-style-admin.js',
			array(),
			KDNA_TABLES_VERSION,
			true
		);

		/*
		 * On the table edit screen the table editor has usually
		 * registered this same Alpine handle already, with only its own
		 * script as a dependency — so wp_enqueue_script would not
		 * re-register it and ours could print after Alpine had booted,
		 * too late for the alpine:init listener. Append ourselves to the
		 * existing dependency list instead. (The component is also
		 * exposed on window, which is what makes x-data resolve either
		 * way, but the ordering should be right rather than merely
		 * survivable.)
		 */
		if ( wp_script_is( self::ALPINE_HANDLE, 'registered' ) ) {
			$alpine = wp_scripts()->query( self::ALPINE_HANDLE, 'registered' );
			if ( $alpine && ! in_array( self::SCRIPT_HANDLE, (array) $alpine->deps, true ) ) {
				$alpine->deps[] = self::SCRIPT_HANDLE;
			}
			wp_enqueue_script( self::ALPINE_HANDLE );
		} else {
			wp_enqueue_script(
				self::ALPINE_HANDLE,
				KDNA_TABLES_URL . 'assets/js/alpine.min.js',
				array( self::SCRIPT_HANDLE ),
				'3.15.12',
				true
			);
		}

		wp_add_inline_script(
			self::SCRIPT_HANDLE,
			'window.KDNATablesStyles = ' . wp_json_encode( self::bootstrap_data( $table_id ) ) . ';',
			'before'
		);
	}

	/**
	 * The table being edited on this screen, or 0 when this is not a
	 * table edit screen at all.
	 */
	private static function edited_table_id( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return 0;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return 0;
		}

		$post = get_post();
		if ( ! $post instanceof WP_Post || KDNA_Tables_CPT::POST_TYPE !== $post->post_type ) {
			return 0;
		}

		return (int) $post->ID;
	}

	/**
	 * The seed the page boots from. The schema travels with it because
	 * the JS needs it for the same reason the PHP does: to know each
	 * control's shape before binding a value to it.
	 */
	private static function bootstrap_data( $table_id = 0 ) {
		$table_id = (int) $table_id;
		$is_table = $table_id > 0;

		return array(
			'schema'    => KDNA_Tables_Style_Schema::get(),
			'sections'  => KDNA_Tables_Style_Schema::get_sections(),
			'devices'   => array_keys( self::device_labels() ),
			'context'   => $is_table ? 'table' : 'global',
			'tableId'   => $table_id,
			'values'    => $is_table ? self::stored_overrides( $table_id ) : self::stored_values(),
			/*
			 * What the layer beneath is contributing, so an inherited
			 * control can show the value it is inheriting rather than a
			 * blank. For a table that is the schema defaults merged with
			 * the global option; on the global page it is the schema
			 * defaults alone, which is what its placeholders already show.
			 */
			'inherited' => $is_table ? KDNA_Tables_Style_Resolver::resolve_values( 0 ) : array(),
			'restUrl'   => $is_table
				? rest_url( self::REST_NAMESPACE . '/styles/' . $table_id )
				: rest_url( self::REST_NAMESPACE . self::REST_ROUTE ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'strings'   => array(
				'saving'    => __( 'Saving…', 'kdna-tables' ),
				'saved'     => __( 'Saved', 'kdna-tables' ),
				'failed'    => __( 'Could not save', 'kdna-tables' ),
				'unsaved'   => __( 'Unsaved changes', 'kdna-tables' ),
				'discarded' => __( 'Some values were not valid and were discarded.', 'kdna-tables' ),
				'inherit'   => __( 'Inherit', 'kdna-tables' ),
				'default'   => __( 'the plugin default', 'kdna-tables' ),
				'confirm'   => __( 'Drop every style override on this table and follow the global defaults again?', 'kdna-tables' ),
			),
		);
	}

	/**
	 * Breakpoint key => label, in cascade order.
	 */
	private static function device_labels() {
		return array(
			'desktop' => __( 'Desktop', 'kdna-tables' ),
			'tablet'  => __( 'Tablet', 'kdna-tables' ),
			'mobile'  => __( 'Mobile', 'kdna-tables' ),
		);
	}

	/**
	 * The stored global defaults, always an array.
	 */
	public static function stored_values() {
		$values = get_option( KDNA_Tables_Style_Resolver::OPTION_KEY, array() );
		return is_array( $values ) ? $values : array();
	}

	/* ─── REST ──────────────────────────────────────────────────────── */

	public static function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_save' ),
				'permission_callback' => array( __CLASS__, 'permission_check' ),
				'args'                => array(
					'values' => array(
						'required' => true,
						'type'     => 'object',
					),
				),
			)
		);

		/*
		 * Per-table overrides. Same callback shape, same permission
		 * callback and the same sanitiser as the global route — the only
		 * difference is where the result is stored, and one extra check
		 * that the id is a table this user may edit.
		 */
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE_TABLE,
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_table_save' ),
				'permission_callback' => array( __CLASS__, 'permission_check' ),
				'args'                => array(
					'id'     => array(
						'required' => true,
						'type'     => 'integer',
					),
					'values' => array(
						'required' => true,
						'type'     => 'object',
					),
				),
			)
		);
	}

	/**
	 * manage_options, plus an explicit wp_rest nonce check.
	 *
	 * The REST cookie handler already rejects a request whose X-WP-Nonce
	 * is missing or stale, by refusing to authenticate it — so this is
	 * belt and braces rather than the only guard. It is worth having
	 * because it fails loudly with a 403 naming the nonce, instead of
	 * failing as "you are not allowed", which is a confusing way to
	 * discover that a settings page has been left open past the nonce
	 * lifetime.
	 */
	public static function permission_check( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce ) {
			$nonce = $request->get_param( '_wpnonce' );
		}

		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'kdna_tables_bad_nonce',
				__( 'The security token has expired. Reload the page and try again.', 'kdna-tables' ),
				array( 'status' => 403 )
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'kdna_tables_forbidden',
				__( 'You do not have permission to edit these settings.', 'kdna-tables' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Save the global defaults.
	 *
	 * Returns the values as they were actually stored, not as they
	 * arrived, so the page can re-seed itself from the response and show
	 * the user the result of sanitising rather than their own input.
	 */
	public static function handle_save( $request ) {
		$incoming = $request->get_param( 'values' );
		if ( ! is_array( $incoming ) ) {
			return new WP_Error(
				'kdna_tables_bad_payload',
				__( 'Expected an object of style values.', 'kdna-tables' ),
				array( 'status' => 400 )
			);
		}

		$clean = self::sanitize_values( $incoming );

		update_option( KDNA_Tables_Style_Resolver::OPTION_KEY, $clean );

		// The resolver memoises per request; a save in the same request
		// as a render would otherwise serve the old values.
		KDNA_Tables_Style_Resolver::flush_cache();

		return rest_ensure_response(
			array(
				'saved'  => true,
				'values' => $clean,
			)
		);
	}

	/**
	 * Save one table's overrides.
	 *
	 * Everything about this is the global save but for the storage
	 * target: same payload shape, same sanitiser, same response contract,
	 * so the page can re-seed from what was actually stored either way.
	 * Overrides that sanitise to nothing are deleted rather than stored
	 * empty, because an absent override is exactly what inherit means to
	 * the resolver.
	 */
	public static function handle_table_save( $request ) {
		$table_id = (int) $request->get_param( 'id' );
		$post     = get_post( $table_id );

		if ( ! $post instanceof WP_Post || KDNA_Tables_CPT::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'kdna_tables_unknown_table',
				__( 'That table does not exist.', 'kdna-tables' ),
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'edit_post', $table_id ) ) {
			return new WP_Error(
				'kdna_tables_forbidden',
				__( 'You do not have permission to edit this table.', 'kdna-tables' ),
				array( 'status' => 403 )
			);
		}

		$incoming = $request->get_param( 'values' );
		if ( ! is_array( $incoming ) ) {
			return new WP_Error(
				'kdna_tables_bad_payload',
				__( 'Expected an object of style values.', 'kdna-tables' ),
				array( 'status' => 400 )
			);
		}

		$clean = self::sanitize_values( $incoming );

		if ( empty( $clean ) ) {
			delete_post_meta( $table_id, KDNA_Tables_Style_Resolver::META_KEY );
		} else {
			update_post_meta( $table_id, KDNA_Tables_Style_Resolver::META_KEY, $clean );
		}

		KDNA_Tables_Style_Resolver::flush_cache();

		return rest_ensure_response(
			array(
				'saved'  => true,
				'values' => $clean,
			)
		);
	}

	/* ─── Sanitising ────────────────────────────────────────────────── */

	/**
	 * Sanitise a whole payload against the schema.
	 *
	 * @param array $incoming Raw control key => value.
	 * @return array Control key => value, ready to store.
	 */
	public static function sanitize_values( array $incoming ) {
		$schema = KDNA_Tables_Style_Schema::get();
		$clean  = array();

		foreach ( $incoming as $key => $value ) {
			// A key with no schema entry is discarded. Schema entries can
			// be removed between versions; stored values for them are not.
			if ( ! isset( $schema[ $key ] ) ) {
				continue;
			}
			$sanitized = self::sanitize_control( $schema[ $key ], $value );
			if ( null === $sanitized ) {
				continue;
			}
			$clean[ $key ] = $sanitized;
		}

		return $clean;
	}

	/**
	 * Sanitise one control's value, in whatever shape its definition
	 * calls for. Returns null when nothing survives, which the caller
	 * reads as "do not store this key at all".
	 */
	private static function sanitize_control( array $definition, $value ) {
		$type = isset( $definition['type'] ) ? $definition['type'] : '';

		// Group control: recurse per field.
		if ( KDNA_Tables_Style_Schema::is_group_type( $type ) ) {
			if ( ! is_array( $value ) ) {
				return null;
			}
			$clean = array();
			foreach ( $definition['fields'] as $field_key => $field ) {
				if ( ! array_key_exists( $field_key, $value ) ) {
					continue;
				}
				$field_clean = self::sanitize_control( $field, $value[ $field_key ] );
				if ( null === $field_clean ) {
					continue;
				}
				$clean[ $field_key ] = $field_clean;
			}
			return empty( $clean ) ? null : $clean;
		}

		// Responsive control: recurse per breakpoint, keeping only the
		// three known device keys.
		if ( ! empty( $definition['responsive'] ) ) {
			if ( ! is_array( $value ) ) {
				// A bare value written without a device map is treated as
				// desktop rather than thrown away.
				$value = array( 'desktop' => $value );
			}
			$clean = array();
			foreach ( KDNA_Tables_Style_Schema::DEVICES as $device ) {
				if ( ! array_key_exists( $device, $value ) ) {
					continue;
				}
				$device_clean = self::sanitize_leaf( $definition, $value[ $device ] );
				if ( null === $device_clean ) {
					continue;
				}
				$clean[ $device ] = $device_clean;
			}
			return empty( $clean ) ? null : $clean;
		}

		return self::sanitize_leaf( $definition, $value );
	}

	/**
	 * Sanitise a single value by type.
	 */
	private static function sanitize_leaf( array $definition, $value ) {
		$type = isset( $definition['type'] ) ? $definition['type'] : '';

		switch ( $type ) {
			case 'color':
				return self::sanitize_color( $value );

			case 'dimensions':
				return self::sanitize_dimensions( $definition, $value );

			case 'slider':
				return self::sanitize_slider( $definition, $value );

			case 'number':
				return self::sanitize_number( $definition, $value );

			case 'select':
				return self::sanitize_select( $definition, $value );
		}

		return null;
	}

	/**
	 * A hex colour, or an rgb()/rgba() colour.
	 *
	 * sanitize_hex_color() rejects rgba outright, so the functional
	 * notations are validated component by component here and rebuilt
	 * from the parsed numbers. Rebuilding rather than passing the input
	 * through means nothing unexpected can survive inside the
	 * parentheses.
	 */
	private static function sanitize_color( $value ) {
		if ( ! is_scalar( $value ) ) {
			return null;
		}

		$value = strtolower( trim( (string) $value ) );
		if ( '' === $value ) {
			return null;
		}

		// Keywords that are legitimate colours and cannot be expressed as
		// hex.
		if ( in_array( $value, array( 'transparent', 'currentcolor', 'inherit' ), true ) ) {
			return 'inherit' === $value ? null : $value;
		}

		if ( preg_match( '/^rgba?\(([^)]*)\)$/', $value, $matches ) ) {
			$parts = array_map( 'trim', explode( ',', $matches[1] ) );
			if ( count( $parts ) < 3 || count( $parts ) > 4 ) {
				return null;
			}
			$rgb = array();
			for ( $i = 0; $i < 3; $i++ ) {
				if ( ! is_numeric( $parts[ $i ] ) ) {
					return null;
				}
				$rgb[] = max( 0, min( 255, (int) round( (float) $parts[ $i ] ) ) );
			}
			if ( 3 === count( $parts ) ) {
				return sprintf( 'rgb(%d, %d, %d)', $rgb[0], $rgb[1], $rgb[2] );
			}
			if ( ! is_numeric( $parts[3] ) ) {
				return null;
			}
			$alpha = max( 0, min( 1, (float) $parts[3] ) );
			$alpha = rtrim( rtrim( number_format( $alpha, 3, '.', '' ), '0' ), '.' );
			return sprintf( 'rgba(%d, %d, %d, %s)', $rgb[0], $rgb[1], $rgb[2], '' === $alpha ? '0' : $alpha );
		}

		$hex = sanitize_hex_color( $value );
		return ( null === $hex || '' === $hex ) ? null : $hex;
	}

	/**
	 * Four numeric sides plus a unit from the schema's list. The link
	 * toggle is UI state, kept so it round-trips, and ignored by
	 * everything downstream.
	 */
	private static function sanitize_dimensions( array $definition, $value ) {
		if ( ! is_array( $value ) ) {
			return null;
		}

		$clean = array();
		$any   = false;

		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			$side_value = isset( $value[ $side ] ) ? $value[ $side ] : '';
			if ( is_string( $side_value ) ) {
				$side_value = trim( $side_value );
			}
			if ( '' === $side_value || null === $side_value || ! is_numeric( $side_value ) ) {
				$clean[ $side ] = '';
				continue;
			}
			$clean[ $side ] = self::to_number( $side_value );
			$any            = true;
		}

		if ( ! $any ) {
			return null;
		}

		$clean['unit'] = self::sanitize_unit( $definition, $value );

		if ( array_key_exists( 'linked', $value ) ) {
			$clean['linked'] = (bool) $value['linked'];
		}

		return $clean;
	}

	/**
	 * A slider's size plus unit. The empty unit is legitimate — a
	 * unitless line height — so only the size may be missing.
	 */
	private static function sanitize_slider( array $definition, $value ) {
		if ( is_numeric( $value ) ) {
			$value = array( 'size' => $value );
		}
		if ( ! is_array( $value ) || ! isset( $value['size'] ) ) {
			return null;
		}

		$size = $value['size'];
		if ( is_string( $size ) ) {
			$size = trim( $size );
		}
		if ( '' === $size || null === $size || ! is_numeric( $size ) ) {
			return null;
		}

		$size = self::clamp( self::to_number( $size ), $definition );

		return array(
			'size' => $size,
			'unit' => self::sanitize_unit( $definition, $value ),
		);
	}

	private static function sanitize_number( array $definition, $value ) {
		if ( is_string( $value ) ) {
			$value = trim( $value );
		}
		if ( '' === $value || null === $value || ! is_numeric( $value ) ) {
			return null;
		}
		return self::clamp( self::to_number( $value ), $definition );
	}

	/**
	 * A select value has to be one of the option keys. The empty key,
	 * where a schema entry offers one, means inherit and is stored as
	 * nothing at all.
	 */
	private static function sanitize_select( array $definition, $value ) {
		if ( ! is_scalar( $value ) ) {
			return null;
		}
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}

		$options = isset( $definition['options'] ) && is_array( $definition['options'] )
			? $definition['options']
			: array();

		// A select flagged free_text is an open field with suggestions,
		// not an allow-list — the typography font family, where a site's
		// own Elementor faces can be typed in by name. It still goes
		// through text sanitising and the resolver's own output check.
		if ( ! empty( $definition['free_text'] ) ) {
			$value = sanitize_text_field( $value );
			// 'inherit' is offered in the suggestions as the way to clear
			// the field. Storing it would be storing a word that means
			// nothing, so it is treated as unset — which is what the
			// resolver would do with it anyway, one layer later.
			if ( '' === $value || 'inherit' === strtolower( $value ) ) {
				return null;
			}
			return $value;
		}

		return array_key_exists( $value, $options ) ? $value : null;
	}

	/**
	 * A unit from the schema's list, falling back to the first one. An
	 * empty unit is only allowed when the schema lists it.
	 */
	private static function sanitize_unit( array $definition, $value ) {
		$units = isset( $definition['units'] ) && is_array( $definition['units'] )
			? $definition['units']
			: array();

		if ( empty( $units ) ) {
			return '';
		}

		$unit = isset( $value['unit'] ) ? (string) $value['unit'] : null;

		return ( null !== $unit && in_array( $unit, $units, true ) ) ? $unit : (string) $units[0];
	}

	private static function clamp( $number, array $definition ) {
		if ( isset( $definition['min'] ) && $number < $definition['min'] ) {
			$number = $definition['min'];
		}
		if ( isset( $definition['max'] ) && $number > $definition['max'] ) {
			$number = $definition['max'];
		}
		return self::to_number( $number );
	}

	/**
	 * Keep integers as integers so the stored option stays readable and
	 * exports cleanly.
	 */
	private static function to_number( $value ) {
		$float = (float) $value;
		return ( (float) (int) $float === $float ) ? (int) $float : $float;
	}
}
