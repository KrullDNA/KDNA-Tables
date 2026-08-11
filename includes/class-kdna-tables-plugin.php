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

require_once KDNA_TABLES_PATH . 'includes/class-kdna-tables-cpt.php';
require_once KDNA_TABLES_PATH . 'includes/class-kdna-tables-data.php';
require_once KDNA_TABLES_PATH . 'includes/class-kdna-tables-admin.php';
require_once KDNA_TABLES_PATH . 'includes/class-kdna-tables-editor.php';
require_once KDNA_TABLES_PATH . 'includes/class-kdna-tables-migration.php';
// The cell-render helpers the templates call, shared by the widget and
// the shortcode. Extends nothing, so it loads with or without Elementor.
require_once KDNA_TABLES_PATH . 'includes/class-kdna-tables-cell-renderer.php';
require_once KDNA_TABLES_PATH . 'includes/class-kdna-tables-shortcode.php';
// Shortcode Style Engine.
require_once KDNA_TABLES_PATH . 'includes/class-kdna-tables-style-schema.php';
require_once KDNA_TABLES_PATH . 'includes/class-kdna-tables-style-resolver.php';
require_once KDNA_TABLES_PATH . 'includes/class-kdna-tables-style-admin.php';

KDNA_Tables_CPT::init();
KDNA_Tables_Shortcode::init();
/*
 * Cache invalidation for writes this plugin did not make: WP-CLI, an
 * importer, another plugin touching the option or the meta. The settings
 * page invalidates directly after its own saves.
 */
KDNA_Tables_Style_Resolver::register_invalidation();
/*
 * Not inside is_admin(): the settings page's REST route has to register
 * on rest_api_init, and a /wp-json/ request is not an admin request. The
 * admin_menu and admin_enqueue_scripts hooks inside simply never fire
 * outside the admin.
 */
KDNA_Tables_Style_Admin::init();
if ( is_admin() ) {
	KDNA_Tables_Admin::init();
	KDNA_Tables_Editor::init();
	KDNA_Tables_Migration::init();
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
