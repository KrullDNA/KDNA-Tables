<?php
/**
 * Replaces the standard WordPress post edit screen for kdna_table entries
 * with the Alpine.js matrix editor. Handles meta boxes, asset enqueuing,
 * the initial-state JSON blob, and post save dispatch.
 *
 * @package KDNA_Tables
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KDNA_Tables_Editor {

	const NONCE_ACTION = 'kdna_tables_editor_save';
	const NONCE_FIELD  = 'kdna_tables_editor_nonce';
	const STATE_INPUT  = 'kdna_tables_editor_state';

	const SCRIPT_HANDLE_ALPINE = 'kdna-tables-alpine';
	const SCRIPT_HANDLE_ADMIN  = 'kdna-tables-admin';
	const STYLE_HANDLE_ADMIN   = 'kdna-tables-admin';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'remove_post_type_supports' ) );
		add_action( 'add_meta_boxes_' . KDNA_Tables_CPT::POST_TYPE, array( __CLASS__, 'register_meta_boxes' ) );
		add_action( 'edit_form_after_title', array( __CLASS__, 'render_nonce_field' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_editor_assets' ) );
		add_action( 'save_post_' . KDNA_Tables_CPT::POST_TYPE, array( __CLASS__, 'save_post' ), 10, 3 );
		add_action( 'edit_form_top', array( __CLASS__, 'render_type_banner' ) );
	}

	public static function remove_post_type_supports() {
		// The CPT registered supports => array( 'title' ) only, this is a
		// safety net for any theme or plugin that re-adds defaults.
		foreach ( array( 'editor', 'thumbnail', 'custom-fields', 'revisions', 'comments', 'excerpt', 'trackbacks', 'page-attributes', 'author' ) as $feature ) {
			remove_post_type_support( KDNA_Tables_CPT::POST_TYPE, $feature );
		}
	}

	public static function register_meta_boxes( $post ) {
		// Strip every default meta box, then add the ones we own.
		$cpt = KDNA_Tables_CPT::POST_TYPE;
		foreach ( array( 'normal', 'side', 'advanced' ) as $context ) {
			foreach ( array( 'high', 'core', 'default', 'low' ) as $priority ) {
				$_unused = $priority; // priorities are iterated for clarity, remove_meta_box is global anyway
			}
		}
		// Standard default meta boxes that show up for any CPT, removed by name.
		remove_meta_box( 'submitdiv', $cpt, 'side' ); // Publish, kept by adding back below.
		remove_meta_box( 'slugdiv', $cpt, 'normal' );
		remove_meta_box( 'authordiv', $cpt, 'normal' );
		remove_meta_box( 'commentstatusdiv', $cpt, 'normal' );
		remove_meta_box( 'commentsdiv', $cpt, 'normal' );
		remove_meta_box( 'postcustom', $cpt, 'normal' );
		remove_meta_box( 'revisionsdiv', $cpt, 'normal' );
		remove_meta_box( 'trackbacksdiv', $cpt, 'normal' );

		// Put Publish back, we still need it.
		add_meta_box(
			'submitdiv',
			__( 'Publish', 'kdna-tables' ),
			'post_submit_meta_box',
			$cpt,
			'side',
			'high'
		);

		add_meta_box(
			'kdna_tables_editor',
			__( 'Table data', 'kdna-tables' ),
			array( __CLASS__, 'render_editor_meta_box' ),
			$cpt,
			'normal',
			'high'
		);

		add_meta_box(
			'kdna_tables_shortcode',
			__( 'Shortcode', 'kdna-tables' ),
			array( __CLASS__, 'render_shortcode_meta_box' ),
			$cpt,
			'side',
			'default'
		);

		// Structural preview, below the editor meta box. Renders a plain
		// HTML table from the current Alpine state via a custom event the
		// editor JS dispatches on every state change.
		add_meta_box(
			'kdna_tables_preview',
			__( 'Structural preview', 'kdna-tables' ),
			array( __CLASS__, 'render_preview_meta_box' ),
			$cpt,
			'normal',
			'low'
		);
	}

	public static function render_nonce_field() {
		$screen = get_current_screen();
		if ( ! $screen || KDNA_Tables_CPT::POST_TYPE !== $screen->post_type ) {
			return;
		}
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
	}

	public static function render_type_banner( $post ) {
		if ( ! $post instanceof WP_Post || KDNA_Tables_CPT::POST_TYPE !== $post->post_type ) {
			return;
		}
		$type = KDNA_Tables_CPT::get_type( $post->ID );
		if ( '' === $type ) {
			return;
		}
		$label = 'general' === $type
			? __( 'General Table', 'kdna-tables' )
			: __( 'Comparison Table', 'kdna-tables' );
		printf(
			'<p class="kdna-editor__type-banner description">%s <strong>%s</strong></p>',
			esc_html__( 'Editing table type:', 'kdna-tables' ),
			esc_html( $label )
		);
	}

	public static function render_editor_meta_box( $post ) {
		$type = KDNA_Tables_CPT::get_type( $post->ID );

		printf(
			'<input type="hidden" id="%1$s" name="%1$s" value="">',
			esc_attr( self::STATE_INPUT )
		);

		/*
		 * ── Say so when the editor has not booted ─────────────────────
		 *
		 * Everything below this is Alpine. If its scripts do not run —
		 * blocked, reordered by an optimiser, 404ing behind a CDN — the
		 * markup still renders, but as an empty grid that looks exactly
		 * like a brand new table. Someone then presses Update on what
		 * they reasonably believe is their table.
		 *
		 * This notice is in the page from the start and removed by the
		 * editor on a successful boot, so the failure state is the one
		 * that talks. The save handler refuses that post regardless; this
		 * is so nobody has to find that out by losing an afternoon.
		 */
		printf(
			'<div class="notice notice-error kdna-editor__boot-warning" id="kdna-editor-boot-warning">'
			. '<p><strong>%1$s</strong></p><p>%2$s</p><p>%3$s</p></div>',
			esc_html__( 'The table editor did not load.', 'kdna-tables' ),
			esc_html__( 'Your table data is safe and still in the database — this screen just cannot show it. Do not press Update: a save from here is refused rather than allowed to overwrite your rows, but there is no reason to try.', 'kdna-tables' ),
			esc_html__( 'This is almost always a script that failed to load or ran out of order. Reload with a hard refresh first; if it persists, check the browser console for an error on kdna-admin.js and disable JavaScript optimisation, deferral or minification for the admin.', 'kdna-tables' )
		);

		$counts = self::stored_counts( $post->ID );
		if ( $counts ) {
			printf(
				'<p class="kdna-editor__boot-counts" id="kdna-editor-boot-counts">%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: number of rows, 2: number of columns. */
						__( 'Stored in the database right now: %1$d rows, %2$d columns.', 'kdna-tables' ),
						$counts['rows'],
						$counts['columns']
					)
				)
			);
		}

		if ( 'general' === $type ) {
			include KDNA_TABLES_PATH . 'templates/admin-editor-general.php';
			return;
		}

		if ( 'comparison' === $type ) {
			include KDNA_TABLES_PATH . 'templates/admin-editor-comparison.php';
			return;
		}

		echo '<div class="kdna-editor__placeholder">';
		echo '<strong>' . esc_html__( 'Pick a table type first.', 'kdna-tables' ) . '</strong>';
		echo '<span>' . esc_html__( 'Tables created without picking a type need to be deleted and recreated from the Add New screen.', 'kdna-tables' ) . '</span>';
		echo '</div>';
	}

	public static function render_preview_meta_box( $post ) {
		?>
		<div class="kdna-preview">
			<p class="description">
				<?php esc_html_e( 'Structural preview only. Visual styling is controlled by the KDNA Table Elementor widget where this table is used.', 'kdna-tables' ); ?>
			</p>
			<div id="kdna-preview-content" class="kdna-preview__content" aria-live="polite"></div>
		</div>
		<style>
			.kdna-preview__content { margin-top: 8px; }
			.kdna-preview__content table { border-collapse: collapse; width: 100%; font-size: 13px; }
			.kdna-preview__content caption { caption-side: top; padding: 4px 0; text-align: left; font-weight: 600; }
			.kdna-preview__content th,
			.kdna-preview__content td { border: 1px solid #ccc; padding: 4px 6px; vertical-align: top; text-align: left; }
			.kdna-preview__content th { background: #f6f7f7; font-weight: 600; }
			.kdna-preview__content .kdna-preview__cell-piece { display: inline-block; margin-right: 4px; vertical-align: middle; }
			.kdna-preview__content .kdna-preview__cell-piece img { max-width: 24px; max-height: 24px; vertical-align: middle; }
			.kdna-preview__content .kdna-preview__cell-state { font-weight: 600; }
			.kdna-preview__content .kdna-preview__badge { display: inline-block; padding: 0 4px; background: #2271b1; color: #fff; border-radius: 3px; font-size: 11px; margin-left: 4px; }
			.kdna-preview__content .kdna-preview__empty { color: #50575e; font-style: italic; }
		</style>
		<?php
	}

	public static function render_shortcode_meta_box( $post ) {
		$shortcode = sprintf( '[kdna_table id="%d"]', (int) $post->ID );
		?>
		<div class="kdna-tables-shortcode-box">
			<p><?php esc_html_e( 'Use this shortcode to render the table outside Elementor (classic editor, theme template, etc).', 'kdna-tables' ); ?></p>
			<code><?php echo esc_html( $shortcode ); ?></code>
			<button type="button" class="button button-secondary kdna-copy-shortcode" data-clipboard-text="<?php echo esc_attr( $shortcode ); ?>">
				<?php esc_html_e( 'Copy shortcode', 'kdna-tables' ); ?>
			</button>
		</div>
		<?php
	}

	public static function enqueue_editor_assets( $hook_suffix ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || KDNA_Tables_CPT::POST_TYPE !== $screen->post_type ) {
			return;
		}
		if ( 'post' !== $screen->base ) {
			// Only enqueue on the post edit screen, not the list table.
			return;
		}

		wp_enqueue_style( self::STYLE_HANDLE_ADMIN );

		// Session 4: icon stylesheets so the picker can render glyphs. We
		// enqueue Elementor's registered handles when available, no-op
		// when not. Both Elementor free and Pro register these on admin.
		foreach ( array( 'elementor-icons', 'font-awesome-5-all', 'font-awesome' ) as $icon_handle ) {
			if ( wp_style_is( $icon_handle, 'registered' ) ) {
				wp_enqueue_style( $icon_handle );
			}
		}

		// Session 4: the image picker uses wp.media().
		wp_enqueue_media();

		// Alpine boots immediately when its script tag is parsed in the
		// footer (document.readyState is no longer 'loading' by then), so
		// kdna-admin.js must execute first to register the component
		// factory and the alpine:init listener. We declare admin.js as a
		// dependency of Alpine to pin that order, since WP guarantees
		// dependencies output before dependents.
		wp_enqueue_script(
			self::SCRIPT_HANDLE_ADMIN,
			KDNA_TABLES_URL . 'assets/js/kdna-admin.js',
			array(),
			KDNA_TABLES_VERSION,
			true
		);

		wp_enqueue_script(
			self::SCRIPT_HANDLE_ALPINE,
			KDNA_TABLES_URL . 'assets/js/alpine.min.js',
			array( self::SCRIPT_HANDLE_ADMIN ),
			'3.15.12',
			true
		);

		global $post;
		if ( $post instanceof WP_Post && KDNA_Tables_CPT::POST_TYPE === $post->post_type ) {
			$state = self::build_initial_state( $post->ID );
			wp_add_inline_script(
				self::SCRIPT_HANDLE_ADMIN,
				'window.kdnaTablesInitialState = ' . wp_json_encode( $state ) . ';',
				'before'
			);
		}

		// Session 4: emit the icon catalogue URL + nonces. The editor JS
		// pulls icons.json lazily on first paint so the initial render is
		// not blocked by the metadata fetch.
		wp_add_inline_script(
			self::SCRIPT_HANDLE_ADMIN,
			'window.KDNATablesAdmin = ' . wp_json_encode(
				array(
					'iconsUrl' => KDNA_TABLES_URL . 'assets/js/kdna-icons.json?ver=' . KDNA_TABLES_VERSION,
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Row and column counts straight from meta, for the boot warning.
	 *
	 * Read here rather than taken from the editor state, because the
	 * whole point is to say what is in the database when the editor
	 * cannot.
	 */
	private static function stored_counts( $post_id ) {
		$type = KDNA_Tables_CPT::get_type( $post_id );
		$key  = ( 'comparison' === $type ) ? KDNA_Tables_CPT::META_COMPARISON : KDNA_Tables_CPT::META_GENERAL;
		$data = get_post_meta( $post_id, $key, true );

		if ( ! is_array( $data ) ) {
			return null;
		}

		$rows    = isset( $data['rows'] ) && is_array( $data['rows'] ) ? count( $data['rows'] ) : 0;
		$columns = isset( $data['columns'] ) && is_array( $data['columns'] ) ? count( $data['columns'] ) : 0;

		if ( 'comparison' === $type ) {
			$rows    = isset( $data['feature_rows'] ) && is_array( $data['feature_rows'] ) ? count( $data['feature_rows'] ) : 0;
			$columns = isset( $data['items'] ) && is_array( $data['items'] ) ? count( $data['items'] ) : 0;
		}

		return array( 'rows' => $rows, 'columns' => $columns );
	}

	/**
	 * Build the JSON-friendly initial state for the editor. The shape is
	 * intentionally aligned with the CPT meta shape, snake_case throughout,
	 * so the editor can round-trip without renaming.
	 */
	private static function build_initial_state( $post_id ) {
		$type    = KDNA_Tables_CPT::get_type( $post_id );
		$caption = (string) get_post_meta( $post_id, KDNA_Tables_CPT::META_CAPTION, true );

		$state = array(
			'post_id' => (int) $post_id,
			'type'    => $type,
			'caption' => $caption,
		);

		if ( 'general' === $type ) {
			$data            = get_post_meta( $post_id, KDNA_Tables_CPT::META_GENERAL, true );
			$state['general'] = self::normalise_general_state( is_array( $data ) ? $data : array() );
		} elseif ( 'comparison' === $type ) {
			$data                = get_post_meta( $post_id, KDNA_Tables_CPT::META_COMPARISON, true );
			$state['comparison'] = is_array( $data ) ? $data : array();
		}

		return $state;
	}

	/**
	 * The editor expects every column to have a width/width_unit pair and
	 * every cell to have content_types/text/icon/image/arrangement/alignment
	 * present, so the Alpine view code can rely on direct property access
	 * without optional-chaining defensive guards everywhere.
	 */
	private static function normalise_general_state( array $data ) {
		$out = array(
			'first_row_is_header'    => ! empty( $data['first_row_is_header'] ),
			'first_column_is_header' => ! empty( $data['first_column_is_header'] ),
			'columns'                => array(),
			'rows'                   => array(),
		);

		$columns = isset( $data['columns'] ) && is_array( $data['columns'] ) ? $data['columns'] : array();
		foreach ( $columns as $i => $col ) {
			$col            = is_array( $col ) ? $col : array();
			$out['columns'][] = array(
				'id'               => isset( $col['id'] ) ? (string) $col['id'] : 'col_' . ( $i + 1 ),
				'label'            => isset( $col['label'] ) ? (string) $col['label'] : '',
				'alignment'        => isset( $col['alignment'] ) ? (string) $col['alignment'] : 'left',
				// Editor used to drop header_alignment when hydrating state.
				// On reload the L/C/R toolbar showed no active state, and the
				// next save round-tripped an empty value through sanitize so
				// the column header reverted to the default centre. Carry it
				// through here so it survives reload+save cycles.
				'header_alignment' => isset( $col['header_alignment'] ) ? (string) $col['header_alignment'] : '',
				'width'            => isset( $col['width'] ) ? (float) $col['width'] : 0,
				'width_unit'       => isset( $col['width_unit'] ) ? (string) $col['width_unit'] : '%',
			);
		}

		$rows = isset( $data['rows'] ) && is_array( $data['rows'] ) ? $data['rows'] : array();
		foreach ( $rows as $i => $row ) {
			$row   = is_array( $row ) ? $row : array();
			$cells = isset( $row['cells'] ) && is_array( $row['cells'] ) ? $row['cells'] : array();
			$out_cells = array();
			foreach ( $cells as $j => $cell ) {
				$cell        = is_array( $cell ) ? $cell : array();
				$out_cells[] = array(
					'id'            => isset( $cell['id'] ) ? (string) $cell['id'] : sprintf( 'cell_%d_%d', $i + 1, $j + 1 ),
					'content_types' => ( isset( $cell['content_types'] ) && is_array( $cell['content_types'] ) ) ? array_values( $cell['content_types'] ) : array( 'text' ),
					'text'          => isset( $cell['text'] ) ? (string) $cell['text'] : '',
					'icon'          => isset( $cell['icon'] ) && is_array( $cell['icon'] ) ? $cell['icon'] : array( 'value' => '', 'library' => '' ),
					'image'         => isset( $cell['image'] ) && is_array( $cell['image'] ) ? $cell['image'] : array( 'id' => 0, 'url' => '', 'alt' => '' ),
					'arrangement'   => isset( $cell['arrangement'] ) ? (string) $cell['arrangement'] : 'icon-text',
					'alignment'     => isset( $cell['alignment'] ) ? (string) $cell['alignment'] : '',
				);
			}
			$out['rows'][] = array(
				'id'    => isset( $row['id'] ) ? (string) $row['id'] : 'row_' . ( $i + 1 ),
				'cells' => $out_cells,
			);
		}

		return $out;
	}

	public static function save_post( $post_id, $post, $update ) {
		// Skip autosaves, revisions, and bulk-edit dispatches.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Type is permanent for the entry, fixed at creation in Session 1.
		// Each editor (general, comparison) owns its own meta key.
		$type = KDNA_Tables_CPT::get_type( $post_id );
		if ( 'general' !== $type && 'comparison' !== $type ) {
			return;
		}

		$raw = isset( $_POST[ self::STATE_INPUT ] ) ? wp_unslash( $_POST[ self::STATE_INPUT ] ) : '';
		if ( '' === $raw ) {
			return;
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return;
		}

		/*
		 * ── Refuse a state the editor never seeded ────────────────────
		 *
		 * The editor seeds itself from window.kdnaTablesInitialState. If
		 * that never arrives — the script blocked, reordered by an
		 * optimiser, 404ing behind a CDN — Alpine falls back to
		 * defaultGeneralState(), which is one empty column and one empty
		 * row. The screen then looks like a brand new table, and pressing
		 * Update writes that 1x1 blank over a real one. Silent, total,
		 * and indistinguishable from "my rows have disappeared".
		 *
		 * The seed carries the post id; the fallback state carries 0. So
		 * a mismatch is an exact statement that this state did not come
		 * from this post's data, and the only safe thing to do with it is
		 * nothing. A genuinely empty table posts its real id and saves as
		 * it always did.
		 *
		 * Deliberately not a "did it shrink?" heuristic: deleting rows is
		 * a thing people do on purpose, and a guard that second-guesses
		 * that is a guard that loses edits instead of saving them.
		 */
		$state_post_id = isset( $decoded['post_id'] ) ? (int) $decoded['post_id'] : 0;
		if ( $state_post_id !== (int) $post_id ) {
			set_transient(
				'kdna_tables_seed_failure_' . (int) $post_id,
				array(
					'expected' => (int) $post_id,
					'received' => $state_post_id,
				),
				HOUR_IN_SECONDS
			);
			return;
		}

		$caption = isset( $decoded['caption'] ) ? sanitize_text_field( (string) $decoded['caption'] ) : '';
		update_post_meta( $post_id, KDNA_Tables_CPT::META_CAPTION, $caption );

		if ( 'general' === $type ) {
			$general_state = isset( $decoded['general'] ) && is_array( $decoded['general'] )
				? $decoded['general']
				: array();
			$sanitised = KDNA_Tables_CPT::sanitize_table_data( $general_state, 'general' );
			update_post_meta( $post_id, KDNA_Tables_CPT::META_GENERAL, $sanitised );
		} else {
			$comparison_state = isset( $decoded['comparison'] ) && is_array( $decoded['comparison'] )
				? $decoded['comparison']
				: array();
			$sanitised = KDNA_Tables_CPT::sanitize_table_data( $comparison_state, 'comparison' );
			update_post_meta( $post_id, KDNA_Tables_CPT::META_COMPARISON, $sanitised );
		}

		update_post_meta( $post_id, KDNA_Tables_CPT::META_SCHEMA, KDNA_Tables_CPT::SCHEMA_VERSION );
	}
}
