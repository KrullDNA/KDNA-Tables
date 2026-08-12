<?php
/**
 * Preset export and import, and the reset action, on the Shortcode
 * Styles settings page.
 *
 * All three are global-layer operations, which is why they live here and
 * not on the per-table panel: a preset is the whole set of global
 * defaults, and there is nothing coherent to export from one table's
 * overrides.
 *
 * @package KDNA_Tables
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="kdna-style-tools">

	<div class="kdna-style-tools__row">
		<button type="button" class="button" @click="exportPreset()">
			<span class="dashicons dashicons-download" aria-hidden="true"></span>
			<?php esc_html_e( 'Export preset', 'kdna-tables' ); ?>
		</button>

		<button
			type="button"
			class="button"
			@click="importOpen = ! importOpen"
			:aria-expanded="importOpen ? 'true' : 'false'"
			aria-controls="kdna-style-import"
		>
			<span class="dashicons dashicons-upload" aria-hidden="true"></span>
			<?php esc_html_e( 'Import preset', 'kdna-tables' ); ?>
		</button>

		<?php
		/*
		 * Reset sits apart from the other two and confirms before it
		 * fires. It is the only action on this page that cannot be
		 * undone by clicking something else.
		 */
		?>
		<button type="button" class="button kdna-style-tools__reset" @click="resetGlobals()">
			<?php esc_html_e( 'Reset all global styles to plugin defaults', 'kdna-tables' ); ?>
		</button>
	</div>

	<div class="kdna-style-tools__panel" id="kdna-style-import" x-show="importOpen" x-cloak>

		<p class="kdna-style-tools__hint">
			<?php esc_html_e( 'Paste a preset, or choose an exported .json file. Importing replaces every global style; tables with their own overrides keep them.', 'kdna-tables' ); ?>
		</p>

		<input
			type="file"
			accept="application/json,.json"
			class="kdna-style-tools__file"
			@change="readPresetFile( $event )"
		/>

		<textarea
			class="kdna-style-tools__textarea"
			rows="6"
			spellcheck="false"
			x-model="importText"
			placeholder="<?php echo esc_attr( '{ "kdna_tables_preset": true, "values": { … } }' ); ?>"
		></textarea>

		<div class="kdna-style-tools__actions">
			<button
				type="button"
				class="button button-primary"
				@click="importPreset()"
				:disabled="importing || ! importText.trim()"
				x-text="importing ? strings.importing : '<?php echo esc_js( __( 'Import', 'kdna-tables' ) ); ?>'"
			></button>
		</div>

		<?php
		/*
		 * What did not survive. An import that quietly dropped half a
		 * preset and reported success would be worse than one that
		 * failed outright, so the keys are named.
		 */
		?>
		<div class="kdna-style-tools__discarded" x-show="discarded.length">
			<p x-text="strings.discardedIntro"></p>
			<ul>
				<template x-for="item in discarded" :key="item.key">
					<li>
						<code x-text="item.key"></code>
						<span x-text="' — ' + item.reason"></span>
					</li>
				</template>
			</ul>
		</div>
	</div>
</div>
