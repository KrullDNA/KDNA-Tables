<?php
/**
 * [kdna_table] shortcode for non-Elementor contexts: the classic editor,
 * theme templates, the Gutenberg shortcode block, JetEngine meta fields,
 * Elementor popups, anywhere.
 *
 * Attributes:
 *   id          int     Required. The table to render.
 *   responsive  string  none | card_stack | pivot_rows | column_picker.
 *                       Default card_stack.
 *   breakpoint  string  mobile | tablet_and_mobile. Default mobile.
 *   sticky      string  yes | no. Default no.
 *   style_id    int     Optional. Borrow another table's style overrides
 *                       instead of using this table's own.
 *
 * Anything unrecognised falls back to the default rather than failing:
 * a shortcode is hand-typed, and a typo should not blank the table.
 *
 * ── Styling ───────────────────────────────────────────────────────────
 *
 * The resolved custom properties are written as an inline style
 * attribute on the wrapper. The obvious alternative, collecting the
 * tables on a page and printing a style block in wp_head, fails for the
 * case this build exists to serve: a shortcode inside a JetEngine
 * repeater field is invisible to has_shortcode(), which only reads
 * post_content. An inline attribute works wherever the shortcode lands,
 * cannot arrive after the markup it styles, and needs no page scanning.
 *
 * @package KDNA_Tables
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KDNA_Tables_Shortcode {

	/** Plugin behaviour settings, separate from the style values. */
	const SETTINGS_OPTION = 'kdna_tables_settings';

	/** Shortcode stylesheet handle. */
	const SHORTCODE_STYLE_HANDLE = 'kdna-shortcode';

	/** Font Awesome handle used when Elementor is not supplying its own. */
	const FONT_AWESOME_HANDLE = 'kdna-tables-font-awesome';

	const VALID_RESPONSIVE_MODES = array( 'none', 'card_stack', 'pivot_rows', 'column_picker' );
	const VALID_BREAKPOINTS      = array( 'mobile', 'tablet_and_mobile' );

	private static $assets_loaded = false;

	public static function init() {
		add_shortcode( 'kdna_table', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	/* ─── Asset registration ────────────────────────────────────────── */

	/**
	 * Register the shortcode assets, and enqueue the stylesheet in the
	 * header when we can tell it will be needed.
	 *
	 * The header enqueue happens when either the post content contains
	 * the shortcode, or the always_load_shortcode_css setting is on,
	 * which it is by default.
	 *
	 * That default exists because has_shortcode() only ever sees
	 * post_content. A shortcode inside a JetEngine meta field, an ACF
	 * field, a page-builder template, a widget or a term description is
	 * invisible to it — and those are the contexts this build is for. The
	 * fallback when neither condition matches is a late enqueue at render
	 * time, which WordPress prints in the footer: the table still ends up
	 * styled, but the browser paints it unstyled first. Loading one small
	 * stylesheet on pages that turn out not to need it is the cheaper
	 * side of that trade, so it is the default. Sites that place every
	 * shortcode in post content can turn it off and lose nothing.
	 */
	public static function register_assets() {
		self::register_style( KDNA_Tables_Plugin::FRONTEND_STYLE_HANDLE, 'kdna-tables.css' );
		self::register_style( KDNA_Tables_Plugin::COMPARISON_STYLE_HANDLE, 'kdna-comparison.css', array( KDNA_Tables_Plugin::FRONTEND_STYLE_HANDLE ) );
		self::register_style( self::SHORTCODE_STYLE_HANDLE, 'kdna-shortcode.css', array( KDNA_Tables_Plugin::FRONTEND_STYLE_HANDLE ) );

		if ( self::always_load_css() || self::post_has_shortcode() ) {
			wp_enqueue_style( KDNA_Tables_Plugin::FRONTEND_STYLE_HANDLE );
			wp_enqueue_style( self::SHORTCODE_STYLE_HANDLE );
		}
	}

	/**
	 * Whether the shortcode stylesheet loads on every front-end page.
	 * Defaults to on. Stage 4's settings page writes the option; the
	 * filter is for sites that would rather decide per request.
	 */
	private static function always_load_css() {
		$settings = get_option( self::SETTINGS_OPTION, array() );
		$enabled  = true;

		if ( is_array( $settings ) && array_key_exists( 'always_load_shortcode_css', $settings ) ) {
			$enabled = ! empty( $settings['always_load_shortcode_css'] );
		}

		/**
		 * Filter whether the shortcode stylesheet is enqueued in the
		 * header on every page.
		 *
		 * @param bool $enabled Current setting.
		 */
		return (bool) apply_filters( 'kdna_tables_always_load_shortcode_css', $enabled );
	}

	/**
	 * Whether the queried post's content contains the shortcode. Only
	 * ever a reason to load early, never a reason not to — see
	 * register_assets() for why the negative case proves nothing.
	 */
	private static function post_has_shortcode() {
		if ( ! is_singular() ) {
			return false;
		}
		$post = get_post();
		if ( ! $post instanceof WP_Post || '' === $post->post_content ) {
			return false;
		}
		return has_shortcode( $post->post_content, 'kdna_table' );
	}

	/**
	 * Register a plugin stylesheet if some earlier hook has not already.
	 *
	 * KDNA_Tables_Plugin registers the frontend styles on
	 * elementor/frontend/after_register_styles, which never fires on a
	 * request Elementor is not handling — including every request this
	 * shortcode cares about when Elementor is deactivated.
	 */
	private static function register_style( $handle, $file, $deps = array() ) {
		if ( wp_style_is( $handle, 'registered' ) ) {
			return;
		}
		wp_register_style(
			$handle,
			KDNA_TABLES_URL . 'assets/css/' . $file,
			$deps,
			KDNA_TABLES_VERSION
		);
	}

	/* ─── Render ────────────────────────────────────────────────────── */

	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'         => 0,
				'responsive' => 'card_stack',
				'breakpoint' => 'mobile',
				'sticky'     => 'no',
				'style_id'   => 0,
			),
			$atts,
			'kdna_table'
		);

		$table_id = (int) $atts['id'];
		if ( $table_id <= 0 || ! self::is_renderable_table( $table_id ) ) {
			return '';
		}

		$type = KDNA_Tables_CPT::get_type( $table_id );
		if ( 'general' !== $type && 'comparison' !== $type ) {
			return '';
		}

		$responsive_mode = self::one_of( $atts['responsive'], self::VALID_RESPONSIVE_MODES, 'card_stack' );
		$breakpoint      = self::one_of( $atts['breakpoint'], self::VALID_BREAKPOINTS, 'mobile' );
		$sticky          = self::is_yes( $atts['sticky'] );

		// style_id borrows another table's overrides. An id that is not a
		// published table falls back to this table's own styles rather
		// than to the bare globals, which is the least surprising
		// outcome of a typo.
		$style_id = (int) $atts['style_id'];
		if ( $style_id <= 0 || ! self::is_renderable_table( $style_id ) ) {
			$style_id = $table_id;
		}

		// Cell indicator icons use Font Awesome 6 defaults so 'available'
		// and 'unavailable' cells render.
		$display_settings = array(
			'selected_table_id'     => $table_id,
			'sticky_first_column'   => $sticky ? 'yes' : '',
			'responsive_mode'       => $responsive_mode,
			'responsive_breakpoint' => $breakpoint,
			'available_icon'        => array( 'value' => 'fas fa-check',  'library' => 'fa-solid' ),
			'unavailable_mode'      => 'icon',
			'unavailable_icon'      => array( 'value' => 'fas fa-minus', 'library' => 'fa-solid' ),
			'unavailable_text'      => '-',
			'tooltip_position'      => 'top',
		);

		$settings = KDNA_Tables_Data::get_settings_for_render( $table_id, $display_settings );
		if ( empty( $settings ) ) {
			return '';
		}

		// The render templates read this to decide whether to wrap the
		// table in the horizontal scroll container.
		$settings['__sticky_first_column'] = $sticky;

		self::enqueue_assets( $settings['table_type'], $responsive_mode, $sticky );

		$render_instance = self::cell_renderer();

		$wrapper_classes = array(
			'kdna-table__wrapper',
			'kdna-table__wrapper--' . sanitize_html_class( $settings['table_type'] ),
			'kdna-table__wrapper--shortcode',
		);

		$wrapper_attrs = array(
			'class'                      => implode( ' ', $wrapper_classes ),
			'data-table-type'            => $settings['table_type'],
			'data-table-id'              => (string) $table_id,
			'data-responsive-mode'       => $responsive_mode,
			'data-responsive-breakpoint' => $breakpoint,
			'data-sticky-first-column'   => $sticky ? 'yes' : 'no',
		);

		$picker_config = self::picker_config( $settings, $responsive_mode );
		if ( ! empty( $picker_config ) ) {
			$wrapper_attrs['data-picker-config'] = wp_json_encode( $picker_config );
		}

		// The cached entry point: this runs once per shortcode on the page,
		// so a page listing several tables would otherwise pay the full
		// resolve for each of them.
		$style_attribute = KDNA_Tables_Style_Resolver::style_attribute_for( $style_id );
		if ( '' !== $style_attribute ) {
			$wrapper_attrs['style'] = $style_attribute;
		}

		$attr_string = '';
		foreach ( $wrapper_attrs as $name => $value ) {
			$attr_string .= sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( $value ) );
		}

		ob_start();
		echo '<div' . $attr_string . '>';
		// Wrap in a closure so the include scope sees both $settings and
		// $this bound to the cell renderer, which is what the templates
		// call their helpers on.
		$render = function () use ( $settings ) {
			$template_file = 'general' === $settings['table_type']
				? KDNA_TABLES_PATH . 'templates/render-general.php'
				: KDNA_TABLES_PATH . 'templates/render-comparison.php';
			include $template_file;
		};
		$bound = Closure::bind( $render, $render_instance, get_class( $render_instance ) );
		$bound();
		echo '</div>';
		return ob_get_clean();
	}

	/* ─── Attribute validation ──────────────────────────────────────── */

	/**
	 * Return $value when the allow-list contains it, otherwise $default.
	 */
	private static function one_of( $value, array $allowed, $default ) {
		$value = strtolower( trim( (string) $value ) );
		return in_array( $value, $allowed, true ) ? $value : $default;
	}

	/**
	 * Read a truthy shortcode attribute. Hand-typed, so the usual
	 * spellings are all accepted; anything else is a no.
	 */
	private static function is_yes( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return in_array( $value, array( 'yes', 'y', 'true', '1', 'on' ), true );
	}

	/**
	 * Whether an id is a published table this shortcode may render.
	 */
	private static function is_renderable_table( $post_id ) {
		$post = get_post( (int) $post_id );
		return $post instanceof WP_Post
			&& KDNA_Tables_CPT::POST_TYPE === $post->post_type
			&& 'publish' === $post->post_status;
	}

	/**
	 * Picker configuration for the frontend script.
	 *
	 * Comparison tables only: the picker hides and shows item columns by
	 * their data-slot attribute, which only the comparison render emits.
	 * A general table in column_picker mode renders normally, without a
	 * picker.
	 */
	private static function picker_config( array $settings, $responsive_mode ) {
		if ( 'column_picker' !== $responsive_mode ) {
			return array();
		}
		if ( ! isset( $settings['table_type'] ) || 'comparison' !== $settings['table_type'] ) {
			return array();
		}

		$items_raw = isset( $settings['items'] ) && is_array( $settings['items'] )
			? array_slice( array_values( $settings['items'] ), 0, KDNA_Tables_CPT::MAX_COMPARISON_ITEMS )
			: array();

		if ( empty( $items_raw ) ) {
			return array();
		}

		$items = array();
		foreach ( $items_raw as $i => $item ) {
			$label = isset( $item['item_label'] ) ? (string) $item['item_label'] : '';
			if ( '' === $label ) {
				/* translators: %d: item number. */
				$label = sprintf( esc_html__( 'Item %d', 'kdna-tables' ), $i + 1 );
			}
			$items[] = array(
				'slot'  => $i + 1,
				'label' => $label,
			);
		}

		return array(
			'items'     => $items,
			'defaults'  => array( 1, 2 ),
			'maxSelect' => 2,
			'label'     => esc_html__( 'Compare', 'kdna-tables' ),
		);
	}

	/* ─── The object the templates are bound to ─────────────────────── */

	/**
	 * The cell renderer the render templates call their $this-> helpers
	 * on.
	 *
	 * A plain KDNA_Tables_Cell_Renderer, never the widget. The two share
	 * the helpers through KDNA_Tables_Cell_Renderer_Trait, so the output
	 * is identical either way, and using the plain one means this path
	 * touches nothing that needs Elementor: the widget class extends
	 * \Elementor\Widget_Base, so instantiating it — or even including its
	 * file — is a fatal error on a site with Elementor deactivated.
	 *
	 * That used to be guarded by refusing to render at all without
	 * Elementor. Now there is nothing to guard.
	 */
	private static function cell_renderer() {
		static $instance = null;
		if ( null === $instance ) {
			$instance = new KDNA_Tables_Cell_Renderer();
		}
		return $instance;
	}

	/* ─── Render-time enqueue ───────────────────────────────────────── */

	/**
	 * Enqueue what this instance needs. Anything already enqueued in the
	 * header by register_assets() is a no-op here; anything not is a late
	 * enqueue, which WordPress prints in the footer.
	 */
	private static function enqueue_assets( $table_type, $responsive_mode, $sticky ) {
		self::register_style( KDNA_Tables_Plugin::FRONTEND_STYLE_HANDLE, 'kdna-tables.css' );
		self::register_style( self::SHORTCODE_STYLE_HANDLE, 'kdna-shortcode.css', array( KDNA_Tables_Plugin::FRONTEND_STYLE_HANDLE ) );

		wp_enqueue_style( KDNA_Tables_Plugin::FRONTEND_STYLE_HANDLE );
		wp_enqueue_style( self::SHORTCODE_STYLE_HANDLE );

		if ( 'comparison' === $table_type ) {
			self::register_style(
				KDNA_Tables_Plugin::COMPARISON_STYLE_HANDLE,
				'kdna-comparison.css',
				array( KDNA_Tables_Plugin::FRONTEND_STYLE_HANDLE )
			);
			wp_enqueue_style( KDNA_Tables_Plugin::COMPARISON_STYLE_HANDLE );
		}

		self::enqueue_font_awesome();

		// The frontend script drives the column picker chrome and the
		// tooltip touch and keyboard behaviour. Sticky is pure CSS, but
		// it is listed here because a sticky table is also the one most
		// likely to carry tooltips.
		if ( 'column_picker' === $responsive_mode || $sticky ) {
			self::enqueue_frontend_script();
		}

		self::$assets_loaded = true;
	}

	/**
	 * Font Awesome, for the indicator icons.
	 *
	 * Elementor registers its icon stylesheets only when a widget on the
	 * page asks for them, so a shortcode-only post renders empty icon
	 * cells. Prefer Elementor's own handles when they are registered so
	 * the file is shared rather than loaded twice; fall back to
	 * Elementor's bundled copy; and with Elementor deactivated there is
	 * no local copy to use, so a site can point the filter at its own.
	 */
	private static function enqueue_font_awesome() {
		/**
		 * Filter whether Font Awesome is enqueued for shortcode tables.
		 *
		 * @param bool $enabled Default true.
		 */
		if ( ! apply_filters( 'kdna_tables_enqueue_font_awesome', true ) ) {
			return;
		}

		$elementor_handles = array( 'elementor-icons-fa-solid', 'elementor-icons-fa-regular', 'elementor-icons-fa-brands' );
		$found             = false;
		foreach ( $elementor_handles as $handle ) {
			if ( wp_style_is( $handle, 'registered' ) ) {
				wp_enqueue_style( $handle );
				$found = true;
			}
		}
		if ( $found ) {
			return;
		}

		$url = '';
		if ( defined( 'ELEMENTOR_ASSETS_URL' ) ) {
			$url = ELEMENTOR_ASSETS_URL . 'lib/font-awesome/css/all.min.css';
		}

		/**
		 * Filter the Font Awesome stylesheet URL used for shortcode
		 * tables. Empty means "do not load one", which is the default
		 * with Elementor deactivated since the plugin bundles no copy.
		 *
		 * @param string $url Stylesheet URL.
		 */
		$url = (string) apply_filters( 'kdna_tables_font_awesome_url', $url );
		if ( '' === $url ) {
			return;
		}

		if ( ! wp_style_is( self::FONT_AWESOME_HANDLE, 'registered' ) ) {
			wp_register_style( self::FONT_AWESOME_HANDLE, $url, array(), KDNA_TABLES_VERSION );
		}
		wp_enqueue_style( self::FONT_AWESOME_HANDLE );
	}

	/**
	 * Enqueue the frontend script.
	 *
	 * KDNA_Tables_Plugin registers this handle with an elementor-frontend
	 * dependency, on a hook that only fires for Elementor requests. On
	 * anything else the handle is missing, so it is registered here with
	 * jQuery alone — same handle either way, so the file is never printed
	 * twice.
	 */
	private static function enqueue_frontend_script() {
		if ( ! wp_script_is( KDNA_Tables_Plugin::FRONTEND_SCRIPT_HANDLE, 'registered' ) ) {
			wp_register_script(
				KDNA_Tables_Plugin::FRONTEND_SCRIPT_HANDLE,
				KDNA_TABLES_URL . 'assets/js/kdna-tables.js',
				array( 'jquery' ),
				KDNA_TABLES_VERSION,
				true
			);
		}
		wp_enqueue_script( KDNA_Tables_Plugin::FRONTEND_SCRIPT_HANDLE );
	}
}
