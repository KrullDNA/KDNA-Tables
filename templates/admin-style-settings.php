<?php
/**
 * Shortcode Styles settings page.
 *
 * Rendered by KDNA_Tables_Style_Admin::render_page(), which supplies
 * $sections, $grouped and $devices. The markup is generated from the
 * schema rather than hand-written, so a control added at Stage 7 appears
 * here without this file changing.
 *
 * Stage 4 renders every control as plain text inputs — but as inputs
 * bound to the real storage shape, not to a flattened string: a
 * dimensions control gets four side fields and a unit field, a slider
 * gets a size and a unit, and a responsive control gets one row per
 * breakpoint. Stages 5 and 6 replace the markup with real controls; the
 * shape they bind to is the one being proved here.
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

/**
 * Emit the input(s) for one leaf value.
 *
 * $path is the Alpine expression addressing this value in state, e.g.
 * values['header_padding']['mobile']. The component guarantees every
 * path exists before Alpine binds, so x-model never writes into
 * undefined.
 */
$kdna_render_leaf = static function ( array $definition, $path ) {
	$type  = isset( $definition['type'] ) ? $definition['type'] : '';
	$units = isset( $definition['units'] ) && is_array( $definition['units'] ) ? $definition['units'] : array();

	if ( 'dimensions' === $type ) {
		?>
		<div class="kdna-style-field__parts">
			<?php foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) : ?>
				<label class="kdna-style-part">
					<span class="kdna-style-part__label"><?php echo esc_html( ucfirst( $side ) ); ?></span>
					<input type="text" inputmode="decimal" x-model="<?php echo esc_attr( $path . "['" . $side . "']" ); ?>" />
				</label>
			<?php endforeach; ?>
			<label class="kdna-style-part kdna-style-part--unit">
				<span class="kdna-style-part__label"><?php esc_html_e( 'Unit', 'kdna-tables' ); ?></span>
				<select x-model="<?php echo esc_attr( $path . "['unit']" ); ?>">
					<?php foreach ( $units as $unit ) : ?>
						<option value="<?php echo esc_attr( $unit ); ?>"><?php echo esc_html( '' === $unit ? '—' : $unit ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		</div>
		<?php
		return;
	}

	if ( 'slider' === $type ) {
		?>
		<div class="kdna-style-field__parts">
			<label class="kdna-style-part">
				<span class="kdna-style-part__label"><?php esc_html_e( 'Size', 'kdna-tables' ); ?></span>
				<input type="text" inputmode="decimal" x-model="<?php echo esc_attr( $path . "['size']" ); ?>" />
			</label>
			<label class="kdna-style-part kdna-style-part--unit">
				<span class="kdna-style-part__label"><?php esc_html_e( 'Unit', 'kdna-tables' ); ?></span>
				<select x-model="<?php echo esc_attr( $path . "['unit']" ); ?>">
					<?php foreach ( $units as $unit ) : ?>
						<option value="<?php echo esc_attr( $unit ); ?>"><?php echo esc_html( '' === $unit ? '—' : $unit ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		</div>
		<?php
		return;
	}

	if ( 'select' === $type && empty( $definition['free_text'] ) ) {
		$options = isset( $definition['options'] ) && is_array( $definition['options'] ) ? $definition['options'] : array();
		/*
		 * A select whose options carry no empty key cannot show the unset
		 * state: the browser falls back to displaying the first option, so
		 * an untouched Alignment control reads as "Left" while nothing is
		 * stored and the schema default is centre. Prepend an explicit
		 * empty option so blank is representable, and choosing it stores
		 * nothing — the sanitiser drops '' and the value falls back
		 * through the layers as any other unset control does.
		 */
		if ( ! array_key_exists( '', $options ) ) {
			$options = array( '' => __( '— Default —', 'kdna-tables' ) ) + $options;
		}
		?>
		<select class="kdna-style-input" x-model="<?php echo esc_attr( $path ); ?>">
			<?php foreach ( $options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
		return;
	}

	// color, number, and the free-text selects: a plain text input. The
	// colour picker and the rest arrive at Stage 5.
	$placeholder = '';
	if ( 'color' === $type ) {
		$placeholder = '#000000';
	} elseif ( 'number' === $type ) {
		$placeholder = '0';
	}
	?>
	<input
		type="text"
		class="kdna-style-input"
		x-model="<?php echo esc_attr( $path ); ?>"
		<?php if ( '' !== $placeholder ) : ?>placeholder="<?php echo esc_attr( $placeholder ); ?>"<?php endif; ?>
	/>
	<?php
};

/**
 * Emit one control: its label, and either a single leaf or one row per
 * breakpoint.
 */
$kdna_render_control = static function ( array $definition, $key, array $devices ) use ( $kdna_render_leaf ) {
	$base = "values['" . $key . "']";
	?>
	<div class="kdna-style-field">
		<div class="kdna-style-field__head">
			<span class="kdna-style-field__label"><?php echo esc_html( $definition['label'] ); ?></span>
			<code class="kdna-style-field__key"><?php echo esc_html( $key ); ?></code>
			<button
				type="button"
				class="kdna-style-field__reset"
				x-show="hasValue( '<?php echo esc_attr( $key ); ?>' )"
				@click="resetControl( '<?php echo esc_attr( $key ); ?>' )"
			><?php esc_html_e( 'Reset to inherit', 'kdna-tables' ); ?></button>
		</div>
		<?php if ( ! empty( $definition['description'] ) ) : ?>
			<p class="kdna-style-field__description"><?php echo esc_html( $definition['description'] ); ?></p>
		<?php endif; ?>

		<?php if ( empty( $definition['responsive'] ) ) : ?>
			<?php $kdna_render_leaf( $definition, $base ); ?>
		<?php else : ?>
			<?php foreach ( $devices as $device => $device_label ) : ?>
				<div class="kdna-style-device">
					<span class="kdna-style-device__label"><?php echo esc_html( $device_label ); ?></span>
					<div class="kdna-style-device__control">
						<?php $kdna_render_leaf( $definition, $base . "['" . $device . "']" ); ?>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<?php
};
?>
<div class="wrap kdna-style-admin" x-data="kdnaTablesStyleAdmin()" x-init="init()" x-cloak>

	<h1 class="wp-heading-inline"><?php esc_html_e( 'Shortcode Styles', 'kdna-tables' ); ?></h1>
	<p class="kdna-style-admin__intro">
		<?php esc_html_e( 'These are the defaults every [kdna_table] shortcode renders with. Individual tables can override them on their own edit screen.', 'kdna-tables' ); ?>
	</p>

	<div class="kdna-style-admin__body">

		<nav class="kdna-style-tabs" aria-label="<?php esc_attr_e( 'Style sections', 'kdna-tables' ); ?>">
			<?php foreach ( $sections as $section_key => $section_label ) :
				$count = isset( $grouped[ $section_key ] ) ? count( $grouped[ $section_key ] ) : 0;
				?>
				<button
					type="button"
					class="kdna-style-tab"
					:class="{ 'is-active': section === '<?php echo esc_attr( $section_key ); ?>' }"
					@click="section = '<?php echo esc_attr( $section_key ); ?>'"
					:aria-current="section === '<?php echo esc_attr( $section_key ); ?>' ? 'true' : 'false'"
				>
					<span class="kdna-style-tab__label"><?php echo esc_html( $section_label ); ?></span>
					<?php if ( $count > 0 ) : ?>
						<span class="kdna-style-tab__count"><?php echo esc_html( (string) $count ); ?></span>
					<?php endif; ?>
				</button>
			<?php endforeach; ?>
		</nav>

		<div class="kdna-style-panel">
			<?php foreach ( $sections as $section_key => $section_label ) : ?>
				<section
					class="kdna-style-panel__section"
					x-show="section === '<?php echo esc_attr( $section_key ); ?>'"
					aria-labelledby="kdna-style-heading-<?php echo esc_attr( $section_key ); ?>"
				>
					<h2 id="kdna-style-heading-<?php echo esc_attr( $section_key ); ?>" class="kdna-style-panel__heading">
						<?php echo esc_html( $section_label ); ?>
					</h2>

					<?php if ( empty( $grouped[ $section_key ] ) ) : ?>
						<p class="kdna-style-panel__empty">
							<?php esc_html_e( 'No controls in this section yet.', 'kdna-tables' ); ?>
						</p>
					<?php else : ?>
						<?php foreach ( $grouped[ $section_key ] as $control_key => $definition ) : ?>
							<?php if ( KDNA_Tables_Style_Schema::is_group_type( $definition['type'] ) ) : ?>
								<fieldset class="kdna-style-group">
									<legend class="kdna-style-group__legend">
										<?php echo esc_html( $definition['label'] ); ?>
										<code class="kdna-style-field__key"><?php echo esc_html( $control_key ); ?></code>
									</legend>
									<?php foreach ( $definition['fields'] as $field_key => $field ) : ?>
										<?php
										$field_path = "values['" . $control_key . "']['" . $field_key . "']";
										?>
										<div class="kdna-style-field kdna-style-field--nested">
											<div class="kdna-style-field__head">
												<span class="kdna-style-field__label"><?php echo esc_html( $field['label'] ); ?></span>
											</div>
											<?php if ( empty( $field['responsive'] ) ) : ?>
												<?php $kdna_render_leaf( $field, $field_path ); ?>
											<?php else : ?>
												<?php foreach ( $devices as $device => $device_label ) : ?>
													<div class="kdna-style-device">
														<span class="kdna-style-device__label"><?php echo esc_html( $device_label ); ?></span>
														<div class="kdna-style-device__control">
															<?php $kdna_render_leaf( $field, $field_path . "['" . $device . "']" ); ?>
														</div>
													</div>
												<?php endforeach; ?>
											<?php endif; ?>
										</div>
									<?php endforeach; ?>
								</fieldset>
							<?php else : ?>
								<?php $kdna_render_control( $definition, $control_key, $devices ); ?>
							<?php endif; ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</section>
			<?php endforeach; ?>
		</div>
	</div>

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
