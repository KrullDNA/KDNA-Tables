/*
 * KDNA Tables admin editor (general table, Session 3 scope).
 *
 * Reads the initial state from window.kdnaTablesInitialState, debounces
 * serialisation into the hidden form input, and exposes the methods the
 * Alpine template calls for column/row manipulation.
 *
 * Cell text uses contenteditable bound one-way (PHP renders the seed via
 * x-init, the input event mutates state) so the cursor does not jump on
 * reactive re-renders.
 */

( function () {
	'use strict';

	var MAX_COLUMNS = 10;
	var SERIALISE_DEBOUNCE_MS = 300;
	var STATE_INPUT_ID = 'kdna_tables_editor_state';

	function defaultState() {
		return {
			post_id: 0,
			type: 'general',
			caption: '',
			general: {
				first_row_is_header: true,
				first_column_is_header: false,
				columns: [ defaultColumn( 1 ) ],
				rows: [ defaultRow( 1, 1 ) ]
			}
		};
	}

	function uid( prefix ) {
		var bytes;
		if ( window.crypto && typeof window.crypto.getRandomValues === 'function' ) {
			bytes = new Uint8Array( 8 );
			window.crypto.getRandomValues( bytes );
			return prefix + '_' + Array.prototype.map.call( bytes, function ( b ) {
				return ( b + 0x100 ).toString( 16 ).slice( 1 );
			} ).join( '' );
		}
		return prefix + '_' + Math.random().toString( 36 ).slice( 2, 10 ) + Date.now().toString( 36 );
	}

	function defaultColumn( seedIdx ) {
		return {
			id: uid( 'col' ),
			label: 'Column ' + ( seedIdx || 1 ),
			alignment: 'left',
			width: 0,
			width_unit: '%'
		};
	}

	function defaultCell() {
		return {
			id: uid( 'cell' ),
			content_types: [ 'text' ],
			text: '',
			icon: { value: '', library: '' },
			image: { id: 0, url: '', alt: '' },
			arrangement: 'icon-text',
			alignment: ''
		};
	}

	function defaultRow( seedIdx, columnCount ) {
		var cells = [];
		var n = Math.max( 1, columnCount || 1 );
		for ( var i = 0; i < n; i++ ) {
			cells.push( defaultCell() );
		}
		return {
			id: uid( 'row' ),
			cells: cells
		};
	}

	function normaliseSeed( raw ) {
		if ( ! raw || typeof raw !== 'object' ) {
			return defaultState();
		}
		var state = defaultState();
		state.post_id = parseInt( raw.post_id, 10 ) || 0;
		state.type = raw.type || 'general';
		state.caption = typeof raw.caption === 'string' ? raw.caption : '';

		if ( raw.general && typeof raw.general === 'object' ) {
			state.general.first_row_is_header = ! ! raw.general.first_row_is_header;
			state.general.first_column_is_header = ! ! raw.general.first_column_is_header;

			if ( Array.isArray( raw.general.columns ) && raw.general.columns.length ) {
				state.general.columns = raw.general.columns.map( function ( col, idx ) {
					col = col || {};
					return {
						id: col.id || uid( 'col' ),
						label: typeof col.label === 'string' ? col.label : 'Column ' + ( idx + 1 ),
						alignment: col.alignment || 'left',
						width: Number( col.width || 0 ),
						width_unit: col.width_unit || '%'
					};
				} );
			}

			if ( Array.isArray( raw.general.rows ) && raw.general.rows.length ) {
				state.general.rows = raw.general.rows.map( function ( row, rowIdx ) {
					row = row || {};
					var cells = Array.isArray( row.cells ) ? row.cells : [];
					var out = cells.map( function ( cell, cellIdx ) {
						cell = cell || {};
						return {
							id: cell.id || uid( 'cell' ),
							content_types: Array.isArray( cell.content_types ) && cell.content_types.length
								? cell.content_types.slice()
								: [ 'text' ],
							text: typeof cell.text === 'string' ? cell.text : '',
							icon: cell.icon && typeof cell.icon === 'object'
								? { value: cell.icon.value || '', library: cell.icon.library || '' }
								: { value: '', library: '' },
							image: cell.image && typeof cell.image === 'object'
								? {
									id: parseInt( cell.image.id, 10 ) || 0,
									url: cell.image.url || '',
									alt: cell.image.alt || ''
								}
								: { id: 0, url: '', alt: '' },
							arrangement: cell.arrangement || 'icon-text',
							alignment: cell.alignment || ''
						};
					} );
					// Pad short rows so the matrix always has one cell per column.
					var columnCount = state.general.columns.length;
					while ( out.length < columnCount ) {
						out.push( defaultCell() );
					}
					if ( out.length > columnCount ) {
						out.length = columnCount;
					}
					return {
						id: row.id || uid( 'row' ),
						cells: out
					};
				} );
			}
		}
		return state;
	}

	function kdnaTablesGeneralEditor() {
		return {
			state: normaliseSeed( window.kdnaTablesInitialState ),
			focusedCell: null,
			maxColumns: MAX_COLUMNS,
			_serialiseTimer: null,

			init: function () {
				var self = this;
				// Deep watch the entire state. Alpine 3 fires the watcher when
				// any nested property is reassigned through the proxy.
				this.$watch( 'state', function () {
					self.queueSerialise();
				} );

				// Flush on form submit so the latest state lands in the POST
				// even if the user mashed Save before the 300ms debounce.
				var form = document.getElementById( 'post' );
				if ( form ) {
					form.addEventListener( 'submit', function () {
						self.serialise();
					}, true );
				}

				// Initial sync so an unmodified post still saves a valid blob.
				this.serialise();
			},

			queueSerialise: function () {
				var self = this;
				clearTimeout( this._serialiseTimer );
				this._serialiseTimer = setTimeout( function () {
					self.serialise();
				}, SERIALISE_DEBOUNCE_MS );
			},

			serialise: function () {
				var input = document.getElementById( STATE_INPUT_ID );
				if ( ! input ) {
					return;
				}
				input.value = JSON.stringify( this.state );
			},

			isCellFocused: function ( rowIdx, colIdx ) {
				return this.focusedCell === rowIdx + '-' + colIdx;
			},

			onCellBlur: function () {
				// Tiny defer so a click on the toolbar buttons (which themselves
				// blur the contenteditable) still fires before the toolbar
				// is hidden. The toolbar buttons call preventDefault on
				// mousedown to avoid stealing focus too aggressively.
				var self = this;
				setTimeout( function () {
					if ( document.activeElement && document.activeElement.closest( '.kdna-editor__cell.is-focused' ) ) {
						return;
					}
					self.focusedCell = null;
				}, 10 );
			},

			/* --------------------------------------------------------------
			 *  Column actions
			 * -------------------------------------------------------------- */

			addColumn: function () {
				if ( this.state.general.columns.length >= MAX_COLUMNS ) {
					return;
				}
				var nextIdx = this.state.general.columns.length + 1;
				this.state.general.columns.push( defaultColumn( nextIdx ) );
				// Append a matching empty cell to every existing row so the
				// matrix stays square.
				this.state.general.rows.forEach( function ( row ) {
					row.cells.push( defaultCell() );
				} );
			},

			removeColumn: function ( idx ) {
				if ( this.state.general.columns.length <= 1 ) {
					return;
				}
				this.state.general.columns.splice( idx, 1 );
				this.state.general.rows.forEach( function ( row ) {
					if ( row.cells.length > idx ) {
						row.cells.splice( idx, 1 );
					}
				} );
			},

			moveColumn: function ( idx, dir ) {
				var target = idx + dir;
				if ( target < 0 || target >= this.state.general.columns.length ) {
					return;
				}
				var cols = this.state.general.columns;
				cols.splice( target, 0, cols.splice( idx, 1 )[ 0 ] );
				this.state.general.rows.forEach( function ( row ) {
					if ( target >= row.cells.length ) {
						return;
					}
					row.cells.splice( target, 0, row.cells.splice( idx, 1 )[ 0 ] );
				} );
			},

			setColumnAlignment: function ( idx, value ) {
				var col = this.state.general.columns[ idx ];
				if ( ! col ) {
					return;
				}
				col.alignment = value;
			},

			setColumnWidth: function ( idx, value ) {
				var col = this.state.general.columns[ idx ];
				if ( ! col ) {
					return;
				}
				var num = parseFloat( value );
				if ( isNaN( num ) || num < 0 ) {
					num = 0;
				}
				if ( num > 100 ) {
					num = 100;
				}
				col.width = num;
			},

			/* --------------------------------------------------------------
			 *  Row actions
			 * -------------------------------------------------------------- */

			addRow: function () {
				this.state.general.rows.push( defaultRow( this.state.general.rows.length + 1, this.state.general.columns.length ) );
			},

			removeRow: function ( idx ) {
				if ( this.state.general.rows.length === 0 ) {
					return;
				}
				this.state.general.rows.splice( idx, 1 );
			},

			moveRow: function ( idx, dir ) {
				var target = idx + dir;
				if ( target < 0 || target >= this.state.general.rows.length ) {
					return;
				}
				var rows = this.state.general.rows;
				rows.splice( target, 0, rows.splice( idx, 1 )[ 0 ] );
			},

			/* --------------------------------------------------------------
			 *  Cell actions
			 * -------------------------------------------------------------- */

			setCellText: function ( rowIdx, colIdx, value ) {
				var row = this.state.general.rows[ rowIdx ];
				if ( ! row || ! row.cells[ colIdx ] ) {
					return;
				}
				row.cells[ colIdx ].text = ( typeof value === 'string' ? value : '' );
				if ( row.cells[ colIdx ].content_types.indexOf( 'text' ) === -1 ) {
					row.cells[ colIdx ].content_types.push( 'text' );
				}
			},

			setAlignment: function ( rowIdx, colIdx, value ) {
				var row = this.state.general.rows[ rowIdx ];
				if ( ! row || ! row.cells[ colIdx ] ) {
					return;
				}
				row.cells[ colIdx ].alignment = value;
			}
		};
	}

	// Register the component for Alpine before it boots. The alpine:init
	// event fires once and is the canonical hook for registering data
	// components in Alpine 3.
	document.addEventListener( 'alpine:init', function () {
		window.Alpine.data( 'kdnaTablesGeneralEditor', kdnaTablesGeneralEditor );
	} );

	// Expose the factory globally so the inline x-data="kdnaTablesGeneralEditor()"
	// in the template can also resolve it pre-Alpine (helps when Alpine boots
	// late on slow connections).
	window.kdnaTablesGeneralEditor = kdnaTablesGeneralEditor;

}() );
