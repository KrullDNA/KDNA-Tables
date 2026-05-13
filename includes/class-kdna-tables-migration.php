<?php
/**
 * Migration from v1.x widget-stored data into v2.0.0 kdna_table CPT
 * entries. Two paths: lazy migration when the editor opens a widget
 * with legacy settings, and a bulk Tools page that walks every
 * Elementor post on the site in chunks.
 *
 * @package KDNA_Tables
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KDNA_Tables_Migration {

	const BACKUP_META_KEY     = '_kdna_tables_pre_migration_backup';
	const NONCE_LAZY          = 'kdna_tables_migrate';
	const NONCE_BULK          = 'kdna_tables_bulk_migrate';
	const TOOLS_PAGE_SLUG     = 'kdna-tables-tools';
	const BULK_CHUNK_SIZE     = 10;

	public static function init() {
		add_action( 'wp_ajax_kdna_tables_migrate_instance', array( __CLASS__, 'ajax_migrate_instance' ) );
		add_action( 'wp_ajax_kdna_tables_bulk_scan',        array( __CLASS__, 'ajax_bulk_scan' ) );
		add_action( 'wp_ajax_kdna_tables_bulk_migrate',     array( __CLASS__, 'ajax_bulk_migrate' ) );
		add_action( 'wp_ajax_kdna_tables_rollback_post',    array( __CLASS__, 'ajax_rollback_post' ) );

		// Mirror the lazy-migration nonce + AJAX url into the Elementor
		// editor so kdna-editor.js can call the endpoint.
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'localize_editor_script' ), 25 );
	}

	public static function localize_editor_script() {
		$handle = KDNA_Tables_Plugin::EDITOR_SCRIPT_HANDLE;
		if ( ! wp_script_is( $handle, 'enqueued' ) ) {
			return;
		}
		wp_add_inline_script(
			$handle,
			'window.KDNATablesMigration = ' . wp_json_encode(
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( self::NONCE_LAZY ),
				)
			) . ';',
			'before'
		);
	}

	/* ==================================================================
	 * Lazy migration of a single widget instance
	 * ================================================================== */

	public static function ajax_migrate_instance() {
		check_ajax_referer( self::NONCE_LAZY );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to migrate tables.', 'kdna-tables' ) ), 403 );
		}
		$raw = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '';
		$settings = is_string( $raw ) ? json_decode( $raw, true ) : null;
		if ( ! is_array( $settings ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid legacy settings payload.', 'kdna-tables' ) ) );
		}
		$result = self::migrate_instance( $settings );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success(
			array(
				'id'       => (int) $result,
				'edit_url' => get_edit_post_link( (int) $result, 'raw' ),
			)
		);
	}

	/**
	 * Migrate a legacy widget settings payload into a kdna_table entry,
	 * or reuse an existing entry with the same content hash so identical
	 * widgets collapse onto one CPT row.
	 *
	 * @return int|WP_Error  New or reused post ID, or WP_Error on failure.
	 */
	public static function migrate_instance( array $legacy_settings ) {
		$type = isset( $legacy_settings['table_type'] ) ? (string) $legacy_settings['table_type'] : '';
		if ( ! in_array( $type, array( 'general', 'comparison' ), true ) ) {
			return new WP_Error( 'kdna_no_type', __( 'Legacy widget has no recognised table type.', 'kdna-tables' ) );
		}
		$caption = isset( $legacy_settings['caption'] ) ? (string) $legacy_settings['caption'] : '';

		if ( 'general' === $type ) {
			$cpt_data = self::legacy_to_general( $legacy_settings );
		} else {
			$cpt_data = self::legacy_to_comparison( $legacy_settings );
		}
		// Run the data through the CPT sanitiser so the migrated entry has
		// the exact shape the editor and data layer expect for a fresh save.
		$cpt_data = KDNA_Tables_CPT::sanitize_table_data( $cpt_data, $type );

		$hash     = self::content_hash( $type, $caption, $cpt_data );
		$existing = self::find_by_hash( $hash );
		if ( $existing ) {
			return $existing;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => KDNA_Tables_CPT::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => sprintf(
					/* translators: %s: ISO 8601 datetime */
					__( 'Migrated table, %s', 'kdna-tables' ),
					current_time( 'Y-m-d H:i' )
				),
				'post_author' => get_current_user_id(),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, KDNA_Tables_CPT::META_TYPE, $type );
		update_post_meta( $post_id, KDNA_Tables_CPT::META_CAPTION, $caption );
		update_post_meta( $post_id, KDNA_Tables_CPT::META_SCHEMA, KDNA_Tables_CPT::SCHEMA_VERSION );
		update_post_meta( $post_id, KDNA_Tables_CPT::META_CONTENT_HASH, $hash );

		$meta_key = ( 'general' === $type ) ? KDNA_Tables_CPT::META_GENERAL : KDNA_Tables_CPT::META_COMPARISON;
		update_post_meta( $post_id, $meta_key, $cpt_data );

		return (int) $post_id;
	}

	private static function content_hash( $type, $caption, $data ) {
		// Strip internal id keys so identical user-visible content
		// produces the same hash regardless of repeater UUIDs.
		$clean = self::strip_ids( $data );
		return md5( (string) wp_json_encode( array( $type, $caption, $clean ) ) );
	}

	private static function strip_ids( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$out = array();
		foreach ( $value as $k => $v ) {
			if ( 'id' === $k || '_id' === $k ) {
				continue;
			}
			$out[ $k ] = is_array( $v ) ? self::strip_ids( $v ) : $v;
		}
		return $out;
	}

	private static function find_by_hash( $hash ) {
		$ids = get_posts(
			array(
				'post_type'      => KDNA_Tables_CPT::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'numberposts'    => 1,
				'fields'         => 'ids',
				'meta_key'       => KDNA_Tables_CPT::META_CONTENT_HASH,
				'meta_value'     => $hash,
				'suppress_filters' => true,
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	/* ==================================================================
	 * Legacy -> CPT shape mapping
	 * ================================================================== */

	public static function legacy_to_general( array $settings ) {
		$columns = array();
		$cols    = isset( $settings['columns'] ) && is_array( $settings['columns'] ) ? $settings['columns'] : array();
		foreach ( $cols as $col ) {
			if ( ! is_array( $col ) ) {
				continue;
			}
			$columns[] = array(
				'id'         => isset( $col['_id'] ) ? (string) $col['_id'] : 'col_' . wp_generate_uuid4(),
				'label'      => isset( $col['column_label'] ) ? (string) $col['column_label'] : '',
				'alignment'  => self::convert_legacy_alignment( $col['column_alignment'] ?? 'left', 'left' ),
				'width'      => isset( $col['column_width']['size'] ) ? (float) $col['column_width']['size'] : 0,
				'width_unit' => ( isset( $col['column_width']['unit'] ) && 'px' === $col['column_width']['unit'] ) ? 'px' : '%',
			);
		}

		$rows = array();
		$src  = isset( $settings['rows'] ) && is_array( $settings['rows'] ) ? $settings['rows'] : array();
		foreach ( $src as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$cells = array();
			$src_cells = isset( $row['cells'] ) && is_array( $row['cells'] ) ? $row['cells'] : array();
			foreach ( $src_cells as $cell ) {
				$cells[] = self::legacy_general_cell_to_cpt( is_array( $cell ) ? $cell : array() );
			}
			$rows[] = array(
				'id'    => isset( $row['_id'] ) ? (string) $row['_id'] : 'row_' . wp_generate_uuid4(),
				'cells' => $cells,
			);
		}

		return array(
			'first_row_is_header'    => isset( $settings['first_row_is_header'] ) && 'yes' === $settings['first_row_is_header'],
			'first_column_is_header' => isset( $settings['first_column_is_header'] ) && 'yes' === $settings['first_column_is_header'],
			'columns'                => $columns,
			'rows'                   => $rows,
		);
	}

	private static function legacy_general_cell_to_cpt( array $cell ) {
		$type        = isset( $cell['cell_type'] ) ? (string) $cell['cell_type'] : 'text';
		$arrangement = isset( $cell['cell_arrangement'] ) ? (string) $cell['cell_arrangement'] : 'icon-text';
		$content_types = self::content_types_from_legacy( $type, $arrangement );

		return array(
			'id'            => isset( $cell['_id'] ) ? (string) $cell['_id'] : 'cell_' . wp_generate_uuid4(),
			'content_types' => $content_types,
			'text'          => isset( $cell['cell_text'] ) ? (string) $cell['cell_text'] : '',
			'icon'          => array(
				'value'   => isset( $cell['cell_icon']['value'] ) ? (string) $cell['cell_icon']['value'] : '',
				'library' => isset( $cell['cell_icon']['library'] ) ? (string) $cell['cell_icon']['library'] : '',
			),
			'image'         => array(
				'id'  => isset( $cell['cell_image']['id'] ) ? (int) $cell['cell_image']['id'] : 0,
				'url' => isset( $cell['cell_image']['url'] ) ? (string) $cell['cell_image']['url'] : '',
				'alt' => isset( $cell['cell_image']['alt'] ) ? (string) $cell['cell_image']['alt'] : '',
			),
			'arrangement'   => $arrangement,
			'alignment'     => self::convert_legacy_alignment( $cell['cell_alignment_override'] ?? 'inherit', '' ),
		);
	}

	public static function legacy_to_comparison( array $settings ) {
		$items     = array();
		$src_items = isset( $settings['items'] ) && is_array( $settings['items'] ) ? $settings['items'] : array();
		foreach ( $src_items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$items[] = array(
				'id'       => isset( $item['_id'] ) ? (string) $item['_id'] : 'item_' . wp_generate_uuid4(),
				'image'    => array(
					'id'  => isset( $item['item_image']['id'] ) ? (int) $item['item_image']['id'] : 0,
					'url' => isset( $item['item_image']['url'] ) ? (string) $item['item_image']['url'] : '',
					'alt' => isset( $item['item_image']['alt'] ) ? (string) $item['item_image']['alt'] : '',
				),
				'label'    => isset( $item['item_label'] ) ? (string) $item['item_label'] : '',
				'sublabel' => isset( $item['item_sublabel'] ) ? (string) $item['item_sublabel'] : '',
				'cta'      => array(
					'enabled' => isset( $item['cta_enable'] ) && 'yes' === $item['cta_enable'],
					'text'    => isset( $item['cta_text'] ) ? (string) $item['cta_text'] : '',
					'url'     => isset( $item['cta_url']['url'] ) ? (string) $item['cta_url']['url'] : '',
				),
			);
		}
		$item_count = count( $items );

		// Legacy 'highlight_item' is a 1-based slot string ('1'..'6') or '' = none.
		$highlight_raw = isset( $settings['highlight_item'] ) ? (string) $settings['highlight_item'] : '';
		$highlight_idx = ( '' === $highlight_raw ) ? -1 : ( (int) $highlight_raw - 1 );
		if ( $highlight_idx >= $item_count ) {
			$highlight_idx = -1;
		}

		$badge_position = isset( $settings['highlight_badge_position'] ) ? (string) $settings['highlight_badge_position'] : 'top-centre';
		if ( ! in_array( $badge_position, array( 'top-left', 'top-centre', 'top-right' ), true ) ) {
			$badge_position = 'top-centre';
		}

		$feature_rows = array();
		$src_features = isset( $settings['feature_rows'] ) && is_array( $settings['feature_rows'] ) ? $settings['feature_rows'] : array();
		foreach ( $src_features as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$cells = array();
			for ( $slot = 1; $slot <= $item_count; $slot++ ) {
				$indicator = isset( $row[ 'cell_' . $slot . '_indicator' ] ) ? (string) $row[ 'cell_' . $slot . '_indicator' ] : 'available';
				if ( 'custom' === $indicator ) {
					$cells[] = array(
						'state'  => 'custom',
						'custom' => self::legacy_custom_cell_to_cpt( $row, $slot ),
					);
					continue;
				}
				$cells[] = array(
					'state' => ( 'unavailable' === $indicator ) ? 'unavailable' : 'available',
				);
			}
			$feature_rows[] = array(
				'id'          => isset( $row['_id'] ) ? (string) $row['_id'] : 'fr_' . wp_generate_uuid4(),
				'label'       => isset( $row['feature_label'] ) ? (string) $row['feature_label'] : '',
				'description' => isset( $row['feature_description'] ) ? (string) $row['feature_description'] : '',
				'tooltip'     => isset( $row['feature_tooltip'] ) ? (string) $row['feature_tooltip'] : '',
				'cells'       => $cells,
			);
		}

		return array(
			'highlighted_item_index' => $highlight_idx,
			'badge_text'             => isset( $settings['highlight_badge_text'] ) ? (string) $settings['highlight_badge_text'] : '',
			'badge_position'         => $badge_position,
			'items'                  => $items,
			'feature_rows'           => $feature_rows,
		);
	}

	private static function legacy_custom_cell_to_cpt( array $row, $slot ) {
		$type        = isset( $row[ 'cell_' . $slot . '_custom_type' ] ) ? (string) $row[ 'cell_' . $slot . '_custom_type' ] : 'text';
		$arrangement = isset( $row[ 'cell_' . $slot . '_arrangement' ] ) ? (string) $row[ 'cell_' . $slot . '_arrangement' ] : 'icon-text';
		$content_types = self::content_types_from_legacy( $type, $arrangement );

		return array(
			'content_types' => $content_types,
			'text'          => isset( $row[ 'cell_' . $slot . '_text' ] ) ? (string) $row[ 'cell_' . $slot . '_text' ] : '',
			'icon'          => array(
				'value'   => isset( $row[ 'cell_' . $slot . '_icon' ]['value'] ) ? (string) $row[ 'cell_' . $slot . '_icon' ]['value'] : '',
				'library' => isset( $row[ 'cell_' . $slot . '_icon' ]['library'] ) ? (string) $row[ 'cell_' . $slot . '_icon' ]['library'] : '',
			),
			'image'         => array(
				'id'  => isset( $row[ 'cell_' . $slot . '_image' ]['id'] ) ? (int) $row[ 'cell_' . $slot . '_image' ]['id'] : 0,
				'url' => isset( $row[ 'cell_' . $slot . '_image' ]['url'] ) ? (string) $row[ 'cell_' . $slot . '_image' ]['url'] : '',
				'alt' => isset( $row[ 'cell_' . $slot . '_image' ]['alt'] ) ? (string) $row[ 'cell_' . $slot . '_image' ]['alt'] : '',
			),
			'arrangement'   => $arrangement,
		);
	}

	/**
	 * Derive the modern content_types array from a legacy 'mixed' arrangement
	 * (icon-text, text-icon, icon-text-image, image-text-icon) or a single-
	 * piece cell_type ('text' / 'icon' / 'image').
	 */
	private static function content_types_from_legacy( $type, $arrangement ) {
		if ( 'mixed' === $type ) {
			$pieces = array_filter( explode( '-', (string) $arrangement ), function ( $p ) {
				return in_array( $p, array( 'text', 'icon', 'image' ), true );
			} );
			$pieces = array_values( array_unique( $pieces ) );
			return $pieces ? $pieces : array( 'text' );
		}
		if ( in_array( $type, array( 'text', 'icon', 'image' ), true ) ) {
			return array( $type );
		}
		return array( 'text' );
	}

	private static function convert_legacy_alignment( $value, $default ) {
		$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';
		if ( 'inherit' === $value || '' === $value ) {
			return $default;
		}
		if ( 'center' === $value ) {
			return 'centre';
		}
		if ( in_array( $value, array( 'left', 'right' ), true ) ) {
			return $value;
		}
		return $default;
	}

	/* ==================================================================
	 * Bulk migration
	 * ================================================================== */

	public static function ajax_bulk_scan() {
		check_ajax_referer( self::NONCE_BULK );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		$results = self::scan_legacy_posts();
		wp_send_json_success(
			array(
				'results'           => $results,
				'total_posts'       => count( $results ),
				'total_instances'   => array_sum( array_map( function ( $r ) { return (int) $r['count']; }, $results ) ),
				'chunk_size'        => self::BULK_CHUNK_SIZE,
			)
		);
	}

	public static function scan_legacy_posts() {
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT pm.post_id, p.post_title
			 FROM {$wpdb->postmeta} pm
			 JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			 WHERE pm.meta_key = '_elementor_data'
			 AND pm.meta_value LIKE '%kdna-table%'
			 AND p.post_status IN ( 'publish', 'draft', 'private', 'future', 'pending' )
			 ORDER BY p.post_modified DESC"
		);
		$out = array();
		if ( ! $rows ) {
			return $out;
		}
		foreach ( $rows as $row ) {
			$raw      = get_post_meta( $row->post_id, '_elementor_data', true );
			$elements = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
			if ( ! is_array( $elements ) ) {
				continue;
			}
			$count = self::count_legacy_instances( $elements );
			if ( $count > 0 ) {
				$out[] = array(
					'id'    => (int) $row->post_id,
					'title' => (string) $row->post_title,
					'count' => $count,
				);
			}
		}
		return $out;
	}

	private static function count_legacy_instances( $elements ) {
		$count = 0;
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			if ( isset( $el['widgetType'] ) && 'kdna-table' === $el['widgetType'] ) {
				if ( self::is_legacy_settings( $el['settings'] ?? array() ) ) {
					$count++;
				}
			}
			if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$count += self::count_legacy_instances( $el['elements'] );
			}
		}
		return $count;
	}

	public static function is_legacy_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return false;
		}
		if ( ! empty( $settings['selected_table_id'] ) ) {
			return false;
		}
		foreach ( array( 'table_type', 'columns', 'rows', 'items', 'feature_rows' ) as $legacy_key ) {
			if ( ! empty( $settings[ $legacy_key ] ) ) {
				return true;
			}
		}
		return false;
	}

	public static function ajax_bulk_migrate() {
		check_ajax_referer( self::NONCE_BULK );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		$post_ids = isset( $_POST['post_ids'] ) ? array_map( 'absint', (array) $_POST['post_ids'] ) : array();
		$log      = array();
		foreach ( $post_ids as $post_id ) {
			$log[] = self::migrate_post( $post_id );
		}
		wp_send_json_success( array( 'log' => $log ) );
	}

	public static function migrate_post( $post_id ) {
		$post_id = (int) $post_id;
		$raw     = get_post_meta( $post_id, '_elementor_data', true );
		$elements = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
		if ( ! is_array( $elements ) ) {
			return array( 'post_id' => $post_id, 'error' => 'no_elementor_data' );
		}

		// Backup once per post so a rollback can restore the pre-migration data.
		if ( ! metadata_exists( 'post', $post_id, self::BACKUP_META_KEY ) ) {
			update_post_meta( $post_id, self::BACKUP_META_KEY, wp_json_encode( $elements ) );
		}

		$tables_created    = array();
		$instances_updated = 0;
		$errors            = array();

		$walker = function ( $element ) use ( &$walker, &$tables_created, &$instances_updated, &$errors ) {
			if ( ! is_array( $element ) ) {
				return $element;
			}
			if ( isset( $element['widgetType'] ) && 'kdna-table' === $element['widgetType'] ) {
				$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
				if ( self::is_legacy_settings( $settings ) ) {
					$id = self::migrate_instance( $settings );
					if ( is_wp_error( $id ) ) {
						$errors[] = $id->get_error_message();
					} else {
						$tables_created[] = (int) $id;
						$instances_updated++;
						$element['settings'] = self::clear_legacy_settings( $settings, (int) $id );
					}
				}
			}
			if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = array_map( $walker, $element['elements'] );
			}
			return $element;
		};
		$elements = array_map( $walker, $elements );

		// Persist. Elementor stores _elementor_data as a slashed JSON string,
		// so we encode + slash to match its update path. Clear the cached CSS
		// blob so the next render rebuilds against the new instance settings.
		update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $elements ) ) );
		delete_post_meta( $post_id, '_elementor_css' );

		return array(
			'post_id'           => $post_id,
			'tables_created'    => array_values( array_unique( $tables_created ) ),
			'instances_updated' => $instances_updated,
			'errors'            => $errors,
		);
	}

	/**
	 * Strip every legacy data control out of a widget instance and bolt
	 * selected_table_id on. Everything else (sticky, responsive, style
	 * settings) stays put so the migrated widget keeps its display.
	 */
	private static function clear_legacy_settings( array $settings, $new_id ) {
		$legacy_keys = array(
			'table_type',
			'caption',
			'first_row_is_header',
			'first_column_is_header',
			'columns',
			'rows',
			'items',
			'item_count',
			'highlight_item',
			'highlight_badge_text',
			'highlight_badge_position',
			'feature_rows',
		);
		foreach ( $legacy_keys as $k ) {
			unset( $settings[ $k ] );
		}
		$settings['selected_table_id']   = (string) $new_id;
		$settings['selected_table_type'] = (string) KDNA_Tables_CPT::get_type( $new_id );
		return $settings;
	}

	/* ==================================================================
	 * Rollback
	 * ================================================================== */

	public static function ajax_rollback_post() {
		check_ajax_referer( self::NONCE_BULK );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$backup  = get_post_meta( $post_id, self::BACKUP_META_KEY, true );
		if ( empty( $backup ) ) {
			wp_send_json_error( array( 'message' => __( 'No backup to restore.', 'kdna-tables' ) ) );
		}
		update_post_meta( $post_id, '_elementor_data', wp_slash( $backup ) );
		delete_post_meta( $post_id, '_elementor_css' );
		delete_post_meta( $post_id, self::BACKUP_META_KEY );
		wp_send_json_success( array( 'post_id' => $post_id ) );
	}

	/* ==================================================================
	 * Tools page
	 * ================================================================== */

	public static function render_tools_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run the migration tool.', 'kdna-tables' ) );
		}
		$nonce = wp_create_nonce( self::NONCE_BULK );
		?>
		<div class="wrap kdna-migration-tool">
			<h1><?php esc_html_e( 'Migrate v1.x widgets', 'kdna-tables' ); ?></h1>

			<div class="notice notice-warning">
				<p><?php esc_html_e( 'This tool scans every Elementor page on this site for legacy KDNA Table widget data and converts each into a reusable table in your library. Take a database backup before continuing.', 'kdna-tables' ); ?></p>
			</div>

			<p>
				<button type="button" class="button button-primary" id="kdna-scan">
					<?php esc_html_e( 'Scan for legacy widgets', 'kdna-tables' ); ?>
				</button>
				<button type="button" class="button" id="kdna-migrate-all" disabled>
					<?php esc_html_e( 'Migrate all', 'kdna-tables' ); ?>
				</button>
				<span id="kdna-progress" style="margin-left:12px; color:#50575e;"></span>
			</p>

			<div id="kdna-results"></div>
			<div id="kdna-log"></div>
		</div>

		<style>
			.kdna-results-table { width: 100%; border-collapse: collapse; margin-top: 12px; background: #fff; }
			.kdna-results-table th, .kdna-results-table td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #dcdcde; vertical-align: middle; }
			.kdna-results-table th { background: #f6f7f7; font-weight: 600; }
			.kdna-results-row.is-done { background: #ecf8ee; }
			.kdna-results-row.is-error { background: #fbeaea; }
			.kdna-progress-bar { display: inline-block; width: 240px; height: 8px; border: 1px solid #c3c4c7; border-radius: 4px; background: #fff; vertical-align: middle; margin-left: 8px; overflow: hidden; }
			.kdna-progress-bar > div { height: 100%; background: #2271b1; width: 0%; transition: width 200ms; }
			.kdna-log { margin-top: 16px; padding: 12px; background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 4px; font-family: Menlo, Consolas, monospace; font-size: 12px; white-space: pre-wrap; max-height: 360px; overflow: auto; }
		</style>

		<script>
		( function () {
			'use strict';
			var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var nonce   = <?php echo wp_json_encode( $nonce ); ?>;
			var chunkSize = <?php echo (int) self::BULK_CHUNK_SIZE; ?>;
			var scanned = [];
			var logEntries = [];

			function $$( sel ) { return document.querySelector( sel ); }

			function ajax( action, params ) {
				return new Promise( function ( resolve, reject ) {
					var body = new URLSearchParams();
					body.set( 'action', action );
					body.set( '_ajax_nonce', nonce );
					Object.keys( params || {} ).forEach( function ( k ) {
						var v = params[ k ];
						if ( Array.isArray( v ) ) {
							v.forEach( function ( item ) { body.append( k + '[]', item ); } );
						} else {
							body.set( k, v );
						}
					} );
					fetch( ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
						.then( function ( r ) { return r.json(); } )
						.then( function ( data ) {
							if ( data && data.success ) {
								resolve( data.data );
							} else {
								reject( ( data && data.data && data.data.message ) || 'ajax error' );
							}
						} )
						.catch( reject );
				} );
			}

			function renderResults() {
				var html = '';
				if ( ! scanned.length ) {
					html = '<p>' + <?php echo wp_json_encode( esc_html__( 'No legacy widgets found.', 'kdna-tables' ) ); ?> + '</p>';
				} else {
					html  = '<p><strong>' + scanned.length + '</strong> ' + <?php echo wp_json_encode( esc_html__( 'post(s) with legacy widgets.', 'kdna-tables' ) ); ?> + '</p>';
					html += '<table class="kdna-results-table"><thead><tr>';
					html += '<th>' + <?php echo wp_json_encode( esc_html__( 'Post', 'kdna-tables' ) ); ?> + '</th>';
					html += '<th>' + <?php echo wp_json_encode( esc_html__( 'Instances', 'kdna-tables' ) ); ?> + '</th>';
					html += '<th>' + <?php echo wp_json_encode( esc_html__( 'Status', 'kdna-tables' ) ); ?> + '</th>';
					html += '<th>' + <?php echo wp_json_encode( esc_html__( 'Action', 'kdna-tables' ) ); ?> + '</th>';
					html += '</tr></thead><tbody>';
					scanned.forEach( function ( row ) {
						var statusCls = row.status === 'done' ? 'is-done' : ( row.status === 'error' ? 'is-error' : '' );
						html += '<tr class="kdna-results-row ' + statusCls + '" data-post-id="' + row.id + '">';
						html += '<td>' + row.id + ' &mdash; ' + row.title + '</td>';
						html += '<td>' + row.count + '</td>';
						html += '<td class="kdna-row-status">' + ( row.status || 'pending' ) + '</td>';
						html += '<td>';
						if ( 'done' === row.status ) {
							html += '<button type="button" class="button-link kdna-rollback" data-post-id="' + row.id + '">' + <?php echo wp_json_encode( esc_html__( 'Rollback', 'kdna-tables' ) ); ?> + '</button>';
						}
						html += '</td></tr>';
					} );
					html += '</tbody></table>';
				}
				$$( '#kdna-results' ).innerHTML = html;

				Array.prototype.forEach.call( document.querySelectorAll( '.kdna-rollback' ), function ( btn ) {
					btn.addEventListener( 'click', function () {
						var id = parseInt( btn.getAttribute( 'data-post-id' ), 10 );
						if ( ! window.confirm( <?php echo wp_json_encode( esc_html__( 'Restore the original Elementor data for this post?', 'kdna-tables' ) ); ?> ) ) {
							return;
						}
						ajax( 'kdna_tables_rollback_post', { post_id: id } ).then( function () {
							btn.replaceWith( document.createTextNode( <?php echo wp_json_encode( esc_html__( 'Rolled back', 'kdna-tables' ) ); ?> ) );
						} ).catch( function ( err ) {
							window.alert( err );
						} );
					} );
				} );
			}

			function setProgress( done, total ) {
				var pct = total ? Math.round( done * 100 / total ) : 0;
				$$( '#kdna-progress' ).innerHTML = done + ' / ' + total + ' <span class="kdna-progress-bar"><div style="width:' + pct + '%"></div></span>';
			}

			function appendLog( entries ) {
				logEntries = logEntries.concat( entries || [] );
				var json = JSON.stringify( logEntries, null, 2 );
				$$( '#kdna-log' ).innerHTML = '<h2>' + <?php echo wp_json_encode( esc_html__( 'Log', 'kdna-tables' ) ); ?> + ' <button type="button" class="button" id="kdna-download-log">' + <?php echo wp_json_encode( esc_html__( 'Download JSON', 'kdna-tables' ) ); ?> + '</button></h2><pre class="kdna-log">' + json.replace( /</g, '&lt;' ) + '</pre>';
				var dl = $$( '#kdna-download-log' );
				if ( dl ) {
					dl.addEventListener( 'click', function () {
						var blob = new Blob( [ json ], { type: 'application/json' } );
						var a = document.createElement( 'a' );
						a.href = URL.createObjectURL( blob );
						a.download = 'kdna-tables-migration-log.json';
						document.body.appendChild( a );
						a.click();
						document.body.removeChild( a );
						setTimeout( function () { URL.revokeObjectURL( a.href ); }, 1000 );
					} );
				}
			}

			$$( '#kdna-scan' ).addEventListener( 'click', function () {
				$$( '#kdna-scan' ).disabled = true;
				$$( '#kdna-results' ).innerHTML = '<p>' + <?php echo wp_json_encode( esc_html__( 'Scanning ...', 'kdna-tables' ) ); ?> + '</p>';
				ajax( 'kdna_tables_bulk_scan', {} ).then( function ( data ) {
					scanned = ( data.results || [] ).map( function ( r ) {
						r.status = 'pending';
						return r;
					} );
					chunkSize = data.chunk_size || chunkSize;
					renderResults();
					$$( '#kdna-migrate-all' ).disabled = scanned.length === 0;
				} ).catch( function ( err ) {
					$$( '#kdna-results' ).innerHTML = '<p>' + <?php echo wp_json_encode( esc_html__( 'Scan failed:', 'kdna-tables' ) ); ?> + ' ' + err + '</p>';
				} ).finally( function () {
					$$( '#kdna-scan' ).disabled = false;
				} );
			} );

			$$( '#kdna-migrate-all' ).addEventListener( 'click', function () {
				if ( ! scanned.length ) {
					return;
				}
				$$( '#kdna-migrate-all' ).disabled = true;
				var pending = scanned.filter( function ( r ) { return 'pending' === r.status; } );
				var total = pending.length;
				var done = 0;
				logEntries = [];

				function nextChunk() {
					if ( ! pending.length ) {
						setProgress( done, total );
						return;
					}
					var chunk = pending.splice( 0, chunkSize );
					ajax( 'kdna_tables_bulk_migrate', { post_ids: chunk.map( function ( r ) { return r.id; } ) } )
						.then( function ( data ) {
							var entries = data.log || [];
							entries.forEach( function ( entry ) {
								var match = scanned.find( function ( r ) { return r.id === entry.post_id; } );
								if ( match ) {
									if ( entry.errors && entry.errors.length ) {
										match.status = 'error';
									} else {
										match.status = 'done';
									}
								}
							} );
							done += chunk.length;
							setProgress( done, total );
							appendLog( entries );
							renderResults();
							nextChunk();
						} )
						.catch( function ( err ) {
							chunk.forEach( function ( r ) { r.status = 'error'; } );
							done += chunk.length;
							setProgress( done, total );
							appendLog( [ { error: err } ] );
							renderResults();
							nextChunk();
						} );
				}
				setProgress( 0, total );
				nextChunk();
			} );
		} )();
		</script>
		<?php
	}
}
