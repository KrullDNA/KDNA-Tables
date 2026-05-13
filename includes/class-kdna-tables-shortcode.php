<?php
/**
 * [kdna_table id="123"] shortcode for non-Elementor contexts (classic
 * editor, theme templates, Gutenberg shortcode block). Renders the
 * selected table with default widget display settings, frontend CSS
 * enqueued lazily on first use.
 *
 * @package KDNA_Tables
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KDNA_Tables_Shortcode {

	private static $assets_loaded = false;

	public static function init() {
		add_shortcode( 'kdna_table', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			$atts,
			'kdna_table'
		);

		$table_id = (int) $atts['id'];
		if ( $table_id <= 0 ) {
			return '';
		}
		$post = get_post( $table_id );
		if ( ! $post || KDNA_Tables_CPT::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return '';
		}

		$type = KDNA_Tables_CPT::get_type( $table_id );
		if ( 'general' !== $type && 'comparison' !== $type ) {
			return '';
		}

		// Default widget display settings, no sticky / no responsive / no
		// per-instance style overrides. Cell indicator icons use Font
		// Awesome 6 defaults so 'available' / 'unavailable' cells render.
		$default_display = array(
			'selected_table_id'     => $table_id,
			'sticky_first_column'   => '',
			'responsive_mode'       => 'none',
			'responsive_breakpoint' => 'mobile',
			'available_icon'        => array( 'value' => 'fas fa-check',  'library' => 'fa-solid' ),
			'unavailable_mode'      => 'icon',
			'unavailable_icon'      => array( 'value' => 'fas fa-minus', 'library' => 'fa-solid' ),
			'unavailable_text'      => '-',
			'tooltip_position'      => 'top',
		);

		$settings = KDNA_Tables_Data::get_settings_for_render( $table_id, $default_display );
		if ( empty( $settings ) ) {
			return '';
		}

		self::enqueue_assets( $settings['table_type'] );

		$wrapper_classes = array(
			'kdna-table__wrapper',
			'kdna-table__wrapper--' . sanitize_html_class( $settings['table_type'] ),
			'kdna-table__wrapper--shortcode',
		);
		$wrapper_attrs = array(
			'class'                      => implode( ' ', $wrapper_classes ),
			'data-table-type'            => $settings['table_type'],
			'data-table-id'              => (string) $table_id,
			'data-responsive-mode'       => 'none',
			'data-sticky-first-column'   => 'no',
		);

		// Use the widget class as $this scope inside the render templates,
		// since they call $this->kdna_render_cell_inner() and friends.
		if ( ! class_exists( 'KDNA_Tables_Widget' ) ) {
			$widget_file = KDNA_TABLES_PATH . 'includes/class-kdna-tables-widget.php';
			if ( file_exists( $widget_file ) ) {
				require_once $widget_file;
			}
		}
		if ( ! class_exists( 'KDNA_Tables_Widget' ) ) {
			return '';
		}
		// Wrap in a closure so the include scope sees both $settings and
		// $this bound to a widget instance. The instance has no Elementor
		// editor lifecycle, just the cell-render helpers.
		$render_instance = self::widget_instance();
		if ( ! $render_instance ) {
			return '';
		}

		$attr_string = '';
		foreach ( $wrapper_attrs as $name => $value ) {
			$attr_string .= sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( $value ) );
		}

		ob_start();
		echo '<div' . $attr_string . '>';
		$render = function () use ( $settings ) {
			$template_file = 'general' === $settings['table_type']
				? KDNA_TABLES_PATH . 'templates/render-general.php'
				: KDNA_TABLES_PATH . 'templates/render-comparison.php';
			include $template_file;
		};
		// Bind the render closure to the widget instance so $this resolves
		// correctly inside the template includes.
		$bound = Closure::bind( $render, $render_instance, get_class( $render_instance ) );
		$bound();
		echo '</div>';
		return ob_get_clean();
	}

	private static function widget_instance() {
		static $instance = null;
		if ( null !== $instance ) {
			return $instance;
		}
		// The widget needs a tiny shim because Elementor's Widget_Base
		// constructor expects Elementor's bootstrap to have run. Calling
		// new KDNA_Tables_Widget() inside a non-Elementor request can
		// throw. Detect Elementor's presence; fall back to an anonymous
		// extension that exposes just the public helpers the templates
		// call.
		if ( class_exists( '\\Elementor\\Widget_Base' ) ) {
			try {
				$instance = new KDNA_Tables_Widget();
				return $instance;
			} catch ( Throwable $e ) {
				// fallthrough
			}
		}
		// Fallback: instantiate via reflection without the parent
		// constructor so we can still use the cell render helpers.
		try {
			$ref      = new ReflectionClass( 'KDNA_Tables_Widget' );
			$instance = $ref->newInstanceWithoutConstructor();
			return $instance;
		} catch ( Throwable $e ) {
			$instance = null;
			return null;
		}
	}

	private static function enqueue_assets( $table_type ) {
		if ( self::$assets_loaded ) {
			// Make sure both stylesheet handles are enqueued, even on the
			// first call we only enqueue the ones we know about.
		}
		// Frontend base stylesheet. Registered by KDNA_Tables_Plugin on
		// elementor/frontend/after_register_styles. On non-Elementor
		// requests that hook never fires, so register here on demand.
		if ( ! wp_style_is( KDNA_Tables_Plugin::FRONTEND_STYLE_HANDLE, 'registered' ) ) {
			wp_register_style(
				KDNA_Tables_Plugin::FRONTEND_STYLE_HANDLE,
				KDNA_TABLES_URL . 'assets/css/kdna-tables.css',
				array(),
				KDNA_TABLES_VERSION
			);
		}
		wp_enqueue_style( KDNA_Tables_Plugin::FRONTEND_STYLE_HANDLE );

		if ( 'comparison' === $table_type ) {
			if ( ! wp_style_is( KDNA_Tables_Plugin::COMPARISON_STYLE_HANDLE, 'registered' ) ) {
				wp_register_style(
					KDNA_Tables_Plugin::COMPARISON_STYLE_HANDLE,
					KDNA_TABLES_URL . 'assets/css/kdna-comparison.css',
					array( KDNA_Tables_Plugin::FRONTEND_STYLE_HANDLE ),
					KDNA_TABLES_VERSION
				);
			}
			wp_enqueue_style( KDNA_Tables_Plugin::COMPARISON_STYLE_HANDLE );
		}

		// Front-end JS is widget-only (column picker, tooltip touch). The
		// shortcode renders without responsive features per the brief, so
		// no JS enqueue.

		self::$assets_loaded = true;
	}
}
