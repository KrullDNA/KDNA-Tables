<?php
/**
 * Shortcode Styles settings page: the global defaults.
 *
 * Rendered by KDNA_Tables_Style_Admin::render_page(), which supplies
 * $sections, $grouped and $devices. Everything below the intro comes
 * from templates/admin-style-controls.php, which the per-table panel
 * includes too — one implementation of the controls, not two.
 *
 * @var array $sections Section key => label.
 * @var array $grouped  Section key => (control key => definition).
 * @var array $devices  Device key => label.
 *
 * @package KDNA_Tables
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kdna_context   = 'global';
$kdna_renderers = require KDNA_TABLES_PATH . 'templates/admin-style-controls.php';
?>
<div class="wrap kdna-style-admin" x-data="kdnaTablesStyleAdmin()" x-init="init()" x-cloak>

	<h1 class="wp-heading-inline"><?php esc_html_e( 'Shortcode Styles', 'kdna-tables' ); ?></h1>
	<p class="kdna-style-admin__intro">
		<?php esc_html_e( 'These are the defaults every [kdna_table] shortcode renders with. Individual tables can override them on their own edit screen.', 'kdna-tables' ); ?>
	</p>

	<?php require KDNA_TABLES_PATH . 'templates/admin-style-tools.php'; ?>

	<?php require KDNA_TABLES_PATH . 'templates/admin-style-preview.php'; ?>

	<?php $kdna_renderers['panel']( $sections, $grouped, $devices ); ?>

	<div class="kdna-style-savebar">
		<button
			type="button"
			class="button button-primary"
			@click="save()"
			:disabled="saving"
			x-text="saving ? strings.saving : '<?php echo esc_js( __( 'Save Styles', 'kdna-tables' ) ); ?>'"
		></button>

		<span class="kdna-style-savebar__status" :class="statusClass" x-text="status" aria-live="polite"></span>

		<span class="kdna-style-savebar__dirty" x-show="dirty && ! saving" x-text="strings.unsaved"></span>
	</div>
</div>
