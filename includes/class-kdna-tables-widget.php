<?php
/**
 * KDNA Table Elementor widget.
 *
 * Session 1 scope: registers the widget, hosts the Type Chooser at the top
 * of the Content tab, exposes a Change Table Type link at the bottom, and
 * dispatches render output to a placeholder template until later sessions
 * fill in the General and Comparison modes.
 *
 * @package KDNA_Tables
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KDNA_Tables_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'kdna-table';
	}

	public function get_title() {
		return esc_html__( 'KDNA Table', 'kdna-tables' );
	}

	public function get_icon() {
		return 'eicon-table';
	}

	public function get_categories() {
		return array( KDNA_Tables_Plugin::CATEGORY_SLUG );
	}

	public function get_keywords() {
		return array( 'table', 'comparison', 'data', 'pricing', 'kdna' );
	}

	/*
	 * Declaring the frontend stylesheet as a style dependency lets
	 * Elementor auto-enqueue it only on pages where the widget renders.
	 * The comparison stylesheet is added only when this instance is a
	 * comparison table, and the responsive stylesheet only when a
	 * responsive mode is active.
	 *
	 * We read raw settings via get_data('settings') rather than
	 * get_settings(). Newer Elementor builds declare
	 * sanitize_settings(array $settings): array as a strict signature
	 * and throw a TypeError when an instance's stored settings are
	 * still null (typical for a brand-new widget before any save).
	 * Raw data sidesteps the sanitiser and is sufficient for the few
	 * keys we consult here.
	 */
	public function get_style_depends() {
		$depends  = array( KDNA_Tables_Plugin::FRONTEND_STYLE_HANDLE );
		$settings = $this->kdna_raw_settings();

		if ( isset( $settings['table_type'] ) && 'comparison' === $settings['table_type'] ) {
			$depends[] = KDNA_Tables_Plugin::COMPARISON_STYLE_HANDLE;
		}

		if ( ! empty( $settings['table_type'] ) ) {
			$mode = isset( $settings['responsive_mode'] ) ? $settings['responsive_mode'] : 'card_stack';
			if ( '' !== $mode && 'none' !== $mode ) {
				$depends[] = KDNA_Tables_Plugin::RESPONSIVE_STYLE_HANDLE;
			}
		}

		return $depends;
	}

	/*
	 * Frontend JS handles the tooltip touch / keyboard interaction and
	 * the column picker. It always loads when the widget is present so
	 * tooltips remain accessible even when no responsive mode is set.
	 * It is still excluded from pages without the widget through
	 * Elementor's per-instance asset auto-loading.
	 */
	public function get_script_depends() {
		return array( KDNA_Tables_Plugin::FRONTEND_SCRIPT_HANDLE );
	}

	protected function kdna_raw_settings() {
		$raw = $this->get_data( 'settings' );
		return is_array( $raw ) ? $raw : array();
	}

	/*
	 * Under the e_optimized_markup experiment Elementor drops the
	 * .elementor-widget-container inner wrapper. The widget output is then
	 * a single .kdna-table__wrapper div, and no CSS should target the
	 * legacy container class.
	 */
	public function has_widget_inner_wrapper(): bool {
		if (
			class_exists( '\Elementor\Plugin' )
			&& isset( \Elementor\Plugin::$instance->experiments )
			&& \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_optimized_markup' )
		) {
			return false;
		}
		return true;
	}

	protected function register_controls() {
		$this->register_type_chooser_controls();
		$this->register_general_content_controls();
		$this->register_general_style_controls();
		$this->register_comparison_content_controls();
		$this->register_comparison_style_controls();
		$this->register_responsive_content_controls();
		$this->register_responsive_style_controls();
		$this->register_sticky_content_controls();
		$this->register_sticky_style_controls();
		$this->register_change_type_controls();
	}

	protected function register_sticky_content_controls() {
		$this->start_controls_section(
			'section_sticky',
			array(
				'label'     => esc_html__( 'Sticky First Column', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_CONTENT,
				'condition' => array( 'table_type!' => '' ),
			)
		);

		$this->add_control(
			'sticky_first_column',
			array(
				'label'        => esc_html__( 'Sticky First Column', 'kdna-tables' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'On', 'kdna-tables' ),
				'label_off'    => esc_html__( 'Off', 'kdna-tables' ),
				'return_value' => 'yes',
				'default'      => '',
				'description'  => esc_html__( 'Keeps the first column visible as the table scrolls horizontally on desktop. Responsive modes take precedence at their breakpoint.', 'kdna-tables' ),
			)
		);

		$this->end_controls_section();
	}

	protected function register_sticky_style_controls() {
		$this->start_controls_section(
			'section_sticky_style',
			array(
				'label'     => esc_html__( 'Sticky Column', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array(
					'table_type!'         => '',
					'sticky_first_column' => 'yes',
				),
			)
		);

		$this->add_control(
			'sticky_bg',
			array(
				'label'       => esc_html__( 'Background', 'kdna-tables' ),
				'type'        => \Elementor\Controls_Manager::COLOR,
				'selectors'   => array(
					'{{WRAPPER}}' => '--kdna-sticky-bg: {{VALUE}};',
				),
				'description' => esc_html__( 'Solid background sits behind the sticky column while the rest of the table scrolls under it.', 'kdna-tables' ),
			)
		);

		$this->add_control(
			'sticky_shadow_color',
			array(
				'label'     => esc_html__( 'Right Edge Shadow Colour', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-sticky-shadow-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'sticky_shadow_size',
			array(
				'label'      => esc_html__( 'Right Edge Shadow Size', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 40, 'step' => 1 ),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 8,
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-sticky-shadow-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'sticky_z_index',
			array(
				'label'     => esc_html__( 'Z-Index', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::NUMBER,
				'min'       => 0,
				'max'       => 100,
				'step'      => 1,
				'default'   => 2,
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-sticky-z-index: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	protected function register_type_chooser_controls() {
		$this->start_controls_section(
			'section_table_type',
			array(
				'label'     => esc_html__( 'Table Type', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_CONTENT,
				'condition' => array(
					'table_type' => '',
				),
			)
		);

		$this->add_control(
			'type_chooser_intro',
			array(
				'type' => \Elementor\Controls_Manager::RAW_HTML,
				'raw'  => '<div class="kdna-table__chooser-intro">'
					. esc_html__( 'Choose the type of table you want to build. You can switch later using the link at the bottom of this tab.', 'kdna-tables' )
					. '</div>',
			)
		);

		$this->add_control(
			'type_chooser_cards',
			array(
				'type' => \Elementor\Controls_Manager::RAW_HTML,
				'raw'  => $this->get_type_chooser_html(),
			)
		);

		$this->add_control(
			'table_type',
			array(
				'label'   => esc_html__( 'Selected table type', 'kdna-tables' ),
				'type'    => \Elementor\Controls_Manager::HIDDEN,
				'default' => '',
			)
		);

		$this->end_controls_section();
	}

	protected function register_change_type_controls() {
		$this->start_controls_section(
			'section_change_table_type',
			array(
				'label'     => esc_html__( 'Change Table Type', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_CONTENT,
				'condition' => array(
					'table_type!' => '',
				),
			)
		);

		$this->add_control(
			'change_table_type_link',
			array(
				'type' => \Elementor\Controls_Manager::RAW_HTML,
				'raw'  => '<a href="#" class="kdna-table__change-type" data-kdna-action="clear-table-type" role="button">'
					. esc_html__( 'Change table type', 'kdna-tables' )
					. '</a>'
					. '<div class="kdna-table__change-type-hint">'
					. esc_html__( 'Resets the table type and shows the chooser again. Your content controls for the current type will be cleared.', 'kdna-tables' )
					. '</div>',
			)
		);

		$this->end_controls_section();
	}

	protected function get_type_chooser_html() {
		$general_label    = esc_html__( 'General Table', 'kdna-tables' );
		$general_desc     = esc_html__( 'A clean, fully styleable table for any tabular content. Up to ten columns.', 'kdna-tables' );
		$comparison_label = esc_html__( 'Comparison Table', 'kdna-tables' );
		$comparison_desc  = esc_html__( 'Compare products or services across up to six items, with feature rows and tooltips.', 'kdna-tables' );

		ob_start();
		?>
		<div class="kdna-table__chooser" role="group" aria-label="<?php echo esc_attr__( 'Choose table type', 'kdna-tables' ); ?>">
			<button type="button" class="kdna-table__chooser-card" data-kdna-action="set-table-type" data-kdna-type="general">
				<span class="kdna-table__chooser-icon" aria-hidden="true"><i class="eicon-table"></i></span>
				<span class="kdna-table__chooser-title"><?php echo $general_label; ?></span>
				<span class="kdna-table__chooser-desc"><?php echo $general_desc; ?></span>
			</button>
			<button type="button" class="kdna-table__chooser-card" data-kdna-action="set-table-type" data-kdna-type="comparison">
				<span class="kdna-table__chooser-icon" aria-hidden="true"><i class="eicon-product-info"></i></span>
				<span class="kdna-table__chooser-title"><?php echo $comparison_label; ?></span>
				<span class="kdna-table__chooser-desc"><?php echo $comparison_desc; ?></span>
			</button>
		</div>
		<?php
		return ob_get_clean();
	}

	protected function register_general_content_controls() {
		// ─── Section: Table ───────────────────────────────────────────────
		$this->start_controls_section(
			'section_general_table',
			array(
				'label'     => esc_html__( 'Table', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_CONTENT,
				'condition' => array(
					'table_type' => 'general',
				),
			)
		);

		$this->add_control(
			'caption',
			array(
				'label'       => esc_html__( 'Caption', 'kdna-tables' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => esc_html__( 'Optional table caption', 'kdna-tables' ),
				'dynamic'     => array( 'active' => true ),
				'description' => esc_html__( 'Rendered as a <caption> element above the table.', 'kdna-tables' ),
			)
		);

		$this->add_control(
			'first_row_is_header',
			array(
				'label'        => esc_html__( 'First row is header', 'kdna-tables' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'kdna-tables' ),
				'label_off'    => esc_html__( 'No', 'kdna-tables' ),
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => esc_html__( 'When on, the first row in the Rows repeater is rendered as <thead> with <th> cells.', 'kdna-tables' ),
			)
		);

		$this->add_control(
			'first_column_is_header',
			array(
				'label'        => esc_html__( 'First column is header', 'kdna-tables' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'kdna-tables' ),
				'label_off'    => esc_html__( 'No', 'kdna-tables' ),
				'return_value' => 'yes',
				'default'      => '',
				'description'  => esc_html__( 'When on, the first cell of every row is rendered as <th scope="row">.', 'kdna-tables' ),
			)
		);

		$this->end_controls_section();

		// ─── Section: Columns ─────────────────────────────────────────────
		$this->start_controls_section(
			'section_general_columns',
			array(
				'label'     => esc_html__( 'Columns', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_CONTENT,
				'condition' => array(
					'table_type' => 'general',
				),
			)
		);

		$columns_repeater = new \Elementor\Repeater();

		$columns_repeater->add_control(
			'column_label',
			array(
				'label'   => esc_html__( 'Label', 'kdna-tables' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => esc_html__( 'Column', 'kdna-tables' ),
				'dynamic' => array( 'active' => true ),
			)
		);

		$columns_repeater->add_control(
			'column_alignment',
			array(
				'label'   => esc_html__( 'Alignment', 'kdna-tables' ),
				'type'    => \Elementor\Controls_Manager::CHOOSE,
				'options' => array(
					'left'   => array(
						'title' => esc_html__( 'Left', 'kdna-tables' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Centre', 'kdna-tables' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => esc_html__( 'Right', 'kdna-tables' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'default' => 'left',
			)
		);

		$columns_repeater->add_control(
			'column_width',
			array(
				'label'       => esc_html__( 'Width (%)', 'kdna-tables' ),
				'type'        => \Elementor\Controls_Manager::SLIDER,
				'size_units'  => array( '%' ),
				'range'       => array(
					'%' => array(
						'min'  => 0,
						'max'  => 100,
						'step' => 1,
					),
				),
				'default'     => array(
					'unit' => '%',
					'size' => 0,
				),
				'description' => esc_html__( 'Set to 0 for automatic width.', 'kdna-tables' ),
			)
		);

		$this->add_control(
			'columns',
			array(
				'label'       => esc_html__( 'Columns', 'kdna-tables' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $columns_repeater->get_controls(),
				'title_field' => '{{{ column_label }}}',
				'default'     => array(
					array(
						'column_label'     => esc_html__( 'Column 1', 'kdna-tables' ),
						'column_alignment' => 'left',
					),
					array(
						'column_label'     => esc_html__( 'Column 2', 'kdna-tables' ),
						'column_alignment' => 'left',
					),
					array(
						'column_label'     => esc_html__( 'Column 3', 'kdna-tables' ),
						'column_alignment' => 'left',
					),
				),
				'description' => esc_html__( 'Maximum 10 columns. Any beyond the tenth are ignored on the front end.', 'kdna-tables' ),
			)
		);

		$this->end_controls_section();

		// ─── Section: Rows ────────────────────────────────────────────────
		$this->start_controls_section(
			'section_general_rows',
			array(
				'label'     => esc_html__( 'Rows', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_CONTENT,
				'condition' => array(
					'table_type' => 'general',
				),
			)
		);

		$cells_repeater = new \Elementor\Repeater();

		$cells_repeater->add_control(
			'cell_type',
			array(
				'label'   => esc_html__( 'Cell type', 'kdna-tables' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array(
					'text'  => esc_html__( 'Text', 'kdna-tables' ),
					'icon'  => esc_html__( 'Icon', 'kdna-tables' ),
					'image' => esc_html__( 'Image', 'kdna-tables' ),
					'mixed' => esc_html__( 'Mixed', 'kdna-tables' ),
				),
				'default' => 'text',
			)
		);

		$cells_repeater->add_control(
			'cell_text',
			array(
				'label'      => esc_html__( 'Text', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::TEXTAREA,
				'default'    => '',
				'dynamic'    => array( 'active' => true ),
				'conditions' => array(
					'relation' => 'or',
					'terms'    => array(
						array(
							'name'     => 'cell_type',
							'operator' => '==',
							'value'    => 'text',
						),
						array(
							'name'     => 'cell_type',
							'operator' => '==',
							'value'    => 'mixed',
						),
					),
				),
			)
		);

		$cells_repeater->add_control(
			'cell_icon',
			array(
				'label'            => esc_html__( 'Icon', 'kdna-tables' ),
				'type'             => \Elementor\Controls_Manager::ICONS,
				'fa4compatibility' => 'cell_icon_fa',
				'conditions'       => array(
					'relation' => 'or',
					'terms'    => array(
						array(
							'name'     => 'cell_type',
							'operator' => '==',
							'value'    => 'icon',
						),
						array(
							'name'     => 'cell_type',
							'operator' => '==',
							'value'    => 'mixed',
						),
					),
				),
			)
		);

		$cells_repeater->add_control(
			'cell_image',
			array(
				'label'      => esc_html__( 'Image', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::MEDIA,
				'dynamic'    => array( 'active' => true ),
				'conditions' => array(
					'relation' => 'or',
					'terms'    => array(
						array(
							'name'     => 'cell_type',
							'operator' => '==',
							'value'    => 'image',
						),
						array(
							'name'     => 'cell_type',
							'operator' => '==',
							'value'    => 'mixed',
						),
					),
				),
			)
		);

		$cells_repeater->add_group_control(
			\Elementor\Group_Control_Image_Size::get_type(),
			array(
				'name'       => 'cell_image',
				'label'      => esc_html__( 'Image size', 'kdna-tables' ),
				'default'    => 'medium',
				'conditions' => array(
					'relation' => 'or',
					'terms'    => array(
						array(
							'name'     => 'cell_type',
							'operator' => '==',
							'value'    => 'image',
						),
						array(
							'name'     => 'cell_type',
							'operator' => '==',
							'value'    => 'mixed',
						),
					),
				),
			)
		);

		$cells_repeater->add_control(
			'cell_arrangement',
			array(
				'label'     => esc_html__( 'Arrangement', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => array(
					'icon-text'       => esc_html__( 'Icon, text', 'kdna-tables' ),
					'text-icon'       => esc_html__( 'Text, icon', 'kdna-tables' ),
					'icon-text-image' => esc_html__( 'Icon, text, image', 'kdna-tables' ),
					'image-text-icon' => esc_html__( 'Image, text, icon', 'kdna-tables' ),
				),
				'default'   => 'icon-text',
				'condition' => array(
					'cell_type' => 'mixed',
				),
			)
		);

		$cells_repeater->add_control(
			'cell_alignment_override',
			array(
				'label'   => esc_html__( 'Alignment override', 'kdna-tables' ),
				'type'    => \Elementor\Controls_Manager::CHOOSE,
				'options' => array(
					'inherit' => array(
						'title' => esc_html__( 'Inherit', 'kdna-tables' ),
						'icon'  => 'eicon-undo',
					),
					'left'    => array(
						'title' => esc_html__( 'Left', 'kdna-tables' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center'  => array(
						'title' => esc_html__( 'Centre', 'kdna-tables' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'   => array(
						'title' => esc_html__( 'Right', 'kdna-tables' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'default' => 'inherit',
			)
		);

		$rows_repeater = new \Elementor\Repeater();

		$rows_repeater->add_control(
			'row_label',
			array(
				'label'       => esc_html__( 'Row label (internal)', 'kdna-tables' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => esc_html__( 'Row', 'kdna-tables' ),
				'description' => esc_html__( 'Used only as the editor title for this row. Not rendered on the front end.', 'kdna-tables' ),
			)
		);

		/*
		 * Cells are stored as a nested REPEATER inside each row. One cell
		 * per column, ordered. Rendering pads any missing cells with empty
		 * <td> elements so the structure stays intact when a row is short.
		 */
		$rows_repeater->add_control(
			'cells',
			array(
				'label'       => esc_html__( 'Cells', 'kdna-tables' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $cells_repeater->get_controls(),
				'title_field' => '<# if ( cell_text ) { #>{{{ cell_text }}}<# } else { #>{{{ cell_type }}}<# } #>',
				'description' => esc_html__( 'Maximum 10 cells per row, in column order.', 'kdna-tables' ),
			)
		);

		$this->add_control(
			'rows',
			array(
				'label'       => esc_html__( 'Rows', 'kdna-tables' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $rows_repeater->get_controls(),
				'title_field' => '{{{ row_label }}}',
				'default'     => array(
					array(
						'row_label' => esc_html__( 'Heading row', 'kdna-tables' ),
						'cells'     => array(
							array(
								'cell_type' => 'text',
								'cell_text' => esc_html__( 'Heading 1', 'kdna-tables' ),
							),
							array(
								'cell_type' => 'text',
								'cell_text' => esc_html__( 'Heading 2', 'kdna-tables' ),
							),
							array(
								'cell_type' => 'text',
								'cell_text' => esc_html__( 'Heading 3', 'kdna-tables' ),
							),
						),
					),
					array(
						'row_label' => esc_html__( 'Row 1', 'kdna-tables' ),
						'cells'     => array(
							array(
								'cell_type' => 'text',
								'cell_text' => esc_html__( 'Cell 1', 'kdna-tables' ),
							),
							array(
								'cell_type' => 'text',
								'cell_text' => esc_html__( 'Cell 2', 'kdna-tables' ),
							),
							array(
								'cell_type' => 'text',
								'cell_text' => esc_html__( 'Cell 3', 'kdna-tables' ),
							),
						),
					),
					array(
						'row_label' => esc_html__( 'Row 2', 'kdna-tables' ),
						'cells'     => array(
							array(
								'cell_type' => 'text',
								'cell_text' => esc_html__( 'Cell 4', 'kdna-tables' ),
							),
							array(
								'cell_type' => 'text',
								'cell_text' => esc_html__( 'Cell 5', 'kdna-tables' ),
							),
							array(
								'cell_type' => 'text',
								'cell_text' => esc_html__( 'Cell 6', 'kdna-tables' ),
							),
						),
					),
				),
			)
		);

		$this->end_controls_section();
	}

	protected function register_general_style_controls() {
		$general_condition = array( 'table_type' => 'general' );

		// ─── Section: Table Wrapper ───────────────────────────────────────
		$this->start_controls_section(
			'section_general_style_wrapper',
			array(
				'label'     => esc_html__( 'Table Wrapper', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => $general_condition,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'     => 'wrapper_background',
				'label'    => esc_html__( 'Background', 'kdna-tables' ),
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .kdna-table__wrapper--general',
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'wrapper_border',
				'selector' => '{{WRAPPER}} .kdna-table__wrapper--general',
			)
		);

		$this->add_responsive_control(
			'wrapper_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-table-border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'wrapper_box_shadow',
				'selector' => '{{WRAPPER}} .kdna-table__wrapper--general',
			)
		);

		$this->add_responsive_control(
			'wrapper_max_width',
			array(
				'label'      => esc_html__( 'Max Width', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array(
					'px' => array( 'min' => 100, 'max' => 1600, 'step' => 1 ),
					'%'  => array( 'min' => 1, 'max' => 100, 'step' => 1 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-table__wrapper--general' => 'max-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'wrapper_alignment',
			array(
				'label'                => esc_html__( 'Alignment', 'kdna-tables' ),
				'type'                 => \Elementor\Controls_Manager::CHOOSE,
				'options'              => array(
					'left'   => array(
						'title' => esc_html__( 'Left', 'kdna-tables' ),
						'icon'  => 'eicon-h-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Centre', 'kdna-tables' ),
						'icon'  => 'eicon-h-align-center',
					),
					'right'  => array(
						'title' => esc_html__( 'Right', 'kdna-tables' ),
						'icon'  => 'eicon-h-align-right',
					),
				),
				'default'              => 'center',
				'selectors_dictionary' => array(
					'left'   => 'margin-right: auto; margin-left: 0;',
					'center' => 'margin-left: auto; margin-right: auto;',
					'right'  => 'margin-left: auto; margin-right: 0;',
				),
				'selectors'            => array(
					'{{WRAPPER}} .kdna-table__wrapper--general' => '{{VALUE}}',
				),
				'condition'            => array(
					'wrapper_max_width[size]!' => '',
				),
			)
		);

		$this->add_responsive_control(
			'wrapper_padding',
			array(
				'label'      => esc_html__( 'Padding', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-table__wrapper--general' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'caption_heading',
			array(
				'label'     => esc_html__( 'Caption', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'caption_typography',
				'selector' => '{{WRAPPER}} .kdna-table--general .kdna-table__caption',
			)
		);

		$this->add_control(
			'caption_color',
			array(
				'label'     => esc_html__( 'Caption Colour', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-table--general .kdna-table__caption' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'caption_alignment',
			array(
				'label'     => esc_html__( 'Caption Alignment', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => array(
					'left'   => array(
						'title' => esc_html__( 'Left', 'kdna-tables' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Centre', 'kdna-tables' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => esc_html__( 'Right', 'kdna-tables' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'default'   => 'left',
				'selectors' => array(
					'{{WRAPPER}} .kdna-table--general .kdna-table__caption' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'caption_spacing',
			array(
				'label'      => esc_html__( 'Caption Spacing', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 100, 'step' => 1 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-table--general .kdna-table__caption' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		// ─── Section: Header Row ──────────────────────────────────────────
		$this->start_controls_section(
			'section_general_style_header',
			array(
				'label'     => esc_html__( 'Header Row', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => $general_condition,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'     => 'header_bg',
				'label'    => esc_html__( 'Background', 'kdna-tables' ),
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .kdna-table--general thead .kdna-table__cell',
			)
		);

		$this->add_control(
			'header_text_color',
			array(
				'label'     => esc_html__( 'Text Colour', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-table-header-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'header_typography',
				'selector' => '{{WRAPPER}} .kdna-table--general thead .kdna-table__cell',
			)
		);

		$this->add_responsive_control(
			'header_padding',
			array(
				'label'      => esc_html__( 'Padding', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-table-header-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'header_text_align',
			array(
				'label'                => esc_html__( 'Text Alignment', 'kdna-tables' ),
				'type'                 => \Elementor\Controls_Manager::CHOOSE,
				'options'              => array(
					'inherit' => array(
						'title' => esc_html__( 'Inherit', 'kdna-tables' ),
						'icon'  => 'eicon-undo',
					),
					'left'    => array(
						'title' => esc_html__( 'Left', 'kdna-tables' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center'  => array(
						'title' => esc_html__( 'Centre', 'kdna-tables' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'   => array(
						'title' => esc_html__( 'Right', 'kdna-tables' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'default'              => 'inherit',
				'selectors_dictionary' => array(
					'inherit' => '',
				),
				'selectors'            => array(
					'{{WRAPPER}} .kdna-table--general thead .kdna-table__cell' => 'text-align: {{VALUE}};',
				),
				'description'          => esc_html__( 'Inherit keeps per-column and per-cell alignment.', 'kdna-tables' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'           => 'header_border',
				'label'          => esc_html__( 'Bottom Edge Border', 'kdna-tables' ),
				'selector'       => '{{WRAPPER}} .kdna-table--general thead .kdna-table__cell',
				'fields_options' => array(
					'border' => array(
						'selectors' => array(
							'{{SELECTOR}}' => 'border-bottom-style: {{VALUE}};',
						),
					),
					'width'  => array(
						'selectors' => array(
							'{{SELECTOR}}' => 'border-bottom-width: {{TOP}}{{UNIT}};',
						),
					),
					'color'  => array(
						'selectors' => array(
							'{{SELECTOR}}' => 'border-bottom-color: {{VALUE}};',
						),
					),
				),
			)
		);

		$this->end_controls_section();

		// ─── Section: First Column ────────────────────────────────────────
		$this->start_controls_section(
			'section_general_style_first_col',
			array(
				'label'     => esc_html__( 'First Column', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array(
					'table_type'             => 'general',
					'first_column_is_header' => 'yes',
				),
			)
		);

		$this->add_control(
			'first_col_bg',
			array(
				'label'     => esc_html__( 'Background Colour', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-table-first-col-bg: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'first_col_text_color',
			array(
				'label'     => esc_html__( 'Text Colour', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-table-first-col-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'first_col_typography',
				'selector' => '{{WRAPPER}} .kdna-table--general .kdna-table__cell--row-header',
			)
		);

		$this->add_responsive_control(
			'first_col_padding',
			array(
				'label'      => esc_html__( 'Padding', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-table--general .kdna-table__cell--row-header' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'           => 'first_col_separator_border',
				'label'          => esc_html__( 'Right Edge Border', 'kdna-tables' ),
				'selector'       => '{{WRAPPER}} .kdna-table--general .kdna-table__cell--row-header',
				'fields_options' => array(
					'border' => array(
						'selectors' => array(
							'{{SELECTOR}}' => 'border-right-style: {{VALUE}};',
						),
					),
					'width'  => array(
						'selectors' => array(
							'{{SELECTOR}}' => 'border-right-width: {{TOP}}{{UNIT}};',
						),
					),
					'color'  => array(
						'selectors' => array(
							'{{SELECTOR}}' => 'border-right-color: {{VALUE}};',
						),
					),
				),
			)
		);

		$this->end_controls_section();

		// ─── Section: Body Cells ──────────────────────────────────────────
		$this->start_controls_section(
			'section_general_style_body',
			array(
				'label'     => esc_html__( 'Body Cells', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => $general_condition,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'     => 'body_bg_odd',
				'label'    => esc_html__( 'Odd Row Background', 'kdna-tables' ),
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .kdna-table--general tbody .kdna-table__row--odd > .kdna-table__cell',
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'     => 'body_bg_even',
				'label'    => esc_html__( 'Even Row Background', 'kdna-tables' ),
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .kdna-table--general tbody .kdna-table__row--even > .kdna-table__cell',
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'body_typography',
				'selector' => '{{WRAPPER}} .kdna-table--general tbody .kdna-table__cell',
			)
		);

		$this->add_control(
			'body_text_color',
			array(
				'label'     => esc_html__( 'Text Colour', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-table-body-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'body_padding',
			array(
				'label'      => esc_html__( 'Padding', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-table-body-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'body_text_align',
			array(
				'label'                => esc_html__( 'Text Alignment', 'kdna-tables' ),
				'type'                 => \Elementor\Controls_Manager::CHOOSE,
				'options'              => array(
					'inherit' => array(
						'title' => esc_html__( 'Inherit', 'kdna-tables' ),
						'icon'  => 'eicon-undo',
					),
					'left'    => array(
						'title' => esc_html__( 'Left', 'kdna-tables' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center'  => array(
						'title' => esc_html__( 'Centre', 'kdna-tables' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'   => array(
						'title' => esc_html__( 'Right', 'kdna-tables' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'default'              => 'inherit',
				'selectors_dictionary' => array(
					'inherit' => '',
				),
				'selectors'            => array(
					'{{WRAPPER}} .kdna-table--general tbody .kdna-table__cell' => 'text-align: {{VALUE}};',
				),
				'description'          => esc_html__( 'Inherit keeps per-column and per-cell alignment.', 'kdna-tables' ),
			)
		);

		$this->add_control(
			'cell_border_per_side',
			array(
				'label'        => esc_html__( 'Border Per Side', 'kdna-tables' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'On', 'kdna-tables' ),
				'label_off'    => esc_html__( 'Off', 'kdna-tables' ),
				'return_value' => 'yes',
				'default'      => '',
				'separator'    => 'before',
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'      => 'cell_border',
				'selector'  => '{{WRAPPER}} .kdna-table--general .kdna-table__cell',
				'condition' => array(
					'cell_border_per_side!' => 'yes',
				),
			)
		);

		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array(
					'name'           => 'cell_border_' . $side,
					/* translators: %s: side of the cell border (top, right, bottom, left). */
					'label'          => sprintf( esc_html__( '%s Border', 'kdna-tables' ), ucfirst( $side ) ),
					'selector'       => '{{WRAPPER}} .kdna-table--general .kdna-table__cell',
					'fields_options' => array(
						'border' => array(
							'selectors' => array(
								'{{SELECTOR}}' => 'border-' . $side . '-style: {{VALUE}};',
							),
						),
						'width'  => array(
							'selectors' => array(
								'{{SELECTOR}}' => 'border-' . $side . '-width: {{TOP}}{{UNIT}};',
							),
						),
						'color'  => array(
							'selectors' => array(
								'{{SELECTOR}}' => 'border-' . $side . '-color: {{VALUE}};',
							),
						),
					),
					'condition'      => array(
						'cell_border_per_side' => 'yes',
					),
				)
			);
		}

		$this->add_control(
			'row_hover_bg',
			array(
				'label'     => esc_html__( 'Row Hover Background', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'separator' => 'before',
				'selectors' => array(
					'{{WRAPPER}} .kdna-table--general tbody .kdna-table__row:hover > .kdna-table__cell' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'row_hover_transition_duration',
			array(
				'label'       => esc_html__( 'Hover Transition (ms)', 'kdna-tables' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 0,
				'max'         => 2000,
				'step'        => 10,
				'default'     => 200,
				'selectors'   => array(
					'{{WRAPPER}}' => '--kdna-table-row-hover-transition: {{VALUE}}ms;',
				),
				'description' => esc_html__( 'Duration applied to row hover background transitions.', 'kdna-tables' ),
			)
		);

		$this->end_controls_section();

		// ─── Section: Cell Content ────────────────────────────────────────
		$this->start_controls_section(
			'section_general_style_content',
			array(
				'label'     => esc_html__( 'Cell Content', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => $general_condition,
			)
		);

		$this->add_responsive_control(
			'icon_size',
			array(
				'label'      => esc_html__( 'Icon Size', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array( 'min' => 8, 'max' => 200, 'step' => 1 ),
					'em' => array( 'min' => 0.5, 'max' => 10, 'step' => 0.1 ),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 24,
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-table-icon-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'icon_color',
			array(
				'label'     => esc_html__( 'Icon Colour', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-table-icon-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'icon_color_hover',
			array(
				'label'     => esc_html__( 'Icon Hover Colour', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-table--general .kdna-table__cell:hover .kdna-table__cell-icon' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'icon_spacing',
			array(
				'label'      => esc_html__( 'Icon Spacing', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 60, 'step' => 1 ),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-table-cell-gap: {{SIZE}}{{UNIT}};',
				),
				'description' => esc_html__( 'Gap between an icon and adjacent text or image in a mixed cell.', 'kdna-tables' ),
			)
		);

		$this->add_responsive_control(
			'image_width',
			array(
				'label'      => esc_html__( 'Image Width', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array(
					'px' => array( 'min' => 10, 'max' => 600, 'step' => 1 ),
					'%'  => array( 'min' => 1, 'max' => 100, 'step' => 1 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-table--general .kdna-table__cell-image img' => 'width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'image_height',
			array(
				'label'      => esc_html__( 'Image Height', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array(
					'px' => array( 'min' => 10, 'max' => 600, 'step' => 1 ),
					'%'  => array( 'min' => 1, 'max' => 100, 'step' => 1 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-table--general .kdna-table__cell-image img' => 'height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'image_border_radius',
			array(
				'label'      => esc_html__( 'Image Border Radius', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-table--general .kdna-table__cell-image img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'image_object_fit',
			array(
				'label'     => esc_html__( 'Image Fit', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => array(
					'cover'   => esc_html__( 'Cover', 'kdna-tables' ),
					'contain' => esc_html__( 'Contain', 'kdna-tables' ),
					'fill'    => esc_html__( 'Fill', 'kdna-tables' ),
					'none'    => esc_html__( 'None', 'kdna-tables' ),
				),
				'default'   => 'cover',
				'selectors' => array(
					'{{WRAPPER}} .kdna-table--general .kdna-table__cell-image img' => 'object-fit: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();

		// ─── Section: Table Layout ────────────────────────────────────────
		$this->start_controls_section(
			'section_general_style_layout',
			array(
				'label'     => esc_html__( 'Table Layout', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => $general_condition,
			)
		);

		$this->add_control(
			'border_collapse',
			array(
				'label'                => esc_html__( 'Border Collapse', 'kdna-tables' ),
				'type'                 => \Elementor\Controls_Manager::SWITCHER,
				'label_on'             => esc_html__( 'On', 'kdna-tables' ),
				'label_off'            => esc_html__( 'Off', 'kdna-tables' ),
				'return_value'         => 'yes',
				'default'              => 'yes',
				'selectors_dictionary' => array(
					'yes' => 'collapse',
					''    => 'separate',
				),
				'selectors'            => array(
					'{{WRAPPER}} .kdna-table--general' => 'border-collapse: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'border_spacing',
			array(
				'label'      => esc_html__( 'Border Spacing', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 30, 'step' => 1 ),
				),
				'condition'  => array(
					'border_collapse' => '',
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-table--general' => 'border-spacing: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	protected function register_comparison_content_controls() {
		$comparison_condition = array( 'table_type' => 'comparison' );

		// ─── Section: Compared Items ──────────────────────────────────────
		$this->start_controls_section(
			'section_comparison_items',
			array(
				'label'     => esc_html__( 'Compared Items', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_CONTENT,
				'condition' => $comparison_condition,
			)
		);

		$item_repeater = new \Elementor\Repeater();

		$item_repeater->add_control(
			'item_image',
			array(
				'label'   => esc_html__( 'Image', 'kdna-tables' ),
				'type'    => \Elementor\Controls_Manager::MEDIA,
				'dynamic' => array( 'active' => true ),
			)
		);

		$item_repeater->add_group_control(
			\Elementor\Group_Control_Image_Size::get_type(),
			array(
				'name'    => 'item_image',
				'label'   => esc_html__( 'Image Size', 'kdna-tables' ),
				'default' => 'medium',
			)
		);

		$item_repeater->add_control(
			'item_label',
			array(
				'label'   => esc_html__( 'Label', 'kdna-tables' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => esc_html__( 'Item', 'kdna-tables' ),
				'dynamic' => array( 'active' => true ),
			)
		);

		$item_repeater->add_control(
			'item_sublabel',
			array(
				'label'       => esc_html__( 'Sublabel', 'kdna-tables' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => esc_html__( 'Optional sublabel', 'kdna-tables' ),
				'dynamic'     => array( 'active' => true ),
			)
		);

		$item_repeater->add_control(
			'cta_enable',
			array(
				'label'        => esc_html__( 'Enable CTA', 'kdna-tables' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'On', 'kdna-tables' ),
				'label_off'    => esc_html__( 'Off', 'kdna-tables' ),
				'return_value' => 'yes',
				'default'      => '',
				'separator'    => 'before',
			)
		);

		$item_repeater->add_control(
			'cta_text',
			array(
				'label'     => esc_html__( 'CTA Text', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => esc_html__( 'Enquire', 'kdna-tables' ),
				'condition' => array( 'cta_enable' => 'yes' ),
				'dynamic'   => array( 'active' => true ),
			)
		);

		$item_repeater->add_control(
			'cta_url',
			array(
				'label'         => esc_html__( 'CTA URL', 'kdna-tables' ),
				'type'          => \Elementor\Controls_Manager::URL,
				'placeholder'   => 'https://example.com',
				'show_external' => true,
				'default'       => array(
					'url'         => '',
					'is_external' => '',
					'nofollow'    => '',
				),
				'condition'     => array( 'cta_enable' => 'yes' ),
				'dynamic'       => array( 'active' => true ),
			)
		);

		$this->add_control(
			'items',
			array(
				'label'       => esc_html__( 'Items', 'kdna-tables' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $item_repeater->get_controls(),
				'title_field' => '{{{ item_label }}}',
				'default'     => array(
					array(
						'item_label'    => 'V10',
						'item_sublabel' => esc_html__( 'Entry-level', 'kdna-tables' ),
					),
					array(
						'item_label'    => 'V20',
						'item_sublabel' => esc_html__( 'Mid-range', 'kdna-tables' ),
					),
					array(
						'item_label'    => 'V30',
						'item_sublabel' => esc_html__( 'Premium', 'kdna-tables' ),
					),
				),
				'description' => esc_html__( 'Minimum 2, maximum 6 items. Additional items are ignored on the front end.', 'kdna-tables' ),
			)
		);

		/*
		 * Hidden mirror of the items repeater length. Editor JS keeps this
		 * in sync so the per-item cell control groups inside the Feature
		 * Rows repeater can use it for visibility conditions. The renderer
		 * also caps to the live items count, so this control is purely a
		 * panel UX aid, not a data dependency.
		 */
		$this->add_control(
			'item_count',
			array(
				'label'   => esc_html__( 'Item count', 'kdna-tables' ),
				'type'    => \Elementor\Controls_Manager::HIDDEN,
				'default' => '3',
			)
		);

		$this->end_controls_section();

		// ─── Section: Highlighted Item ────────────────────────────────────
		$this->start_controls_section(
			'section_comparison_highlight',
			array(
				'label'     => esc_html__( 'Highlighted Item', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_CONTENT,
				'condition' => $comparison_condition,
			)
		);

		$this->add_control(
			'highlight_item',
			array(
				'label'   => esc_html__( 'Highlight Item', 'kdna-tables' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array(
					''  => esc_html__( 'None', 'kdna-tables' ),
					'1' => esc_html__( 'Item 1', 'kdna-tables' ),
					'2' => esc_html__( 'Item 2', 'kdna-tables' ),
					'3' => esc_html__( 'Item 3', 'kdna-tables' ),
					'4' => esc_html__( 'Item 4', 'kdna-tables' ),
					'5' => esc_html__( 'Item 5', 'kdna-tables' ),
					'6' => esc_html__( 'Item 6', 'kdna-tables' ),
				),
				'default' => '',
			)
		);

		$this->add_control(
			'highlight_badge_text',
			array(
				'label'     => esc_html__( 'Badge Text', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => esc_html__( 'Most Popular', 'kdna-tables' ),
				'condition' => array( 'highlight_item!' => '' ),
				'dynamic'   => array( 'active' => true ),
			)
		);

		$this->add_control(
			'highlight_badge_position',
			array(
				'label'     => esc_html__( 'Badge Position', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => array(
					'top-left'   => array(
						'title' => esc_html__( 'Top Left', 'kdna-tables' ),
						'icon'  => 'eicon-h-align-left',
					),
					'top-centre' => array(
						'title' => esc_html__( 'Top Centre', 'kdna-tables' ),
						'icon'  => 'eicon-h-align-center',
					),
					'top-right'  => array(
						'title' => esc_html__( 'Top Right', 'kdna-tables' ),
						'icon'  => 'eicon-h-align-right',
					),
				),
				'default'   => 'top-right',
				'condition' => array( 'highlight_item!' => '' ),
			)
		);

		$this->end_controls_section();

		// ─── Section: Cell Indicators ─────────────────────────────────────
		$this->start_controls_section(
			'section_comparison_indicators',
			array(
				'label'     => esc_html__( 'Cell Indicators', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_CONTENT,
				'condition' => $comparison_condition,
			)
		);

		$this->add_control(
			'available_icon',
			array(
				'label'   => esc_html__( 'Available Icon', 'kdna-tables' ),
				'type'    => \Elementor\Controls_Manager::ICONS,
				'default' => array(
					'value'   => 'fas fa-check',
					'library' => 'fa-solid',
				),
			)
		);

		$this->add_control(
			'unavailable_mode',
			array(
				'label'   => esc_html__( 'Unavailable Indicator', 'kdna-tables' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array(
					'icon'   => esc_html__( 'Icon', 'kdna-tables' ),
					'text'   => esc_html__( 'Text', 'kdna-tables' ),
					'hidden' => esc_html__( 'Hidden', 'kdna-tables' ),
				),
				'default' => 'icon',
			)
		);

		$this->add_control(
			'unavailable_icon',
			array(
				'label'     => esc_html__( 'Unavailable Icon', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::ICONS,
				'default'   => array(
					'value'   => 'fas fa-minus',
					'library' => 'fa-solid',
				),
				'condition' => array( 'unavailable_mode' => 'icon' ),
			)
		);

		$this->add_control(
			'unavailable_text',
			array(
				'label'     => esc_html__( 'Unavailable Text', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => '-',
				'condition' => array( 'unavailable_mode' => 'text' ),
			)
		);

		$this->end_controls_section();

		// ─── Section: Feature Rows ────────────────────────────────────────
		$this->start_controls_section(
			'section_comparison_features',
			array(
				'label'     => esc_html__( 'Feature Rows', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_CONTENT,
				'condition' => $comparison_condition,
			)
		);

		$feature_repeater = new \Elementor\Repeater();

		$feature_repeater->add_control(
			'feature_label',
			array(
				'label'   => esc_html__( 'Feature Label', 'kdna-tables' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => esc_html__( 'Feature', 'kdna-tables' ),
				'dynamic' => array( 'active' => true ),
			)
		);

		$feature_repeater->add_control(
			'feature_description',
			array(
				'label'       => esc_html__( 'Description', 'kdna-tables' ),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'default'     => '',
				'rows'        => 2,
				'placeholder' => esc_html__( 'Optional short description shown below the label.', 'kdna-tables' ),
				'dynamic'     => array( 'active' => true ),
			)
		);

		$feature_repeater->add_control(
			'feature_tooltip',
			array(
				'label'       => esc_html__( 'Tooltip', 'kdna-tables' ),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'default'     => '',
				'rows'        => 2,
				'description' => esc_html__( 'When filled, an info icon appears next to the feature label and reveals this text on hover or touch.', 'kdna-tables' ),
				'dynamic'     => array( 'active' => true ),
			)
		);

		/*
		 * Hard-capped per-item cell control groups. The brief calls for one
		 * group per maximum item slot, conditionally hidden based on item
		 * count. Conditions reference the top-level item_count setting,
		 * which the editor JS keeps in sync with the items repeater length.
		 * If Elementor's panel does not honour the cross-scope condition
		 * inside this repeater, the renderer still caps cells to the live
		 * items count so the front end is correct either way.
		 */
		for ( $n = 1; $n <= 6; $n++ ) {
			$slot_conditions = array(
				'relation' => 'and',
				'terms'    => array(
					array(
						'name'     => 'item_count',
						'operator' => '>=',
						'value'    => $n,
					),
				),
			);

			$feature_repeater->add_control(
				'cell_' . $n . '_heading',
				array(
					/* translators: %d: slot number (1 to 6). */
					'label'      => sprintf( esc_html__( 'Item %d Cell', 'kdna-tables' ), $n ),
					'type'       => \Elementor\Controls_Manager::HEADING,
					'separator'  => 'before',
					'conditions' => $slot_conditions,
				)
			);

			$feature_repeater->add_control(
				'cell_' . $n . '_indicator',
				array(
					'label'      => esc_html__( 'Indicator', 'kdna-tables' ),
					'type'       => \Elementor\Controls_Manager::SELECT,
					'options'    => array(
						'available'   => esc_html__( 'Available', 'kdna-tables' ),
						'unavailable' => esc_html__( 'Unavailable', 'kdna-tables' ),
						'custom'      => esc_html__( 'Custom', 'kdna-tables' ),
					),
					'default'    => 'available',
					'conditions' => $slot_conditions,
				)
			);

			$feature_repeater->add_control(
				'cell_' . $n . '_custom_type',
				array(
					'label'      => esc_html__( 'Custom Type', 'kdna-tables' ),
					'type'       => \Elementor\Controls_Manager::SELECT,
					'options'    => array(
						'text'  => esc_html__( 'Text', 'kdna-tables' ),
						'icon'  => esc_html__( 'Icon', 'kdna-tables' ),
						'image' => esc_html__( 'Image', 'kdna-tables' ),
						'mixed' => esc_html__( 'Mixed', 'kdna-tables' ),
					),
					'default'    => 'text',
					'conditions' => array(
						'relation' => 'and',
						'terms'    => array(
							array(
								'name'     => 'item_count',
								'operator' => '>=',
								'value'    => $n,
							),
							array(
								'name'     => 'cell_' . $n . '_indicator',
								'operator' => '==',
								'value'    => 'custom',
							),
						),
					),
				)
			);

			$feature_repeater->add_control(
				'cell_' . $n . '_text',
				array(
					'label'      => esc_html__( 'Text', 'kdna-tables' ),
					'type'       => \Elementor\Controls_Manager::TEXTAREA,
					'default'    => '',
					'rows'       => 2,
					'dynamic'    => array( 'active' => true ),
					'conditions' => array(
						'relation' => 'and',
						'terms'    => array(
							array(
								'name'     => 'item_count',
								'operator' => '>=',
								'value'    => $n,
							),
							array(
								'name'     => 'cell_' . $n . '_indicator',
								'operator' => '==',
								'value'    => 'custom',
							),
							array(
								'name'     => 'cell_' . $n . '_custom_type',
								'operator' => 'in',
								'value'    => array( 'text', 'mixed' ),
							),
						),
					),
				)
			);

			$feature_repeater->add_control(
				'cell_' . $n . '_icon',
				array(
					'label'      => esc_html__( 'Icon', 'kdna-tables' ),
					'type'       => \Elementor\Controls_Manager::ICONS,
					'conditions' => array(
						'relation' => 'and',
						'terms'    => array(
							array(
								'name'     => 'item_count',
								'operator' => '>=',
								'value'    => $n,
							),
							array(
								'name'     => 'cell_' . $n . '_indicator',
								'operator' => '==',
								'value'    => 'custom',
							),
							array(
								'name'     => 'cell_' . $n . '_custom_type',
								'operator' => 'in',
								'value'    => array( 'icon', 'mixed' ),
							),
						),
					),
				)
			);

			$feature_repeater->add_control(
				'cell_' . $n . '_image',
				array(
					'label'      => esc_html__( 'Image', 'kdna-tables' ),
					'type'       => \Elementor\Controls_Manager::MEDIA,
					'dynamic'    => array( 'active' => true ),
					'conditions' => array(
						'relation' => 'and',
						'terms'    => array(
							array(
								'name'     => 'item_count',
								'operator' => '>=',
								'value'    => $n,
							),
							array(
								'name'     => 'cell_' . $n . '_indicator',
								'operator' => '==',
								'value'    => 'custom',
							),
							array(
								'name'     => 'cell_' . $n . '_custom_type',
								'operator' => 'in',
								'value'    => array( 'image', 'mixed' ),
							),
						),
					),
				)
			);

			$feature_repeater->add_group_control(
				\Elementor\Group_Control_Image_Size::get_type(),
				array(
					'name'       => 'cell_' . $n . '_image',
					'label'      => esc_html__( 'Image Size', 'kdna-tables' ),
					'default'    => 'medium',
					'conditions' => array(
						'relation' => 'and',
						'terms'    => array(
							array(
								'name'     => 'item_count',
								'operator' => '>=',
								'value'    => $n,
							),
							array(
								'name'     => 'cell_' . $n . '_indicator',
								'operator' => '==',
								'value'    => 'custom',
							),
							array(
								'name'     => 'cell_' . $n . '_custom_type',
								'operator' => 'in',
								'value'    => array( 'image', 'mixed' ),
							),
						),
					),
				)
			);

			$feature_repeater->add_control(
				'cell_' . $n . '_arrangement',
				array(
					'label'      => esc_html__( 'Arrangement', 'kdna-tables' ),
					'type'       => \Elementor\Controls_Manager::SELECT,
					'options'    => array(
						'icon-text'       => esc_html__( 'Icon, text', 'kdna-tables' ),
						'text-icon'       => esc_html__( 'Text, icon', 'kdna-tables' ),
						'icon-text-image' => esc_html__( 'Icon, text, image', 'kdna-tables' ),
						'image-text-icon' => esc_html__( 'Image, text, icon', 'kdna-tables' ),
					),
					'default'    => 'icon-text',
					'conditions' => array(
						'relation' => 'and',
						'terms'    => array(
							array(
								'name'     => 'item_count',
								'operator' => '>=',
								'value'    => $n,
							),
							array(
								'name'     => 'cell_' . $n . '_indicator',
								'operator' => '==',
								'value'    => 'custom',
							),
							array(
								'name'     => 'cell_' . $n . '_custom_type',
								'operator' => '==',
								'value'    => 'mixed',
							),
						),
					),
				)
			);
		}

		$this->add_control(
			'feature_rows',
			array(
				'label'       => esc_html__( 'Feature Rows', 'kdna-tables' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $feature_repeater->get_controls(),
				'title_field' => '{{{ feature_label }}}',
				'default'     => array(
					array(
						'feature_label'       => 'V-FR',
						'feature_description' => esc_html__( 'Fractional RF', 'kdna-tables' ),
						'cell_1_indicator'    => 'available',
						'cell_2_indicator'    => 'available',
						'cell_3_indicator'    => 'available',
					),
					array(
						'feature_label'       => 'V-IPL',
						'feature_description' => esc_html__( 'Intense Pulsed Light', 'kdna-tables' ),
						'cell_1_indicator'    => 'unavailable',
						'cell_2_indicator'    => 'available',
						'cell_3_indicator'    => 'available',
					),
					array(
						'feature_label'       => 'V-NIR',
						'feature_description' => esc_html__( 'Near Infrared', 'kdna-tables' ),
						'cell_1_indicator'    => 'unavailable',
						'cell_2_indicator'    => 'unavailable',
						'cell_3_indicator'    => 'available',
					),
					array(
						'feature_label'       => 'V-Tone',
						'feature_description' => esc_html__( 'Skin tone treatment', 'kdna-tables' ),
						'cell_1_indicator'    => 'available',
						'cell_2_indicator'    => 'available',
						'cell_3_indicator'    => 'available',
					),
					array(
						'feature_label'       => 'V-Lift',
						'feature_description' => esc_html__( 'Lifting protocol', 'kdna-tables' ),
						'cell_1_indicator'    => 'unavailable',
						'cell_2_indicator'    => 'unavailable',
						'cell_3_indicator'    => 'available',
					),
				),
			)
		);

		$this->end_controls_section();
	}

	protected function register_comparison_style_controls() {
		$cmp_condition         = array( 'table_type' => 'comparison' );
		$highlight_selector    = '{{WRAPPER}} .kdna-comparison--has-highlight .kdna-comparison__cell--highlighted';
		$highlight_card_sel    = '{{WRAPPER}} .kdna-comparison--has-highlight .kdna-comparison__item--highlighted';
		$wrapper_selector      = '{{WRAPPER}} .kdna-table__wrapper--comparison';

		// ─── Section: Table Wrapper ───────────────────────────────────────
		$this->start_controls_section(
			'section_comparison_style_wrapper',
			array(
				'label'     => esc_html__( 'Table Wrapper', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => $cmp_condition,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'     => 'cmp_wrapper_background',
				'label'    => esc_html__( 'Background', 'kdna-tables' ),
				'types'    => array( 'classic', 'gradient' ),
				'selector' => $wrapper_selector,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'cmp_wrapper_border',
				'selector' => $wrapper_selector,
			)
		);

		$this->add_responsive_control(
			'cmp_wrapper_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-comparison-border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'cmp_wrapper_box_shadow',
				'selector' => $wrapper_selector,
			)
		);

		$this->add_responsive_control(
			'cmp_wrapper_padding',
			array(
				'label'      => esc_html__( 'Padding', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					$wrapper_selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'cmp_wrapper_max_width',
			array(
				'label'      => esc_html__( 'Max Width', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array(
					'px' => array( 'min' => 100, 'max' => 1600, 'step' => 1 ),
					'%'  => array( 'min' => 1, 'max' => 100, 'step' => 1 ),
				),
				'selectors'  => array(
					$wrapper_selector => 'max-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'cmp_wrapper_alignment',
			array(
				'label'                => esc_html__( 'Alignment', 'kdna-tables' ),
				'type'                 => \Elementor\Controls_Manager::CHOOSE,
				'options'              => array(
					'left'   => array(
						'title' => esc_html__( 'Left', 'kdna-tables' ),
						'icon'  => 'eicon-h-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Centre', 'kdna-tables' ),
						'icon'  => 'eicon-h-align-center',
					),
					'right'  => array(
						'title' => esc_html__( 'Right', 'kdna-tables' ),
						'icon'  => 'eicon-h-align-right',
					),
				),
				'default'              => 'center',
				'selectors_dictionary' => array(
					'left'   => 'margin-right: auto; margin-left: 0;',
					'center' => 'margin-left: auto; margin-right: auto;',
					'right'  => 'margin-left: auto; margin-right: 0;',
				),
				'selectors'            => array(
					$wrapper_selector => '{{VALUE}}',
				),
				'condition'            => array(
					'cmp_wrapper_max_width[size]!' => '',
				),
			)
		);

		$this->end_controls_section();

		// ─── Section: Items Header Row ────────────────────────────────────
		$this->start_controls_section(
			'section_comparison_style_header',
			array(
				'label'     => esc_html__( 'Items Header Row', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => $cmp_condition,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'     => 'cmp_header_bg',
				'label'    => esc_html__( 'Background', 'kdna-tables' ),
				'types'    => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'default' => 'classic',
					),
					'color' => array(
						'default' => '#000000',
					),
				),
				'selector' => '{{WRAPPER}} .kdna-comparison thead .kdna-comparison__cell',
			)
		);

		$this->add_responsive_control(
			'cmp_header_padding',
			array(
				'label'      => esc_html__( 'Padding', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-comparison-header-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'cmp_header_min_height',
			array(
				'label'      => esc_html__( 'Minimum Height', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 400, 'step' => 1 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-comparison thead .kdna-comparison__cell' => 'min-height: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'cmp_header_vertical_align',
			array(
				'label'     => esc_html__( 'Vertical Alignment', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => array(
					'top'    => array(
						'title' => esc_html__( 'Top', 'kdna-tables' ),
						'icon'  => 'eicon-v-align-top',
					),
					'middle' => array(
						'title' => esc_html__( 'Middle', 'kdna-tables' ),
						'icon'  => 'eicon-v-align-middle',
					),
					'bottom' => array(
						'title' => esc_html__( 'Bottom', 'kdna-tables' ),
						'icon'  => 'eicon-v-align-bottom',
					),
				),
				'default'   => 'bottom',
				'selectors' => array(
					'{{WRAPPER}} .kdna-comparison thead .kdna-comparison__cell' => 'vertical-align: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();

		// ─── Section: Item Card ───────────────────────────────────────────
		$this->start_controls_section(
			'section_comparison_style_item_card',
			array(
				'label'     => esc_html__( 'Item Card', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => $cmp_condition,
			)
		);

		$this->add_responsive_control(
			'item_image_width',
			array(
				'label'      => esc_html__( 'Image Width', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array(
					'px' => array( 'min' => 10, 'max' => 400, 'step' => 1 ),
					'%'  => array( 'min' => 1, 'max' => 100, 'step' => 1 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-comparison__item-image img' => 'width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'item_image_height',
			array(
				'label'      => esc_html__( 'Image Height', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array(
					'px' => array( 'min' => 10, 'max' => 400, 'step' => 1 ),
					'%'  => array( 'min' => 1, 'max' => 100, 'step' => 1 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-comparison__item-image img' => 'height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'item_image_border_radius',
			array(
				'label'      => esc_html__( 'Image Border Radius', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-comparison__item-image img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'item_image_spacing',
			array(
				'label'      => esc_html__( 'Image Spacing', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 60, 'step' => 1 ),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-comparison-item-image-spacing: {{SIZE}}{{UNIT}};',
				),
				'description' => esc_html__( 'Gap below the image, before the label.', 'kdna-tables' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'item_label_typography',
				'label'    => esc_html__( 'Label Typography', 'kdna-tables' ),
				'selector' => '{{WRAPPER}} .kdna-comparison__item-label',
			)
		);

		$this->add_control(
			'item_label_color',
			array(
				'label'     => esc_html__( 'Label Colour', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-comparison-item-label-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'item_sublabel_typography',
				'label'    => esc_html__( 'Sublabel Typography', 'kdna-tables' ),
				'selector' => '{{WRAPPER}} .kdna-comparison__item-sublabel',
			)
		);

		$this->add_control(
			'item_sublabel_color',
			array(
				'label'     => esc_html__( 'Sublabel Colour', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-comparison-item-sublabel-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'item_sublabel_spacing',
			array(
				'label'      => esc_html__( 'Sublabel Spacing', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 60, 'step' => 1 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-comparison__item-sublabel' => 'margin-top: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'item_padding',
			array(
				'label'      => esc_html__( 'Item Card Padding', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-comparison__item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'description' => esc_html__( 'Inner padding around the image, label and sublabel.', 'kdna-tables' ),
			)
		);

		$this->end_controls_section();

		// ─── Section: Highlighted Item ────────────────────────────────────
		$this->start_controls_section(
			'section_comparison_style_highlight',
			array(
				'label'     => esc_html__( 'Highlighted Item', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => $cmp_condition,
			)
		);

		$this->add_control(
			'highlight_bg',
			array(
				'label'     => esc_html__( 'Background Colour', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-comparison-highlight-bg: {{VALUE}};',
				),
				'description' => esc_html__( 'Applied to the highlighted column body cells.', 'kdna-tables' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'highlight_border',
				'selector' => $highlight_selector . ', ' . $highlight_card_sel,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'highlight_shadow',
				'selector' => $highlight_card_sel,
			)
		);

		$this->add_control(
			'highlight_scale',
			array(
				'label'   => esc_html__( 'Scale', 'kdna-tables' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 0.8,
				'max'     => 1.2,
				'step'    => 0.01,
				'default' => 1,
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-comparison-highlight-scale: {{VALUE}};',
				),
				'description' => esc_html__( 'Slight scale applied to the highlighted item card.', 'kdna-tables' ),
			)
		);

		$this->add_control(
			'badge_heading',
			array(
				'label'     => esc_html__( 'Badge', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'badge_bg',
			array(
				'label'     => esc_html__( 'Badge Background', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-comparison-badge-bg: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'badge_color',
			array(
				'label'     => esc_html__( 'Badge Text Colour', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-comparison-badge-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'badge_typography',
				'label'    => esc_html__( 'Badge Typography', 'kdna-tables' ),
				'selector' => '{{WRAPPER}} .kdna-comparison__badge',
			)
		);

		$this->add_responsive_control(
			'badge_padding',
			array(
				'label'      => esc_html__( 'Badge Padding', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-comparison__badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'badge_border',
				'selector' => '{{WRAPPER}} .kdna-comparison__badge',
			)
		);

		$this->add_responsive_control(
			'badge_border_radius',
			array(
				'label'      => esc_html__( 'Badge Border Radius', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-comparison__badge' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'badge_offset_y',
			array(
				'label'      => esc_html__( 'Badge Offset Y', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array( 'min' => -80, 'max' => 80, 'step' => 1 ),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-comparison-badge-offset-y: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		// ─── Section: Feature Rows ────────────────────────────────────────
		$this->start_controls_section(
			'section_comparison_style_rows',
			array(
				'label'     => esc_html__( 'Feature Rows', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => $cmp_condition,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'     => 'row_bg_odd',
				'label'    => esc_html__( 'Odd Row Background', 'kdna-tables' ),
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .kdna-comparison tbody .kdna-comparison__row--odd > .kdna-comparison__cell',
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'     => 'row_bg_even',
				'label'    => esc_html__( 'Even Row Background', 'kdna-tables' ),
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .kdna-comparison tbody .kdna-comparison__row--even > .kdna-comparison__cell',
			)
		);

		$this->add_responsive_control(
			'row_padding',
			array(
				'label'      => esc_html__( 'Row Padding', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-comparison-body-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'           => 'row_divider_border',
				'label'          => esc_html__( 'Row Divider Border', 'kdna-tables' ),
				'selector'       => '{{WRAPPER}} .kdna-comparison tbody .kdna-comparison__cell',
				'fields_options' => array(
					'border' => array(
						'selectors' => array(
							'{{SELECTOR}}' => 'border-bottom-style: {{VALUE}};',
						),
					),
					'width'  => array(
						'selectors' => array(
							'{{SELECTOR}}' => 'border-bottom-width: {{TOP}}{{UNIT}};',
						),
					),
					'color'  => array(
						'selectors' => array(
							'{{SELECTOR}}' => 'border-bottom-color: {{VALUE}};',
						),
					),
				),
			)
		);

		$this->add_control(
			'row_hover_bg',
			array(
				'label'     => esc_html__( 'Row Hover Background', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'separator' => 'before',
				'selectors' => array(
					'{{WRAPPER}} .kdna-comparison tbody .kdna-comparison__row:hover > .kdna-comparison__cell' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'row_hover_transition_duration',
			array(
				'label'       => esc_html__( 'Hover Transition (ms)', 'kdna-tables' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 0,
				'max'         => 2000,
				'step'        => 10,
				'default'     => 200,
				'selectors'   => array(
					'{{WRAPPER}}' => '--kdna-comparison-row-hover-transition: {{VALUE}}ms;',
				),
			)
		);

		$this->end_controls_section();

		// ─── Section: Feature Label Column ───────────────────────────────
		$this->start_controls_section(
			'section_comparison_style_label_col',
			array(
				'label'     => esc_html__( 'Feature Label Column', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => $cmp_condition,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'     => 'label_bg',
				'label'    => esc_html__( 'Background', 'kdna-tables' ),
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .kdna-comparison tbody .kdna-comparison__cell--label',
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'feature_label_typography',
				'label'    => esc_html__( 'Label Typography', 'kdna-tables' ),
				'selector' => '{{WRAPPER}} .kdna-comparison__feature-label',
			)
		);

		$this->add_control(
			'feature_label_color',
			array(
				'label'     => esc_html__( 'Label Colour', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-comparison-label-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'feature_description_typography',
				'label'    => esc_html__( 'Description Typography', 'kdna-tables' ),
				'selector' => '{{WRAPPER}} .kdna-comparison__feature-description',
			)
		);

		$this->add_control(
			'feature_description_color',
			array(
				'label'     => esc_html__( 'Description Colour', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-comparison-label-description-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'feature_description_spacing',
			array(
				'label'      => esc_html__( 'Description Spacing', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 40, 'step' => 1 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-comparison__feature-description' => 'margin-top: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'feature_label_alignment',
			array(
				'label'     => esc_html__( 'Label Alignment', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => array(
					'left'   => array(
						'title' => esc_html__( 'Left', 'kdna-tables' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Centre', 'kdna-tables' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => esc_html__( 'Right', 'kdna-tables' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'default'   => 'left',
				'selectors' => array(
					'{{WRAPPER}} .kdna-comparison tbody .kdna-comparison__cell--label' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'info_icon_heading',
			array(
				'label'     => esc_html__( 'Info Icon', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'info_icon_color',
			array(
				'label'     => esc_html__( 'Icon Colour', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-comparison__tooltip-icon' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'info_icon_size',
			array(
				'label'      => esc_html__( 'Icon Size', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array( 'min' => 8, 'max' => 40, 'step' => 1 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-comparison__tooltip-icon' => 'font-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'info_icon_spacing',
			array(
				'label'      => esc_html__( 'Icon Spacing', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 40, 'step' => 1 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-comparison__tooltip-wrap' => 'margin-left: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		// ─── Section: Available Indicator ─────────────────────────────────
		$this->start_controls_section(
			'section_comparison_style_available',
			array(
				'label'     => esc_html__( 'Available Indicator', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => $cmp_condition,
			)
		);

		$this->add_control(
			'available_icon_color',
			array(
				'label'     => esc_html__( 'Icon Colour', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-comparison-available-color: {{VALUE}};',
				),
				'description' => esc_html__( 'Colour of the tick or check icon. Baseline default is white so it reads on the blue circle background.', 'kdna-tables' ),
			)
		);

		$this->add_responsive_control(
			'available_icon_size',
			array(
				'label'      => esc_html__( 'Icon Size', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array( 'min' => 8, 'max' => 80, 'step' => 1 ),
					'em' => array( 'min' => 0.5, 'max' => 4, 'step' => 0.1 ),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 26,
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-comparison-available-icon-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'available_icon_bg',
			array(
				'label'     => esc_html__( 'Background Colour', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-comparison-available-bg: {{VALUE}};',
				),
				'description' => esc_html__( 'Background colour of the shape behind the icon.', 'kdna-tables' ),
			)
		);

		$this->add_responsive_control(
			'available_icon_bg_size',
			array(
				'label'      => esc_html__( 'Shape Size', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array( 'min' => 10, 'max' => 120, 'step' => 1 ),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-comparison-available-shape-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'available_icon_bg_shape',
			array(
				'label'                => esc_html__( 'Shape', 'kdna-tables' ),
				'type'                 => \Elementor\Controls_Manager::CHOOSE,
				'options'              => array(
					'circle'         => array(
						'title' => esc_html__( 'Circle', 'kdna-tables' ),
						'icon'  => 'eicon-circle',
					),
					'square'         => array(
						'title' => esc_html__( 'Square', 'kdna-tables' ),
						'icon'  => 'eicon-square',
					),
					'rounded-square' => array(
						'title' => esc_html__( 'Rounded Square', 'kdna-tables' ),
						'icon'  => 'eicon-frame-expand',
					),
					'none'           => array(
						'title' => esc_html__( 'None', 'kdna-tables' ),
						'icon'  => 'eicon-ban',
					),
				),
				'default'              => 'circle',
				'selectors_dictionary' => array(
					'circle'         => '50%',
					'square'         => '0',
					'rounded-square' => '8px',
					'none'           => '0',
				),
				'selectors'            => array(
					'{{WRAPPER}}' => '--kdna-comparison-available-shape-radius: {{VALUE}};',
				),
				'description' => esc_html__( 'Pick a shape, or set Background to transparent for no shape behind the icon.', 'kdna-tables' ),
			)
		);

		$this->end_controls_section();

		// ─── Section: Unavailable Indicator ───────────────────────────────
		$this->start_controls_section(
			'section_comparison_style_unavailable',
			array(
				'label'     => esc_html__( 'Unavailable Indicator', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => $cmp_condition,
			)
		);

		$this->add_control(
			'unavailable_color',
			array(
				'label'     => esc_html__( 'Colour', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-comparison-unavailable-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'unavailable_size',
			array(
				'label'      => esc_html__( 'Size', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array( 'min' => 8, 'max' => 60, 'step' => 1 ),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-comparison-unavailable-size: {{SIZE}}{{UNIT}};',
				),
				'description' => esc_html__( 'Font size for text mode, icon font size for icon mode.', 'kdna-tables' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'      => 'unavailable_typography',
				'label'     => esc_html__( 'Typography', 'kdna-tables' ),
				'selector'  => '{{WRAPPER}} .kdna-comparison__indicator--unavailable.kdna-comparison__indicator--text',
				'condition' => array(
					'unavailable_mode' => 'text',
				),
			)
		);

		$this->end_controls_section();

		// ─── Section: CTA Button ──────────────────────────────────────────
		$this->start_controls_section(
			'section_comparison_style_cta',
			array(
				'label'     => esc_html__( 'CTA Button', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => $cmp_condition,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'cta_typography',
				'selector' => '{{WRAPPER}} .kdna-comparison__cta',
			)
		);

		$this->start_controls_tabs( 'cta_state_tabs' );

		$this->start_controls_tab(
			'cta_state_normal',
			array( 'label' => esc_html__( 'Normal', 'kdna-tables' ) )
		);

		$this->add_control(
			'cta_text_color',
			array(
				'label'     => esc_html__( 'Text Colour', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-comparison-cta-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'     => 'cta_bg',
				'label'    => esc_html__( 'Background', 'kdna-tables' ),
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .kdna-comparison__cta',
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'cta_border',
				'selector' => '{{WRAPPER}} .kdna-comparison__cta',
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'cta_state_hover',
			array( 'label' => esc_html__( 'Hover', 'kdna-tables' ) )
		);

		$this->add_control(
			'cta_text_color_hover',
			array(
				'label'     => esc_html__( 'Text Colour', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-comparison-cta-hover-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'     => 'cta_bg_hover',
				'label'    => esc_html__( 'Background', 'kdna-tables' ),
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .kdna-comparison__cta:hover, {{WRAPPER}} .kdna-comparison__cta:focus',
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'cta_border_hover',
				'selector' => '{{WRAPPER}} .kdna-comparison__cta:hover, {{WRAPPER}} .kdna-comparison__cta:focus',
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'cta_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'separator'  => 'before',
				'selectors'  => array(
					'{{WRAPPER}} .kdna-comparison__cta' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'cta_padding',
			array(
				'label'      => esc_html__( 'Padding', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-comparison__cta' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'cta_full_width',
			array(
				'label'                => esc_html__( 'Full Width', 'kdna-tables' ),
				'type'                 => \Elementor\Controls_Manager::SWITCHER,
				'label_on'             => esc_html__( 'On', 'kdna-tables' ),
				'label_off'            => esc_html__( 'Off', 'kdna-tables' ),
				'return_value'         => 'yes',
				'default'              => '',
				'selectors_dictionary' => array(
					'yes' => 'display: flex; width: 100%;',
					''    => 'display: inline-flex; width: auto;',
				),
				'selectors'            => array(
					'{{WRAPPER}} .kdna-comparison__cta' => '{{VALUE}}',
				),
			)
		);

		$this->add_control(
			'cta_icon',
			array(
				'label'     => esc_html__( 'Icon', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::ICONS,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'cta_icon_position',
			array(
				'label'     => esc_html__( 'Icon Position', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => array(
					'before' => array(
						'title' => esc_html__( 'Before', 'kdna-tables' ),
						'icon'  => 'eicon-h-align-left',
					),
					'after'  => array(
						'title' => esc_html__( 'After', 'kdna-tables' ),
						'icon'  => 'eicon-h-align-right',
					),
				),
				'default'   => 'after',
				'condition' => array(
					'cta_icon[value]!' => '',
				),
			)
		);

		$this->add_responsive_control(
			'cta_icon_spacing',
			array(
				'label'      => esc_html__( 'Icon Spacing', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 40, 'step' => 1 ),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 8,
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-comparison-cta-icon-spacing: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'cta_icon[value]!' => '',
				),
			)
		);

		$this->add_control(
			'cta_transition_duration',
			array(
				'label'     => esc_html__( 'Transition (ms)', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::NUMBER,
				'min'       => 0,
				'max'       => 2000,
				'step'      => 10,
				'default'   => 150,
				'separator' => 'before',
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-comparison-cta-transition: {{VALUE}}ms;',
				),
			)
		);

		$this->end_controls_section();

		// ─── Section: Tooltip ────────────────────────────────────────────
		$this->start_controls_section(
			'section_comparison_style_tooltip',
			array(
				'label'     => esc_html__( 'Tooltip', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => $cmp_condition,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			array(
				'name'     => 'tooltip_bg',
				'label'    => esc_html__( 'Background', 'kdna-tables' ),
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .kdna-comparison__tooltip',
			)
		);

		$this->add_control(
			'tooltip_color',
			array(
				'label'     => esc_html__( 'Text Colour', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-tooltip-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'tooltip_typography',
				'selector' => '{{WRAPPER}} .kdna-comparison__tooltip',
			)
		);

		$this->add_responsive_control(
			'tooltip_padding',
			array(
				'label'      => esc_html__( 'Padding', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-comparison__tooltip' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'tooltip_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-tooltip-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'tooltip_max_width',
			array(
				'label'      => esc_html__( 'Max Width', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array( 'min' => 80, 'max' => 600, 'step' => 1 ),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-tooltip-max-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'tooltip_arrow_size',
			array(
				'label'      => esc_html__( 'Arrow Size', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 20, 'step' => 1 ),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 6,
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-tooltip-arrow-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'tooltip_position',
			array(
				'label'   => esc_html__( 'Position', 'kdna-tables' ),
				'type'    => \Elementor\Controls_Manager::CHOOSE,
				'options' => array(
					'top'    => array(
						'title' => esc_html__( 'Top', 'kdna-tables' ),
						'icon'  => 'eicon-v-align-top',
					),
					'bottom' => array(
						'title' => esc_html__( 'Bottom', 'kdna-tables' ),
						'icon'  => 'eicon-v-align-bottom',
					),
					'auto'   => array(
						'title' => esc_html__( 'Auto', 'kdna-tables' ),
						'icon'  => 'eicon-flip',
					),
				),
				'default' => 'top',
				'description' => esc_html__( 'Auto smart-flips to fit the viewport once the Session 7 tooltip script is in place; until then it follows the Top layout.', 'kdna-tables' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'tooltip_shadow',
				'selector' => '{{WRAPPER}} .kdna-comparison__tooltip',
			)
		);

		$this->end_controls_section();
	}

	protected function register_responsive_content_controls() {
		$this->start_controls_section(
			'section_responsive',
			array(
				'label'     => esc_html__( 'Responsive', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_CONTENT,
				'condition' => array(
					'table_type!' => '',
				),
			)
		);

		$this->add_control(
			'responsive_mode',
			array(
				'label'   => esc_html__( 'Responsive Mode', 'kdna-tables' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array(
					'none'          => esc_html__( 'None (table stays in place)', 'kdna-tables' ),
					'card_stack'    => esc_html__( 'Card Stack', 'kdna-tables' ),
					'pivot_rows'    => esc_html__( 'Pivot Rows', 'kdna-tables' ),
					'column_picker' => esc_html__( 'Column Picker', 'kdna-tables' ),
				),
				'default' => 'card_stack',
			)
		);

		$this->add_control(
			'responsive_breakpoint',
			array(
				'label'     => esc_html__( 'Activate At', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => array(
					'mobile'             => esc_html__( 'Mobile only', 'kdna-tables' ),
					'tablet_and_mobile'  => esc_html__( 'Tablet and Mobile', 'kdna-tables' ),
				),
				'default'   => 'mobile',
				'condition' => array(
					'responsive_mode!' => 'none',
				),
			)
		);

		$this->add_control(
			'picker_max_select',
			array(
				'label'     => esc_html__( 'Maximum Selectable', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::NUMBER,
				'min'       => 1,
				'max'       => 2,
				'step'      => 1,
				'default'   => 2,
				'condition' => array( 'responsive_mode' => 'column_picker' ),
			)
		);

		$this->add_control(
			'picker_default_items',
			array(
				'label'       => esc_html__( 'Default Selected Items', 'kdna-tables' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'options'     => array(
					'1' => esc_html__( 'Item 1', 'kdna-tables' ),
					'2' => esc_html__( 'Item 2', 'kdna-tables' ),
					'3' => esc_html__( 'Item 3', 'kdna-tables' ),
					'4' => esc_html__( 'Item 4', 'kdna-tables' ),
					'5' => esc_html__( 'Item 5', 'kdna-tables' ),
					'6' => esc_html__( 'Item 6', 'kdna-tables' ),
				),
				'default'     => array( '1', '2' ),
				'condition'   => array( 'responsive_mode' => 'column_picker' ),
				'description' => esc_html__( 'Slot numbers (1 to 6) of the items selected on initial load.', 'kdna-tables' ),
			)
		);

		$this->add_control(
			'picker_label_text',
			array(
				'label'     => esc_html__( 'Picker Label', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => esc_html__( 'Compare', 'kdna-tables' ),
				'condition' => array( 'responsive_mode' => 'column_picker' ),
				'dynamic'   => array( 'active' => true ),
			)
		);

		$this->end_controls_section();
	}

	protected function register_responsive_style_controls() {
		// ─── Section: Card Stack Mode ─────────────────────────────────────
		$this->start_controls_section(
			'section_responsive_style_card',
			array(
				'label'     => esc_html__( 'Card Stack Mode', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array(
					'table_type!'     => '',
					'responsive_mode' => 'card_stack',
				),
			)
		);

		$this->add_control(
			'card_bg',
			array(
				'label'     => esc_html__( 'Card Background', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-card-bg: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'card_border',
				'selector' => '{{WRAPPER}} .kdna-table__wrapper [data-responsive-mode="card_stack"] .kdna-table__card, {{WRAPPER}} .kdna-table__wrapper [data-responsive-mode="card_stack"] .kdna-comparison__card',
			)
		);

		$this->add_responsive_control(
			'card_border_radius',
			array(
				'label'      => esc_html__( 'Card Border Radius', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-card-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'card_shadow',
				'selector' => '{{WRAPPER}} .kdna-table__wrapper [data-responsive-mode="card_stack"] .kdna-table__card, {{WRAPPER}} .kdna-table__wrapper [data-responsive-mode="card_stack"] .kdna-comparison__card',
			)
		);

		$this->add_responsive_control(
			'card_padding',
			array(
				'label'      => esc_html__( 'Card Padding', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-card-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'card_spacing',
			array(
				'label'      => esc_html__( 'Card Spacing', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80, 'step' => 1 ) ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-card-spacing: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'card_header_sticky',
			array(
				'label'        => esc_html__( 'Sticky Card Header', 'kdna-tables' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'On', 'kdna-tables' ),
				'label_off'    => esc_html__( 'Off', 'kdna-tables' ),
				'return_value' => 'yes',
				'default'      => '',
				'description'  => esc_html__( 'Keeps the item header visible while scrolling within the card.', 'kdna-tables' ),
			)
		);

		$this->end_controls_section();

		// ─── Section: Pivot Rows Mode ─────────────────────────────────────
		$this->start_controls_section(
			'section_responsive_style_pivot',
			array(
				'label'     => esc_html__( 'Pivot Rows Mode', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array(
					'table_type!'     => '',
					'responsive_mode' => 'pivot_rows',
				),
			)
		);

		$this->add_control(
			'pivot_label_position',
			array(
				'label'   => esc_html__( 'Label Position', 'kdna-tables' ),
				'type'    => \Elementor\Controls_Manager::CHOOSE,
				'options' => array(
					'above'  => array(
						'title' => esc_html__( 'Above', 'kdna-tables' ),
						'icon'  => 'eicon-v-align-top',
					),
					'inline' => array(
						'title' => esc_html__( 'Inline', 'kdna-tables' ),
						'icon'  => 'eicon-h-align-left',
					),
				),
				'default' => 'above',
			)
		);

		$this->add_responsive_control(
			'pivot_label_width',
			array(
				'label'      => esc_html__( 'Label Width (%)', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( '%' ),
				'range'      => array( '%' => array( 'min' => 10, 'max' => 80, 'step' => 1 ) ),
				'default'    => array( 'unit' => '%', 'size' => 30 ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-pivot-label-width: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array( 'pivot_label_position' => 'inline' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'pivot_label_typography',
				'label'    => esc_html__( 'Label Typography', 'kdna-tables' ),
				'selector' => '{{WRAPPER}} [data-responsive-mode="pivot_rows"] .kdna-table__cell::before, {{WRAPPER}} [data-responsive-mode="pivot_rows"] .kdna-comparison__cell--value::before',
			)
		);

		$this->add_control(
			'pivot_label_color',
			array(
				'label'     => esc_html__( 'Label Colour', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-pivot-label-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'pivot_row_spacing',
			array(
				'label'      => esc_html__( 'Row Spacing', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80, 'step' => 1 ) ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-pivot-row-spacing: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'           => 'pivot_divider_border',
				'label'          => esc_html__( 'Row Divider', 'kdna-tables' ),
				'selector'       => '{{WRAPPER}} [data-responsive-mode="pivot_rows"] .kdna-table tbody tr, {{WRAPPER}} [data-responsive-mode="pivot_rows"] .kdna-comparison tbody tr',
				'fields_options' => array(
					'border' => array(
						'selectors' => array(
							'{{SELECTOR}}' => 'border-bottom-style: {{VALUE}};',
						),
					),
					'width'  => array(
						'selectors' => array(
							'{{SELECTOR}}' => 'border-bottom-width: {{TOP}}{{UNIT}};',
						),
					),
					'color'  => array(
						'selectors' => array(
							'{{SELECTOR}}' => 'border-bottom-color: {{VALUE}};',
						),
					),
				),
			)
		);

		$this->end_controls_section();

		// ─── Section: Column Picker Mode ──────────────────────────────────
		$this->start_controls_section(
			'section_responsive_style_picker',
			array(
				'label'     => esc_html__( 'Column Picker Mode', 'kdna-tables' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array(
					'table_type!'     => '',
					'responsive_mode' => 'column_picker',
				),
			)
		);

		$this->add_control(
			'picker_bg',
			array(
				'label'     => esc_html__( 'Picker Background', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-picker-bg: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'picker_border',
				'selector' => '{{WRAPPER}} .kdna-picker',
			)
		);

		$this->add_responsive_control(
			'picker_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-picker-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'picker_padding',
			array(
				'label'      => esc_html__( 'Picker Padding', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-picker-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'picker_label_typography',
				'label'    => esc_html__( 'Label Typography', 'kdna-tables' ),
				'selector' => '{{WRAPPER}} .kdna-picker__label',
			)
		);

		$this->add_control(
			'picker_label_color',
			array(
				'label'     => esc_html__( 'Label Colour', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-picker-label-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'picker_dropdown_bg',
			array(
				'label'     => esc_html__( 'Dropdown Background', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'separator' => 'before',
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-picker-dropdown-bg: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'picker_dropdown_typography',
				'label'    => esc_html__( 'Dropdown Typography', 'kdna-tables' ),
				'selector' => '{{WRAPPER}} .kdna-picker__select',
			)
		);

		$this->add_control(
			'picker_dropdown_color',
			array(
				'label'     => esc_html__( 'Dropdown Text Colour', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-picker-dropdown-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'picker_dropdown_border',
				'selector' => '{{WRAPPER}} .kdna-picker__select',
			)
		);

		$this->add_control(
			'picker_chip_bg',
			array(
				'label'     => esc_html__( 'Chip Background', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'separator' => 'before',
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-picker-chip-bg: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'picker_chip_color',
			array(
				'label'     => esc_html__( 'Chip Text Colour', 'kdna-tables' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-picker-chip-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'picker_chip_radius',
			array(
				'label'      => esc_html__( 'Chip Border Radius', 'kdna-tables' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-picker-chip-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/*
	 * Comparison render helpers. Public where the template files need to
	 * call them via $this->method().
	 */

	public function kdna_render_comparison_value( $feature_row, $slot, $settings ) {
		$indicator_key = 'cell_' . $slot . '_indicator';
		$indicator     = isset( $feature_row[ $indicator_key ] ) ? $feature_row[ $indicator_key ] : 'available';

		if ( 'available' === $indicator ) {
			return $this->kdna_render_available_indicator( $settings );
		}

		if ( 'unavailable' === $indicator ) {
			return $this->kdna_render_unavailable_indicator( $settings );
		}

		if ( 'custom' === $indicator ) {
			$normalized = array(
				'cell_type'        => $feature_row[ 'cell_' . $slot . '_custom_type' ] ?? 'text',
				'cell_text'        => $feature_row[ 'cell_' . $slot . '_text' ] ?? '',
				'cell_icon'        => $feature_row[ 'cell_' . $slot . '_icon' ] ?? array(),
				'cell_image'       => $feature_row[ 'cell_' . $slot . '_image' ] ?? array(),
				'cell_image_size'  => $feature_row[ 'cell_' . $slot . '_image_size' ] ?? 'medium',
				'cell_arrangement' => $feature_row[ 'cell_' . $slot . '_arrangement' ] ?? 'icon-text',
			);
			return $this->kdna_render_cell_inner( $normalized );
		}

		return '';
	}

	public function kdna_render_available_indicator( $settings ) {
		$icon = isset( $settings['available_icon'] ) ? $settings['available_icon'] : null;
		if ( empty( $icon ) || ( empty( $icon['value'] ) && empty( $icon['library'] ) ) ) {
			return '';
		}

		ob_start();
		echo '<span class="kdna-comparison__indicator kdna-comparison__indicator--available">';
		\Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
		echo '<span class="kdna-table__sr-only">' . esc_html__( 'Available', 'kdna-tables' ) . '</span>';
		echo '</span>';
		return ob_get_clean();
	}

	public function kdna_render_unavailable_indicator( $settings ) {
		$mode = isset( $settings['unavailable_mode'] ) ? $settings['unavailable_mode'] : 'icon';

		if ( 'hidden' === $mode ) {
			return '<span class="kdna-table__sr-only">' . esc_html__( 'Not available', 'kdna-tables' ) . '</span>';
		}

		if ( 'icon' === $mode ) {
			$icon = isset( $settings['unavailable_icon'] ) ? $settings['unavailable_icon'] : null;
			if ( empty( $icon ) || ( empty( $icon['value'] ) && empty( $icon['library'] ) ) ) {
				return '';
			}
			ob_start();
			echo '<span class="kdna-comparison__indicator kdna-comparison__indicator--unavailable">';
			\Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
			echo '<span class="kdna-table__sr-only">' . esc_html__( 'Not available', 'kdna-tables' ) . '</span>';
			echo '</span>';
			return ob_get_clean();
		}

		if ( 'text' === $mode ) {
			$text = isset( $settings['unavailable_text'] ) ? (string) $settings['unavailable_text'] : '-';
			return '<span class="kdna-comparison__indicator kdna-comparison__indicator--unavailable kdna-comparison__indicator--text" aria-label="' . esc_attr__( 'Not available', 'kdna-tables' ) . '">'
				. esc_html( $text )
				. '</span>';
		}

		return '';
	}

	/*
	 * Cell render helpers. Public so the template files can call them via
	 * $this->method() while included from the render method.
	 */

	public function kdna_cell_modifier_class( $cell ) {
		$type = isset( $cell['cell_type'] ) ? $cell['cell_type'] : '';
		if ( '' === $type ) {
			return '';
		}
		return 'kdna-table__cell--' . sanitize_html_class( $type );
	}

	public function kdna_resolve_cell_alignment( $cell, $column ) {
		$override = isset( $cell['cell_alignment_override'] ) ? $cell['cell_alignment_override'] : 'inherit';
		if ( in_array( $override, array( 'left', 'center', 'right' ), true ) ) {
			return $override;
		}
		$col_align = isset( $column['column_alignment'] ) ? $column['column_alignment'] : 'left';
		if ( ! in_array( $col_align, array( 'left', 'center', 'right' ), true ) ) {
			$col_align = 'left';
		}
		return $col_align;
	}

	public function kdna_render_cell_inner( $cell ) {
		if ( empty( $cell ) || empty( $cell['cell_type'] ) ) {
			return '';
		}

		$type   = $cell['cell_type'];
		$pieces = array();

		if ( 'text' === $type ) {
			$pieces['text'] = $this->kdna_render_cell_text_piece( $cell );
		} elseif ( 'icon' === $type ) {
			$pieces['icon'] = $this->kdna_render_cell_icon_piece( $cell );
		} elseif ( 'image' === $type ) {
			$pieces['image'] = $this->kdna_render_cell_image_piece( $cell );
		} elseif ( 'mixed' === $type ) {
			$arrangement = isset( $cell['cell_arrangement'] ) ? $cell['cell_arrangement'] : 'icon-text';
			foreach ( $this->kdna_arrangement_order( $arrangement ) as $piece ) {
				if ( 'text' === $piece ) {
					$pieces['text'] = $this->kdna_render_cell_text_piece( $cell );
				} elseif ( 'icon' === $piece ) {
					$pieces['icon'] = $this->kdna_render_cell_icon_piece( $cell );
				} elseif ( 'image' === $piece ) {
					$pieces['image'] = $this->kdna_render_cell_image_piece( $cell );
				}
			}
		}

		return implode( '', array_filter( $pieces ) );
	}

	protected function kdna_arrangement_order( $arrangement ) {
		switch ( $arrangement ) {
			case 'text-icon':
				return array( 'text', 'icon' );
			case 'icon-text-image':
				return array( 'icon', 'text', 'image' );
			case 'image-text-icon':
				return array( 'image', 'text', 'icon' );
			case 'icon-text':
			default:
				return array( 'icon', 'text' );
		}
	}

	protected function kdna_render_cell_text_piece( $cell ) {
		$text = isset( $cell['cell_text'] ) ? $cell['cell_text'] : '';
		if ( '' === $text ) {
			return '';
		}
		return '<span class="kdna-table__cell-text">' . wp_kses_post( $text ) . '</span>';
	}

	protected function kdna_render_cell_icon_piece( $cell ) {
		if ( empty( $cell['cell_icon'] ) ) {
			return '';
		}
		$icon = $cell['cell_icon'];
		if ( empty( $icon['value'] ) && empty( $icon['library'] ) ) {
			return '';
		}
		ob_start();
		echo '<span class="kdna-table__cell-icon">';
		\Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
		echo '</span>';
		return ob_get_clean();
	}

	protected function kdna_render_cell_image_piece( $cell ) {
		if ( empty( $cell['cell_image']['id'] ) && empty( $cell['cell_image']['url'] ) ) {
			return '';
		}
		$image_html = \Elementor\Group_Control_Image_Size::get_attachment_image_html( $cell, 'cell_image_size', 'cell_image' );
		if ( '' === $image_html ) {
			return '';
		}
		return '<span class="kdna-table__cell-image">' . $image_html . '</span>';
	}

	protected function render() {
		$settings   = $this->get_settings_for_display();
		$table_type = isset( $settings['table_type'] ) ? $settings['table_type'] : '';

		$wrapper_classes = array( 'kdna-table__wrapper' );
		if ( '' !== $table_type ) {
			$wrapper_classes[] = 'kdna-table__wrapper--' . sanitize_html_class( $table_type );
		}

		$responsive_mode       = isset( $settings['responsive_mode'] ) ? $settings['responsive_mode'] : 'card_stack';
		$responsive_breakpoint = isset( $settings['responsive_breakpoint'] ) ? $settings['responsive_breakpoint'] : 'mobile';
		$pivot_label_position  = isset( $settings['pivot_label_position'] ) ? $settings['pivot_label_position'] : 'above';

		$picker_config = array();
		if ( 'comparison' === $table_type && 'column_picker' === $responsive_mode ) {
			$items_for_picker = array();
			$items_raw        = isset( $settings['items'] ) && is_array( $settings['items'] ) ? array_values( $settings['items'] ) : array();
			$items_raw        = array_slice( $items_raw, 0, 6 );
			foreach ( $items_raw as $i => $item ) {
				$items_for_picker[] = array(
					'slot'  => $i + 1,
					'label' => isset( $item['item_label'] ) ? (string) $item['item_label'] : sprintf( esc_html__( 'Item %d', 'kdna-tables' ), $i + 1 ),
				);
			}
			$defaults = isset( $settings['picker_default_items'] ) && is_array( $settings['picker_default_items'] ) ? $settings['picker_default_items'] : array();
			$picker_config = array(
				'items'      => $items_for_picker,
				'defaults'   => array_values( array_map( 'intval', $defaults ) ),
				'maxSelect'  => isset( $settings['picker_max_select'] ) ? (int) $settings['picker_max_select'] : 2,
				'label'      => isset( $settings['picker_label_text'] ) ? (string) $settings['picker_label_text'] : esc_html__( 'Compare', 'kdna-tables' ),
			);
		}

		$sticky_first_column = ! empty( $settings['sticky_first_column'] ) && 'yes' === $settings['sticky_first_column'];

		$wrapper_attrs = array(
			'class'                       => implode( ' ', $wrapper_classes ),
			'data-table-type'             => $table_type,
			'data-responsive-mode'        => '' !== $table_type ? $responsive_mode : 'none',
			'data-responsive-breakpoint'  => '' !== $table_type ? $responsive_breakpoint : 'mobile',
			'data-pivot-label-position'   => $pivot_label_position,
			'data-sticky-first-column'    => $sticky_first_column ? 'yes' : 'no',
		);
		if ( ! empty( $picker_config ) ) {
			$wrapper_attrs['data-picker-config'] = wp_json_encode( $picker_config );
		}

		// Expose the sticky flag to render templates so they can wrap the
		// table in the horizontal scroll container.
		$settings['__sticky_first_column'] = $sticky_first_column;

		$attr_string = '';
		foreach ( $wrapper_attrs as $name => $value ) {
			$attr_string .= sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( $value ) );
		}

		?>
		<div<?php echo $attr_string; ?>>
			<?php
			if ( '' === $table_type ) {
				include KDNA_TABLES_PATH . 'templates/render-placeholder.php';
			} elseif ( 'general' === $table_type ) {
				include KDNA_TABLES_PATH . 'templates/render-general.php';
			} elseif ( 'comparison' === $table_type ) {
				include KDNA_TABLES_PATH . 'templates/render-comparison.php';
			}
			?>
		</div>
		<?php
	}
}
