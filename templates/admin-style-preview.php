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
 * @package KDNA_Tables
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
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
				<template x-for="table in (preview ? preview.tables : [])" :key="table.id">
					<option :value="table.id" x-text="table.title"></option>
				</template>
			</select>
		</label>

		<label class="kdna-style-preview__control">
			<span class="kdna-style-preview__label"><?php esc_html_e( 'Responsive mode', 'kdna-tables' ); ?></span>
			<select x-model="previewMode" @change="paintPreview()">
				<template x-for="(label, key) in (preview ? preview.modes : {})" :key="key">
					<option :value="key" x-text="label"></option>
				</template>
			</select>
		</label>

		<label class="kdna-style-preview__control">
			<span class="kdna-style-preview__label"><?php esc_html_e( 'Applies at', 'kdna-tables' ); ?></span>
			<select x-model="previewBreakpoint" @change="paintPreview()">
				<template x-for="(label, key) in (preview ? preview.breakpoints : {})" :key="key">
					<option :value="key" x-text="label"></option>
				</template>
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
		<?php esc_html_e( 'This table rendered nothing. It may have no columns, or Elementor may be deactivated.', 'kdna-tables' ); ?>
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
