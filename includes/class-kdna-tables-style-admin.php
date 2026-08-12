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
	/* Markup for the live preview iframe. */
	const REST_ROUTE_PREVIEW = '/preview/(?P<id>\d+)';
	/* Preset export and import. */
	const REST_ROUTE_EXPORT = '/styles/export';
	const REST_ROUTE_IMPORT = '/styles/import';
	const META_BOX_ID       = 'kdna_table_styles';

	/** How many tables the preview picker lists. */
	const PREVIEW_TABLE_LIMIT = 100;

	/** Iframe widths for the preview device toggle. */
	const PREVIEW_WIDTHS = array(
		'desktop' => 1200,
		'tablet'  => 900,
		'mobile'  => 390,
	);

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
			'exportUrl' => $is_table ? '' : rest_url( self::REST_NAMESPACE . self::REST_ROUTE_EXPORT ),
			'importUrl' => $is_table ? '' : rest_url( self::REST_NAMESPACE . self::REST_ROUTE_IMPORT ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			/*
			 * The preview pane is on the global settings page only. A
			 * per-table panel previewing itself would be the better tool,
			 * but it needs the resolver's table layer in the preview's
			 * variable maths, which is a change to what the pane resolves
			 * rather than to where it is rendered.
			 */
			'preview'   => $is_table ? null : self::preview_data(),
			'strings'   => array(
				'saving'    => __( 'Saving…', 'kdna-tables' ),
				'saved'     => __( 'Saved', 'kdna-tables' ),
				'failed'    => __( 'Could not save', 'kdna-tables' ),
				'unsaved'   => __( 'Unsaved changes', 'kdna-tables' ),
				'discarded' => __( 'Some values were not valid and were discarded.', 'kdna-tables' ),
				'inherit'   => __( 'Inherit', 'kdna-tables' ),
				'default'   => __( 'the plugin default', 'kdna-tables' ),
				'confirm'   => __( 'Drop every style override on this table and follow the global defaults again?', 'kdna-tables' ),
				'resetAll'  => __( 'Reset every global style to the plugin defaults? Tables with their own overrides keep them.', 'kdna-tables' ),
				'resetDone' => __( 'Reset to plugin defaults', 'kdna-tables' ),
				'exported'  => __( 'Preset downloaded', 'kdna-tables' ),
				'exportDirty' => __( 'Save first — the export is of the saved styles, not what is on screen.', 'kdna-tables' ),
				'importing' => __( 'Importing…', 'kdna-tables' ),
				'imported'  => __( 'Imported', 'kdna-tables' ),
				'importFailed' => __( 'Could not import that preset.', 'kdna-tables' ),
				'importConfirm' => __( 'Importing replaces every global style with the preset. Continue?', 'kdna-tables' ),
				'discardedIntro' => __( 'These keys were not imported:', 'kdna-tables' ),
				'loading'   => __( 'Loading preview…', 'kdna-tables' ),
				'noPreview' => __( 'Publish a table to see it previewed here.', 'kdna-tables' ),
				'previewFailed' => __( 'Could not load the preview.', 'kdna-tables' ),
			),
		);
	}

	/* ─── Live preview ──────────────────────────────────────────────── */

	/**
	 * Everything the preview pane needs to boot: what it can show, what
	 * to show first, where to fetch markup, and what to load inside the
	 * iframe.
	 *
	 * Returns null when there is nothing to preview, which the pane reads
	 * as "render the empty state" rather than as an error.
	 */
	private static function preview_data() {
		$tables = self::preview_tables();
		if ( empty( $tables ) ) {
			return null;
		}

		return array(
			'tables'  => $tables,
			// Most recently modified: the one the user was last working on,
			// which is the one they are most likely to be styling for.
			'tableId' => $tables[0]['id'],
			'restUrl' => rest_url( self::REST_NAMESPACE . '/preview/' ),
			'widths'  => self::PREVIEW_WIDTHS,
			/*
			 * The iframe is a document of our own making, so it loads the
			 * front-end stylesheets by URL rather than inheriting the admin's.
			 * Order matters: kdna-shortcode.css declares its private
			 * resolution layer on top of the base rules.
			 */
			'css'     => self::preview_stylesheets(),
			'devices' => array(
				'desktop' => __( 'Desktop', 'kdna-tables' ),
				'tablet'  => __( 'Tablet', 'kdna-tables' ),
				'mobile'  => __( 'Mobile', 'kdna-tables' ),
			),
			'modes'   => array(
				'none'          => __( 'No responsive mode', 'kdna-tables' ),
				'card_stack'    => __( 'Card Stack', 'kdna-tables' ),
				'pivot_rows'    => __( 'Pivot Rows', 'kdna-tables' ),
				'column_picker' => __( 'Column Picker', 'kdna-tables' ),
			),
			'breakpoints' => array(
				'mobile'            => __( 'Mobile only', 'kdna-tables' ),
				'tablet_and_mobile' => __( 'Tablet and mobile', 'kdna-tables' ),
			),
		);
	}

	/**
	 * Published tables the preview can render, most recently modified
	 * first. Whether each carries its own overrides travels with it, so
	 * the pane can say that the front end will look different from what
	 * is being previewed here.
	 */
	private static function preview_tables() {
		$posts = get_posts(
			array(
				'post_type'        => KDNA_Tables_CPT::POST_TYPE,
				'post_status'      => 'publish',
				'numberposts'      => self::PREVIEW_TABLE_LIMIT,
				'orderby'          => 'modified',
				'order'            => 'DESC',
				'suppress_filters' => false,
			)
		);

		$tables = array();
		foreach ( (array) $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$title = trim( (string) $post->post_title );
			$tables[] = array(
				'id'           => (int) $post->ID,
				/* translators: %d: table post id. */
				'title'        => '' === $title ? sprintf( __( 'Table %d', 'kdna-tables' ), (int) $post->ID ) : $title,
				'type'         => (string) KDNA_Tables_CPT::get_type( $post->ID ),
				'hasOverrides' => ! empty( self::stored_overrides( $post->ID ) ),
			);
		}

		return $tables;
	}

	/**
	 * Stylesheet URLs the preview iframe loads.
	 *
	 * Font Awesome comes from Elementor's bundled copy when Elementor is
	 * active, matching what enqueue_font_awesome() does on the front end.
	 * Without it the indicator icons preview as empty boxes, which is also
	 * what the front end would show, so nothing is faked here.
	 */
	private static function preview_stylesheets() {
		$urls = array(
			KDNA_TABLES_URL . 'assets/css/kdna-tables.css',
			KDNA_TABLES_URL . 'assets/css/kdna-comparison.css',
			KDNA_TABLES_URL . 'assets/css/kdna-shortcode.css',
		);

		$font_awesome = defined( 'ELEMENTOR_ASSETS_URL' )
			? ELEMENTOR_ASSETS_URL . 'lib/font-awesome/css/all.min.css'
			: '';

		/** This filter is documented in includes/class-kdna-tables-shortcode.php */
		$font_awesome = (string) apply_filters( 'kdna_tables_font_awesome_url', $font_awesome );
		if ( '' !== $font_awesome ) {
			$urls[] = $font_awesome;
		}

		$versioned = array();
		foreach ( $urls as $url ) {
			$versioned[] = esc_url_raw( add_query_arg( 'ver', KDNA_TABLES_VERSION, $url ) );
		}

		return $versioned;
	}

	/**
	 * Markup for the preview iframe.
	 *
	 * The shortcode renders it, so the preview is the render templates'
	 * own output rather than a second layout that could disagree with the
	 * front end — including the structural pieces the CSS alone cannot
	 * produce, like the scroll container a sticky first column needs.
	 *
	 * The one thing deliberately withheld is the resolved style attribute.
	 * The pane writes the custom properties itself, from the values
	 * currently in the form, and it can only do that reliably if the
	 * wrapper is not already carrying a saved set underneath: an unset
	 * control has to read as absent in the iframe exactly as it does on
	 * the front end, and a leftover attribute would make it read as the
	 * last saved value instead.
	 */
	public static function handle_preview( $request ) {
		$table_id = (int) $request->get_param( 'id' );
		$post     = get_post( $table_id );

		if ( ! $post instanceof WP_Post
			|| KDNA_Tables_CPT::POST_TYPE !== $post->post_type
			|| 'publish' !== $post->post_status ) {
			return new WP_Error(
				'kdna_tables_unknown_table',
				__( 'That table does not exist, or is not published.', 'kdna-tables' ),
				array( 'status' => 404 )
			);
		}

		$strip = static function () {
			return array();
		};
		add_filter( 'kdna_tables_style_properties', $strip, 99 );

		$html = KDNA_Tables_Shortcode::render(
			array(
				'id' => $table_id,
				// Mode and breakpoint are wrapper data attributes, and the
				// pane rewrites them in place rather than re-fetching. Sticky
				// is structural, so it is a render argument.
				'responsive' => 'card_stack',
				'breakpoint' => 'tablet_and_mobile',
				'sticky'     => $request->get_param( 'sticky' ) ? 'yes' : 'no',
			)
		);

		remove_filter( 'kdna_tables_style_properties', $strip, 99 );

		return rest_ensure_response(
			array(
				'id'    => $table_id,
				'type'  => (string) KDNA_Tables_CPT::get_type( $table_id ),
				'html'  => $html,
				'empty' => ( '' === trim( (string) $html ) ),
			)
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

		/*
		 * Preset export and import. Registered before the numeric
		 * per-table route would be reached, though the two cannot collide
		 * anyway: 'export' does not match \d+.
		 */
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE_EXPORT,
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_export' ),
				'permission_callback' => array( __CLASS__, 'permission_check' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE_IMPORT,
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_import' ),
				'permission_callback' => array( __CLASS__, 'permission_check' ),
				'args'                => array(
					'preset' => array(
						'required' => true,
					),
				),
			)
		);

		/*
		 * Preview markup. Read-only, but behind the same permission
		 * callback as the two save routes: it renders a table's content,
		 * including tables that are published but not yet linked from
		 * anywhere, and this is a settings-page facility rather than a
		 * public one.
		 */
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE_PREVIEW,
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_preview' ),
				'permission_callback' => array( __CLASS__, 'permission_check' ),
				'args'                => array(
					'id'     => array(
						'required' => true,
						'type'     => 'integer',
					),
					'sticky' => array(
						'required' => false,
						'type'     => 'boolean',
						'default'  => false,
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

		// Every table can be affected by a change to the globals, so the
		// whole generation moves on. This also drops the per-request memo,
		// which a save in the same request as a render would otherwise
		// leave serving the old values.
		KDNA_Tables_Style_Resolver::invalidate_all();
		self::flush_page_caches();

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

		// One table's overrides cannot change what any other table
		// resolves to, so only this one is invalidated.
		KDNA_Tables_Style_Resolver::invalidate_table( $table_id );
		self::flush_page_caches();

		return rest_ensure_response(
			array(
				'saved'  => true,
				'values' => $clean,
			)
		);
	}

	/* ─── Presets ───────────────────────────────────────────────────── */

	/**
	 * The global defaults as a portable preset.
	 *
	 * Exports what is STORED, not what is on screen. A preset that
	 * silently included unsaved edits would be a preset nobody could
	 * reproduce; the page says so when there are unsaved changes rather
	 * than quietly folding them in.
	 *
	 * The schema version travels with it so an import into a later build
	 * can say which keys it no longer recognises instead of guessing.
	 */
	public static function handle_export( $request ) {
		unset( $request );

		return rest_ensure_response(
			array(
				'kdna_tables_preset' => true,
				'plugin_version'     => KDNA_TABLES_VERSION,
				'exported'           => gmdate( 'c' ),
				'site'               => home_url(),
				'values'             => self::stored_values(),
			)
		);
	}

	/**
	 * Replace the global defaults from a preset.
	 *
	 * Import REPLACES rather than merges. Merging would make the result
	 * depend on what was already there, so importing the same preset onto
	 * two sites could produce two different tables — which is the one
	 * thing a preset exists to prevent.
	 *
	 * Anything the schema does not accept is dropped and REPORTED. A
	 * preset from a newer build, or a hand-edited file, should tell the
	 * user which of their values did not survive rather than appearing to
	 * work and quietly rendering something else.
	 */
	public static function handle_import( $request ) {
		$payload = $request->get_param( 'preset' );

		// Accept the file's whole contents as a string, since that is what
		// a paste or a file read produces.
		if ( is_string( $payload ) ) {
			$decoded = json_decode( $payload, true );
			if ( ! is_array( $decoded ) ) {
				return new WP_Error(
					'kdna_tables_bad_preset',
					__( 'That is not valid JSON.', 'kdna-tables' ),
					array( 'status' => 400 )
				);
			}
			$payload = $decoded;
		}

		if ( ! is_array( $payload ) ) {
			return new WP_Error(
				'kdna_tables_bad_preset',
				__( 'Expected a preset object.', 'kdna-tables' ),
				array( 'status' => 400 )
			);
		}

		// A bare map of control keys is accepted as well as a full export,
		// so a preset can be hand-written without ceremony.
		$values = array_key_exists( 'values', $payload ) ? $payload['values'] : $payload;

		if ( ! is_array( $values ) ) {
			return new WP_Error(
				'kdna_tables_bad_preset',
				__( 'The preset carries no style values.', 'kdna-tables' ),
				array( 'status' => 400 )
			);
		}

		$discarded = array();
		$clean     = self::sanitize_values( $values, $discarded );

		update_option( KDNA_Tables_Style_Resolver::OPTION_KEY, $clean );
		KDNA_Tables_Style_Resolver::invalidate_all();
		self::flush_page_caches();

		return rest_ensure_response(
			array(
				'saved'     => true,
				'imported'  => count( $clean ),
				'offered'   => count( $values ),
				'discarded' => array_values( $discarded ),
				'values'    => $clean,
			)
		);
	}

	/* ─── Page caches ───────────────────────────────────────────────── */

	/**
	 * Ask a page cache to drop what it has, after a style change.
	 *
	 * The resolved variables are written into the markup as an inline
	 * style attribute, so a cached page keeps the old styling until it is
	 * regenerated — the plugin's own transient being fresh does not help
	 * if nothing re-renders. WP Rocket is handled because it is what this
	 * site runs; everything else is left to the filter.
	 */
	public static function flush_page_caches() {
		/**
		 * Filter whether a style save flushes page caches.
		 *
		 * @param bool $enabled Default true.
		 */
		if ( ! apply_filters( 'kdna_tables_flush_page_cache', true ) ) {
			return;
		}

		// Guarded because Rocket may not be installed, may be deactivated,
		// or may have renamed its helpers between versions. A fatal here
		// would take down the save that just succeeded.
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}

		if ( function_exists( 'rocket_clean_minify' ) ) {
			rocket_clean_minify( 'css' );
		}

		/**
		 * Fires after a style save, for other page caches to hook.
		 */
		do_action( 'kdna_tables_styles_changed' );
	}

	/* ─── Sanitising ────────────────────────────────────────────────── */

	/**
	 * Sanitise a whole payload against the schema.
	 *
	 * @param array      $incoming  Raw control key => value.
	 * @param array|null $discarded Filled with a report of what was
	 *                              dropped and why. Import shows this to
	 *                              the user; the ordinary save ignores it,
	 *                              because there a dropped value means the
	 *                              user cleared a control.
	 * @return array Control key => value, ready to store.
	 */
	public static function sanitize_values( array $incoming, &$discarded = null ) {
		$schema    = KDNA_Tables_Style_Schema::get();
		$clean     = array();
		$discarded = array();

		foreach ( $incoming as $key => $value ) {
			// A key with no schema entry is discarded. Schema entries can
			// be removed between versions; stored values for them are not.
			if ( ! isset( $schema[ $key ] ) ) {
				$discarded[] = array(
					'key'    => (string) $key,
					'reason' => __( 'not a known control', 'kdna-tables' ),
				);
				continue;
			}
			$sanitized = self::sanitize_control( $schema[ $key ], $value );
			if ( null === $sanitized ) {
				$discarded[] = array(
					'key'    => (string) $key,
					'label'  => isset( $schema[ $key ]['label'] ) ? $schema[ $key ]['label'] : (string) $key,
					'reason' => __( 'no usable value', 'kdna-tables' ),
				);
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
