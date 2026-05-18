/*
 * KDNA Tables, Elementor editor-only JS.
 *
 * Two jobs:
 * 1. Mirror the picked CPT entry's type into the hidden
 *    selected_table_type control so Style sections re-evaluate.
 * 2. When a widget instance carries v1.x legacy data (table_type,
 *    columns, rows, items, feature_rows present and no selected_table_id),
 *    show a one-time migration notice + button that POSTs the legacy
 *    settings to the server, gets back a new kdna_table id, and rewrites
 *    the instance to use selected_table_id instead.
 */

( function ( $ ) {
	'use strict';

	var WIDGET_NAME = 'kdna-table';
	var typeCache  = {};
	var migrationBoundInstances = {};

	function adminConfig() {
		return ( window.KDNATablesEditor && typeof window.KDNATablesEditor === 'object' ) ? window.KDNATablesEditor : null;
	}

	function migrationConfig() {
		return ( window.KDNATablesMigration && typeof window.KDNATablesMigration === 'object' ) ? window.KDNATablesMigration : null;
	}

	/* ------------------------------------------------------------------
	 * Source table -> type mirror
	 * ------------------------------------------------------------------ */

	function fetchTableType( tableId, callback ) {
		tableId = parseInt( tableId, 10 ) || 0;
		if ( tableId <= 0 ) {
			callback( '' );
			return;
		}
		if ( Object.prototype.hasOwnProperty.call( typeCache, tableId ) ) {
			var cached = typeCache[ tableId ];
			callback( cached.type || '', cached.itemCount || 0 );
			return;
		}
		var cfg = adminConfig();
		if ( ! cfg || ! cfg.ajaxUrl || ! cfg.nonce ) {
			callback( '', 0 );
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
			var itemCount = 0;
			if ( response && response.success && response.data ) {
				if ( typeof response.data.type === 'string' ) {
					type = response.data.type;
				}
				if ( typeof response.data.item_count === 'number' ) {
					itemCount = response.data.item_count;
				}
			}
			typeCache[ tableId ] = { type: type, itemCount: itemCount };
			callback( type, itemCount );
		} ).fail( function () {
			callback( '', 0 );
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
			fetchTableType( tableId, function ( type, itemCount ) {
				if ( type !== settings.get( 'selected_table_type' ) ) {
					settings.set( 'selected_table_type', type, { silent: false } );
				}
				var countStr = String( Math.max( 0, Math.min( 6, itemCount || 0 ) ) );
				if ( countStr !== settings.get( 'selected_table_item_count' ) ) {
					settings.set( 'selected_table_item_count', countStr, { silent: false } );
				}
			} );
		};

		settings.on( 'change:selected_table_id', sync );
		sync();
	}

	/* ------------------------------------------------------------------
	 * Lazy migration of v1.x instances
	 * ------------------------------------------------------------------ */

	function hasLegacyData( settings ) {
		if ( ! settings || typeof settings.get !== 'function' ) {
			return false;
		}
		if ( settings.get( 'selected_table_id' ) ) {
			return false;
		}
		var type = settings.get( 'table_type' );
		if ( type === 'general' || type === 'comparison' ) {
			return true;
		}
		var legacyArrays = [ 'columns', 'rows', 'items', 'feature_rows' ];
		for ( var i = 0; i < legacyArrays.length; i++ ) {
			var v = settings.get( legacyArrays[ i ] );
			if ( v && ( ( v.length && v.length > 0 ) || ( v.models && v.models.length > 0 ) ) ) {
				return true;
			}
		}
		return false;
	}

	function legacySettingsPayload( settings ) {
		// Take the model's full settings snapshot via toJSON so repeaters,
		// nested groups, etc are serialised back into plain JS values.
		if ( ! settings || typeof settings.toJSON !== 'function' ) {
			return null;
		}
		try {
			return settings.toJSON( { remove: [ 'default' ] } );
		} catch ( e ) {
			try {
				return settings.toJSON();
			} catch ( e2 ) {
				return null;
			}
		}
	}

	function markPageDirty() {
		if ( window.elementor && window.elementor.saver && typeof window.elementor.saver.setFlagEditorChange === 'function' ) {
			window.elementor.saver.setFlagEditorChange( true );
		} else if ( window.elementor && window.elementor.channels && window.elementor.channels.editor ) {
			window.elementor.channels.editor.trigger( 'change' );
		}
	}

	function findPanelRoot() {
		// Anchor the notice inside the controls panel header for visibility.
		var panel = window.elementor && window.elementor.panel ? window.elementor.panel : null;
		if ( panel && panel.$el && panel.$el[ 0 ] ) {
			var $controls = panel.$el.find( '.elementor-panel-navigation-tabs, .elementor-panel-content, .elementor-controls-stack' ).first();
			if ( $controls.length ) {
				return $controls;
			}
		}
		return $( '#elementor-panel-content-wrapper' );
	}

	function renderMigrationNotice( model ) {
		var $root = findPanelRoot();
		if ( ! $root || ! $root.length ) {
			return;
		}
		$root.find( '.kdna-migration-notice' ).remove();

		var $notice = $(
			'<div class="kdna-migration-notice" style="margin:8px 12px;padding:10px 12px;background:#fffbeb;border-left:4px solid #d4a01f;color:#1d2327;">' +
				'<strong>' + 'KDNA Tables: legacy data detected.' + '</strong>' +
				'<p style="margin:6px 0 8px;">' + 'This widget still stores its data inline. Click Migrate to convert it into a reusable table in your library.' + '</p>' +
				'<button type="button" class="elementor-button elementor-button-success kdna-migration-go">Migrate</button>' +
				'<span class="kdna-migration-status" style="margin-left:8px;color:#50575e;"></span>' +
			'</div>'
		);
		$notice.prependTo( $root );

		$notice.find( '.kdna-migration-go' ).on( 'click', function () {
			runMigration( model, $notice );
		} );
	}

	function runMigration( model, $notice ) {
		var cfg = migrationConfig();
		if ( ! cfg || ! cfg.ajaxUrl || ! cfg.nonce ) {
			$notice.find( '.kdna-migration-status' ).text( 'Migration endpoint not available.' );
			return;
		}
		var settings = model.get( 'settings' );
		var payload  = legacySettingsPayload( settings );
		if ( ! payload ) {
			$notice.find( '.kdna-migration-status' ).text( 'Cannot read legacy settings.' );
			return;
		}
		var $btn    = $notice.find( '.kdna-migration-go' ).prop( 'disabled', true );
		var $status = $notice.find( '.kdna-migration-status' ).text( 'Migrating ...' );

		$.ajax( {
			url: cfg.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: 'kdna_tables_migrate_instance',
				_ajax_nonce: cfg.nonce,
				settings: JSON.stringify( payload )
			}
		} ).done( function ( response ) {
			if ( ! response || ! response.success || ! response.data || ! response.data.id ) {
				$status.text( ( response && response.data && response.data.message ) || 'Migration failed.' );
				$btn.prop( 'disabled', false );
				return;
			}
			applyMigratedId( model, response.data.id );
			$notice.css( 'background', '#ecf8ee' ).find( 'strong' ).text( 'Migrated.' );
			$status.text( 'Table id ' + response.data.id + '. Save the page to persist the change.' );
			$btn.remove();
		} ).fail( function () {
			$status.text( 'Migration request failed.' );
			$btn.prop( 'disabled', false );
		} );
	}

	function applyMigratedId( model, newId ) {
		var settings = model.get( 'settings' );
		if ( ! settings || typeof settings.set !== 'function' ) {
			return;
		}
		// Clear the legacy data keys. Elementor stores repeaters as
		// Backbone collections; setting them to an empty array swaps the
		// underlying collection.
		var clears = {
			table_type: '',
			caption: '',
			first_row_is_header: '',
			first_column_is_header: '',
			columns: [],
			rows: [],
			items: [],
			item_count: '',
			highlight_item: '',
			highlight_badge_text: '',
			highlight_badge_position: '',
			feature_rows: []
		};
		Object.keys( clears ).forEach( function ( key ) {
			try {
				settings.set( key, clears[ key ], { silent: true } );
			} catch ( e ) { /* property may not exist on this instance */ }
		} );
		settings.set( 'selected_table_id', String( newId ) );
		// Type mirror picks up via fetchTableType subscriber on change.
		markPageDirty();
	}

	function maybeOfferMigration( model ) {
		if ( ! model || typeof model.get !== 'function' ) {
			return;
		}
		var id = ( typeof model.cid === 'string' ) ? model.cid : null;
		if ( id && migrationBoundInstances[ id ] ) {
			return;
		}
		if ( id ) {
			migrationBoundInstances[ id ] = true;
		}
		var settings = model.get( 'settings' );
		if ( ! hasLegacyData( settings ) ) {
			return;
		}
		renderMigrationNotice( model );
	}

	/* ------------------------------------------------------------------
	 * Boot
	 * ------------------------------------------------------------------ */

	if ( typeof window.elementor !== 'undefined' && window.elementor.hooks ) {
		window.elementor.hooks.addAction( 'panel/open_editor/widget/' + WIDGET_NAME, function ( panel, model ) {
			bindSourceTableMirror( model );
			maybeOfferMigration( model );
		} );
	}

}( jQuery ) );
