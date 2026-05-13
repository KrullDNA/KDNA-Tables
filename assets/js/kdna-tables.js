/*
 * KDNA Tables, frontend handler.
 *
 * Attached via Elementor's element-handler pattern. The Column Picker
 * mode reads a JSON config from the wrapper's data-picker-config
 * attribute, builds a select + chips UI, and toggles each item column's
 * cells via the .kdna-comparison__col--hidden modifier. Re-runs on
 * Elementor breakpoint resize events.
 */

( function ( $ ) {
	'use strict';

	var WIDGET_NAME = 'kdna-table.default';

	function readPickerConfig( $wrapper ) {
		var raw = $wrapper.attr( 'data-picker-config' );
		if ( ! raw ) {
			return null;
		}
		try {
			return JSON.parse( raw );
		} catch ( err ) {
			return null;
		}
	}

	function applySelection( $wrapper, selectedSlots ) {
		var $cells = $wrapper.find( '[data-slot]' );
		$cells.each( function () {
			var slot = parseInt( this.getAttribute( 'data-slot' ), 10 );
			if ( ! slot ) {
				return;
			}
			var visible = selectedSlots.indexOf( slot ) !== -1;
			$( this ).toggleClass( 'kdna-comparison__cell--col-hidden', ! visible );
		} );
	}

	function renderChips( $chipsRoot, selectedSlots, items, onRemove ) {
		$chipsRoot.empty();
		selectedSlots.forEach( function ( slot ) {
			var item = items.find( function ( i ) { return i.slot === slot; } );
			if ( ! item ) {
				return;
			}
			var $chip = $( '<span class="kdna-picker__chip" />' ).text( item.label );
			var $remove = $( '<button type="button" class="kdna-picker__chip-remove" aria-label="Remove" />' ).text( '×' );
			$remove.on( 'click', function () {
				onRemove( slot );
			} );
			$chip.append( $remove );
			$chipsRoot.append( $chip );
		} );
	}

	function buildPicker( $wrapper, config ) {
		var existing = $wrapper.find( '> .kdna-picker' );
		if ( existing.length ) {
			existing.remove();
		}

		var $picker = $( '<div class="kdna-picker" role="group" aria-label="Column picker" />' );
		var $label = $( '<span class="kdna-picker__label" />' ).text( config.label || 'Compare' );
		var $chips = $( '<div class="kdna-picker__chips" aria-live="polite" />' );
		var selectId = 'kdna-picker-' + Math.random().toString( 36 ).slice( 2, 9 );
		var $select = $( '<select class="kdna-picker__select" />' ).attr( 'id', selectId );

		$label.attr( 'for', selectId );
		$picker.append( $label ).append( $chips ).append( $select );

		var maxSelect = ( config.maxSelect === 1 || config.maxSelect === 2 ) ? config.maxSelect : 2;

		var available = ( config.items || [] ).map( function ( item ) {
			return { slot: parseInt( item.slot, 10 ), label: item.label };
		} ).filter( function ( i ) { return i.slot >= 1 && i.slot <= 6; } );

		var defaults = ( config.defaults || [] )
			.map( function ( s ) { return parseInt( s, 10 ); } )
			.filter( function ( s ) {
				return available.some( function ( i ) { return i.slot === s; } );
			} );

		var selected = defaults.slice( 0, maxSelect );
		if ( selected.length === 0 && available.length > 0 ) {
			selected = available.slice( 0, maxSelect ).map( function ( i ) { return i.slot; } );
		}

		function rebuildSelectOptions() {
			$select.empty();
			$select.append( $( '<option />' ).attr( 'value', '' ).text( '+ Add item' ) );
			available.forEach( function ( item ) {
				if ( selected.indexOf( item.slot ) !== -1 ) {
					return;
				}
				$select.append( $( '<option />' ).attr( 'value', String( item.slot ) ).text( item.label ) );
			} );
			var atMax = selected.length >= maxSelect || $select.find( 'option' ).length <= 1;
			$select.prop( 'disabled', atMax );
		}

		function refresh() {
			renderChips( $chips, selected, available, function ( slot ) {
				selected = selected.filter( function ( s ) { return s !== slot; } );
				rebuildSelectOptions();
				refresh();
			} );
			rebuildSelectOptions();
			applySelection( $wrapper, selected );
		}

		$select.on( 'change', function () {
			var value = parseInt( $select.val(), 10 );
			if ( ! value ) {
				return;
			}
			if ( selected.length >= maxSelect ) {
				selected = selected.slice( 1 );
			}
			if ( selected.indexOf( value ) === -1 ) {
				selected.push( value );
			}
			$select.val( '' );
			refresh();
		} );

		$wrapper.prepend( $picker );
		refresh();
	}

	function clearPickerState( $wrapper ) {
		$wrapper.find( '.kdna-comparison__cell--col-hidden' ).removeClass( 'kdna-comparison__cell--col-hidden' );
		$wrapper.find( '> .kdna-picker' ).remove();
	}

	function initInstance( $scope ) {
		var $wrapper = $scope.find( '.kdna-table__wrapper' ).first();
		if ( ! $wrapper.length ) {
			return;
		}

		var mode = $wrapper.attr( 'data-responsive-mode' );
		if ( 'column_picker' !== mode ) {
			clearPickerState( $wrapper );
			return;
		}

		var config = readPickerConfig( $wrapper );
		if ( ! config || ! config.items || ! config.items.length ) {
			return;
		}

		buildPicker( $wrapper, config );
	}

	$( window ).on( 'elementor/frontend/init', function () {
		if ( 'undefined' === typeof window.elementorFrontend ) {
			return;
		}

		elementorFrontend.elementsHandler.attachHandler( WIDGET_NAME.split( '.' )[ 0 ], function ( $scope ) {
			initInstance( $scope );

			if ( elementorFrontend.elements && elementorFrontend.elements.$window ) {
				elementorFrontend.elements.$window.on( 'resize', function () {
					initInstance( $scope );
				} );
			}
		} );
	} );

}( jQuery ) );
