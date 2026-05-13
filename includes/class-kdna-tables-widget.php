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
