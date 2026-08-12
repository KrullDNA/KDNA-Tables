<?php
/**
 * Style control schema, the single source of truth for the Shortcode
 * Style Engine.
 *
 * Every style control is defined once, here. Three consumers read this
 * array and nothing else:
 *
 *   1. the admin UI renders its controls from it (Stages 4 to 6),
 *   2. KDNA_Tables_Style_Resolver turns stored values into CSS custom
 *      properties from it (Stage 1, this stage),
 *   3. the live preview binds to it (Stage 9).
 *
 * Adding a control later is therefore one array entry rather than edits
 * across four files. With around sixty controls to support, that is the
 * difference between a maintainable build and an unmaintainable one.
 *
 * ── Control definition ────────────────────────────────────────────────
 *
 * Required on every entry:
 *   key         string   Array key, repeated inside the entry so a
 *                        detached entry still knows its own name.
 *   label       string   Admin-facing label.
 *   section     string   One of SECTION_ORDER.
 *   type        string   color | dimensions | slider | select | number |
 *                        typography | border | background
 *   css_var     string   The custom property it writes. Null on group
 *                        controls (typography, border, background) —
 *                        their fields carry their own.
 *   default     mixed    Value in this type's value shape, or null for
 *                        "not set", which emits no property at all and
 *                        lets the stylesheet's var() fallback apply.
 *   responsive  bool     Whether the control stores per-breakpoint
 *                        values. Null on group controls; responsiveness
 *                        on those is per field.
 *
 * Optional, by type:
 *   units       array    dimensions, slider. First entry is the default.
 *   options     array    select. Value => label. An '' key means
 *                        "inherit", which emits no property.
 *   value_map   array    select. Option value => CSS value, when the two
 *                        differ (alignment mapping to a margin, say).
 *   min/max/step number   slider, number.
 *   suffix      string   number. Appended to the raw value, e.g. 'ms'.
 *   fields      array    typography, border, background. Field key =>
 *                        definition, each carrying its own type, css_var,
 *                        default and responsive flag.
 *   description string   Shown under the control in the admin UI.
 *
 * ── Value shapes ──────────────────────────────────────────────────────
 *
 *   color        '#ffffff' | 'rgba(0,0,0,.5)'
 *   select       'left'
 *   number       200
 *   slider       array( 'size' => 12, 'unit' => 'px' )
 *   dimensions   array( 'top' => 14, 'right' => 16, 'bottom' => 14,
 *                       'left' => 16, 'unit' => 'px' )
 *   responsive   array( 'desktop' => <shape above>,
 *                       'tablet'  => <shape above>,
 *                       'mobile'  => <shape above> )
 *                Breakpoints left unset are ABSENT from the array rather
 *                than present and empty. The resolver relies on that to
 *                omit the suffixed property entirely.
 *   group        array( <field key> => <shape above, or a responsive map
 *                       when that field is responsive> )
 *
 * ── On CSS variable names ─────────────────────────────────────────────
 *
 * Where kdna-tables.css already reads a variable for a given property,
 * the schema writes that same variable rather than inventing a parallel
 * one. The shortcode wrapper carries .kdna-table__wrapper--general as
 * well as --shortcode, so it picks up the existing general-table rules;
 * reusing the names means one inline attribute drives both stylesheets
 * instead of them disagreeing. New names are only introduced where the
 * widget wrote a direct CSS declaration and no variable exists yet,
 * which is most of the typography and the wrapper frame.
 *
 * Defaults duplicate the value the current stylesheet renders. That
 * duplication is deliberate: the settings page has to show the real
 * starting point, and the Stage 2 stylesheet still carries the same
 * value as its var() fallback so an unstyled wrapper is never bare.
 *
 * @package KDNA_Tables
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KDNA_Tables_Style_Schema {

	/**
	 * Section keys in admin display order.
	 *
	 * These do not mirror the widget's own sections exactly. The widget
	 * keeps its rule lines in three separate places and its caption
	 * inside Table Wrapper; here the lines are gathered into one section
	 * because they are one system, and the caption has its own. The
	 * mapping from each widget control to its schema key is in the
	 * README.
	 */
	const SECTION_ORDER = array(
		'wrapper',
		'caption',
		'header',
		'first_col',
		'body',
		'lines',
		'cell_content',
		'card_stack',
		'sticky',
	);

	/**
	 * Breakpoint keys, in cascade order. 'desktop' writes the base
	 * property name; the others append '-' . $device.
	 */
	const DEVICES = array( 'desktop', 'tablet', 'mobile' );

	/** Group control types, whose fields carry the css_var. */
	const GROUP_TYPES = array( 'typography', 'border', 'background' );

	/**
	 * Datalist suggestions for a typography font family.
	 *
	 * Suggestions, not an allow-list: the field stays free text so a
	 * site's own Elementor faces — Apotheca, say — can be typed in by
	 * name. 'inherit' leads the list because clearing a font is the most
	 * common thing to want from it, and the sanitiser treats that value
	 * as unset rather than storing a word that means nothing.
	 */
	const FONT_SUGGESTIONS = array(
		'inherit',
		'Arial, Helvetica, sans-serif',
		'Georgia, serif',
		'"Helvetica Neue", Helvetica, Arial, sans-serif',
		'"Times New Roman", Times, serif',
		'Tahoma, Geneva, sans-serif',
		'Verdana, Geneva, sans-serif',
		'"Courier New", Courier, monospace',
		'system-ui, sans-serif',
	);

	/** Built schema, memoised per request. */
	private static $controls = null;

	/**
	 * Section key => label.
	 */
	public static function get_sections() {
		return array(
			'wrapper'      => esc_html__( 'Table Wrapper', 'kdna-tables' ),
			'caption'      => esc_html__( 'Caption', 'kdna-tables' ),
			'header'       => esc_html__( 'Header Row', 'kdna-tables' ),
			'first_col'    => esc_html__( 'First Column', 'kdna-tables' ),
			'body'         => esc_html__( 'Body Cells', 'kdna-tables' ),
			'lines'        => esc_html__( 'Rule Lines', 'kdna-tables' ),
			'cell_content' => esc_html__( 'Cell Content', 'kdna-tables' ),
			'card_stack'   => esc_html__( 'Responsive Modes', 'kdna-tables' ),
			'sticky'       => esc_html__( 'Sticky First Column', 'kdna-tables' ),
		);
	}

	/**
	 * The schema: control key => definition.
	 */
	public static function get() {
		if ( null === self::$controls ) {
			self::$controls = self::build();
		}
		return self::$controls;
	}

	/**
	 * One control definition, or null when the key is unknown. Callers
	 * sanitising input use the null return to discard stray keys.
	 */
	public static function get_control( $key ) {
		$controls = self::get();
		return isset( $controls[ $key ] ) ? $controls[ $key ] : null;
	}

	/**
	 * Controls grouped by section, in SECTION_ORDER. Sections with no
	 * controls yet come back as empty arrays rather than being missing.
	 */
	public static function get_by_section() {
		$grouped = array_fill_keys( self::SECTION_ORDER, array() );
		foreach ( self::get() as $key => $control ) {
			$section = isset( $control['section'] ) ? $control['section'] : '';
			if ( ! isset( $grouped[ $section ] ) ) {
				continue;
			}
			$grouped[ $section ][ $key ] = $control;
		}
		return $grouped;
	}

	/**
	 * Control key => default value, in the storage shape the resolver
	 * merges. Controls defaulting to null are omitted, so the returned
	 * array is also the "plugin defaults" preset Stage 10 resets to.
	 */
	public static function get_defaults() {
		$defaults = array();
		foreach ( self::get() as $key => $control ) {
			$value = self::default_value_for( $control );
			if ( null !== $value ) {
				$defaults[ $key ] = $value;
			}
		}
		return $defaults;
	}

	/**
	 * Whether a control type expands into fields.
	 */
	public static function is_group_type( $type ) {
		return in_array( $type, self::GROUP_TYPES, true );
	}

	/**
	 * The default value for one control, in storage shape. Group
	 * controls fold their fields' defaults into a field map; responsive
	 * entries wrap theirs under 'desktop'. Returns null when nothing is
	 * set anywhere, so the caller can skip the key entirely.
	 */
	private static function default_value_for( array $control ) {
		if ( self::is_group_type( $control['type'] ) ) {
			$value = array();
			foreach ( $control['fields'] as $field_key => $field ) {
				$field_default = self::default_value_for( $field );
				if ( null !== $field_default ) {
					$value[ $field_key ] = $field_default;
				}
			}
			return empty( $value ) ? null : $value;
		}

		if ( ! isset( $control['default'] ) || null === $control['default'] ) {
			return null;
		}

		if ( ! empty( $control['responsive'] ) ) {
			return array( 'desktop' => $control['default'] );
		}

		return $control['default'];
	}

	/**
	 * Build the schema. Sections beyond wrapper and header land at
	 * Stage 7.
	 */
	private static function build() {
		$controls = array_merge(
			self::wrapper_controls(),
			self::caption_controls(),
			self::header_controls(),
			self::first_col_controls(),
			self::body_controls(),
			self::lines_controls(),
			self::cell_content_controls(),
			self::responsive_controls(),
			self::sticky_controls()
		);

		// Stamp each entry with its own key so a definition passed around
		// on its own still identifies itself.
		foreach ( $controls as $key => $control ) {
			$controls[ $key ]['key'] = $key;
		}

		/**
		 * Filter the style control schema.
		 *
		 * @param array $controls Control key => definition.
		 */
		return apply_filters( 'kdna_tables_style_schema', $controls );
	}

	/* ─── Section: wrapper ──────────────────────────────────────────── */

	private static function wrapper_controls() {
		return array(
			'wrapper_background'    => self::background_group(
				esc_html__( 'Background', 'kdna-tables' ),
				'wrapper',
				'--kdna-table-wrapper-bg',
				'transparent'
			),

			'wrapper_border'        => self::border_group(
				esc_html__( 'Border', 'kdna-tables' ),
				'wrapper',
				'--kdna-table-wrapper-border'
			),

			'wrapper_border_radius' => array(
				'label'      => esc_html__( 'Border Radius', 'kdna-tables' ),
				'section'    => 'wrapper',
				'type'       => 'dimensions',
				'css_var'    => '--kdna-table-border-radius',
				'units'      => array( 'px', '%', 'em' ),
				'responsive' => true,
				'default'    => self::dimensions( 12, 12, 12, 12, 'px' ),
			),

			'wrapper_max_width'     => array(
				'label'      => esc_html__( 'Max Width', 'kdna-tables' ),
				'section'    => 'wrapper',
				'type'       => 'slider',
				'css_var'    => '--kdna-table-wrapper-max-width',
				'units'      => array( 'px', '%' ),
				'min'        => 0,
				'max'        => 1600,
				'step'       => 1,
				'responsive' => true,
				// Unset, so the stylesheet's own "none" applies and the
				// table stays full width as it does today.
				'default'    => null,
			),

			'wrapper_alignment'     => array(
				'label'       => esc_html__( 'Alignment', 'kdna-tables' ),
				'section'     => 'wrapper',
				'type'        => 'select',
				'css_var'     => '--kdna-table-wrapper-margin',
				'options'     => array(
					'left'   => esc_html__( 'Left', 'kdna-tables' ),
					'center' => esc_html__( 'Centre', 'kdna-tables' ),
					'right'  => esc_html__( 'Right', 'kdna-tables' ),
				),
				// One variable carrying the whole margin shorthand keeps
				// the shortcode path variable-driven; a select writing two
				// separate properties would not.
				'value_map'   => array(
					'left'   => '0 auto 0 0',
					'center' => '0 auto',
					'right'  => '0 0 0 auto',
				),
				'responsive'  => true,
				'default'     => 'center',
				'description' => esc_html__( 'Only visible once a max width is set.', 'kdna-tables' ),
			),

			'wrapper_padding'       => array(
				'label'      => esc_html__( 'Padding', 'kdna-tables' ),
				'section'    => 'wrapper',
				'type'       => 'dimensions',
				'css_var'    => '--kdna-table-wrapper-padding',
				'units'      => array( 'px', '%', 'em' ),
				'responsive' => true,
				'default'    => self::dimensions( 0, 0, 0, 0, 'px' ),
			),

			/*
			 * The widget keeps these two in their own Table Layout section.
			 * There is no layout section in this schema, and they describe
			 * the table frame, so they live with the rest of the frame.
			 */
			'border_collapse'       => array(
				'label'      => esc_html__( 'Border Collapse', 'kdna-tables' ),
				'section'    => 'wrapper',
				'type'       => 'select',
				'css_var'    => '--kdna-table-border-collapse',
				'responsive' => false,
				'default'    => 'collapse',
				'options'    => array(
					'collapse' => esc_html__( 'Collapse', 'kdna-tables' ),
					'separate' => esc_html__( 'Separate', 'kdna-tables' ),
				),
			),

			'border_spacing'        => array(
				'label'       => esc_html__( 'Border Spacing', 'kdna-tables' ),
				'section'     => 'wrapper',
				'type'        => 'slider',
				'css_var'     => '--kdna-table-border-spacing',
				'units'       => array( 'px' ),
				'min'         => 0,
				'max'         => 30,
				'step'        => 1,
				'responsive'  => true,
				'default'     => null,
				'description' => esc_html__( 'Only applies when Border Collapse is Separate.', 'kdna-tables' ),
			),
		);
	}

	/* ─── Section: caption ──────────────────────────────────────────── */

	private static function caption_controls() {
		return array(
			'caption_typography' => self::typography_group(
				esc_html__( 'Typography', 'kdna-tables' ),
				'caption',
				'--kdna-table-caption',
				// The only typographic property the stylesheet declares on
				// the caption.
				array( 'font_weight' => '600' )
			),

			'caption_color'      => array(
				'label'       => esc_html__( 'Colour', 'kdna-tables' ),
				'section'     => 'caption',
				'type'        => 'color',
				'css_var'     => '--kdna-table-caption-color',
				'responsive'  => false,
				// Unset, the caption takes the body text colour, which is
				// what it renders as today.
				'default'     => null,
				'description' => esc_html__( 'Unset, the caption follows the Body Cells text colour.', 'kdna-tables' ),
			),

			'caption_alignment'  => array(
				'label'      => esc_html__( 'Alignment', 'kdna-tables' ),
				'section'    => 'caption',
				'type'       => 'select',
				'css_var'    => '--kdna-table-caption-align',
				'responsive' => true,
				'default'    => 'left',
				'options'    => array(
					'left'   => esc_html__( 'Left', 'kdna-tables' ),
					'center' => esc_html__( 'Centre', 'kdna-tables' ),
					'right'  => esc_html__( 'Right', 'kdna-tables' ),
				),
			),

			'caption_spacing'    => array(
				'label'      => esc_html__( 'Spacing Below', 'kdna-tables' ),
				'section'    => 'caption',
				'type'       => 'slider',
				'css_var'    => '--kdna-table-caption-spacing',
				'units'      => array( 'px' ),
				'min'        => 0,
				'max'        => 100,
				'step'       => 1,
				'responsive' => true,
				'default'    => array( 'size' => 12, 'unit' => 'px' ),
			),
		);
	}

	/* ─── Section: first_col ────────────────────────────────────────── */

	private static function first_col_controls() {
		return array(
			/*
			 * Background and colour default to null rather than to a
			 * concrete value, and that is load-bearing rather than lazy.
			 * kdna-shortcode.css resolves an unset first-column background
			 * to the row stripe it would otherwise have had, and an unset
			 * colour to the body colour. A default of 'transparent' here
			 * would paint over the striping on every table that has never
			 * touched the control.
			 */
			'first_col_bg'         => array(
				'label'       => esc_html__( 'Background Colour', 'kdna-tables' ),
				'section'     => 'first_col',
				'type'        => 'color',
				'css_var'     => '--kdna-table-first-col-bg',
				'responsive'  => false,
				'default'     => null,
				'description' => esc_html__( 'Unset, the first column keeps the odd and even row backgrounds.', 'kdna-tables' ),
			),

			'first_col_text_color' => array(
				'label'       => esc_html__( 'Text Colour', 'kdna-tables' ),
				'section'     => 'first_col',
				'type'        => 'color',
				'css_var'     => '--kdna-table-first-col-color',
				'responsive'  => false,
				'default'     => null,
				'description' => esc_html__( 'Unset, the first column follows the Body Cells text colour.', 'kdna-tables' ),
			),

			'first_col_typography' => self::typography_group(
				esc_html__( 'Typography', 'kdna-tables' ),
				'first_col',
				'--kdna-table-first-col'
			),

			'first_col_padding'    => array(
				'label'       => esc_html__( 'Padding', 'kdna-tables' ),
				'section'     => 'first_col',
				'type'        => 'dimensions',
				'css_var'     => '--kdna-table-first-col-padding',
				'units'       => array( 'px', 'em' ),
				'responsive'  => true,
				'default'     => null,
				'description' => esc_html__( 'Unset, the first column follows the Body Cells padding.', 'kdna-tables' ),
			),
		);
	}

	/* ─── Section: body ─────────────────────────────────────────────── */

	private static function body_controls() {
		return array(
			'body_bg_odd'                   => self::background_group(
				esc_html__( 'Odd Row Background', 'kdna-tables' ),
				'body',
				'--kdna-table-body-bg-odd',
				'#ffffff'
			),

			'body_bg_even'                  => self::background_group(
				esc_html__( 'Even Row Background', 'kdna-tables' ),
				'body',
				'--kdna-table-body-bg-even',
				'#f7f7f8'
			),

			'body_typography'               => self::typography_group(
				esc_html__( 'Typography', 'kdna-tables' ),
				'body',
				'--kdna-table-body'
			),

			'body_text_color'               => array(
				'label'      => esc_html__( 'Text Colour', 'kdna-tables' ),
				'section'    => 'body',
				'type'       => 'color',
				'css_var'    => '--kdna-table-body-color',
				'responsive' => false,
				'default'    => '#1f2937',
			),

			'body_padding'                  => array(
				'label'      => esc_html__( 'Padding', 'kdna-tables' ),
				'section'    => 'body',
				'type'       => 'dimensions',
				'css_var'    => '--kdna-table-body-padding',
				'units'      => array( 'px', 'em' ),
				'responsive' => true,
				'default'    => self::dimensions( 12, 16, 12, 16, 'px' ),
			),

			'body_text_align'               => array(
				'label'       => esc_html__( 'Text Alignment', 'kdna-tables' ),
				'section'     => 'body',
				'type'        => 'select',
				'css_var'     => '--kdna-table-body-text-align',
				'responsive'  => true,
				'default'     => '',
				'options'     => array(
					''       => esc_html__( 'Inherit', 'kdna-tables' ),
					'left'   => esc_html__( 'Left', 'kdna-tables' ),
					'center' => esc_html__( 'Centre', 'kdna-tables' ),
					'right'  => esc_html__( 'Right', 'kdna-tables' ),
				),
				'description' => esc_html__( 'Inherit keeps the per-column and per-cell alignment.', 'kdna-tables' ),
			),

			'row_hover_bg'                  => array(
				'label'      => esc_html__( 'Row Hover Background', 'kdna-tables' ),
				'section'    => 'body',
				'type'       => 'color',
				'css_var'    => '--kdna-table-row-hover-bg',
				'responsive' => false,
				'default'    => null,
			),

			'row_hover_color'               => array(
				'label'      => esc_html__( 'Row Hover Text Colour', 'kdna-tables' ),
				'section'    => 'body',
				'type'       => 'color',
				'css_var'    => '--kdna-table-row-hover-color',
				'responsive' => false,
				'default'    => null,
			),

			'row_hover_transition_duration' => array(
				'label'      => esc_html__( 'Hover Transition', 'kdna-tables' ),
				'section'    => 'body',
				'type'       => 'number',
				'css_var'    => '--kdna-table-row-hover-transition',
				'suffix'     => 'ms',
				'min'        => 0,
				'max'        => 2000,
				'step'       => 10,
				'responsive' => false,
				'default'    => 200,
			),
		);
	}

	/* ─── Section: lines ────────────────────────────────────────────── */

	/**
	 * Every rule line in one section.
	 *
	 * The widget spreads these across its Header Row, First Column and
	 * Body Cells sections. They are gathered here instead because they
	 * are one system: five sets of lines with identical controls, two of
	 * which inherit from a third. Reading them side by side is the only
	 * way to see that.
	 */
	private static function lines_controls() {
		return array_merge(
			self::line_group(
				'body_h_line',
				esc_html__( 'Horizontal Lines', 'kdna-tables' ),
				'--kdna-table-h-line',
				esc_html__( 'Lines between body rows.', 'kdna-tables' )
			),
			self::line_group(
				'body_v_line',
				esc_html__( 'Vertical Lines', 'kdna-tables' ),
				'--kdna-table-v-line',
				esc_html__( 'Lines between columns.', 'kdna-tables' )
			),
			self::line_group(
				'header_divider',
				esc_html__( 'Header Bottom Divider', 'kdna-tables' ),
				'--kdna-table-header-divider',
				esc_html__( 'The single line under the header row.', 'kdna-tables' )
			),
			self::line_group(
				'header_v_line',
				esc_html__( 'Header Vertical Lines', 'kdna-tables' ),
				'--kdna-table-header-v-line',
				esc_html__( 'Unset, these follow the Vertical Lines above.', 'kdna-tables' ),
				true
			),
			self::line_group(
				'first_col_edge',
				esc_html__( 'First Column Right Edge', 'kdna-tables' ),
				'--kdna-table-first-col-edge',
				esc_html__( 'Unset, this follows the Vertical Lines above.', 'kdna-tables' ),
				true
			)
		);
	}

	/* ─── Section: cell_content ─────────────────────────────────────── */

	private static function cell_content_controls() {
		return array(
			'icon_color'          => array(
				'label'      => esc_html__( 'Icon Colour', 'kdna-tables' ),
				'section'    => 'cell_content',
				'type'       => 'color',
				'css_var'    => '--kdna-table-icon-color',
				'responsive' => false,
				'default'    => '#3362dd',
			),

			'icon_color_hover'    => array(
				'label'      => esc_html__( 'Icon Hover Colour', 'kdna-tables' ),
				'section'    => 'cell_content',
				'type'       => 'color',
				'css_var'    => '--kdna-table-icon-hover-color',
				'responsive' => false,
				'default'    => null,
			),

			'icon_size'           => array(
				'label'      => esc_html__( 'Icon Size', 'kdna-tables' ),
				'section'    => 'cell_content',
				'type'       => 'slider',
				'css_var'    => '--kdna-table-icon-size',
				'units'      => array( 'em', 'px' ),
				'min'        => 0,
				'max'        => 200,
				'step'       => 0.05,
				'responsive' => true,
				// The stylesheet renders 1.25em. The widget's own control
				// defaults to 24px, but matching the widget here would
				// change every existing shortcode, so the rendered value
				// wins.
				'default'    => array( 'size' => 1.25, 'unit' => 'em' ),
			),

			'icon_spacing'        => array(
				'label'       => esc_html__( 'Content Spacing', 'kdna-tables' ),
				'section'     => 'cell_content',
				'type'        => 'slider',
				'css_var'     => '--kdna-table-cell-gap',
				'units'       => array( 'px', 'em' ),
				'min'         => 0,
				'max'         => 60,
				'step'        => 1,
				'responsive'  => true,
				'default'     => array( 'size' => 8, 'unit' => 'px' ),
				'description' => esc_html__( 'Gap between the icon, text and image pieces of a cell.', 'kdna-tables' ),
			),

			'image_width'         => array(
				'label'      => esc_html__( 'Image Width', 'kdna-tables' ),
				'section'    => 'cell_content',
				'type'       => 'slider',
				'css_var'    => '--kdna-table-image-width',
				'units'      => array( 'px', '%' ),
				'min'        => 0,
				'max'        => 600,
				'step'       => 1,
				'responsive' => true,
				'default'    => null,
			),

			'image_height'        => array(
				'label'      => esc_html__( 'Image Height', 'kdna-tables' ),
				'section'    => 'cell_content',
				'type'       => 'slider',
				'css_var'    => '--kdna-table-image-height',
				'units'      => array( 'px', '%' ),
				'min'        => 0,
				'max'        => 600,
				'step'       => 1,
				'responsive' => true,
				'default'    => null,
			),

			'image_border_radius' => array(
				'label'      => esc_html__( 'Image Border Radius', 'kdna-tables' ),
				'section'    => 'cell_content',
				'type'       => 'dimensions',
				'css_var'    => '--kdna-table-image-radius',
				'units'      => array( 'px', '%' ),
				'responsive' => true,
				'default'    => null,
			),

			'image_object_fit'    => array(
				'label'       => esc_html__( 'Image Fit', 'kdna-tables' ),
				'section'     => 'cell_content',
				'type'        => 'select',
				'css_var'     => '--kdna-table-image-object-fit',
				'responsive'  => false,
				// The widget defaults this to cover. Left unset here,
				// because object-fit does nothing until a height is set and
				// defaulting it would change existing shortcodes.
				'default'     => null,
				'options'     => array(
					''        => esc_html__( '— Default —', 'kdna-tables' ),
					'cover'   => esc_html__( 'Cover', 'kdna-tables' ),
					'contain' => esc_html__( 'Contain', 'kdna-tables' ),
					'fill'    => esc_html__( 'Fill', 'kdna-tables' ),
					'none'    => esc_html__( 'None', 'kdna-tables' ),
				),
				'description' => esc_html__( 'Only has an effect once an image height is set.', 'kdna-tables' ),
			),
		);
	}

	/* ─── Section: card_stack (responsive modes) ────────────────────── */

	private static function responsive_controls() {
		return array(
			/* Card Stack */
			'card_bg'               => array(
				'label'      => esc_html__( 'Card Background', 'kdna-tables' ),
				'section'    => 'card_stack',
				'type'       => 'color',
				'css_var'    => '--kdna-card-bg',
				'responsive' => false,
				'default'    => '#ffffff',
			),

			'card_border'           => self::border_group(
				esc_html__( 'Card Border', 'kdna-tables' ),
				'card_stack',
				'--kdna-card-border'
			),

			'card_border_radius'    => array(
				'label'      => esc_html__( 'Card Border Radius', 'kdna-tables' ),
				'section'    => 'card_stack',
				'type'       => 'dimensions',
				'css_var'    => '--kdna-card-radius',
				'units'      => array( 'px', '%' ),
				'responsive' => true,
				'default'    => self::dimensions( 12, 12, 12, 12, 'px' ),
			),

			'card_padding'          => array(
				'label'      => esc_html__( 'Card Padding', 'kdna-tables' ),
				'section'    => 'card_stack',
				'type'       => 'dimensions',
				'css_var'    => '--kdna-card-padding',
				'units'      => array( 'px', 'em' ),
				'responsive' => true,
				'default'    => self::dimensions( 16, 16, 16, 16, 'px' ),
			),

			'card_spacing'          => array(
				'label'      => esc_html__( 'Card Spacing', 'kdna-tables' ),
				'section'    => 'card_stack',
				'type'       => 'slider',
				'css_var'    => '--kdna-card-spacing',
				'units'      => array( 'px' ),
				'min'        => 0,
				'max'        => 80,
				'step'       => 1,
				'responsive' => true,
				'default'    => array( 'size' => 16, 'unit' => 'px' ),
			),

			/* Pivot Rows */
			'pivot_label_typography' => self::typography_group(
				esc_html__( 'Column Heading Typography', 'kdna-tables' ),
				'card_stack',
				'--kdna-pivot-label',
				array( 'font_weight' => '700' )
			),

			'pivot_label_color'     => array(
				'label'       => esc_html__( 'Column Heading Colour', 'kdna-tables' ),
				'section'     => 'card_stack',
				'type'        => 'color',
				'css_var'     => '--kdna-pivot-label-color',
				'responsive'  => false,
				'default'     => null,
				'description' => esc_html__( 'Unset, headings take the Header Row text colour.', 'kdna-tables' ),
			),

			'pivot_heading_bg'      => array(
				'label'       => esc_html__( 'Column Heading Background', 'kdna-tables' ),
				'section'     => 'card_stack',
				'type'        => 'color',
				'css_var'     => '--kdna-pivot-heading-bg',
				'responsive'  => false,
				'default'     => null,
				'description' => esc_html__( 'Unset, headings take the Header Row background.', 'kdna-tables' ),
			),

			'pivot_heading_padding' => array(
				'label'      => esc_html__( 'Column Heading Padding', 'kdna-tables' ),
				'section'    => 'card_stack',
				'type'       => 'dimensions',
				'css_var'    => '--kdna-pivot-heading-padding',
				'units'      => array( 'px', 'em' ),
				'responsive' => true,
				'default'    => self::dimensions( 0, 0, 0, 0, 'px' ),
			),

			'pivot_heading_radius'  => array(
				'label'      => esc_html__( 'Column Heading Radius', 'kdna-tables' ),
				'section'    => 'card_stack',
				'type'       => 'dimensions',
				'css_var'    => '--kdna-pivot-heading-radius',
				'units'      => array( 'px', '%' ),
				'responsive' => true,
				'default'    => self::dimensions( 0, 0, 0, 0, 'px' ),
			),

			'pivot_heading_spacing' => array(
				'label'      => esc_html__( 'Spacing Below Heading', 'kdna-tables' ),
				'section'    => 'card_stack',
				'type'       => 'slider',
				'css_var'    => '--kdna-pivot-heading-spacing',
				'units'      => array( 'px' ),
				'min'        => 0,
				'max'        => 40,
				'step'       => 1,
				'responsive' => true,
				'default'    => array( 'size' => 2, 'unit' => 'px' ),
			),

			'pivot_label_width'     => array(
				'label'       => esc_html__( 'Inline Label Width', 'kdna-tables' ),
				'section'     => 'card_stack',
				'type'        => 'slider',
				'css_var'     => '--kdna-pivot-label-width',
				'units'       => array( '%' ),
				'min'         => 10,
				'max'         => 80,
				'step'        => 1,
				'responsive'  => true,
				'default'     => array( 'size' => 30, 'unit' => '%' ),
				'description' => esc_html__( 'Only applies to the inline label position.', 'kdna-tables' ),
			),

			'pivot_row_spacing'     => array(
				'label'      => esc_html__( 'Row Spacing', 'kdna-tables' ),
				'section'    => 'card_stack',
				'type'       => 'slider',
				'css_var'    => '--kdna-pivot-row-spacing',
				'units'      => array( 'px' ),
				'min'        => 0,
				'max'        => 80,
				'step'       => 1,
				'responsive' => true,
				'default'    => array( 'size' => 16, 'unit' => 'px' ),
			),

			/*
			 * A line trio rather than a border group: this is the single
			 * edge under each pivoted row, so a four-sided width would be
			 * both meaningless and invalid — a dimensions value fed to the
			 * border shorthand takes the whole declaration down with it.
			 */
		) + self::line_group(
			'pivot_divider',
			esc_html__( 'Row Divider', 'kdna-tables' ),
			'--kdna-pivot-divider',
			esc_html__( 'The line under each pivoted row. Unset, rows are separated by spacing alone.', 'kdna-tables' ),
			true,
			'card_stack'
		);
	}

	/* ─── Section: sticky ───────────────────────────────────────────── */

	private static function sticky_controls() {
		return array(
			'sticky_bg'           => array(
				'label'      => esc_html__( 'Background', 'kdna-tables' ),
				'section'    => 'sticky',
				'type'       => 'color',
				'css_var'    => '--kdna-sticky-bg',
				'responsive' => false,
				'default'    => '#ffffff',
			),

			'sticky_shadow_color' => array(
				'label'      => esc_html__( 'Right Edge Shadow Colour', 'kdna-tables' ),
				'section'    => 'sticky',
				'type'       => 'color',
				'css_var'    => '--kdna-sticky-shadow-color',
				'responsive' => false,
				'default'    => 'rgba(0, 0, 0, 0.08)',
			),

			'sticky_shadow_size'  => array(
				'label'      => esc_html__( 'Right Edge Shadow Size', 'kdna-tables' ),
				'section'    => 'sticky',
				'type'       => 'slider',
				'css_var'    => '--kdna-sticky-shadow-size',
				'units'      => array( 'px' ),
				'min'        => 0,
				'max'        => 40,
				'step'       => 1,
				'responsive' => true,
				'default'    => array( 'size' => 8, 'unit' => 'px' ),
			),

			'sticky_z_index'      => array(
				'label'      => esc_html__( 'Z-Index', 'kdna-tables' ),
				'section'    => 'sticky',
				'type'       => 'number',
				'css_var'    => '--kdna-sticky-z-index',
				'min'        => 0,
				'max'        => 999,
				'step'       => 1,
				'responsive' => false,
				'default'    => 2,
			),
		);
	}

	/* ─── Section: header ───────────────────────────────────────────── */

	private static function header_controls() {
		return array(
			'header_background' => self::background_group(
				esc_html__( 'Background', 'kdna-tables' ),
				'header',
				'--kdna-table-header-bg',
				'#000000'
			),

			'header_text_color' => array(
				'label'      => esc_html__( 'Text Colour', 'kdna-tables' ),
				'section'    => 'header',
				'type'       => 'color',
				'css_var'    => '--kdna-table-header-color',
				'responsive' => false,
				'default'    => '#ffffff',
			),

			'header_typography' => self::typography_group(
				esc_html__( 'Typography', 'kdna-tables' ),
				'header',
				'--kdna-table-header',
				// The only typographic property the current stylesheet
				// declares on header cells.
				array( 'font_weight' => '600' )
			),

			'header_padding'    => array(
				'label'      => esc_html__( 'Padding', 'kdna-tables' ),
				'section'    => 'header',
				'type'       => 'dimensions',
				'css_var'    => '--kdna-table-header-padding',
				'units'      => array( 'px', 'em', '%' ),
				'responsive' => true,
				'default'    => self::dimensions( 14, 16, 14, 16, 'px' ),
			),

			'header_text_align' => array(
				'label'       => esc_html__( 'Text Alignment', 'kdna-tables' ),
				'section'     => 'header',
				'type'        => 'select',
				'css_var'     => '--kdna-table-header-text-align',
				'options'     => array(
					''       => esc_html__( 'Inherit', 'kdna-tables' ),
					'left'   => esc_html__( 'Left', 'kdna-tables' ),
					'center' => esc_html__( 'Centre', 'kdna-tables' ),
					'right'  => esc_html__( 'Right', 'kdna-tables' ),
				),
				'responsive'  => true,
				'default'     => '',
				'description' => esc_html__( 'Inherit keeps the per-column and per-cell alignment.', 'kdna-tables' ),
			),
		);
	}

	/* ─── Group builders ────────────────────────────────────────────── */

	/**
	 * A typography group. Size, line height and letter spacing are the
	 * responsive fields; the rest read the same at every breakpoint.
	 *
	 * @param string $label      Admin label.
	 * @param string $section    Section key.
	 * @param string $var_prefix Custom property prefix, e.g.
	 *                           '--kdna-table-header' produces
	 *                           '--kdna-table-header-font-size'.
	 * @param array  $defaults   Field key => default, for the fields the
	 *                           current stylesheet actually declares.
	 */
	private static function typography_group( $label, $section, $var_prefix, array $defaults = array() ) {
		$field = static function ( $key, $definition ) use ( $defaults ) {
			$definition['default'] = isset( $defaults[ $key ] ) ? $defaults[ $key ] : null;
			return $definition;
		};

		return array(
			'label'      => $label,
			'section'    => $section,
			'type'       => 'typography',
			'css_var'    => null,
			'responsive' => null,
			'default'    => null,
			'fields'     => array(
				'font_family'     => $field(
					'font_family',
					array(
						'label'       => esc_html__( 'Font Family', 'kdna-tables' ),
						'type'        => 'select',
						'css_var'     => $var_prefix . '-font-family',
						'responsive'  => false,
						// Free text with suggestions rather than an
						// allow-list: options stays empty, and the datalist
						// is built from FONT_SUGGESTIONS.
						'options'     => array(),
						'free_text'   => true,
						'suggestions' => self::FONT_SUGGESTIONS,
						'description' => esc_html__( 'Web-safe stacks are suggested. Custom Elementor fonts, such as Apotheca, can be typed in by name.', 'kdna-tables' ),
					)
				),
				'font_size'       => $field(
					'font_size',
					array(
						'label'      => esc_html__( 'Size', 'kdna-tables' ),
						'type'       => 'slider',
						'css_var'    => $var_prefix . '-font-size',
						'units'      => array( 'px', 'em', 'rem' ),
						'min'        => 0,
						'max'        => 100,
						'step'       => 1,
						'responsive' => true,
					)
				),
				'font_weight'     => $field(
					'font_weight',
					array(
						'label'      => esc_html__( 'Weight', 'kdna-tables' ),
						'type'       => 'select',
						'css_var'    => $var_prefix . '-font-weight',
						'responsive' => false,
						'options'    => array(
							''       => esc_html__( 'Inherit', 'kdna-tables' ),
							'100'    => '100',
							'200'    => '200',
							'300'    => '300',
							'400'    => esc_html__( '400 (Normal)', 'kdna-tables' ),
							'500'    => '500',
							'600'    => '600',
							'700'    => esc_html__( '700 (Bold)', 'kdna-tables' ),
							'800'    => '800',
							'900'    => '900',
							'normal' => esc_html__( 'Normal', 'kdna-tables' ),
							'bold'   => esc_html__( 'Bold', 'kdna-tables' ),
						),
					)
				),
				'text_transform'  => $field(
					'text_transform',
					array(
						'label'      => esc_html__( 'Transform', 'kdna-tables' ),
						'type'       => 'select',
						'css_var'    => $var_prefix . '-text-transform',
						'responsive' => false,
						'options'    => array(
							''           => esc_html__( 'Inherit', 'kdna-tables' ),
							'none'       => esc_html__( 'None', 'kdna-tables' ),
							'uppercase'  => esc_html__( 'Uppercase', 'kdna-tables' ),
							'lowercase'  => esc_html__( 'Lowercase', 'kdna-tables' ),
							'capitalize' => esc_html__( 'Capitalise', 'kdna-tables' ),
						),
					)
				),
				'font_style'      => $field(
					'font_style',
					array(
						'label'      => esc_html__( 'Style', 'kdna-tables' ),
						'type'       => 'select',
						'css_var'    => $var_prefix . '-font-style',
						'responsive' => false,
						'options'    => array(
							''       => esc_html__( 'Inherit', 'kdna-tables' ),
							'normal' => esc_html__( 'Normal', 'kdna-tables' ),
							'italic' => esc_html__( 'Italic', 'kdna-tables' ),
							'oblique' => esc_html__( 'Oblique', 'kdna-tables' ),
						),
					)
				),
				'text_decoration' => $field(
					'text_decoration',
					array(
						'label'      => esc_html__( 'Decoration', 'kdna-tables' ),
						'type'       => 'select',
						'css_var'    => $var_prefix . '-text-decoration',
						'responsive' => false,
						'options'    => array(
							''             => esc_html__( 'Inherit', 'kdna-tables' ),
							'none'         => esc_html__( 'None', 'kdna-tables' ),
							'underline'    => esc_html__( 'Underline', 'kdna-tables' ),
							'overline'     => esc_html__( 'Overline', 'kdna-tables' ),
							'line-through' => esc_html__( 'Line Through', 'kdna-tables' ),
						),
					)
				),
				'line_height'     => $field(
					'line_height',
					array(
						'label'      => esc_html__( 'Line Height', 'kdna-tables' ),
						'type'       => 'slider',
						'css_var'    => $var_prefix . '-line-height',
						// The empty unit is a unitless multiplier, which is
						// the right default for line height.
						'units'      => array( '', 'px', 'em' ),
						'min'        => 0,
						'max'        => 10,
						'step'       => 0.1,
						'responsive' => true,
					)
				),
				'letter_spacing'  => $field(
					'letter_spacing',
					array(
						'label'      => esc_html__( 'Letter Spacing', 'kdna-tables' ),
						'type'       => 'slider',
						'css_var'    => $var_prefix . '-letter-spacing',
						'units'      => array( 'px', 'em' ),
						'min'        => -5,
						'max'        => 20,
						'step'       => 0.1,
						'responsive' => true,
					)
				),
				'word_spacing'    => $field(
					'word_spacing',
					array(
						'label'      => esc_html__( 'Word Spacing', 'kdna-tables' ),
						'type'       => 'slider',
						'css_var'    => $var_prefix . '-word-spacing',
						'units'      => array( 'px', 'em' ),
						'min'        => -5,
						'max'        => 20,
						'step'       => 0.1,
						'responsive' => false,
					)
				),
			),
		);
	}

	/**
	 * A border group: style, four-sided width, colour.
	 *
	 * Defaults to style "none" with zero width, which is what the general
	 * table renders today — the wrapper draws no frame unless asked to.
	 */
	private static function border_group( $label, $section, $var_prefix ) {
		return array(
			'label'      => $label,
			'section'    => $section,
			'type'       => 'border',
			'css_var'    => null,
			'responsive' => null,
			'default'    => null,
			'fields'     => array(
				'style' => array(
					'label'      => esc_html__( 'Style', 'kdna-tables' ),
					'type'       => 'select',
					'css_var'    => $var_prefix . '-style',
					'responsive' => false,
					'default'    => 'none',
					'options'    => array(
						''       => esc_html__( 'Inherit', 'kdna-tables' ),
						'none'   => esc_html__( 'None', 'kdna-tables' ),
						'solid'  => esc_html__( 'Solid', 'kdna-tables' ),
						'dashed' => esc_html__( 'Dashed', 'kdna-tables' ),
						'dotted' => esc_html__( 'Dotted', 'kdna-tables' ),
						'double' => esc_html__( 'Double', 'kdna-tables' ),
					),
				),
				'width' => array(
					'label'      => esc_html__( 'Width', 'kdna-tables' ),
					'type'       => 'dimensions',
					'css_var'    => $var_prefix . '-width',
					'units'      => array( 'px', 'em', '%' ),
					'responsive' => true,
					'default'    => self::dimensions( 0, 0, 0, 0, 'px' ),
				),
				'color' => array(
					'label'      => esc_html__( 'Colour', 'kdna-tables' ),
					'type'       => 'color',
					'css_var'    => $var_prefix . '-color',
					'responsive' => false,
					'default'    => '#e5e7eb',
				),
			),
		);
	}

	/**
	 * One set of rule lines: style, width, colour.
	 *
	 * Three flat controls rather than a border group, because a rule line
	 * is one edge with one width, not four sides — and because Style:
	 * None is how a set of lines is removed, so it wants to be the first
	 * thing in the row rather than buried in a group.
	 *
	 * $inherits marks the two sets that follow another when unset: the
	 * header verticals and the first column's right edge both fall back
	 * to the body verticals through a var() chain in the stylesheet.
	 * Their defaults are therefore null, so they emit nothing at all and
	 * the chain can do its work.
	 *
	 * @param string $prefix      Control key prefix.
	 * @param string $label       Human label for the set.
	 * @param string $var_prefix  Custom property prefix.
	 * @param string $description Shown under the style control.
	 * @param bool   $inherits    Whether this set follows another by default.
	 */
	private static function line_group( $prefix, $label, $var_prefix, $description = '', $inherits = false, $section = 'lines' ) {
		return array(
			$prefix . '_style' => array(
				/* translators: %s: name of the set of rule lines. */
				'label'       => sprintf( esc_html__( '%s: Style', 'kdna-tables' ), $label ),
				'section'     => $section,
				'type'        => 'select',
				'css_var'     => $var_prefix . '-style',
				'responsive'  => false,
				'default'     => $inherits ? null : 'solid',
				'options'     => array(
					''       => $inherits
						? esc_html__( '— Inherit —', 'kdna-tables' )
						: esc_html__( '— Default —', 'kdna-tables' ),
					'solid'  => esc_html__( 'Solid', 'kdna-tables' ),
					'dashed' => esc_html__( 'Dashed', 'kdna-tables' ),
					'dotted' => esc_html__( 'Dotted', 'kdna-tables' ),
					'double' => esc_html__( 'Double', 'kdna-tables' ),
					'none'   => esc_html__( 'None (hidden)', 'kdna-tables' ),
				),
				'description' => $description,
			),

			$prefix . '_width' => array(
				/* translators: %s: name of the set of rule lines. */
				'label'      => sprintf( esc_html__( '%s: Width', 'kdna-tables' ), $label ),
				'section'    => $section,
				'type'       => 'slider',
				'css_var'    => $var_prefix . '-width',
				'units'      => array( 'px' ),
				'min'        => 0,
				'max'        => 20,
				'step'       => 1,
				'responsive' => true,
				'default'    => $inherits ? null : array( 'size' => 1, 'unit' => 'px' ),
			),

			$prefix . '_color' => array(
				/* translators: %s: name of the set of rule lines. */
				'label'      => sprintf( esc_html__( '%s: Colour', 'kdna-tables' ), $label ),
				'section'    => $section,
				'type'       => 'color',
				'css_var'    => $var_prefix . '-color',
				'responsive' => false,
				'default'    => $inherits ? null : '#e5e7eb',
			),
		);
	}

	/**
	 * A background group.
	 *
	 * Colour only. Gradients are a deliberate future addition, not an
	 * oversight: they need a second colour, an angle and a type, the group
	 * would have to write background-image rather than background-color,
	 * and every rule in kdna-shortcode.css consuming the variable would
	 * have to accept an image as well as a colour. That is a change to the
	 * stylesheet's shape, not just another field here, so it is out of
	 * scope for this build.
	 */
	private static function background_group( $label, $section, $css_var, $default ) {
		return array(
			'label'      => $label,
			'section'    => $section,
			'type'       => 'background',
			'css_var'    => null,
			'responsive' => null,
			'default'    => null,
			'fields'     => array(
				'color' => array(
					'label'      => esc_html__( 'Colour', 'kdna-tables' ),
					'type'       => 'color',
					'css_var'    => $css_var,
					'responsive' => false,
					'default'    => $default,
				),
			),
		);
	}

	/**
	 * Shorthand for a dimensions value.
	 */
	private static function dimensions( $top, $right, $bottom, $left, $unit ) {
		return array(
			'top'    => $top,
			'right'  => $right,
			'bottom' => $bottom,
			'left'   => $left,
			'unit'   => $unit,
		);
	}
}
