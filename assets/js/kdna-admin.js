/*
 * KDNA Tables admin editor (general table).
 *
 * Session 3 scope: matrix editor, text-only cells, debounced save.
 * Session 4 scope: rich cell content (icon picker, image picker via
 * wp.media, arrangement selector), column width unit selector.
 *
 * State is snake_case end-to-end to match CPT meta keys. The Alpine
 * factory is exposed both on window and through Alpine.data() so the
 * inline x-data="kdnaTablesGeneralEditor()" resolves either way.
 */

( function () {
	'use strict';

	var MAX_COLUMNS = 10;
	var SERIALISE_DEBOUNCE_MS = 300;
	var STATE_INPUT_ID = 'kdna_tables_editor_state';

	/* ------------------------------------------------------------------
	 * State helpers
	 * ------------------------------------------------------------------ */

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
						width_unit: col.width_unit === 'px' ? 'px' : '%'
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

	/* ------------------------------------------------------------------
	 * Arrangement helpers
	 * ------------------------------------------------------------------ */

	function sortedTypes( types ) {
		var order = { icon: 0, text: 1, image: 2 };
		return ( types || [] ).slice().sort( function ( a, b ) {
			return ( order[ a ] || 99 ) - ( order[ b ] || 99 );
		} );
	}

	function arrangementOptionsFor( types ) {
		var pieces = sortedTypes( types );
		if ( pieces.length < 2 ) {
			return [];
		}
		if ( pieces.length === 2 ) {
			return [
				pieces[ 0 ] + '-' + pieces[ 1 ],
				pieces[ 1 ] + '-' + pieces[ 0 ]
			];
		}
		// Three pieces. All six permutations are valid per the CPT VALID_ARRANGEMENTS.
		return [
			'icon-text-image',
			'icon-image-text',
			'text-icon-image',
			'text-image-icon',
			'image-text-icon',
			'image-icon-text'
		];
	}

	function pickDefaultArrangement( types, current ) {
		var options = arrangementOptionsFor( types );
		if ( options.length === 0 ) {
			return current || 'icon-text';
		}
		if ( current && options.indexOf( current ) !== -1 ) {
			return current;
		}
		return options[ 0 ];
	}

	function formatArrangementLabel( arrangement ) {
		// Turn 'icon-text' into 'Icon, Text', 'icon-text-image' into 'Icon, Text, Image'.
		return arrangement.split( '-' ).map( function ( w ) {
			return w.charAt( 0 ).toUpperCase() + w.slice( 1 );
		} ).join( ', ' );
	}

	/* ------------------------------------------------------------------
	 * Icon catalogue
	 * ------------------------------------------------------------------ */

	function loadIconCatalogue( callback ) {
		var cfg = window.KDNATablesAdmin || {};
		if ( ! cfg.iconsUrl ) {
			callback( { libraries: [], icons: [] } );
			return;
		}
		var xhr = new XMLHttpRequest();
		xhr.open( 'GET', cfg.iconsUrl, true );
		xhr.onreadystatechange = function () {
			if ( xhr.readyState !== 4 ) {
				return;
			}
			if ( xhr.status >= 200 && xhr.status < 300 ) {
				try {
					var parsed = JSON.parse( xhr.responseText );
					callback( parsed );
					return;
				} catch ( e ) { /* fallthrough */ }
			}
			callback( { libraries: [], icons: [] } );
		};
		xhr.send();
	}

	/* ------------------------------------------------------------------
	 * Alpine component factory
	 * ------------------------------------------------------------------ */

	function kdnaTablesGeneralEditor() {
		return {
			state: normaliseSeed( window.kdnaTablesInitialState ),
			focusedCell: null,
			maxColumns: MAX_COLUMNS,
			iconCatalogue: { libraries: [], icons: [] },
			iconPicker: {
				open: false,
				query: '',
				library: '',
				targetRow: -1,
				targetCol: -1
			},
			_serialiseTimer: null,

			init: function () {
				var self = this;
				this.$watch( 'state', function () {
					self.queueSerialise();
				} );

				var form = document.getElementById( 'post' );
				if ( form ) {
					form.addEventListener( 'submit', function () {
						self.serialise();
					}, true );
				}

				this.serialise();

				loadIconCatalogue( function ( catalogue ) {
					self.iconCatalogue = catalogue || { libraries: [], icons: [] };
				} );
			},

			get iconLibraries() {
				return this.iconCatalogue.libraries || [];
			},

			get filteredIcons() {
				var icons = this.iconCatalogue.icons || [];
				var lib   = this.iconPicker.library;
				var q     = ( this.iconPicker.query || '' ).toLowerCase().trim();
				return icons.filter( function ( icon ) {
					if ( lib && icon.library !== lib ) {
						return false;
					}
					if ( ! q ) {
						return true;
					}
					var haystack = ( icon.name + ' ' + icon.class + ' ' + ( icon.keywords || '' ) ).toLowerCase();
					return haystack.indexOf( q ) !== -1;
				} ).slice( 0, 180 );
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
				var self = this;
				setTimeout( function () {
					if ( document.activeElement && document.activeElement.closest( '.kdna-editor__cell.is-focused' ) ) {
						return;
					}
					self.focusedCell = null;
				}, 10 );
			},

			/* ------------------------------------------------------------------
			 * Column actions
			 * ------------------------------------------------------------------ */

			addColumn: function () {
				if ( this.state.general.columns.length >= MAX_COLUMNS ) {
					return;
				}
				var nextIdx = this.state.general.columns.length + 1;
				this.state.general.columns.push( defaultColumn( nextIdx ) );
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
				if ( col.width_unit === '%' && num > 100 ) {
					num = 100;
				}
				col.width = num;
			},

			setColumnWidthUnit: function ( idx, value ) {
				var col = this.state.general.columns[ idx ];
				if ( ! col ) {
					return;
				}
				col.width_unit = ( value === 'px' ) ? 'px' : '%';
				// If switching to %, clamp the existing value.
				if ( col.width_unit === '%' && col.width > 100 ) {
					col.width = 100;
				}
			},

			/* ------------------------------------------------------------------
			 * Row actions
			 * ------------------------------------------------------------------ */

			addRow: function () {
				this.state.general.rows.push(
					defaultRow( this.state.general.rows.length + 1, this.state.general.columns.length )
				);
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

			/* ------------------------------------------------------------------
			 * Cell actions
			 * ------------------------------------------------------------------ */

			cellAt: function ( rowIdx, colIdx ) {
				var row = this.state.general.rows[ rowIdx ];
				if ( ! row ) {
					return null;
				}
				return row.cells[ colIdx ] || null;
			},

			setCellText: function ( rowIdx, colIdx, value ) {
				var cell = this.cellAt( rowIdx, colIdx );
				if ( ! cell ) {
					return;
				}
				cell.text = ( typeof value === 'string' ? value : '' );
				if ( cell.content_types.indexOf( 'text' ) === -1 ) {
					cell.content_types.push( 'text' );
					cell.arrangement = pickDefaultArrangement( cell.content_types, cell.arrangement );
				}
			},

			setAlignment: function ( rowIdx, colIdx, value ) {
				var cell = this.cellAt( rowIdx, colIdx );
				if ( ! cell ) {
					return;
				}
				cell.alignment = value;
			},

			setArrangement: function ( rowIdx, colIdx, value ) {
				var cell = this.cellAt( rowIdx, colIdx );
				if ( ! cell ) {
					return;
				}
				if ( arrangementOptionsFor( cell.content_types ).indexOf( value ) !== -1 ) {
					cell.arrangement = value;
				}
			},

			/* ------------------------------------------------------------------
			 * Piece visibility / ordering for the cell preview
			 * ------------------------------------------------------------------ */

			hasPiece: function ( cell, piece ) {
				return !! cell && cell.content_types.indexOf( piece ) !== -1;
			},

			pieceOrder: function ( cell, piece ) {
				if ( ! cell || ! cell.arrangement ) {
					return 99;
				}
				var idx = cell.arrangement.split( '-' ).indexOf( piece );
				return idx === -1 ? 99 : idx + 1;
			},

			arrangementOptions: function ( cell ) {
				return arrangementOptionsFor( cell ? cell.content_types : [] );
			},

			formatArrangement: function ( arrangement ) {
				return formatArrangementLabel( arrangement );
			},

			iconClassesFor: function ( cell ) {
				if ( ! cell || ! cell.icon || ! cell.icon.value ) {
					return '';
				}
				return cell.icon.value;
			},

			/* ------------------------------------------------------------------
			 * Icon picker
			 * ------------------------------------------------------------------ */

			openIconPicker: function ( rowIdx, colIdx ) {
				this.iconPicker.open = true;
				this.iconPicker.targetRow = rowIdx;
				this.iconPicker.targetCol = colIdx;
				this.iconPicker.query = '';
				this.iconPicker.library = '';
				var self = this;
				this.$nextTick( function () {
					if ( self.$refs.iconSearchInput ) {
						self.$refs.iconSearchInput.focus();
					}
				} );
			},

			closeIconPicker: function () {
				this.iconPicker.open = false;
				this.iconPicker.targetRow = -1;
				this.iconPicker.targetCol = -1;
			},

			selectIcon: function ( icon ) {
				var rowIdx = this.iconPicker.targetRow;
				var colIdx = this.iconPicker.targetCol;
				var cell = this.cellAt( rowIdx, colIdx );
				if ( ! cell ) {
					this.closeIconPicker();
					return;
				}
				cell.icon = {
					value: icon.class || '',
					library: icon.library || ''
				};
				if ( cell.content_types.indexOf( 'icon' ) === -1 ) {
					cell.content_types.push( 'icon' );
				}
				cell.arrangement = pickDefaultArrangement( cell.content_types, cell.arrangement );
				this.closeIconPicker();
			},

			removeIcon: function ( rowIdx, colIdx ) {
				var cell = this.cellAt( rowIdx, colIdx );
				if ( ! cell ) {
					return;
				}
				cell.icon = { value: '', library: '' };
				cell.content_types = cell.content_types.filter( function ( t ) {
					return t !== 'icon';
				} );
				if ( cell.content_types.length === 0 ) {
					cell.content_types = [ 'text' ];
				}
				cell.arrangement = pickDefaultArrangement( cell.content_types, cell.arrangement );
			},

			/* ------------------------------------------------------------------
			 * Image picker (wp.media)
			 * ------------------------------------------------------------------ */

			openImagePicker: function ( rowIdx, colIdx ) {
				if ( ! window.wp || ! window.wp.media ) {
					/* eslint-disable-next-line no-alert */
					alert( 'Image picker is unavailable. Reload the page.' );
					return;
				}
				var self  = this;
				var frame = window.wp.media( {
					title: 'Select cell image',
					button: { text: 'Use this image' },
					multiple: false,
					library: { type: 'image' }
				} );
				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					self.applyImage( rowIdx, colIdx, attachment );
				} );
				frame.open();
			},

			applyImage: function ( rowIdx, colIdx, attachment ) {
				var cell = this.cellAt( rowIdx, colIdx );
				if ( ! cell || ! attachment ) {
					return;
				}
				cell.image = {
					id: parseInt( attachment.id, 10 ) || 0,
					url: attachment.url || '',
					alt: attachment.alt || attachment.title || ''
				};
				if ( cell.content_types.indexOf( 'image' ) === -1 ) {
					cell.content_types.push( 'image' );
				}
				cell.arrangement = pickDefaultArrangement( cell.content_types, cell.arrangement );
			},

			removeImage: function ( rowIdx, colIdx ) {
				var cell = this.cellAt( rowIdx, colIdx );
				if ( ! cell ) {
					return;
				}
				cell.image = { id: 0, url: '', alt: '' };
				cell.content_types = cell.content_types.filter( function ( t ) {
					return t !== 'image';
				} );
				if ( cell.content_types.length === 0 ) {
					cell.content_types = [ 'text' ];
				}
				cell.arrangement = pickDefaultArrangement( cell.content_types, cell.arrangement );
			}
		};
	}

	document.addEventListener( 'alpine:init', function () {
		window.Alpine.data( 'kdnaTablesGeneralEditor', kdnaTablesGeneralEditor );
	} );

	window.kdnaTablesGeneralEditor = kdnaTablesGeneralEditor;

}() );
