<?php
/**
 * Admin surface for the kdna_table custom post type. Registers the
 * top-level KDNA Tables menu, customises the list table, adds the
 * Duplicate row action, and routes Add New to the Type Chooser page.
 *
 * @package KDNA_Tables
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KDNA_Tables_Admin {

	const MENU_SLUG_LIST       = 'edit.php?post_type=kdna_table';
	const MENU_SLUG_ADD_NEW    = 'kdna-tables-add-new';
	const MENU_SLUG_TOOLS      = 'kdna-tables-tools';
	const NONCE_CREATE_TABLE   = 'kdna_tables_create_table';
	const NONCE_DUPLICATE      = 'kdna_tables_duplicate_table';
	const NONCE_EDITOR_AJAX    = 'kdna_tables_editor_ajax';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_menu', array( __CLASS__, 'fix_submenu_highlight' ), 999 );
		add_action( 'admin_init', array( __CLASS__, 'intercept_default_add_new' ) );

		add_filter( 'manage_' . KDNA_Tables_CPT::POST_TYPE . '_posts_columns', array( __CLASS__, 'list_table_columns' ) );
		add_action( 'manage_' . KDNA_Tables_CPT::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_list_column' ), 10, 2 );

		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_action( 'admin_action_kdna_tables_duplicate', array( __CLASS__, 'handle_duplicate' ) );
		add_action( 'admin_action_kdna_tables_create', array( __CLASS__, 'handle_create' ) );

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );

		add_filter( 'parent_file', array( __CLASS__, 'parent_file' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'submenu_file' ) );

		// AJAX endpoint used by the Elementor editor JS to look up a table's
		// type when the source dropdown changes. Authenticated only.
		add_action( 'wp_ajax_kdna_tables_get_table_type', array( __CLASS__, 'ajax_get_table_type' ) );

		// Localise the Elementor editor JS with the AJAX url and nonce.
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'localize_editor_script' ), 20 );

		// Bust the Used-in transient on every post save (Elementor data may
		// have shifted) and when a kdna_table moves through statuses.
		add_action( 'save_post', array( __CLASS__, 'flush_usage_counts' ) );
		add_action( 'deleted_post', array( __CLASS__, 'flush_usage_counts' ) );
	}

	public static function ajax_get_table_type() {
		check_ajax_referer( self::NONCE_EDITOR_AJAX );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		$table_id = isset( $_POST['table_id'] ) ? absint( $_POST['table_id'] ) : 0;
		if ( $table_id <= 0 ) {
			wp_send_json_success( array( 'type' => '' ) );
		}
		$type = KDNA_Tables_CPT::get_type( $table_id );
		wp_send_json_success( array( 'type' => $type ) );
	}

	public static function localize_editor_script() {
		// Hooked after Elementor's editor scripts so the plugin's editor JS
		// is already registered, but only emits the global if the handle
		// actually got enqueued.
		if ( ! wp_script_is( KDNA_Tables_Plugin::EDITOR_SCRIPT_HANDLE, 'enqueued' ) ) {
			return;
		}
		wp_localize_script(
			KDNA_Tables_Plugin::EDITOR_SCRIPT_HANDLE,
			'KDNATablesEditor',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_EDITOR_AJAX ),
			)
		);
	}

	public static function register_menu() {
		add_menu_page(
			__( 'KDNA Tables', 'kdna-tables' ),
			__( 'KDNA Tables', 'kdna-tables' ),
			'edit_posts',
			self::MENU_SLUG_LIST,
			'',
			'dashicons-grid-view',
			25
		);

		add_submenu_page(
			self::MENU_SLUG_LIST,
			__( 'All Tables', 'kdna-tables' ),
			__( 'All Tables', 'kdna-tables' ),
			'edit_posts',
			self::MENU_SLUG_LIST
		);

		add_submenu_page(
			self::MENU_SLUG_LIST,
			__( 'Add New Table', 'kdna-tables' ),
			__( 'Add New', 'kdna-tables' ),
			'edit_posts',
			self::MENU_SLUG_ADD_NEW,
			array( __CLASS__, 'render_type_chooser_page' )
		);

		// Tools page contains the bulk migration utility, which writes to
		// arbitrary posts' _elementor_data. Restrict to administrators.
		add_submenu_page(
			self::MENU_SLUG_LIST,
			__( 'KDNA Tables Tools', 'kdna-tables' ),
			__( 'Tools', 'kdna-tables' ),
			'manage_options',
			self::MENU_SLUG_TOOLS,
			array( __CLASS__, 'render_tools_page' )
		);
	}

	/**
	 * WordPress auto-adds an "Add New" submenu when a CPT supports show_ui.
	 * Even with show_in_menu=false, edit.php?post_type=kdna_table will still
	 * link Add New to post-new.php. We swap that link, and intercept the URL
	 * directly in intercept_default_add_new() as a belt-and-braces guard.
	 */
	public static function fix_submenu_highlight() {
		// Nothing to remove here for show_in_menu=false, but keep this hook
		// available for future submenu adjustments.
	}

	/**
	 * Anyone who lands on post-new.php?post_type=kdna_table or clicks the
	 * "Add New" admin-bar shortcut is redirected to the Type Chooser. Type
	 * is permanent for the entry, so we never let the user reach the empty
	 * edit screen without committing to a type first.
	 */
	public static function intercept_default_add_new() {
		if ( ! is_admin() ) {
			return;
		}
		global $pagenow;
		if ( 'post-new.php' !== $pagenow ) {
			return;
		}
		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
		if ( KDNA_Tables_CPT::POST_TYPE !== $post_type ) {
			return;
		}
		wp_safe_redirect( self::get_type_chooser_url() );
		exit;
	}

	public static function get_type_chooser_url() {
		return admin_url( 'admin.php?page=' . self::MENU_SLUG_ADD_NEW );
	}

	public static function get_list_url() {
		return admin_url( self::MENU_SLUG_LIST );
	}

	public static function parent_file( $parent_file ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && KDNA_Tables_CPT::POST_TYPE === $screen->post_type ) {
			return self::MENU_SLUG_LIST;
		}
		return $parent_file;
	}

	public static function submenu_file( $submenu_file ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && KDNA_Tables_CPT::POST_TYPE === $screen->post_type && in_array( $screen->base, array( 'post', 'edit' ), true ) ) {
			return self::MENU_SLUG_LIST;
		}
		return $submenu_file;
	}

	const USAGE_TRANSIENT = 'kdna_tables_usage_v1';

	public static function list_table_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['kdna_type']      = __( 'Type', 'kdna-tables' );
				$new['kdna_count']     = __( 'Rows / Items', 'kdna-tables' );
				$new['kdna_features']  = __( 'Feature rows', 'kdna-tables' );
				$new['kdna_usage']     = __( 'Used in', 'kdna-tables' );
				$new['kdna_shortcode'] = __( 'Shortcode', 'kdna-tables' );
			}
		}
		return $new;
	}

	/**
	 * Cheap usage count: one LIKE query per kdna_table id, cached in a
	 * transient for 5 minutes. The transient is busted whenever any post
	 * is saved (Elementor data may have moved). For small libraries this
	 * adds a single page-load cost; larger libraries should switch to
	 * indexed references in v2.1.
	 */
	public static function get_usage_counts() {
		$cached = get_transient( self::USAGE_TRANSIENT );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		$counts = array();
		$ids    = get_posts(
			array(
				'post_type'      => KDNA_Tables_CPT::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'numberposts'    => -1,
				'fields'         => 'ids',
				'suppress_filters' => true,
			)
		);
		foreach ( $ids as $id ) {
			$like   = '%' . $wpdb->esc_like( '"selected_table_id":"' . (int) $id . '"' ) . '%';
			$counts[ (int) $id ] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND meta_value LIKE %s",
					$like
				)
			);
		}
		set_transient( self::USAGE_TRANSIENT, $counts, 5 * MINUTE_IN_SECONDS );
		return $counts;
	}

	public static function flush_usage_counts() {
		delete_transient( self::USAGE_TRANSIENT );
	}

	public static function render_list_column( $column, $post_id ) {
		switch ( $column ) {
			case 'kdna_type':
				$type = KDNA_Tables_CPT::get_type( $post_id );
				if ( 'general' === $type ) {
					echo '<span class="kdna-pill kdna-pill--general">' . esc_html__( 'General', 'kdna-tables' ) . '</span>';
				} elseif ( 'comparison' === $type ) {
					echo '<span class="kdna-pill kdna-pill--comparison">' . esc_html__( 'Comparison', 'kdna-tables' ) . '</span>';
				} else {
					echo '<span aria-hidden="true">,</span><span class="screen-reader-text">' . esc_html__( 'Unknown type', 'kdna-tables' ) . '</span>';
				}
				break;

			case 'kdna_count':
				$type = KDNA_Tables_CPT::get_type( $post_id );
				if ( 'general' === $type ) {
					$data = get_post_meta( $post_id, KDNA_Tables_CPT::META_GENERAL, true );
					$rows = ( is_array( $data ) && ! empty( $data['rows'] ) && is_array( $data['rows'] ) ) ? count( $data['rows'] ) : 0;
					$cols = ( is_array( $data ) && ! empty( $data['columns'] ) && is_array( $data['columns'] ) ) ? count( $data['columns'] ) : 0;
					printf(
						/* translators: 1: number of rows, 2: number of columns */
						esc_html__( '%1$d rows, %2$d cols', 'kdna-tables' ),
						(int) $rows,
						(int) $cols
					);
				} elseif ( 'comparison' === $type ) {
					$data  = get_post_meta( $post_id, KDNA_Tables_CPT::META_COMPARISON, true );
					$items = ( is_array( $data ) && ! empty( $data['items'] ) && is_array( $data['items'] ) ) ? count( $data['items'] ) : 0;
					printf(
						/* translators: %d: number of items */
						esc_html__( '%d items', 'kdna-tables' ),
						(int) $items
					);
				} else {
					echo '<span aria-hidden="true">,</span>';
				}
				break;

			case 'kdna_features':
				$type = KDNA_Tables_CPT::get_type( $post_id );
				if ( 'comparison' === $type ) {
					$data = get_post_meta( $post_id, KDNA_Tables_CPT::META_COMPARISON, true );
					$rows = ( is_array( $data ) && ! empty( $data['feature_rows'] ) && is_array( $data['feature_rows'] ) ) ? count( $data['feature_rows'] ) : 0;
					printf(
						/* translators: %d: number of feature rows */
						esc_html__( '%d rows', 'kdna-tables' ),
						(int) $rows
					);
				} else {
					echo '<span aria-hidden="true">,</span>';
				}
				break;

			case 'kdna_usage':
				$counts = self::get_usage_counts();
				$count  = isset( $counts[ (int) $post_id ] ) ? (int) $counts[ (int) $post_id ] : 0;
				if ( $count > 0 ) {
					printf(
						/* translators: %d: number of posts */
						esc_html( _n( '%d post', '%d posts', $count, 'kdna-tables' ) ),
						(int) $count
					);
				} else {
					echo '<span aria-hidden="true">,</span><span class="screen-reader-text">' . esc_html__( 'Not used yet', 'kdna-tables' ) . '</span>';
				}
				break;

			case 'kdna_shortcode':
				$shortcode = sprintf( '[kdna_table id="%d"]', (int) $post_id );
				printf(
					'<code class="kdna-shortcode">%1$s</code> <button type="button" class="button-link kdna-copy-shortcode" data-clipboard-text="%2$s" aria-label="%3$s">%4$s</button>',
					esc_html( $shortcode ),
					esc_attr( $shortcode ),
					esc_attr__( 'Copy shortcode to clipboard', 'kdna-tables' ),
					esc_html__( 'Copy', 'kdna-tables' )
				);
				break;
		}
	}

	public static function row_actions( $actions, $post ) {
		if ( ! $post instanceof WP_Post || KDNA_Tables_CPT::POST_TYPE !== $post->post_type ) {
			return $actions;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'kdna_tables_duplicate',
					'post'   => $post->ID,
				),
				admin_url( 'admin.php' )
			),
			self::NONCE_DUPLICATE . '_' . $post->ID
		);
		$actions['kdna_duplicate'] = sprintf(
			'<a href="%1$s" aria-label="%2$s">%3$s</a>',
			esc_url( $url ),
			esc_attr__( 'Duplicate this table', 'kdna-tables' ),
			esc_html__( 'Duplicate', 'kdna-tables' )
		);
		return $actions;
	}

	public static function handle_duplicate() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		if ( ! $post_id ) {
			wp_die( esc_html__( 'Missing table id.', 'kdna-tables' ) );
		}
		check_admin_referer( self::NONCE_DUPLICATE . '_' . $post_id );
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to duplicate this table.', 'kdna-tables' ) );
		}
		$original = get_post( $post_id );
		if ( ! $original || KDNA_Tables_CPT::POST_TYPE !== $original->post_type ) {
			wp_die( esc_html__( 'Table not found.', 'kdna-tables' ) );
		}

		$new_id = wp_insert_post(
			array(
				'post_type'   => KDNA_Tables_CPT::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => $original->post_title . ' ' . __( '(copy)', 'kdna-tables' ),
				'post_author' => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $new_id ) ) {
			wp_die( esc_html( $new_id->get_error_message() ) );
		}

		// Copy every kdna meta key across so type, caption, data and schema all carry.
		$meta_keys = array(
			KDNA_Tables_CPT::META_TYPE,
			KDNA_Tables_CPT::META_CAPTION,
			KDNA_Tables_CPT::META_GENERAL,
			KDNA_Tables_CPT::META_COMPARISON,
			KDNA_Tables_CPT::META_SCHEMA,
		);
		foreach ( $meta_keys as $key ) {
			$value = get_post_meta( $post_id, $key, true );
			if ( '' === $value || array() === $value ) {
				continue;
			}
			update_post_meta( $new_id, $key, $value );
		}
		// Do not copy the content hash, the duplicate is logically a new entry.
		delete_post_meta( $new_id, KDNA_Tables_CPT::META_CONTENT_HASH );

		wp_safe_redirect( get_edit_post_link( $new_id, 'redirect' ) );
		exit;
	}

	public static function render_type_chooser_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to add tables.', 'kdna-tables' ) );
		}
		$template = KDNA_TABLES_PATH . 'templates/admin-type-chooser-modal.php';
		if ( file_exists( $template ) ) {
			$create_action_url = admin_url( 'admin.php?action=kdna_tables_create' );
			$nonce_field_name  = self::NONCE_CREATE_TABLE;
			$cancel_url        = self::get_list_url();
			include $template;
		}
	}

	public static function render_tools_page() {
		if ( class_exists( 'KDNA_Tables_Migration' ) ) {
			KDNA_Tables_Migration::render_tools_page();
			return;
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'kdna-tables' ) );
		}
		?>
		<div class="wrap kdna-tools-page">
			<h1><?php esc_html_e( 'KDNA Tables Tools', 'kdna-tables' ); ?></h1>
			<p><?php esc_html_e( 'Migration utilities not available, the migration class failed to load.', 'kdna-tables' ); ?></p>
		</div>
		<?php
	}

	public static function handle_create() {
		check_admin_referer( self::NONCE_CREATE_TABLE );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to add tables.', 'kdna-tables' ) );
		}
		$type = isset( $_POST['kdna_table_type'] ) ? sanitize_key( wp_unslash( $_POST['kdna_table_type'] ) ) : '';
		if ( ! in_array( $type, array( 'general', 'comparison' ), true ) ) {
			wp_safe_redirect( self::get_type_chooser_url() );
			exit;
		}

		$title = 'general' === $type
			? __( 'Untitled general table', 'kdna-tables' )
			: __( 'Untitled comparison table', 'kdna-tables' );

		$post_id = wp_insert_post(
			array(
				'post_type'   => KDNA_Tables_CPT::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => $title,
				'post_author' => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			wp_die( esc_html( $post_id->get_error_message() ) );
		}

		update_post_meta( $post_id, KDNA_Tables_CPT::META_TYPE, $type );
		update_post_meta( $post_id, KDNA_Tables_CPT::META_SCHEMA, KDNA_Tables_CPT::SCHEMA_VERSION );
		update_post_meta( $post_id, KDNA_Tables_CPT::META_CAPTION, '' );

		if ( 'general' === $type ) {
			update_post_meta( $post_id, KDNA_Tables_CPT::META_GENERAL, KDNA_Tables_CPT::default_general_data() );
		} else {
			update_post_meta( $post_id, KDNA_Tables_CPT::META_COMPARISON, KDNA_Tables_CPT::default_comparison_data() );
		}

		wp_safe_redirect( get_edit_post_link( $post_id, 'redirect' ) );
		exit;
	}

	public static function enqueue_admin_assets( $hook_suffix ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_kdna_screen = false;

		if ( $screen ) {
			if ( KDNA_Tables_CPT::POST_TYPE === $screen->post_type ) {
				$is_kdna_screen = true;
			}
			if ( isset( $_GET['page'] ) && in_array( $_GET['page'], array( self::MENU_SLUG_ADD_NEW, self::MENU_SLUG_TOOLS ), true ) ) {
				$is_kdna_screen = true;
			}
		}

		if ( ! $is_kdna_screen ) {
			return;
		}

		wp_enqueue_style(
			'kdna-tables-admin',
			KDNA_TABLES_URL . 'assets/css/kdna-admin.css',
			array(),
			KDNA_TABLES_VERSION
		);

		// Tiny clipboard helper for the Shortcode column. Bigger admin JS
		// arrives in Session 3 with the Alpine editor.
		wp_add_inline_script(
			'common',
			"(function(){document.addEventListener('click',function(e){var b=e.target.closest('.kdna-copy-shortcode');if(!b)return;e.preventDefault();var t=b.getAttribute('data-clipboard-text')||'';if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(t);}else{var ta=document.createElement('textarea');ta.value=t;document.body.appendChild(ta);ta.select();try{document.execCommand('copy');}catch(err){}document.body.removeChild(ta);}var o=b.textContent;b.textContent=" . wp_json_encode( __( 'Copied', 'kdna-tables' ) ) . ";setTimeout(function(){b.textContent=o;},1500);});})();"
		);
	}
}
