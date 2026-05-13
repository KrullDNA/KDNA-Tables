/*
 * KDNA Tables, editor-only JS.
 *
 * The Type Chooser is now a native SELECT, so Elementor handles
 * type switching directly. The only job left for this script is to
 * mirror the items repeater length into the hidden item_count setting
 * so the Feature Rows repeater can conditionally hide per-item cell
 * groups beyond the current item count.
 */

( function ( $ ) {
	'use strict';

	var WIDGET_NAME = 'kdna-table';

	function bindItemCountSync( model ) {
		if ( ! model || 'function' !== typeof model.get ) {
			return;
		}

		var settings = model.get( 'settings' );
		if ( ! settings || 'function' !== typeof settings.get ) {
			return;
		}

		var items = settings.get( 'items' );
		if ( ! items || 'function' !== typeof items.on ) {
			return;
		}

		var sync = function () {
			var current = settings.get( 'items' );
			if ( ! current ) {
				return;
			}
			var length = current.length;
			if ( 'number' !== typeof length && current.models ) {
				length = current.models.length;
			}
			if ( 'number' !== typeof length ) {
				return;
			}
			settings.set( 'item_count', String( length ) );
		};

		if ( ! items.__kdnaItemCountBound ) {
			items.on( 'add remove reset', sync );
			items.__kdnaItemCountBound = true;
		}
		sync();
	}

	if ( 'undefined' !== typeof window.elementor && window.elementor.hooks ) {
		window.elementor.hooks.addAction( 'panel/open_editor/widget/' + WIDGET_NAME, function ( panel, model ) {
			bindItemCountSync( model );
		} );
	}

}( jQuery ) );
