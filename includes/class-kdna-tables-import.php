<?php
/**
 * JSON import: turn a table definition file into a table in the library.
 *
 * ── Why this validates rather than just sanitising ────────────────────
 *
 * KDNA_Tables_CPT::sanitize_table_data() is deliberately forgiving. It is
 * the last line of defence on a save from the editor, where the data has
 * already been through the UI and anything odd is a bug to be absorbed
 * rather than shouted about. Hand it an empty array and it returns a
 * valid, empty table.
 *
 * That is exactly the wrong behaviour for an import. A file with a typo
 * in "columns" would sanitise to a table with no columns, save without
 * complaint, and show up in the library as an empty row waiting to be
 * filled in — with nothing anywhere saying which line of the file was
 * wrong. Silent acceptance is how an import tool wastes an afternoon.
 *
 * So the payload is checked first and the result is reported in two
 * kinds:
 *
 *   errors   stop the import. The file cannot produce a working table.
 *   warnings let it through and say what was changed on the way in —
 *            a row padded to the column count, an item list truncated
 *            to the maximum, a value that is not one of the ones we
 *            accept and fell back to its default.
 *
 * Every warning names the path it came from, so "rows[3].cells" points at
 * somewhere in the file rather than at the idea of a row.
 *
 * @package KDNA_Tables
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KDNA_Tables_Import {

	const MENU_SLUG = 'kdna-tables-import';
	const NONCE     = 'kdna_tables_import';

	/** Upload ceiling. A table of text is kilobytes; this is only a sanity bound. */
	const MAX_BYTES = 2097152;

	/**
	 * Top-level keys the format defines. Anything else is reported rather
	 * than ignored, because an unrecognised key is usually a
	 * misremembered name — "rows" typed at the top level instead of
	 * inside "data" — and that is worth saying out loud.
	 */
	const KNOWN_KEYS = array( 'version', 'type', 'title', 'caption', 'data', 'styles' );

	public static function init() {
		// Priority 11, after KDNA_Tables_Admin has registered the rest of
		// the menu at the default 10. A submenu added before its parent
		// exists is given a hook name derived from the bare slug and its
		// link then points at the front end — the bug fixed in 3.1.1.
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ), 11 );
	}

	public static function register_page() {
		// The parent is the table list screen, which is where every other
		// KDNA Tables submenu hangs — the plugin has no top-level page of
		// its own.
		add_submenu_page(
			KDNA_Tables_Admin::MENU_SLUG_LIST,
			__( 'Import Table', 'kdna-tables' ),
			__( 'Import', 'kdna-tables' ),
			'edit_posts',
			self::MENU_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function page_url() {
		return admin_url( 'admin.php?page=' . self::MENU_SLUG );
	}

	/* ─── The page ──────────────────────────────────────────────────── */

	public static function render_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to import tables.', 'kdna-tables' ) );
		}

		$result = null;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- handle_submission verifies.
		if ( ! empty( $_POST['kdna_import_submit'] ) ) {
			$result = self::handle_submission();
		}

		$raw_posted = '';
		if ( $result && ! empty( $result['raw'] ) ) {
			// Hand a failed paste back to the textarea so a one-character
			// fix does not mean pasting the whole thing again.
			$raw_posted = $result['raw'];
		}
		?>
		<div class="wrap kdna-import-page">
			<h1><?php esc_html_e( 'Import a table', 'kdna-tables' ); ?></h1>

			<?php if ( $result ) : ?>
				<?php self::render_result( $result ); ?>
			<?php endif; ?>

			<p class="description" style="max-width:48em;">
				<?php esc_html_e( 'Upload a KDNA Tables JSON file, or paste its contents below. A new table is created in your library; nothing existing is touched or overwritten.', 'kdna-tables' ); ?>
			</p>

			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( self::page_url() ); ?>">
				<?php wp_nonce_field( self::NONCE ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="kdna-import-file"><?php esc_html_e( 'JSON file', 'kdna-tables' ); ?></label>
						</th>
						<td>
							<input type="file" name="kdna_import_file" id="kdna-import-file" accept=".json,application/json" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="kdna-import-json"><?php esc_html_e( 'Or paste JSON', 'kdna-tables' ); ?></label>
						</th>
						<td>
							<textarea
								name="kdna_import_json"
								id="kdna-import-json"
								rows="14"
								class="large-text code"
								spellcheck="false"
								placeholder="<?php esc_attr_e( '{ "type": "general", "title": "…", "data": { … } }', 'kdna-tables' ); ?>"><?php echo esc_textarea( $raw_posted ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Used only when no file is chosen.', 'kdna-tables' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" name="kdna_import_submit" value="1" class="button button-primary">
						<?php esc_html_e( 'Import table', 'kdna-tables' ); ?>
					</button>
				</p>
			</form>

			<?php self::render_format_help(); ?>
		</div>
		<?php
	}

	/**
	 * The outcome panel: what happened, and what to do next.
	 */
	private static function render_result( array $result ) {
		if ( ! empty( $result['errors'] ) ) {
			?>
			<div class="notice notice-error">
				<p><strong><?php esc_html_e( 'Nothing was imported.', 'kdna-tables' ); ?></strong></p>
				<ul class="ul-disc">
					<?php foreach ( $result['errors'] as $error ) : ?>
						<li><?php echo esc_html( $error ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php
		}

		if ( ! empty( $result['post_id'] ) ) {
			$post_id = (int) $result['post_id'];
			?>
			<div class="notice notice-success">
				<p>
					<strong>
						<?php
						printf(
							/* translators: %s: table title. */
							esc_html__( 'Imported “%s”.', 'kdna-tables' ),
							esc_html( get_the_title( $post_id ) )
						);
						?>
					</strong>
				</p>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>">
						<?php esc_html_e( 'Open the table', 'kdna-tables' ); ?>
					</a>
					<code style="margin-left:8px;">[kdna_table id="<?php echo (int) $post_id; ?>"]</code>
				</p>
			</div>
			<?php
		}

		if ( ! empty( $result['warnings'] ) ) {
			?>
			<div class="notice notice-warning">
				<p>
					<strong>
						<?php
						echo esc_html(
							empty( $result['post_id'] )
								? __( 'Also worth knowing:', 'kdna-tables' )
								: __( 'Imported, with changes made on the way in:', 'kdna-tables' )
						);
						?>
					</strong>
				</p>
				<ul class="ul-disc">
					<?php foreach ( $result['warnings'] as $warning ) : ?>
						<li><?php echo esc_html( $warning ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php
		}
	}

	/**
	 * A short, copyable statement of the format, so the page can be used
	 * without going and finding the documentation.
	 */
	private static function render_format_help() {
		$sample = array(
			'version' => 1,
			'type'    => 'general',
			'title'   => 'Vitamin C forms',
			'caption' => 'Vitamin C forms & properties',
			'data'    => array(
				// false, because the headings are the column labels below.
				// True would promote the first row of data into the header
				// and drop the labels.
				'first_row_is_header'    => false,
				'first_column_is_header' => false,
				'columns'                => array(
					array( 'label' => 'Form', 'width' => 30, 'width_unit' => '%' ),
					array( 'label' => 'Stable?', 'alignment' => 'centre' ),
				),
				'rows'                   => array(
					array( 'cells' => array( array( 'text' => 'L-ascorbic acid' ), array( 'text' => 'Poorly' ) ) ),
					array( 'cells' => array( array( 'text' => 'MAP' ), array( 'text' => 'Very' ) ) ),
				),
			),
		);
		?>
		<h2><?php esc_html_e( 'The file format', 'kdna-tables' ); ?></h2>
		<p class="description" style="max-width:48em;">
			<?php esc_html_e( 'One JSON object. "type" is "general" or "comparison", "title" names the table in your library, and "data" holds the table itself. "caption" and "styles" are optional. A general table needs at least one column; a comparison table needs at least two items.', 'kdna-tables' ); ?>
		</p>
		<textarea rows="22" class="large-text code" readonly spellcheck="false"><?php
			echo esc_textarea( wp_json_encode( $sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		?></textarea>
		<?php
	}

	/* ─── Submission ────────────────────────────────────────────────── */

	private static function handle_submission() {
		check_admin_referer( self::NONCE );

		if ( ! current_user_can( 'edit_posts' ) ) {
			return array( 'errors' => array( __( 'You do not have permission to import tables.', 'kdna-tables' ) ) );
		}

		$read = self::read_payload();
		if ( ! empty( $read['errors'] ) ) {
			return array( 'errors' => $read['errors'], 'raw' => isset( $read['raw'] ) ? $read['raw'] : '' );
		}

		$check = self::validate( $read['data'] );
		if ( ! empty( $check['errors'] ) ) {
			return array(
				'errors'   => $check['errors'],
				'warnings' => $check['warnings'],
				'raw'      => $read['raw'],
			);
		}

		$post_id = self::create( $check['table'] );
		if ( is_wp_error( $post_id ) ) {
			return array(
				'errors' => array( $post_id->get_error_message() ),
				'raw'    => $read['raw'],
			);
		}

		return array(
			'post_id'  => $post_id,
			'warnings' => $check['warnings'],
		);
	}

	/**
	 * Get the JSON text from whichever input was used, and decode it.
	 *
	 * The decode error is reported verbatim rather than as "invalid
	 * JSON". "Syntax error" is useless; "Control character error" points
	 * straight at a newline pasted inside a string.
	 */
	private static function read_payload() {
		$raw = '';

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by the caller.
		if ( isset( $_FILES['kdna_import_file'] ) && is_array( $_FILES['kdna_import_file'] ) ) {
			$file = $_FILES['kdna_import_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- members handled individually below.

			$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

			if ( UPLOAD_ERR_OK === $error ) {
				$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
				if ( $size > self::MAX_BYTES ) {
					return array( 'errors' => array( __( 'That file is larger than 2 MB. A table definition should be a few kilobytes — this is probably not one.', 'kdna-tables' ) ) );
				}

				$tmp = isset( $file['tmp_name'] ) ? $file['tmp_name'] : '';
				// The genuine guard against being handed an arbitrary path.
				if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
					return array( 'errors' => array( __( 'The upload did not arrive intact. Try again.', 'kdna-tables' ) ) );
				}

				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a PHP upload temp file, not the filesystem WP_Filesystem abstracts.
				$raw = (string) file_get_contents( $tmp );
			} elseif ( UPLOAD_ERR_NO_FILE !== $error ) {
				return array( 'errors' => array( self::upload_error_message( $error ) ) );
			}
		}

		if ( '' === trim( $raw ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by the caller.
			$raw = isset( $_POST['kdna_import_json'] ) ? (string) wp_unslash( $_POST['kdna_import_json'] ) : '';
		}

		$raw = trim( $raw );

		if ( '' === $raw ) {
			return array( 'errors' => array( __( 'Choose a file or paste some JSON first.', 'kdna-tables' ) ) );
		}

		// A file saved from a Windows editor can carry a byte-order mark,
		// which json_decode rejects with a syntax error that points at
		// character 1 and explains nothing.
		$raw = preg_replace( '/^\xEF\xBB\xBF/', '', $raw );

		$data = json_decode( $raw, true );

		if ( null === $data && JSON_ERROR_NONE !== json_last_error() ) {
			return array(
				'errors' => array(
					sprintf(
						/* translators: %s: the JSON parser's own error message. */
						__( 'That is not valid JSON: %s', 'kdna-tables' ),
						json_last_error_msg()
					),
				),
				'raw'    => $raw,
			);
		}

		if ( ! is_array( $data ) ) {
			return array(
				'errors' => array( __( 'The file must contain a JSON object — one set of braces holding "type", "title" and "data".', 'kdna-tables' ) ),
				'raw'    => $raw,
			);
		}

		return array( 'data' => $data, 'raw' => $raw );
	}

	private static function upload_error_message( $error ) {
		switch ( $error ) {
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return __( 'That file is too large for this server to accept.', 'kdna-tables' );
			case UPLOAD_ERR_PARTIAL:
				return __( 'The file only uploaded partially. Try again.', 'kdna-tables' );
			case UPLOAD_ERR_NO_TMP_DIR:
			case UPLOAD_ERR_CANT_WRITE:
				return __( 'This server could not store the upload. Ask your host about the PHP temporary directory.', 'kdna-tables' );
			default:
				return __( 'The upload failed.', 'kdna-tables' );
		}
	}

	/* ─── Validation ────────────────────────────────────────────────── */

	/**
	 * Check a decoded payload and prepare it for saving.
	 *
	 * @return array errors, warnings, and on success 'table' => the
	 *               pieces create() needs.
	 */
	public static function validate( $payload ) {
		$errors   = array();
		$warnings = array();

		if ( ! is_array( $payload ) ) {
			return array( 'errors' => array( __( 'The import must be a JSON object.', 'kdna-tables' ) ), 'warnings' => array() );
		}

		foreach ( array_keys( $payload ) as $key ) {
			if ( ! in_array( (string) $key, self::KNOWN_KEYS, true ) ) {
				$warnings[] = sprintf(
					/* translators: 1: the unexpected key, 2: the keys that are expected. */
					__( '"%1$s" is not part of the format and was ignored. Expected: %2$s.', 'kdna-tables' ),
					(string) $key,
					implode( ', ', self::KNOWN_KEYS )
				);
			}
		}

		$type = isset( $payload['type'] ) ? strtolower( trim( (string) $payload['type'] ) ) : '';
		if ( ! in_array( $type, array( 'general', 'comparison' ), true ) ) {
			$errors[] = '' === $type
				? __( '"type" is missing. It must be "general" or "comparison".', 'kdna-tables' )
				: sprintf(
					/* translators: %s: the value found. */
					__( '"type" is "%s". It must be "general" or "comparison".', 'kdna-tables' ),
					(string) $payload['type']
				);
		}

		$title = isset( $payload['title'] ) ? trim( wp_strip_all_tags( (string) $payload['title'] ) ) : '';
		if ( '' === $title ) {
			// Not a warning with a generated fallback: the title is how
			// the table is found in the library and in the widget's table
			// picker, and an untitled one is lost the moment there are
			// two of them.
			$errors[] = __( '"title" is missing. It names the table in your library and in the Elementor table picker.', 'kdna-tables' );
		}

		$data = isset( $payload['data'] ) && is_array( $payload['data'] ) ? $payload['data'] : null;
		if ( null === $data ) {
			$errors[] = __( '"data" is missing, or is not an object. It holds the table itself.', 'kdna-tables' );
		}

		if ( $data && 'general' === $type ) {
			self::check_general( $data, $errors, $warnings );
		} elseif ( $data && 'comparison' === $type ) {
			self::check_comparison( $data, $errors, $warnings );
		}

		$styles = array();
		if ( isset( $payload['styles'] ) ) {
			if ( ! is_array( $payload['styles'] ) ) {
				$warnings[] = __( '"styles" is not an object and was ignored.', 'kdna-tables' );
			} elseif ( ! class_exists( 'KDNA_Tables_Style_Admin' ) ) {
				// Never true in a running plugin. Said anyway, because a
				// styles block quietly disappearing is worse than one
				// that explains itself.
				$warnings[] = __( '"styles" could not be checked — the style engine is not loaded — so it was not applied.', 'kdna-tables' );
			} else {
				$discarded = array();
				$styles    = KDNA_Tables_Style_Admin::sanitize_values( $payload['styles'], $discarded );
				foreach ( $discarded as $drop ) {
					$warnings[] = sprintf(
						/* translators: 1: style control key, 2: why it was dropped. */
						__( 'styles.%1$s was dropped: %2$s.', 'kdna-tables' ),
						isset( $drop['key'] ) ? $drop['key'] : '?',
						isset( $drop['reason'] ) ? $drop['reason'] : __( 'not accepted', 'kdna-tables' )
					);
				}
			}
		}

		if ( $errors ) {
			return array( 'errors' => $errors, 'warnings' => $warnings );
		}

		return array(
			'errors'   => array(),
			'warnings' => $warnings,
			'table'    => array(
				'type'    => $type,
				'title'   => $title,
				'caption' => isset( $payload['caption'] ) ? (string) $payload['caption'] : '',
				'data'    => $data,
				'styles'  => $styles,
			),
		);
	}

	/**
	 * General table: columns are the spine. Without them the sanitiser
	 * discards every cell in every row, and the import would "succeed"
	 * into an empty table.
	 */
	private static function check_general( array $data, array &$errors, array &$warnings ) {
		$columns = isset( $data['columns'] ) && is_array( $data['columns'] ) ? $data['columns'] : null;

		if ( null === $columns || 0 === count( $columns ) ) {
			$errors[] = __( 'A general table needs "data.columns" with at least one column. Rows are matched to the columns, so with none, every cell would be discarded.', 'kdna-tables' );
			return;
		}

		$column_count = count( $columns );
		if ( $column_count > KDNA_Tables_CPT::MAX_GENERAL_COLUMNS ) {
			$warnings[] = sprintf(
				/* translators: 1: number of columns in the file, 2: the maximum. */
				__( 'data.columns has %1$d columns; the maximum is %2$d, so the extras were dropped.', 'kdna-tables' ),
				$column_count,
				KDNA_Tables_CPT::MAX_GENERAL_COLUMNS
			);
			$column_count = KDNA_Tables_CPT::MAX_GENERAL_COLUMNS;
		}

		foreach ( array_values( $columns ) as $i => $col ) {
			if ( ! is_array( $col ) ) {
				$warnings[] = sprintf( /* translators: %d: column index. */ __( 'data.columns[%d] is not an object and became an empty column.', 'kdna-tables' ), $i );
				continue;
			}
			self::check_alignment( $col, 'alignment', "data.columns[$i]", $warnings );
			self::check_alignment( $col, 'header_alignment', "data.columns[$i]", $warnings );
			if ( isset( $col['width_unit'] ) && ! in_array( $col['width_unit'], array( '%', 'px' ), true ) ) {
				$warnings[] = sprintf(
					/* translators: 1: path, 2: the value found. */
					__( '%1$s.width_unit is "%2$s"; only "%%" and "px" are accepted, so it fell back to "%%".', 'kdna-tables' ),
					"data.columns[$i]",
					(string) $col['width_unit']
				);
			}
		}

		/*
		 * ── The trap in first_row_is_header ───────────────────────────
		 *
		 * It does not mean "the columns are headings". It means "promote
		 * the first row of data to the header", and when it is on the
		 * column labels are not rendered at all. So a file written the
		 * obvious way — labels on the columns AND a full set of data
		 * rows — loses its first row of data into the header and shows
		 * the labels nowhere.
		 *
		 * Nothing else catches this. The table renders, it has a header,
		 * and only counting the rows reveals that one went missing.
		 */
		$labelled = false;
		foreach ( $columns as $col ) {
			if ( is_array( $col ) && '' !== trim( (string) ( isset( $col['label'] ) ? $col['label'] : '' ) ) ) {
				$labelled = true;
				break;
			}
		}
		if ( ! empty( $data['first_row_is_header'] ) && $labelled ) {
			$warnings[] = __( 'data.first_row_is_header is true, so the first row of data becomes the header and the column labels are not shown. If the labels are your headings, set it to false.', 'kdna-tables' );
		}

		$rows = isset( $data['rows'] ) && is_array( $data['rows'] ) ? $data['rows'] : array();
		if ( 0 === count( $rows ) ) {
			$warnings[] = __( 'data.rows is empty, so the table imported with its columns but no content.', 'kdna-tables' );
			return;
		}

		foreach ( array_values( $rows ) as $i => $row ) {
			if ( ! is_array( $row ) ) {
				$warnings[] = sprintf( /* translators: %d: row index. */ __( 'data.rows[%d] is not an object and became an empty row.', 'kdna-tables' ), $i );
				continue;
			}
			$cells = isset( $row['cells'] ) && is_array( $row['cells'] ) ? $row['cells'] : array();
			if ( count( $cells ) !== $column_count ) {
				$warnings[] = sprintf(
					/* translators: 1: row index, 2: cells found, 3: columns. */
					__( 'data.rows[%1$d] has %2$d cells for %3$d columns. It was padded or trimmed to match.', 'kdna-tables' ),
					$i,
					count( $cells ),
					$column_count
				);
			}
			foreach ( array_values( $cells ) as $j => $cell ) {
				self::check_cell( $cell, "data.rows[$i].cells[$j]", $warnings );
			}
		}
	}

	/**
	 * Comparison table: the renderer refuses to draw fewer than two
	 * items, printing a placeholder instead — so one item is an error
	 * here rather than something to discover on the page.
	 */
	private static function check_comparison( array $data, array &$errors, array &$warnings ) {
		$items = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array();
		$count = count( $items );

		if ( $count < 2 ) {
			$errors[] = sprintf(
				/* translators: %d: number of items found. */
				__( 'A comparison table needs at least two items in "data.items"; this has %d. Fewer than two renders a placeholder rather than a table.', 'kdna-tables' ),
				$count
			);
			return;
		}

		if ( $count > KDNA_Tables_CPT::MAX_COMPARISON_ITEMS ) {
			$warnings[] = sprintf(
				/* translators: 1: items in the file, 2: the maximum. */
				__( 'data.items has %1$d items; the maximum is %2$d, so the extras were dropped.', 'kdna-tables' ),
				$count,
				KDNA_Tables_CPT::MAX_COMPARISON_ITEMS
			);
			$count = KDNA_Tables_CPT::MAX_COMPARISON_ITEMS;
		}

		foreach ( array_values( $items ) as $i => $item ) {
			if ( is_array( $item ) && isset( $item['image'] ) ) {
				self::check_image( $item['image'], "data.items[$i].image", $warnings );
			}
		}

		if ( isset( $data['highlighted_item_index'] ) ) {
			$highlight = (int) $data['highlighted_item_index'];
			if ( $highlight >= $count ) {
				$warnings[] = sprintf(
					/* translators: 1: the index given, 2: number of items. */
					__( 'data.highlighted_item_index is %1$d but there are only %2$d items, so no item is highlighted. It is zero-based; -1 means none.', 'kdna-tables' ),
					$highlight,
					$count
				);
			}
		}

		if ( isset( $data['badge_position'] ) && ! in_array( $data['badge_position'], KDNA_Tables_CPT::VALID_BADGE_POSITIONS, true ) && 'top-center' !== $data['badge_position'] ) {
			$warnings[] = sprintf(
				/* translators: 1: the value found, 2: the accepted values. */
				__( 'data.badge_position is "%1$s"; accepted values are %2$s. It fell back to "top-centre".', 'kdna-tables' ),
				(string) $data['badge_position'],
				implode( ', ', KDNA_Tables_CPT::VALID_BADGE_POSITIONS )
			);
		}

		$rows = isset( $data['feature_rows'] ) && is_array( $data['feature_rows'] ) ? $data['feature_rows'] : array();
		if ( 0 === count( $rows ) ) {
			$warnings[] = __( 'data.feature_rows is empty, so the table imported with its items but no rows to compare them on.', 'kdna-tables' );
			return;
		}

		foreach ( array_values( $rows ) as $i => $row ) {
			if ( ! is_array( $row ) ) {
				$warnings[] = sprintf( /* translators: %d: row index. */ __( 'data.feature_rows[%d] is not an object and became an empty row.', 'kdna-tables' ), $i );
				continue;
			}
			$cells = isset( $row['cells'] ) && is_array( $row['cells'] ) ? $row['cells'] : array();
			if ( count( $cells ) !== $count ) {
				$warnings[] = sprintf(
					/* translators: 1: row index, 2: cells found, 3: items. */
					__( 'data.feature_rows[%1$d] has %2$d cells for %3$d items. It was padded or trimmed to match.', 'kdna-tables' ),
					$i,
					count( $cells ),
					$count
				);
			}
			foreach ( array_values( $cells ) as $j => $cell ) {
				if ( ! is_array( $cell ) ) {
					continue;
				}
				$path = "data.feature_rows[$i].cells[$j]";
				if ( isset( $cell['state'] ) && ! in_array( $cell['state'], array( 'available', 'unavailable', 'custom' ), true ) ) {
					$warnings[] = sprintf(
						/* translators: 1: path, 2: the value found. */
						__( '%1$s.state is "%2$s"; accepted values are available, unavailable, custom. It fell back to "available".', 'kdna-tables' ),
						$path,
						(string) $cell['state']
					);
				}
				if ( isset( $cell['custom'] ) && is_array( $cell['custom'] ) ) {
					self::check_cell( $cell['custom'], $path . '.custom', $warnings );
				}
			}
		}
	}

	/**
	 * Cell-level values that fall back silently in the sanitiser.
	 */
	private static function check_cell( $cell, $path, array &$warnings ) {
		if ( ! is_array( $cell ) ) {
			$warnings[] = sprintf( /* translators: %s: path. */ __( '%s is not an object and became an empty cell.', 'kdna-tables' ), $path );
			return;
		}

		if ( isset( $cell['content_types'] ) ) {
			$types = is_array( $cell['content_types'] ) ? $cell['content_types'] : array( $cell['content_types'] );
			foreach ( $types as $type ) {
				if ( ! is_string( $type ) || ! in_array( $type, KDNA_Tables_CPT::VALID_CONTENT_TYPES, true ) ) {
					$warnings[] = sprintf(
						/* translators: 1: path, 2: the value found, 3: accepted values. */
						__( '%1$s.content_types contains "%2$s"; accepted values are %3$s.', 'kdna-tables' ),
						$path,
						is_scalar( $type ) ? (string) $type : gettype( $type ),
						implode( ', ', KDNA_Tables_CPT::VALID_CONTENT_TYPES )
					);
				}
			}
		}

		if ( isset( $cell['arrangement'] ) && ! in_array( $cell['arrangement'], KDNA_Tables_CPT::VALID_ARRANGEMENTS, true ) ) {
			$warnings[] = sprintf(
				/* translators: 1: path, 2: the value found. */
				__( '%1$s.arrangement is "%2$s", which is not one of the accepted arrangements, so it fell back to "icon-text".', 'kdna-tables' ),
				$path,
				(string) $cell['arrangement']
			);
		}

		self::check_alignment( $cell, 'alignment', $path, $warnings );

		if ( isset( $cell['image'] ) ) {
			self::check_image( $cell['image'], $path . '.image', $warnings );
		}
	}

	private static function check_alignment( $holder, $key, $path, array &$warnings ) {
		if ( ! isset( $holder[ $key ] ) || '' === $holder[ $key ] ) {
			return;
		}
		$value = is_string( $holder[ $key ] ) ? strtolower( trim( $holder[ $key ] ) ) : '';
		if ( ! in_array( $value, KDNA_Tables_CPT::VALID_ALIGNMENTS, true ) ) {
			$warnings[] = sprintf(
				/* translators: 1: path and key, 2: the value found. */
				__( '%1$s is "%2$s"; accepted values are left, centre, right.', 'kdna-tables' ),
				$path . '.' . $key,
				is_scalar( $holder[ $key ] ) ? (string) $holder[ $key ] : gettype( $holder[ $key ] )
			);
		}
	}

	/**
	 * An attachment id is meaningless on a different site — id 412 is
	 * whatever happens to be attachment 412 here, which is very likely
	 * somebody else's photograph. Worth saying, because the table will
	 * render an image either way and nothing will look broken.
	 */
	private static function check_image( $image, $path, array &$warnings ) {
		if ( ! is_array( $image ) ) {
			return;
		}
		$id = isset( $image['id'] ) ? absint( $image['id'] ) : 0;
		if ( ! $id ) {
			return;
		}
		if ( 'attachment' !== get_post_type( $id ) ) {
			$warnings[] = sprintf(
				/* translators: 1: path, 2: the id. */
				__( '%1$s.id is %2$d, which is not an image in this site\'s media library. Re-pick the image in the editor.', 'kdna-tables' ),
				$path,
				$id
			);
		} else {
			$warnings[] = sprintf(
				/* translators: 1: path, 2: the id, 3: the attachment title. */
				__( '%1$s.id is %2$d, which on this site is “%3$s”. Check it is the image you meant — attachment ids do not carry between sites.', 'kdna-tables' ),
				$path,
				$id,
				get_the_title( $id )
			);
		}
	}

	/* ─── Creating ──────────────────────────────────────────────────── */

	/**
	 * Write the validated table into a new post.
	 *
	 * Published rather than draft: a draft does not render, so a shortcode
	 * pasted straight after importing would show nothing and look like the
	 * import had failed.
	 *
	 * @return int|WP_Error New post id.
	 */
	public static function create( array $table ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => KDNA_Tables_CPT::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $table['title'],
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// The type is written before the data, because sanitize_table_data
		// branches on it.
		update_post_meta( $post_id, KDNA_Tables_CPT::META_TYPE, $table['type'] );
		update_post_meta( $post_id, KDNA_Tables_CPT::META_CAPTION, sanitize_text_field( $table['caption'] ) );
		update_post_meta(
			$post_id,
			'general' === $table['type'] ? KDNA_Tables_CPT::META_GENERAL : KDNA_Tables_CPT::META_COMPARISON,
			KDNA_Tables_CPT::sanitize_table_data( $table['data'], $table['type'] )
		);
		update_post_meta( $post_id, KDNA_Tables_CPT::META_SCHEMA, KDNA_Tables_CPT::SCHEMA_VERSION );

		if ( ! empty( $table['styles'] ) && class_exists( 'KDNA_Tables_Style_Resolver' ) ) {
			update_post_meta( $post_id, KDNA_Tables_Style_Resolver::META_KEY, $table['styles'] );
		}

		return (int) $post_id;
	}
}
