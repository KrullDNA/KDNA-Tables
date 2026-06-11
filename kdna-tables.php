<?php
/**
 * Plugin Name: KDNA Tables
 * Description: A reusable table library, with an Elementor widget that picks tables from the library and renders them. Supports general data tables and product comparison tables, with three responsive modes per instance.
 * Version: 2.2.0
 * Author: KDNA
 * Text Domain: kdna-tables
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * @package KDNA_Tables
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KDNA_TABLES_VERSION', '2.2.0' );
define( 'KDNA_TABLES_FILE', __FILE__ );
define( 'KDNA_TABLES_PATH', plugin_dir_path( __FILE__ ) );
define( 'KDNA_TABLES_URL', plugin_dir_url( __FILE__ ) );

require_once KDNA_TABLES_PATH . 'includes/class-kdna-tables-plugin.php';

/*
 * Elementor hook registration MUST happen at file load time.
 * By the time this plugin file is parsed, the elementor/loaded action has
 * already fired in normal load order, so wrapping these registrations in
 * an elementor/loaded callback would silently never run.
 * The hooks themselves fire later in Elementor's own lifecycle, so it is
 * safe to register them unconditionally here.
 */
add_action( 'elementor/elements/categories_registered', array( 'KDNA_Tables_Plugin', 'register_category' ) );
add_action( 'elementor/widgets/register', array( 'KDNA_Tables_Plugin', 'register_widgets' ) );
add_action( 'elementor/frontend/after_register_styles', array( 'KDNA_Tables_Plugin', 'register_frontend_styles' ) );
add_action( 'elementor/frontend/after_register_scripts', array( 'KDNA_Tables_Plugin', 'register_frontend_scripts' ) );
add_action( 'elementor/editor/after_enqueue_styles', array( 'KDNA_Tables_Plugin', 'enqueue_editor_styles' ) );
add_action( 'elementor/editor/after_enqueue_scripts', array( 'KDNA_Tables_Plugin', 'enqueue_editor_scripts' ) );
add_action( 'init', array( 'KDNA_Tables_Plugin', 'load_textdomain' ) );
