/*
 * KDNA Tables, frontend handler.
 *
 * Attached via Elementor's element-handler pattern. Initialises the
 * tooltip touch / keyboard behaviour for every widget instance and the
 * Column Picker chrome for column-picker mode. The handler re-runs on
 * Elementor breakpoint resize events.
 */

( function ( $ ) {
	'use strict';

	var WIDGET_NAME = 'kdna-table';

	/* ── Column Picker ────────────────────────────────────────────── */

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
		$wrapper.find( '[data-slot]' ).each( function () {
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
			var $remove = $( '<button type="button" class="kdna-picker__chip-remove" />' )
				.attr( 'aria-label', 'Remove ' + item.label )
				.text( '×' );
			$remove.on( 'click', function () {
				onRemove( slot );
			} );
			$chip.append( $remove );
			$chipsRoot.append( $chip );
		} );
	}

	function buildPicker( $wrapper, config ) {
		$wrapper.find( '> .kdna-picker' ).remove();

		var $picker = $( '<div class="kdna-picker" role="group" />' )
			.attr( 'aria-label', config.label || 'Compare' );
		var selectId = 'kdna-picker-' + Math.random().toString( 36 ).slice( 2, 9 );
		var $label = $( '<label class="kdna-picker__label" />' )
			.attr( 'for', selectId )
			.text( config.label || 'Compare' );
		var $chips = $( '<div class="kdna-picker__chips" aria-live="polite" />' );
		var $select = $( '<select class="kdna-picker__select" />' ).attr( 'id', selectId );

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

	/* ── Tooltips ─────────────────────────────────────────────────── */

	function setTooltipOpen( $wrap, open ) {
		var $trigger = $wrap.find( '> .kdna-comparison__tooltip-trigger' );
		$wrap.toggleClass( 'is-open', !! open );
		$trigger.attr( 'aria-expanded', open ? 'true' : 'false' );
	}

	function flipPositionIfAuto( $wrap ) {
		var stored = $wrap.data( 'kdnaTooltipMode' );
		if ( ! stored ) {
			stored = $wrap.attr( 'data-tooltip-position' ) || 'top';
			$wrap.data( 'kdnaTooltipMode', stored );
		}
		if ( 'auto' !== stored ) {
			return;
		}

		var $trigger = $wrap.find( '> .kdna-comparison__tooltip-trigger' );
		var $tooltip = $wrap.find( '> .kdna-comparison__tooltip' );
		if ( ! $trigger.length || ! $tooltip.length ) {
			return;
		}

		var triggerRect = $trigger[ 0 ].getBoundingClientRect();
		// Force measurement: temporarily make tooltip visible offscreen for
		// a stable height even before transitions land.
		var prevVisibility = $tooltip[ 0 ].style.visibility;
		var prevDisplay    = $tooltip[ 0 ].style.display;
		$tooltip[ 0 ].style.visibility = 'hidden';
		$tooltip[ 0 ].style.display    = 'block';
		var tooltipHeight = $tooltip[ 0 ].offsetHeight || 60;
		$tooltip[ 0 ].style.visibility = prevVisibility;
		$tooltip[ 0 ].style.display    = prevDisplay;

		var roomAbove = triggerRect.top;
		var resolved  = ( roomAbove < tooltipHeight + 12 ) ? 'bottom' : 'top';
		$wrap.attr( 'data-tooltip-position', resolved );
	}

	function initTooltips( $scope ) {
		var $wraps = $scope.find( '.kdna-comparison__tooltip-wrap' );

		$wraps.each( function () {
			var $wrap    = $( this );
			var $trigger = $wrap.find( '> .kdna-comparison__tooltip-trigger' );
			var $tooltip = $wrap.find( '> .kdna-comparison__tooltip' );

			if ( ! $trigger.length || ! $tooltip.length ) {
				return;
			}

			$trigger
				.attr( 'role', 'button' )
				.attr( 'tabindex', '0' )
				.attr( 'aria-expanded', 'false' );

			if ( ! $tooltip.attr( 'role' ) ) {
				$tooltip.attr( 'role', 'tooltip' );
			}

			if ( $wrap.data( 'kdnaTooltipBound' ) ) {
				return;
			}
			$wrap.data( 'kdnaTooltipBound', true );

			$trigger.on( 'click.kdnaTooltip', function ( event ) {
				event.preventDefault();
				event.stopPropagation();
				var isOpen = $wrap.hasClass( 'is-open' );
				if ( ! isOpen ) {
					flipPositionIfAuto( $wrap );
				}
				setTooltipOpen( $wrap, ! isOpen );
			} );

			$trigger.on( 'keydown.kdnaTooltip', function ( event ) {
				if ( 'Enter' === event.key || ' ' === event.key || 'Spacebar' === event.key ) {
					event.preventDefault();
					flipPositionIfAuto( $wrap );
					setTooltipOpen( $wrap, true );
				} else if ( 'Escape' === event.key || 'Esc' === event.key ) {
					if ( $wrap.hasClass( 'is-open' ) ) {
						event.preventDefault();
						setTooltipOpen( $wrap, false );
						$trigger.focus();
					}
				}
			} );

			$trigger.on( 'mouseenter.kdnaTooltip focus.kdnaTooltip', function () {
				flipPositionIfAuto( $wrap );
			} );
		} );
	}

	/* ── Per-instance init ────────────────────────────────────────── */

	function initInstance( $scope ) {
		var $wrapper = $scope.find( '.kdna-table__wrapper' ).first();
		if ( ! $wrapper.length ) {
			return;
		}

		initTooltips( $wrapper );

		var mode = $wrapper.attr( 'data-responsive-mode' );
		if ( 'column_picker' === mode ) {
			var config = readPickerConfig( $wrapper );
			if ( config && config.items && config.items.length ) {
				buildPicker( $wrapper, config );
			}
		} else {
			clearPickerState( $wrapper );
		}
	}

	var documentClickBound = false;
	function bindDocumentDismiss() {
		if ( documentClickBound ) {
			return;
		}
		documentClickBound = true;
		$( document ).on( 'click.kdnaTooltip', function ( event ) {
			$( '.kdna-comparison__tooltip-wrap.is-open' ).each( function () {
				if ( $( event.target ).closest( this ).length ) {
					return;
				}
				setTooltipOpen( $( this ), false );
			} );
		} );
		$( document ).on( 'keydown.kdnaTooltip', function ( event ) {
			if ( 'Escape' === event.key || 'Esc' === event.key ) {
				$( '.kdna-comparison__tooltip-wrap.is-open' ).each( function () {
					setTooltipOpen( $( this ), false );
				} );
			}
		} );
	}

	$( window ).on( 'elementor/frontend/init', function () {
		if ( 'undefined' === typeof window.elementorFrontend ) {
			return;
		}

		bindDocumentDismiss();

		elementorFrontend.elementsHandler.attachHandler( WIDGET_NAME, function ( $scope ) {
			initInstance( $scope );

			if ( elementorFrontend.elements && elementorFrontend.elements.$window ) {
				elementorFrontend.elements.$window.on( 'resize.kdnaTables', function () {
					initInstance( $scope );
				} );
			}
		} );
	} );

}( jQuery ) );
