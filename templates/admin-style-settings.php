<?php
/**
 * Shortcode Styles settings page.
 *
 * Rendered by KDNA_Tables_Style_Admin::render_page(), which supplies
 * $sections, $grouped and $devices. Every control's markup is generated
 * from its schema entry, so a control added at Stage 7 appears here
 * without this file changing.
 *
 * ── Addressing ────────────────────────────────────────────────────────
 *
 * Each control is identified to the component by two strings: its
 * control key, and a field key that is empty for everything except the
 * fields inside a typography, border or background group. The component
 * navigates state from those rather than from an eval'd path, so the
 * only expressions in the markup are x-model bindings.
 *
 * A responsive control renders ONE control bound to the breakpoint
 * currently selected in its switcher, not three stacked rows. The
 * x-model path therefore reads the device out of state —
 * values['header_padding'][device['header_padding']]['top'] — which is
 * still a plain assignable expression, so binding works exactly as it
 * does for a flat control. Switching breakpoint re-points the same
 * inputs at a different slot.
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

/** Dashicon per breakpoint, for the switcher. */
$kdna_device_icons = array(
	'desktop' => 'dashicons-desktop',
	'tablet'  => 'dashicons-tablet',
	'mobile'  => 'dashicons-smartphone',
);

/**
 * The Alpine expression addressing one leaf value.
 *
 * @param string $key        Control key.
 * @param string $field_key  Group field key, or '' when not in a group.
 * @param bool   $responsive Whether to route through the device switcher.
 */
$kdna_leaf_path = static function ( $key, $field_key, $responsive ) {
	$path = "values['" . $key . "']";
	if ( '' !== $field_key ) {
		$path .= "['" . $field_key . "']";
	}
	if ( $responsive ) {
		$slot  = '' === $field_key ? $key : $key . '.' . $field_key;
		$path .= "[device['" . $slot . "']]";
	}
	return $path;
};

/** The arguments every component call takes: control, field, device. */
$kdna_args = static function ( $key, $field_key, $responsive ) {
	$slot = '' === $field_key ? $key : $key . '.' . $field_key;
	return "'" . $key . "', '" . $field_key . "', " . ( $responsive ? "device['" . $slot . "']" : "''" );
};

/**
 * The breakpoint switcher. Rendered only for responsive entries. The
 * dot on a button marks a breakpoint that already carries a value, so
 * an override elsewhere in the cascade is visible without clicking
 * through all three.
 */
$kdna_render_switcher = static function ( $key, $field_key, array $devices, array $icons ) {
	$slot = '' === $field_key ? $key : $key . '.' . $field_key;
	?>
	<span class="kdna-style-devices" role="group" aria-label="<?php esc_attr_e( 'Breakpoint', 'kdna-tables' ); ?>">
		<?php foreach ( $devices as $device => $device_label ) : ?>
			<button
				type="button"
				class="kdna-style-devices__btn"
				:class="{
					'is-active': device['<?php echo esc_attr( $slot ); ?>'] === '<?php echo esc_attr( $device ); ?>',
					'has-value': hasDeviceValue( '<?php echo esc_attr( $key ); ?>', '<?php echo esc_attr( $field_key ); ?>', '<?php echo esc_attr( $device ); ?>' )
				}"
				@click="device['<?php echo esc_attr( $slot ); ?>'] = '<?php echo esc_attr( $device ); ?>'"
				:aria-pressed="device['<?php echo esc_attr( $slot ); ?>'] === '<?php echo esc_attr( $device ); ?>' ? 'true' : 'false'"
				title="<?php echo esc_attr( $device_label ); ?>"
			>
				<span class="dashicons <?php echo esc_attr( $icons[ $device ] ); ?>" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php echo esc_html( $device_label ); ?></span>
			</button>
		<?php endforeach; ?>
	</span>
	<?php
};

/**
 * The schema default, as placeholder text.
 *
 * A blank control means inherit, and inherit means "the schema default
 * applies" — so showing that default greyed in the empty field is the
 * difference between a user reading blank as "nothing" and reading it as
 * "1px, unless I say otherwise". Returns '' where there is no default,
 * and the caller falls back to the word inherit.
 *
 * @param array  $definition Control or field definition.
 * @param string $part       Which piece of a compound value: '', 'size',
 *                           or a dimensions side.
 */
$kdna_placeholder = static function ( array $definition, $part = '' ) {
	if ( ! isset( $definition['default'] ) || null === $definition['default'] ) {
		return '';
	}

	$default = $definition['default'];

	if ( '' === $part ) {
		return is_scalar( $default ) ? (string) $default : '';
	}

	return ( is_array( $default ) && isset( $default[ $part ] ) && '' !== $default[ $part ] )
		? (string) $default[ $part ]
		: '';
};

/**
 * The control itself, by type.
 */
$kdna_render_leaf = static function ( array $definition, $key, $field_key, $responsive ) use ( $kdna_leaf_path, $kdna_args, $kdna_placeholder ) {
	$type  = isset( $definition['type'] ) ? $definition['type'] : '';
	$units = isset( $definition['units'] ) && is_array( $definition['units'] ) ? $definition['units'] : array();
	$path  = $kdna_leaf_path( $key, $field_key, $responsive );
	$args  = $kdna_args( $key, $field_key, $responsive );
	$min   = isset( $definition['min'] ) ? $definition['min'] : 0;
	$max   = isset( $definition['max'] ) ? $definition['max'] : 100;
	$step  = isset( $definition['step'] ) ? $definition['step'] : 1;

	/* ── Colour ────────────────────────────────────────────────────
	 * The native picker cannot represent "unset" — it shows #000000
	 * for an empty value and would write one the moment it is
	 * focused. So it is bound one way, through a swatch helper, and
	 * only writes back on a real input event. The text field carries
	 * the actual value, including the rgba() forms the native picker
	 * cannot show.
	 */
	if ( 'color' === $type ) {
		?>
		<div class="kdna-style-color">
			<input
				type="color"
				class="kdna-style-color__picker"
				:value="colorSwatch( <?php echo esc_attr( $args ); ?> )"
				@input="setLeaf( <?php echo esc_attr( $args ); ?>, $event.target.value )"
				aria-label="<?php esc_attr_e( 'Colour picker', 'kdna-tables' ); ?>"
			/>
			<?php $kdna_default = $kdna_placeholder( $definition ); ?>
			<input
				type="text"
				class="kdna-style-color__text"
				x-model="<?php echo esc_attr( $path ); ?>"
				placeholder="<?php echo esc_attr( '' !== $kdna_default ? $kdna_default : __( 'inherit', 'kdna-tables' ) ); ?>"
				spellcheck="false"
			/>
			<button
				type="button"
				class="kdna-style-clear"
				x-show="hasDeviceValue( <?php echo esc_attr( $args ); ?> )"
				@click="clearLeaf( <?php echo esc_attr( $args ); ?> )"
				title="<?php esc_attr_e( 'Clear', 'kdna-tables' ); ?>"
			>
				<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Clear', 'kdna-tables' ); ?></span>
			</button>
		</div>
		<?php
		return;
	}

	/* ── Slider ────────────────────────────────────────────────────
	 * Range plus number plus unit. The range is bound one way for the
	 * same reason as the colour picker: an unset value has to park the
	 * thumb somewhere, and parking it must not count as a value.
	 */
	if ( 'slider' === $type ) {
		?>
		<div class="kdna-style-slider">
			<input
				type="range"
				class="kdna-style-slider__range"
				min="<?php echo esc_attr( (string) $min ); ?>"
				max="<?php echo esc_attr( (string) $max ); ?>"
				step="<?php echo esc_attr( (string) $step ); ?>"
				:value="sliderPosition( <?php echo esc_attr( $args ); ?>, <?php echo esc_attr( (string) $min ); ?> )"
				@input="setSize( <?php echo esc_attr( $args ); ?>, $event.target.value )"
				aria-label="<?php echo esc_attr( $definition['label'] ); ?>"
			/>
			<input
				type="number"
				class="kdna-style-slider__number"
				min="<?php echo esc_attr( (string) $min ); ?>"
				max="<?php echo esc_attr( (string) $max ); ?>"
				step="<?php echo esc_attr( (string) $step ); ?>"
				x-model="<?php echo esc_attr( $path . "['size']" ); ?>"
				placeholder="<?php echo esc_attr( $kdna_placeholder( $definition, 'size' ) ?: '—' ); ?>"
			/>
			<?php if ( count( $units ) > 1 ) : ?>
				<select class="kdna-style-unit" x-model="<?php echo esc_attr( $path . "['unit']" ); ?>" aria-label="<?php esc_attr_e( 'Unit', 'kdna-tables' ); ?>">
					<?php foreach ( $units as $unit ) : ?>
						<option value="<?php echo esc_attr( $unit ); ?>"><?php echo esc_html( '' === $unit ? '—' : $unit ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php elseif ( ! empty( $units ) ) : ?>
				<span class="kdna-style-unit kdna-style-unit--fixed"><?php echo esc_html( '' === $units[0] ? '—' : $units[0] ); ?></span>
			<?php endif; ?>
			<button
				type="button"
				class="kdna-style-clear"
				x-show="hasDeviceValue( <?php echo esc_attr( $args ); ?> )"
				@click="clearLeaf( <?php echo esc_attr( $args ); ?> )"
				title="<?php esc_attr_e( 'Clear', 'kdna-tables' ); ?>"
			>
				<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Clear', 'kdna-tables' ); ?></span>
			</button>
		</div>
		<?php
		return;
	}

	/* ── Dimensions ────────────────────────────────────────────────
	 * Four sides, a unit, and a link toggle. Linked, editing any side
	 * writes all four, which is how Elementor's padding behaves. The
	 * link state itself is stored alongside the value so it survives a
	 * reload; the resolver and the sanitiser both ignore it.
	 */
	if ( 'dimensions' === $type ) {
		?>
		<div class="kdna-style-dimensions">
			<?php foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) : ?>
				<label class="kdna-style-dimensions__side">
					<input
						type="number"
						step="any"
						x-model="<?php echo esc_attr( $path . "['" . $side . "']" ); ?>"
						@input="syncLinked( <?php echo esc_attr( $args ); ?>, $event.target.value )"
						placeholder="<?php echo esc_attr( $kdna_placeholder( $definition, $side ) ?: '—' ); ?>"
						aria-label="<?php echo esc_attr( ucfirst( $side ) ); ?>"
					/>
					<span class="kdna-style-dimensions__label"><?php echo esc_html( ucfirst( $side ) ); ?></span>
				</label>
			<?php endforeach; ?>

			<?php if ( count( $units ) > 1 ) : ?>
				<select class="kdna-style-unit" x-model="<?php echo esc_attr( $path . "['unit']" ); ?>" aria-label="<?php esc_attr_e( 'Unit', 'kdna-tables' ); ?>">
					<?php foreach ( $units as $unit ) : ?>
						<option value="<?php echo esc_attr( $unit ); ?>"><?php echo esc_html( '' === $unit ? '—' : $unit ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>

			<button
				type="button"
				class="kdna-style-link"
				:class="{ 'is-linked': isLinked( <?php echo esc_attr( $args ); ?> ) }"
				@click="toggleLink( <?php echo esc_attr( $args ); ?> )"
				:aria-pressed="isLinked( <?php echo esc_attr( $args ); ?> ) ? 'true' : 'false'"
				:title="isLinked( <?php echo esc_attr( $args ); ?> ) ? '<?php echo esc_js( __( 'Unlink sides', 'kdna-tables' ) ); ?>' : '<?php echo esc_js( __( 'Link sides', 'kdna-tables' ) ); ?>'"
			>
				<span class="dashicons" :class="isLinked( <?php echo esc_attr( $args ); ?> ) ? 'dashicons-admin-links' : 'dashicons-editor-unlink'" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Link sides', 'kdna-tables' ); ?></span>
			</button>

			<button
				type="button"
				class="kdna-style-clear"
				x-show="hasDeviceValue( <?php echo esc_attr( $args ); ?> )"
				@click="clearLeaf( <?php echo esc_attr( $args ); ?> )"
				title="<?php esc_attr_e( 'Clear', 'kdna-tables' ); ?>"
			>
				<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Clear', 'kdna-tables' ); ?></span>
			</button>
		</div>
		<?php
		return;
	}

	/* ── Number ────────────────────────────────────────────────── */
	if ( 'number' === $type ) {
		?>
		<div class="kdna-style-number">
			<input
				type="number"
				min="<?php echo esc_attr( (string) $min ); ?>"
				max="<?php echo esc_attr( (string) $max ); ?>"
				step="<?php echo esc_attr( (string) $step ); ?>"
				x-model="<?php echo esc_attr( $path ); ?>"
				placeholder="<?php echo esc_attr( $kdna_placeholder( $definition ) ?: '—' ); ?>"
			/>
			<?php if ( ! empty( $definition['suffix'] ) ) : ?>
				<span class="kdna-style-unit kdna-style-unit--fixed"><?php echo esc_html( $definition['suffix'] ); ?></span>
			<?php endif; ?>
			<button
				type="button"
				class="kdna-style-clear"
				x-show="hasDeviceValue( <?php echo esc_attr( $args ); ?> )"
				@click="clearLeaf( <?php echo esc_attr( $args ); ?> )"
				title="<?php esc_attr_e( 'Clear', 'kdna-tables' ); ?>"
			>
				<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Clear', 'kdna-tables' ); ?></span>
			</button>
		</div>
		<?php
		return;
	}

	/* ── Select ────────────────────────────────────────────────────
	 * A select whose options carry no empty key cannot show the unset
	 * state: the browser falls back to displaying the first option, so
	 * an untouched Alignment control would read as "Left" while
	 * nothing is stored and the schema default is centre. Prepend an
	 * explicit empty option so blank is representable; choosing it
	 * stores nothing and the value falls back through the layers as
	 * any other unset control does.
	 */
	if ( 'select' === $type && empty( $definition['free_text'] ) ) {
		$options = isset( $definition['options'] ) && is_array( $definition['options'] ) ? $definition['options'] : array();
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

	/* ── Free text, e.g. the typography font family ────────────────
	 * A text field with a datalist of suggestions rather than an
	 * allow-list, so a site's own Elementor faces can be typed in by
	 * name. The suggestions come from the schema entry, which leads them
	 * with 'inherit' as the way to clear the field.
	 */
	$suggestions = isset( $definition['suggestions'] ) && is_array( $definition['suggestions'] )
		? $definition['suggestions']
		: array();
	$list_id     = 'kdna-style-list-' . sanitize_html_class( $key . '-' . $field_key );
	?>
	<div class="kdna-style-number">
		<input
			type="text"
			class="kdna-style-input"
			x-model="<?php echo esc_attr( $path ); ?>"
			<?php if ( ! empty( $suggestions ) ) : ?>list="<?php echo esc_attr( $list_id ); ?>"<?php endif; ?>
			placeholder="<?php echo esc_attr( $kdna_placeholder( $definition ) ?: __( 'inherit', 'kdna-tables' ) ); ?>"
			spellcheck="false"
		/>
		<?php if ( ! empty( $suggestions ) ) : ?>
			<datalist id="<?php echo esc_attr( $list_id ); ?>">
				<?php foreach ( $suggestions as $suggestion ) : ?>
					<option value="<?php echo esc_attr( $suggestion ); ?>"></option>
				<?php endforeach; ?>
			</datalist>
		<?php endif; ?>
		<button
			type="button"
			class="kdna-style-clear"
			x-show="hasDeviceValue( <?php echo esc_attr( $args ); ?> )"
			@click="clearLeaf( <?php echo esc_attr( $args ); ?> )"
			title="<?php esc_attr_e( 'Clear', 'kdna-tables' ); ?>"
		>
			<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
			<span class="screen-reader-text"><?php esc_html_e( 'Clear', 'kdna-tables' ); ?></span>
		</button>
	</div>
	<?php
};

/**
 * One field row: label, breakpoint switcher, reset, and the control.
 */
$kdna_render_field = static function ( array $definition, $key, $field_key, array $devices, array $icons, $nested = false ) use ( $kdna_render_leaf, $kdna_render_switcher ) {
	$responsive = ! empty( $definition['responsive'] );
	?>
	<div class="kdna-style-field<?php echo $nested ? ' kdna-style-field--nested' : ''; ?>">
		<div class="kdna-style-field__head">
			<span class="kdna-style-field__label"><?php echo esc_html( $definition['label'] ); ?></span>
			<?php if ( ! $nested ) : ?>
				<code class="kdna-style-field__key"><?php echo esc_html( $key ); ?></code>
			<?php endif; ?>

			<span class="kdna-style-field__tools">
				<?php if ( $responsive ) : ?>
					<?php $kdna_render_switcher( $key, $field_key, $devices, $icons ); ?>
				<?php endif; ?>

				<button
					type="button"
					class="kdna-style-field__reset"
					x-show="hasValue( '<?php echo esc_attr( $key ); ?>', '<?php echo esc_attr( $field_key ); ?>' )"
					@click="resetControl( '<?php echo esc_attr( $key ); ?>', '<?php echo esc_attr( $field_key ); ?>' )"
					title="<?php esc_attr_e( 'Clear this control back to inherit, at every breakpoint', 'kdna-tables' ); ?>"
				><?php esc_html_e( 'Reset', 'kdna-tables' ); ?></button>
			</span>
		</div>

		<?php if ( ! empty( $definition['description'] ) ) : ?>
			<p class="kdna-style-field__description"><?php echo esc_html( $definition['description'] ); ?></p>
		<?php endif; ?>

		<?php $kdna_render_leaf( $definition, $key, $field_key, $responsive ); ?>
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
								<?php
								/*
								 * A group collapses to a single row. Closed, it
								 * shows a live summary of what is set inside it,
								 * which is what keeps a section of sixty controls
								 * readable — typography alone is nine fields, and
								 * eight of them are usually inherit.
								 *
								 * Disclosure pattern rather than a fieldset: the
								 * heading is the toggle, so it needs to be a
								 * button, and a button is not what a legend is
								 * for.
								 */
								$group_id = 'kdna-style-group-' . sanitize_html_class( $control_key );
								?>
								<section class="kdna-style-group" :class="{ 'is-open': isOpen( '<?php echo esc_attr( $control_key ); ?>' ) }">
									<h3 class="kdna-style-group__heading">
										<button
											type="button"
											class="kdna-style-group__toggle"
											id="<?php echo esc_attr( $group_id ); ?>-toggle"
											aria-controls="<?php echo esc_attr( $group_id ); ?>"
											:aria-expanded="isOpen( '<?php echo esc_attr( $control_key ); ?>' ) ? 'true' : 'false'"
											@click="toggleGroup( '<?php echo esc_attr( $control_key ); ?>' )"
										>
											<span
												class="dashicons kdna-style-group__chevron"
												:class="isOpen( '<?php echo esc_attr( $control_key ); ?>' ) ? 'dashicons-arrow-down-alt2' : 'dashicons-arrow-right-alt2'"
												aria-hidden="true"
											></span>
											<span class="kdna-style-group__label"><?php echo esc_html( $definition['label'] ); ?></span>
											<code class="kdna-style-field__key"><?php echo esc_html( $control_key ); ?></code>
											<span
												class="kdna-style-group__summary"
												:class="{ 'is-set': hasValue( '<?php echo esc_attr( $control_key ); ?>', '' ) }"
												x-text="groupSummary( '<?php echo esc_attr( $control_key ); ?>' )"
											></span>
										</button>
									</h3>

									<div
										class="kdna-style-group__body"
										id="<?php echo esc_attr( $group_id ); ?>"
										x-show="isOpen( '<?php echo esc_attr( $control_key ); ?>' )"
										role="group"
										aria-labelledby="<?php echo esc_attr( $group_id ); ?>-toggle"
									>
										<div class="kdna-style-group__actions" x-show="hasValue( '<?php echo esc_attr( $control_key ); ?>', '' )">
											<button
												type="button"
												class="kdna-style-field__reset"
												@click="resetControl( '<?php echo esc_attr( $control_key ); ?>', '' )"
												title="<?php esc_attr_e( 'Clear every field in this group back to inherit', 'kdna-tables' ); ?>"
											><?php esc_html_e( 'Reset group', 'kdna-tables' ); ?></button>
										</div>

										<?php foreach ( $definition['fields'] as $field_key => $field ) : ?>
											<?php $kdna_render_field( $field, $control_key, $field_key, $devices, $kdna_device_icons, true ); ?>
										<?php endforeach; ?>
									</div>
								</section>
							<?php else : ?>
								<?php $kdna_render_field( $definition, $control_key, '', $devices, $kdna_device_icons ); ?>
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
