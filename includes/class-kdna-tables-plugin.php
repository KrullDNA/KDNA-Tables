<?php
/**
 * Plugin bootstrap. Handles Elementor category and widget registration,
 * plus frontend and editor asset registration.
 *
 * @package KDNA_Tables
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KDNA_Tables_Plugin {

	const FRONTEND_STYLE_HANDLE    = 'kdna-tables';
	const COMPARISON_STYLE_HANDLE  = 'kdna-comparison';
	const RESPONSIVE_STYLE_HANDLE  = 'kdna-responsive';
	const FRONTEND_SCRIPT_HANDLE   = 'kdna-tables';
	const EDITOR_STYLE_HANDLE      = 'kdna-tables-editor';
	const EDITOR_SCRIPT_HANDLE     = 'kdna-tables-editor';
	const CATEGORY_SLUG            = 'kdna-tables';

	public static function load_textdomain() {
		load_plugin_textdomain(
			'kdna-tables',
			false,
			dirname( plugin_basename( KDNA_TABLES_FILE ) ) . '/languages'
		);
	}

	public static function register_category( $elements_manager ) {
		$elements_manager->add_category(
			self::CATEGORY_SLUG,
			array(
				'title' => esc_html__( 'KDNA Tables', 'kdna-tables' ),
				'icon'  => 'eicon-table',
			)
		);
	}

	public static function register_widgets( $widgets_manager ) {
		require_once KDNA_TABLES_PATH . 'includes/class-kdna-tables-widget.php';
		$widgets_manager->register( new KDNA_Tables_Widget() );
	}

	/*
	 * Frontend CSS is registered (not enqueued) so the widget's
	 * get_style_depends() can pull it in only on pages that actually
	 * render a KDNA Table.
	 */
	public static function register_frontend_styles() {
		wp_register_style(
			self::FRONTEND_STYLE_HANDLE,
			KDNA_TABLES_URL . 'assets/css/kdna-tables.css',
			array(),
			KDNA_TABLES_VERSION
		);

		/*
		 * The comparison stylesheet is registered separately so it only
		 * loads when a widget instance returns it from get_style_depends().
		 * Pages with only General tables stay free of comparison CSS.
		 */
		wp_register_style(
			self::COMPARISON_STYLE_HANDLE,
			KDNA_TABLES_URL . 'assets/css/kdna-comparison.css',
			array( self::FRONTEND_STYLE_HANDLE ),
			KDNA_TABLES_VERSION
		);

		wp_register_style(
			self::RESPONSIVE_STYLE_HANDLE,
			KDNA_TABLES_URL . 'assets/css/kdna-responsive.css',
			array( self::FRONTEND_STYLE_HANDLE ),
			KDNA_TABLES_VERSION
		);
	}

	public static function register_frontend_scripts() {
		wp_register_script(
			self::FRONTEND_SCRIPT_HANDLE,
			KDNA_TABLES_URL . 'assets/js/kdna-tables.js',
			array( 'jquery', 'elementor-frontend' ),
			KDNA_TABLES_VERSION,
			true
		);
	}

	public static function enqueue_editor_styles() {
		wp_enqueue_style(
			self::EDITOR_STYLE_HANDLE,
			KDNA_TABLES_URL . 'assets/css/kdna-editor.css',
			array(),
			KDNA_TABLES_VERSION
		);

		// The editor preview iframe also needs the frontend stylesheets so
		// the widget renders with parity inside the Elementor canvas.
		wp_enqueue_style(
			self::FRONTEND_STYLE_HANDLE,
			KDNA_TABLES_URL . 'assets/css/kdna-tables.css',
			array(),
			KDNA_TABLES_VERSION
		);

		wp_enqueue_style(
			self::COMPARISON_STYLE_HANDLE,
			KDNA_TABLES_URL . 'assets/css/kdna-comparison.css',
			array( self::FRONTEND_STYLE_HANDLE ),
			KDNA_TABLES_VERSION
		);

		wp_enqueue_style(
			self::RESPONSIVE_STYLE_HANDLE,
			KDNA_TABLES_URL . 'assets/css/kdna-responsive.css',
			array( self::FRONTEND_STYLE_HANDLE ),
			KDNA_TABLES_VERSION
		);
	}

	public static function enqueue_editor_scripts() {
		wp_enqueue_script(
			self::EDITOR_SCRIPT_HANDLE,
			KDNA_TABLES_URL . 'assets/js/kdna-editor.js',
			array( 'jquery', 'elementor-editor' ),
			KDNA_TABLES_VERSION,
			true
		);
	}
}
