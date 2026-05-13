<?php
/**
 * Type Chooser screen rendered when the user clicks Add New on the
 * KDNA Tables admin menu. The user must pick a type before any data
 * is persisted, since type is permanent for the entry once created.
 *
 * Variables expected from the caller:
 * - $create_action_url   string  Full admin-post URL for kdna_tables_create action
 * - $nonce_field_name    string  The nonce action name (same constant used in the handler)
 * - $cancel_url          string  URL the Cancel link returns to (All Tables list)
 *
 * @package KDNA_Tables
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap kdna-type-chooser">
	<h1 class="screen-reader-text"><?php esc_html_e( 'Add New Table', 'kdna-tables' ); ?></h1>

	<div
		class="kdna-type-chooser__modal"
		role="dialog"
		aria-modal="true"
		aria-labelledby="kdna-type-chooser-heading"
		aria-describedby="kdna-type-chooser-intro"
	>
		<div class="kdna-type-chooser__inner">
			<h2 id="kdna-type-chooser-heading" class="kdna-type-chooser__title">
				<?php esc_html_e( 'Pick a table type', 'kdna-tables' ); ?>
			</h2>
			<p id="kdna-type-chooser-intro" class="kdna-type-chooser__intro">
				<?php esc_html_e( 'The type is fixed once the table is created. To convert an existing table, duplicate it from the All Tables list and pick the other type.', 'kdna-tables' ); ?>
			</p>

			<div class="kdna-type-chooser__cards" role="list">
				<form method="post" action="<?php echo esc_url( $create_action_url ); ?>" class="kdna-type-chooser__card-form" role="listitem">
					<?php wp_nonce_field( $nonce_field_name ); ?>
					<input type="hidden" name="kdna_table_type" value="general" />
					<button
						type="submit"
						class="kdna-type-chooser__card kdna-type-chooser__card--general"
						data-kdna-chooser-card="general"
						aria-labelledby="kdna-card-general-label kdna-card-general-desc"
					>
						<span class="kdna-type-chooser__card-icon" aria-hidden="true">
							<span class="dashicons dashicons-editor-table"></span>
						</span>
						<span id="kdna-card-general-label" class="kdna-type-chooser__card-title">
							<?php esc_html_e( 'General Table', 'kdna-tables' ); ?>
						</span>
						<span id="kdna-card-general-desc" class="kdna-type-chooser__card-desc">
							<?php esc_html_e( 'A clean, fully styleable data table. Up to 10 columns. Each cell can be text, icon, image, or any combination.', 'kdna-tables' ); ?>
						</span>
						<span class="kdna-type-chooser__card-cta button button-primary">
							<?php esc_html_e( 'Choose', 'kdna-tables' ); ?>
						</span>
					</button>
				</form>

				<form method="post" action="<?php echo esc_url( $create_action_url ); ?>" class="kdna-type-chooser__card-form" role="listitem">
					<?php wp_nonce_field( $nonce_field_name ); ?>
					<input type="hidden" name="kdna_table_type" value="comparison" />
					<button
						type="submit"
						class="kdna-type-chooser__card kdna-type-chooser__card--comparison"
						data-kdna-chooser-card="comparison"
						aria-labelledby="kdna-card-comparison-label kdna-card-comparison-desc"
					>
						<span class="kdna-type-chooser__card-icon" aria-hidden="true">
							<span class="dashicons dashicons-screenoptions"></span>
						</span>
						<span id="kdna-card-comparison-label" class="kdna-type-chooser__card-title">
							<?php esc_html_e( 'Comparison Table', 'kdna-tables' ); ?>
						</span>
						<span id="kdna-card-comparison-desc" class="kdna-type-chooser__card-desc">
							<?php esc_html_e( 'A product or service comparison table. Up to 6 items, unlimited feature rows, optional highlighted column and badge.', 'kdna-tables' ); ?>
						</span>
						<span class="kdna-type-chooser__card-cta button button-primary">
							<?php esc_html_e( 'Choose', 'kdna-tables' ); ?>
						</span>
					</button>
				</form>
			</div>

			<p class="kdna-type-chooser__cancel">
				<a href="<?php echo esc_url( $cancel_url ); ?>">
					<?php esc_html_e( 'Cancel and return to All Tables', 'kdna-tables' ); ?>
				</a>
			</p>
		</div>
	</div>
</div>

<script>
( function () {
	'use strict';
	document.addEventListener( 'DOMContentLoaded', function () {
		var cards = Array.prototype.slice.call(
			document.querySelectorAll( '[data-kdna-chooser-card]' )
		);
		if ( ! cards.length ) {
			return;
		}
		cards[ 0 ].focus();
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key !== 'Tab' ) {
				return;
			}
			var active = document.activeElement;
			var idx = cards.indexOf( active );
			if ( idx === -1 ) {
				e.preventDefault();
				cards[ 0 ].focus();
				return;
			}
			e.preventDefault();
			var nextIdx;
			if ( e.shiftKey ) {
				nextIdx = idx === 0 ? cards.length - 1 : idx - 1;
			} else {
				nextIdx = ( idx + 1 ) % cards.length;
			}
			cards[ nextIdx ].focus();
		} );
	} );
} )();
</script>
