<?php
/**
 * The live preview pane on the Shortcode Styles settings page.
 *
 * ── Why an iframe ─────────────────────────────────────────────────────
 *
 * The responsive modes are viewport media queries. Only a document with a
 * real 390px viewport makes the mobile query fire, and an iframe is the
 * one way to give a panel inside a 1400px admin screen a real 390px
 * viewport. The alternative — previewing inline and restating every
 * breakpoint rule as a container query or a class override — is a second
 * copy of the responsive layer that would have to be kept in step with
 * the first for ever.
 *
 * The frame carries no src, so its document is about:blank and therefore
 * same-origin. Everything after the initial markup fetch is a DOM write
 * through contentDocument: no postMessage plumbing, no re-fetch, and
 * nothing to serialise.
 *
 * ── Why the options are rendered here, not with x-for ─────────────────
 *
 * Alpine applies x-model to a <select> before an x-for INSIDE that select
 * has produced its options. With nothing to match, the select falls back
 * to its first option and never re-syncs — so the Responsive Mode
 * dropdown read "No responsive mode" while the preview was rendering card
 * stack, and picking the mode it already claimed to be on did nothing.
 * These lists are fixed and known to PHP, so they are printed here and
 * x-model has something to bind to from the first paint.
 *
 * @var array|null $preview Preview configuration, null when nothing is
 *                          published to preview.
 *
 * @package KDNA_Tables
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kdna_preview = isset( $preview ) && is_array( $preview ) ? $preview : array();
$kdna_tables_list = isset( $kdna_preview['tables'] ) ? $kdna_preview['tables'] : array();
$kdna_modes       = isset( $kdna_preview['modes'] ) ? $kdna_preview['modes'] : array();
$kdna_bands       = isset( $kdna_preview['breakpoints'] ) ? $kdna_preview['breakpoints'] : array();
?>
<div class="kdna-style-preview" x-show="preview">

	<div class="kdna-style-preview__bar">

		<label class="kdna-style-preview__control">
			<span class="kdna-style-preview__label"><?php esc_html_e( 'Table', 'kdna-tables' ); ?></span>
			<?php
			/*
			 * Changing the table is the one control here that re-fetches:
			 * a different table is different markup. Everything else is
			 * either a variable or a wrapper attribute.
			 */
			?>
			<select x-model="previewTable" @change="loadPreview()">
				<?php foreach ( $kdna_tables_list as $kdna_table ) : ?>
					<option value="<?php echo esc_attr( (string) $kdna_table['id'] ); ?>">
						<?php echo esc_html( $kdna_table['title'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</label>

		<label class="kdna-style-preview__control">
			<span class="kdna-style-preview__label"><?php esc_html_e( 'Responsive mode', 'kdna-tables' ); ?></span>
			<select x-model="previewMode" @change="paintPreview()">
				<?php foreach ( $kdna_modes as $kdna_key => $kdna_label ) : ?>
					<option value="<?php echo esc_attr( $kdna_key ); ?>"><?php echo esc_html( $kdna_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>

		<label class="kdna-style-preview__control">
			<span class="kdna-style-preview__label"><?php esc_html_e( 'Applies at', 'kdna-tables' ); ?></span>
			<select x-model="previewBreakpoint" @change="paintPreview()">
				<?php foreach ( $kdna_bands as $kdna_key => $kdna_label ) : ?>
					<option value="<?php echo esc_attr( $kdna_key ); ?>"><?php echo esc_html( $kdna_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>

		<label class="kdna-style-preview__control kdna-style-preview__control--check">
			<?php /* Structural: sticky wraps the table in a scroll container. */ ?>
			<input type="checkbox" x-model="previewSticky" @change="loadPreview()" />
			<span><?php esc_html_e( 'Sticky first column', 'kdna-tables' ); ?></span>
		</label>

		<span class="kdna-style-preview__devices" role="group" aria-label="<?php esc_attr_e( 'Preview width', 'kdna-tables' ); ?>">
			<template x-for="(label, key) in (preview ? preview.devices : {})" :key="key">
				<button
					type="button"
					class="kdna-style-preview__device"
					:class="{ 'is-active': previewDevice === key }"
					@click="setPreviewDevice( key )"
					:aria-pressed="previewDevice === key ? 'true' : 'false'"
				>
					<span x-text="label"></span>
					<span class="kdna-style-preview__width" x-text="(preview.widths[key] || '') + 'px'"></span>
				</button>
			</template>
		</span>

		<span class="kdna-style-preview__status" x-show="previewLoading" x-text="strings.loading"></span>
		<span class="kdna-style-preview__status is-error" x-show="previewError" x-text="previewError"></span>
	</div>

	<?php /* The front end additionally applies this table's own overrides. */ ?>
	<p class="kdna-style-preview__notice" x-show="previewHasOverrides()">
		<?php esc_html_e( 'This table has its own style overrides. The preview shows the global defaults only, so the live table will differ where it overrides them.', 'kdna-tables' ); ?>
	</p>

	<p class="kdna-style-preview__notice" x-show="previewEmpty">
		<?php esc_html_e( 'This table rendered nothing. It may have no columns yet.', 'kdna-tables' ); ?>
	</p>

	<div class="kdna-style-preview__stage">
		<?php
		/*
		 * The width is the whole point of the device toggle, so it is set
		 * on the frame itself rather than on a wrapper: the frame's width
		 * IS the viewport the media queries see.
		 */
		?>
		<iframe
			x-ref="previewFrame"
			class="kdna-style-preview__frame"
			title="<?php esc_attr_e( 'Table preview', 'kdna-tables' ); ?>"
			:style="'width: ' + previewWidth() + 'px'"
		></iframe>
	</div>
</div>

<?php /* Nothing published yet, so there is nothing to preview. */ ?>
<p class="kdna-style-preview__empty" x-show="! preview" x-text="strings.noPreview"></p>
