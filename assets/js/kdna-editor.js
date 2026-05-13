/*
 * KDNA Tables, editor-only JS.
 *
 * v2.0 widgets pick a table from the kdna_table library via the Source
 * Table dropdown. When the picked table changes, the editor asks the
 * server for that table's type and mirrors it into the hidden
 * selected_table_type control so the type-conditioned Style sections
 * re-evaluate.
 */

( function ( $ ) {
	'use strict';

	var WIDGET_NAME = 'kdna-table';
	var typeCache  = {};

	function getConfig() {
		return ( window.KDNATablesEditor && typeof window.KDNATablesEditor === 'object' ) ? window.KDNATablesEditor : null;
	}

	function fetchTableType( tableId, callback ) {
		tableId = parseInt( tableId, 10 ) || 0;
		if ( tableId <= 0 ) {
			callback( '' );
			return;
		}
		if ( Object.prototype.hasOwnProperty.call( typeCache, tableId ) ) {
			callback( typeCache[ tableId ] );
			return;
		}

		var cfg = getConfig();
		if ( ! cfg || ! cfg.ajaxUrl || ! cfg.nonce ) {
			callback( '' );
			return;
		}

		$.ajax( {
			url: cfg.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: 'kdna_tables_get_table_type',
				table_id: tableId,
				_ajax_nonce: cfg.nonce
			}
		} ).done( function ( response ) {
			var type = '';
			if ( response && response.success && response.data && typeof response.data.type === 'string' ) {
				type = response.data.type;
			}
			typeCache[ tableId ] = type;
			callback( type );
		} ).fail( function () {
			callback( '' );
		} );
	}

	function bindSourceTableMirror( model ) {
		if ( ! model || typeof model.get !== 'function' ) {
			return;
		}
		var settings = model.get( 'settings' );
		if ( ! settings || typeof settings.on !== 'function' || typeof settings.get !== 'function' ) {
			return;
		}
		if ( settings.__kdnaSourceBound ) {
			return;
		}
		settings.__kdnaSourceBound = true;

		var sync = function () {
			var tableId = settings.get( 'selected_table_id' );
			fetchTableType( tableId, function ( type ) {
				if ( type !== settings.get( 'selected_table_type' ) ) {
					settings.set( 'selected_table_type', type, { silent: false } );
				}
			} );
		};

		settings.on( 'change:selected_table_id', sync );
		// Run once on panel open so the hidden mirror is correct even if
		// the widget was just loaded from the page payload.
		sync();
	}

	if ( typeof window.elementor !== 'undefined' && window.elementor.hooks ) {
		window.elementor.hooks.addAction( 'panel/open_editor/widget/' + WIDGET_NAME, function ( panel, model ) {
			bindSourceTableMirror( model );
		} );
	}

}( jQuery ) );
