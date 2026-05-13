/*
 * KDNA Tables, editor-only JS.
 *
 * Wires the Type Chooser cards and the Change Table Type link to
 * Elementor's setSettings API so picking a card writes table_type on
 * the currently edited widget model. Setting an empty string clears
 * the value and brings the chooser back.
 */

( function ( $ ) {
	'use strict';

	var WIDGET_NAME = 'kdna-table';

	function getCurrentEditModel() {
		if ( 'undefined' === typeof window.elementor ) {
			return null;
		}

		try {
			var panel = window.elementor.getPanelView();
			if ( ! panel ) {
				return null;
			}

			var page = panel.getCurrentPageView();
			if ( ! page || 'function' !== typeof page.getOption ) {
				return null;
			}

			var view = page.getOption( 'editedElementView' );
			if ( ! view || 'function' !== typeof view.getEditModel ) {
				return null;
			}

			var model = view.getEditModel();
			if ( ! model || model.get( 'widgetType' ) !== WIDGET_NAME ) {
				return null;
			}

			return model;
		} catch ( err ) {
			return null;
		}
	}

	function setTableType( value ) {
		var model = getCurrentEditModel();
		if ( ! model ) {
			return;
		}
		model.setSetting( 'table_type', value );
	}

	$( document ).on( 'click', '.kdna-table__chooser-card', function ( event ) {
		event.preventDefault();
		var type = $( this ).attr( 'data-kdna-type' );
		if ( 'general' !== type && 'comparison' !== type ) {
			return;
		}
		setTableType( type );
	} );

	$( document ).on( 'click', '.kdna-table__change-type', function ( event ) {
		event.preventDefault();
		setTableType( '' );
	} );

	$( document ).on( 'keydown', '.kdna-table__chooser-card', function ( event ) {
		if ( 'Enter' === event.key || ' ' === event.key || 'Spacebar' === event.key ) {
			event.preventDefault();
			$( this ).trigger( 'click' );
		}
	} );

	/*
	 * Items repeater length sync for comparison mode.
	 *
	 * The Feature Rows repeater hard-caps at six per-item cell control
	 * groups. Visibility for each group references the hidden item_count
	 * setting. This handler keeps item_count aligned with the live length
	 * of the items repeater so groups beyond the current item count stay
	 * hidden in the panel UI. The PHP renderer caps to the live items
	 * length too, so this is purely a panel UX aid.
	 */
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

} )( jQuery );
