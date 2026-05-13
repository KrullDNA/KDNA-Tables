<?php
/**
 * Empty-state placeholder, shown when no table_type has been chosen yet.
 *
 * @package KDNA_Tables
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="kdna-table__placeholder" role="status">
	<span class="kdna-table__placeholder-message">
		<?php esc_html_e( 'Choose a table type to begin', 'kdna-tables' ); ?>
	</span>
</div>
