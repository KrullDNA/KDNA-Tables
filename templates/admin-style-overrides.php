<?php
/**
 * Per-table Styles panel, rendered as a meta box on the kdna_table edit
 * screen by KDNA_Tables_Style_Admin::render_overrides_panel().
 *
 * The controls come from templates/admin-style-controls.php, the same
 * file the global settings page uses, rendered in 'table' context: every
 * field shows what it is inheriting until it is explicitly overridden,
 * and the panel gains per-section and whole-table resets.
 *
 * @var array $sections Section key => label.
 * @var array $grouped  Section key => (control key => definition).
 * @var array $devices  Device key => label.
 * @var int   $table_id The table being edited.
 *
 * @package KDNA_Tables
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kdna_context   = 'table';
$kdna_renderers = require KDNA_TABLES_PATH . 'templates/admin-style-controls.php';
?>
<div class="kdna-style-admin kdna-style-admin--panel" x-data="kdnaTablesStyleAdmin()" x-init="init()" x-cloak>

	<p class="kdna-style-admin__intro">
		<?php
		printf(
			/* translators: %s: link to the Shortcode Styles settings page. */
			esc_html__( 'This table follows the %s until you override something here. Overrides apply wherever this table is rendered by a shortcode.', 'kdna-tables' ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . KDNA_Tables_Style_Admin::MENU_SLUG ) ) . '">'
				. esc_html__( 'global Shortcode Styles', 'kdna-tables' ) . '</a>'
		);
		?>
	</p>

	<?php $kdna_renderers['panel']( $sections, $grouped, $devices ); ?>

	<div class="kdna-style-savebar kdna-style-savebar--panel">
		<button
			type="button"
			class="button button-primary"
			@click="save()"
			:disabled="saving"
			x-text="saving ? strings.saving : '<?php echo esc_js( __( 'Save Table Styles', 'kdna-tables' ) ); ?>'"
		></button>

		<span class="kdna-style-savebar__status" :class="statusClass" x-text="status" aria-live="polite"></span>

		<span class="kdna-style-savebar__dirty" x-show="dirty && ! saving" x-text="strings.unsaved"></span>

		<?php
		/*
		 * The whole-table reset sits at the far end of the bar, away from
		 * Save, and asks before it fires: it is the one action here that
		 * cannot be undone by clicking something else.
		 */
		?>
		<button
			type="button"
			class="button kdna-style-savebar__reset"
			x-show="anyOverrides()"
			@click="resetAll()"
		><?php esc_html_e( 'Reset entire table to inherit', 'kdna-tables' ); ?></button>
	</div>
</div>
