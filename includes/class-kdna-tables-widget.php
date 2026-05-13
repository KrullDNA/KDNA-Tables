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
	 * Elementor auto-enqueue it only on pages where the widget renders,
	 * which satisfies the conditional asset loading rule.
	 */
	public function get_style_depends() {
		return array( KDNA_Tables_Plugin::FRONTEND_STYLE_HANDLE );
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
		$this->register_change_type_controls();
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

		?>
		<div class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>" data-table-type="<?php echo esc_attr( $table_type ); ?>">
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
