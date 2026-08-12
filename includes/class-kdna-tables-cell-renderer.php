<?php
/**
 * A plain cell renderer for the render templates.
 *
 * The templates are included with $this bound to an object carrying the
 * cell-render helpers. The widget is such an object because it uses the
 * same trait; this is the one the shortcode uses, and its whole point is
 * that it extends nothing. It can be instantiated on a site with
 * Elementor deactivated, which the widget cannot: that class extends
 * \Elementor\Widget_Base, so merely including its file without Elementor
 * is a fatal error.
 *
 * @package KDNA_Tables
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once KDNA_TABLES_PATH . 'includes/trait-kdna-tables-cell-renderer.php';

class KDNA_Tables_Cell_Renderer {

	use KDNA_Tables_Cell_Renderer_Trait;
}
