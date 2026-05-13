/*
 * KDNA Tables admin editor (general + comparison).
 *
 * Session 3: matrix editor for general tables (text-only cells).
 * Session 4: rich cell content for general tables (icon picker, image
 *            picker via wp.media, arrangement selector, width unit).
 * Session 5: full editor for comparison tables (items strip, feature
 *            rows, highlight, badge, CTAs, per-cell state + custom).
 *
 * State is snake_case end-to-end to match CPT meta keys. The Alpine
 * factories are exposed on window and through Alpine.data() so the
 * inline x-data="..." in each template resolves either way.
 */

( function () {
	'use strict';

	var MAX_COLUMNS = 10;
	var MAX_ITEMS = 6;
	var SERIALISE_DEBOUNCE_MS = 300;
	var STATE_INPUT_ID = 'kdna_tables_editor_state';

	/* ==================================================================
	 * Shared module helpers
	 * ================================================================== */

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
		return arrangement.split( '-' ).map( function ( w ) {
			return w.charAt( 0 ).toUpperCase() + w.slice( 1 );
		} ).join( ', ' );
	}

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

	function dispatchStateEvent( state ) {
		try {
			document.dispatchEvent( new CustomEvent( 'kdna:state', { detail: state } ) );
		} catch ( e ) { /* old browser, ignore */ }
	}

	function bindFormFlush( self ) {
		var form = document.getElementById( 'post' );
		if ( form ) {
			form.addEventListener( 'submit', function () {
				self.serialise();
			}, true );
		}
	}

	function openWpMediaFrame( onSelect ) {
		if ( ! window.wp || ! window.wp.media ) {
			/* eslint-disable-next-line no-alert */
			alert( 'Image picker is unavailable. Reload the page.' );
			return;
		}
		var frame = window.wp.media( {
			title: 'Select image',
			button: { text: 'Use this image' },
			multiple: false,
			library: { type: 'image' }
		} );
		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			onSelect( attachment );
		} );
		frame.open();
	}

	function attachmentToImage( attachment ) {
		return {
			id: parseInt( attachment.id, 10 ) || 0,
			url: attachment.url || '',
			alt: attachment.alt || attachment.title || ''
		};
	}

	/* ==================================================================
	 * General editor (Session 3 + 4)
	 * ================================================================== */

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

	function defaultGeneralState() {
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

	function normaliseGeneralSeed( raw ) {
		if ( ! raw || typeof raw !== 'object' ) {
			return defaultGeneralState();
		}
		var state = defaultGeneralState();
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
					var out = cells.map( function ( cell ) {
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

	function kdnaTablesGeneralEditor() {
		return {
			state: normaliseGeneralSeed( window.kdnaTablesInitialState ),
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
				bindFormFlush( this );
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
				if ( input ) {
					input.value = JSON.stringify( this.state );
				}
				dispatchStateEvent( this.state );
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
				if ( col.width_unit === '%' && col.width > 100 ) {
					col.width = 100;
				}
			},

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
				var cell = this.cellAt( this.iconPicker.targetRow, this.iconPicker.targetCol );
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

			openImagePicker: function ( rowIdx, colIdx ) {
				var self = this;
				openWpMediaFrame( function ( attachment ) {
					self.applyImage( rowIdx, colIdx, attachment );
				} );
			},

			applyImage: function ( rowIdx, colIdx, attachment ) {
				var cell = this.cellAt( rowIdx, colIdx );
				if ( ! cell || ! attachment ) {
					return;
				}
				cell.image = attachmentToImage( attachment );
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

	/* ==================================================================
	 * Comparison editor (Session 5)
	 * ================================================================== */

	function defaultComparisonCell() {
		return {
			state: 'available',
			custom: {
				content_types: [ 'text' ],
				text: '',
				icon: { value: '', library: '' },
				image: { id: 0, url: '', alt: '' },
				arrangement: 'icon-text'
			}
		};
	}

	function defaultComparisonItem( seedIdx ) {
		return {
			id: uid( 'item' ),
			image: { id: 0, url: '', alt: '' },
			label: 'Item ' + ( seedIdx || 1 ),
			sublabel: '',
			cta: { enabled: false, text: 'Learn more', url: '' }
		};
	}

	function defaultComparisonFeatureRow( seedIdx, itemCount ) {
		var cells = [];
		var n = Math.max( 0, itemCount || 0 );
		for ( var i = 0; i < n; i++ ) {
			cells.push( defaultComparisonCell() );
		}
		return {
			id: uid( 'fr' ),
			label: 'Feature ' + ( seedIdx || 1 ),
			description: '',
			tooltip: '',
			cells: cells
		};
	}

	function defaultComparisonState() {
		return {
			post_id: 0,
			type: 'comparison',
			caption: '',
			comparison: {
				highlighted_item_index: -1,
				badge_text: 'Recommended',
				badge_position: 'top-centre',
				items: [ defaultComparisonItem( 1 ), defaultComparisonItem( 2 ) ],
				feature_rows: [
					defaultComparisonFeatureRow( 1, 2 ),
					defaultComparisonFeatureRow( 2, 2 ),
					defaultComparisonFeatureRow( 3, 2 )
				]
			}
		};
	}

	function normaliseComparisonCell( cell ) {
		cell = cell || {};
		var state = ( cell.state === 'unavailable' || cell.state === 'custom' ) ? cell.state : 'available';
		var custom = ( cell.custom && typeof cell.custom === 'object' ) ? cell.custom : {};
		return {
			state: state,
			custom: {
				content_types: Array.isArray( custom.content_types ) && custom.content_types.length
					? custom.content_types.slice()
					: [ 'text' ],
				text: typeof custom.text === 'string' ? custom.text : '',
				icon: custom.icon && typeof custom.icon === 'object'
					? { value: custom.icon.value || '', library: custom.icon.library || '' }
					: { value: '', library: '' },
				image: custom.image && typeof custom.image === 'object'
					? {
						id: parseInt( custom.image.id, 10 ) || 0,
						url: custom.image.url || '',
						alt: custom.image.alt || ''
					}
					: { id: 0, url: '', alt: '' },
				arrangement: custom.arrangement || 'icon-text'
			}
		};
	}

	function normaliseComparisonSeed( raw ) {
		if ( ! raw || typeof raw !== 'object' ) {
			return defaultComparisonState();
		}
		var state = defaultComparisonState();
		state.post_id = parseInt( raw.post_id, 10 ) || 0;
		state.type = 'comparison';
		state.caption = typeof raw.caption === 'string' ? raw.caption : '';

		if ( raw.comparison && typeof raw.comparison === 'object' ) {
			state.comparison.highlighted_item_index = parseInt( raw.comparison.highlighted_item_index, 10 );
			if ( isNaN( state.comparison.highlighted_item_index ) ) {
				state.comparison.highlighted_item_index = -1;
			}
			state.comparison.badge_text = typeof raw.comparison.badge_text === 'string'
				? raw.comparison.badge_text
				: 'Recommended';
			var pos = raw.comparison.badge_position;
			state.comparison.badge_position = ( pos === 'top-left' || pos === 'top-right' )
				? pos
				: 'top-centre';

			if ( Array.isArray( raw.comparison.items ) && raw.comparison.items.length ) {
				state.comparison.items = raw.comparison.items.map( function ( item, idx ) {
					item = item || {};
					var cta = ( item.cta && typeof item.cta === 'object' ) ? item.cta : {};
					return {
						id: item.id || uid( 'item' ),
						image: item.image && typeof item.image === 'object'
							? {
								id: parseInt( item.image.id, 10 ) || 0,
								url: item.image.url || '',
								alt: item.image.alt || ''
							}
							: { id: 0, url: '', alt: '' },
						label: typeof item.label === 'string' ? item.label : 'Item ' + ( idx + 1 ),
						sublabel: typeof item.sublabel === 'string' ? item.sublabel : '',
						cta: {
							enabled: ! ! cta.enabled,
							text: typeof cta.text === 'string' ? cta.text : '',
							url: typeof cta.url === 'string' ? cta.url : ''
						}
					};
				} ).slice( 0, MAX_ITEMS );
			}

			var itemCount = state.comparison.items.length;
			if ( state.comparison.highlighted_item_index >= itemCount ) {
				state.comparison.highlighted_item_index = -1;
			}

			if ( Array.isArray( raw.comparison.feature_rows ) && raw.comparison.feature_rows.length ) {
				state.comparison.feature_rows = raw.comparison.feature_rows.map( function ( row, idx ) {
					row = row || {};
					var cells = Array.isArray( row.cells ) ? row.cells.map( normaliseComparisonCell ) : [];
					while ( cells.length < itemCount ) {
						cells.push( defaultComparisonCell() );
					}
					if ( cells.length > itemCount ) {
						cells.length = itemCount;
					}
					return {
						id: row.id || uid( 'fr' ),
						label: typeof row.label === 'string' ? row.label : 'Feature ' + ( idx + 1 ),
						description: typeof row.description === 'string' ? row.description : '',
						tooltip: typeof row.tooltip === 'string' ? row.tooltip : '',
						cells: cells
					};
				} );
			}
		}
		return state;
	}

	function kdnaTablesComparisonEditor() {
		return {
			state: normaliseComparisonSeed( window.kdnaTablesInitialState ),
			maxItems: MAX_ITEMS,
			iconCatalogue: { libraries: [], icons: [] },
			iconPicker: {
				open: false,
				query: '',
				library: '',
				targetRow: -1,
				targetCol: -1
			},
			focusedCell: null,
			_serialiseTimer: null,

			init: function () {
				var self = this;
				this.$watch( 'state', function () {
					self.queueSerialise();
				} );
				bindFormFlush( this );
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
				if ( input ) {
					input.value = JSON.stringify( this.state );
				}
				dispatchStateEvent( this.state );
			},

			/* ----------------------------------------------------------
			 * Items
			 * ---------------------------------------------------------- */

			addItem: function () {
				if ( this.state.comparison.items.length >= MAX_ITEMS ) {
					return;
				}
				var nextIdx = this.state.comparison.items.length + 1;
				this.state.comparison.items.push( defaultComparisonItem( nextIdx ) );
				// Append a blank cell to every existing feature row at the new index.
				this.state.comparison.feature_rows.forEach( function ( row ) {
					row.cells.push( defaultComparisonCell() );
				} );
			},

			removeItem: function ( idx ) {
				if ( this.state.comparison.items.length <= 1 ) {
					return;
				}
				this.state.comparison.items.splice( idx, 1 );
				this.state.comparison.feature_rows.forEach( function ( row ) {
					if ( row.cells.length > idx ) {
						row.cells.splice( idx, 1 );
					}
				} );
				// Shift highlight if the deleted item was highlighted, or
				// if removing earlier index moved the highlighted index down.
				var h = this.state.comparison.highlighted_item_index;
				if ( h === idx ) {
					this.state.comparison.highlighted_item_index = -1;
				} else if ( h > idx ) {
					this.state.comparison.highlighted_item_index = h - 1;
				}
			},

			moveItem: function ( idx, dir ) {
				var target = idx + dir;
				if ( target < 0 || target >= this.state.comparison.items.length ) {
					return;
				}
				var items = this.state.comparison.items;
				items.splice( target, 0, items.splice( idx, 1 )[ 0 ] );
				// Reorder every feature row's cells in lockstep so the
				// per-item state stays attached to its item.
				this.state.comparison.feature_rows.forEach( function ( row ) {
					if ( target >= row.cells.length ) {
						return;
					}
					row.cells.splice( target, 0, row.cells.splice( idx, 1 )[ 0 ] );
				} );
				// Move highlight along with the dragged item.
				var h = this.state.comparison.highlighted_item_index;
				if ( h === idx ) {
					this.state.comparison.highlighted_item_index = target;
				} else if ( dir < 0 && h === target ) {
					this.state.comparison.highlighted_item_index = idx;
				} else if ( dir > 0 && h === target ) {
					this.state.comparison.highlighted_item_index = idx;
				}
			},

			toggleHighlight: function ( idx ) {
				if ( this.state.comparison.highlighted_item_index === idx ) {
					this.state.comparison.highlighted_item_index = -1;
				} else {
					this.state.comparison.highlighted_item_index = idx;
				}
			},

			isHighlighted: function ( idx ) {
				return this.state.comparison.highlighted_item_index === idx;
			},

			hasHighlight: function () {
				return this.state.comparison.highlighted_item_index >= 0;
			},

			openItemImagePicker: function ( idx ) {
				var self = this;
				openWpMediaFrame( function ( attachment ) {
					var item = self.state.comparison.items[ idx ];
					if ( item ) {
						item.image = attachmentToImage( attachment );
					}
				} );
			},

			removeItemImage: function ( idx ) {
				var item = this.state.comparison.items[ idx ];
				if ( item ) {
					item.image = { id: 0, url: '', alt: '' };
				}
			},

			/* ----------------------------------------------------------
			 * Feature rows
			 * ---------------------------------------------------------- */

			addFeatureRow: function () {
				var idx = this.state.comparison.feature_rows.length + 1;
				this.state.comparison.feature_rows.push(
					defaultComparisonFeatureRow( idx, this.state.comparison.items.length )
				);
			},

			removeFeatureRow: function ( idx ) {
				if ( this.state.comparison.feature_rows.length === 0 ) {
					return;
				}
				this.state.comparison.feature_rows.splice( idx, 1 );
			},

			moveFeatureRow: function ( idx, dir ) {
				var target = idx + dir;
				if ( target < 0 || target >= this.state.comparison.feature_rows.length ) {
					return;
				}
				var rows = this.state.comparison.feature_rows;
				rows.splice( target, 0, rows.splice( idx, 1 )[ 0 ] );
			},

			/* ----------------------------------------------------------
			 * Cell state + custom content
			 * ---------------------------------------------------------- */

			featureCellAt: function ( rowIdx, colIdx ) {
				var row = this.state.comparison.feature_rows[ rowIdx ];
				if ( ! row ) {
					return null;
				}
				return row.cells[ colIdx ] || null;
			},

			setCellState: function ( rowIdx, colIdx, value ) {
				var cell = this.featureCellAt( rowIdx, colIdx );
				if ( ! cell ) {
					return;
				}
				if ( value !== 'available' && value !== 'unavailable' && value !== 'custom' ) {
					return;
				}
				cell.state = value;
			},

			setCustomText: function ( rowIdx, colIdx, value ) {
				var cell = this.featureCellAt( rowIdx, colIdx );
				if ( ! cell ) {
					return;
				}
				cell.custom.text = ( typeof value === 'string' ? value : '' );
				if ( cell.custom.content_types.indexOf( 'text' ) === -1 ) {
					cell.custom.content_types.push( 'text' );
					cell.custom.arrangement = pickDefaultArrangement( cell.custom.content_types, cell.custom.arrangement );
				}
			},

			setCustomArrangement: function ( rowIdx, colIdx, value ) {
				var cell = this.featureCellAt( rowIdx, colIdx );
				if ( ! cell ) {
					return;
				}
				if ( arrangementOptionsFor( cell.custom.content_types ).indexOf( value ) !== -1 ) {
					cell.custom.arrangement = value;
				}
			},

			customHasPiece: function ( cell, piece ) {
				return !! cell && cell.custom && cell.custom.content_types.indexOf( piece ) !== -1;
			},

			customPieceOrder: function ( cell, piece ) {
				if ( ! cell || ! cell.custom || ! cell.custom.arrangement ) {
					return 99;
				}
				var idx = cell.custom.arrangement.split( '-' ).indexOf( piece );
				return idx === -1 ? 99 : idx + 1;
			},

			customArrangementOptions: function ( cell ) {
				return arrangementOptionsFor( cell && cell.custom ? cell.custom.content_types : [] );
			},

			formatArrangement: function ( arrangement ) {
				return formatArrangementLabel( arrangement );
			},

			/* ----------------------------------------------------------
			 * Icon picker for custom cells
			 * ---------------------------------------------------------- */

			openCustomIconPicker: function ( rowIdx, colIdx ) {
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
				var cell = this.featureCellAt( this.iconPicker.targetRow, this.iconPicker.targetCol );
				if ( ! cell ) {
					this.closeIconPicker();
					return;
				}
				cell.custom.icon = {
					value: icon.class || '',
					library: icon.library || ''
				};
				if ( cell.custom.content_types.indexOf( 'icon' ) === -1 ) {
					cell.custom.content_types.push( 'icon' );
				}
				cell.custom.arrangement = pickDefaultArrangement( cell.custom.content_types, cell.custom.arrangement );
				this.closeIconPicker();
			},

			removeCustomIcon: function ( rowIdx, colIdx ) {
				var cell = this.featureCellAt( rowIdx, colIdx );
				if ( ! cell ) {
					return;
				}
				cell.custom.icon = { value: '', library: '' };
				cell.custom.content_types = cell.custom.content_types.filter( function ( t ) {
					return t !== 'icon';
				} );
				if ( cell.custom.content_types.length === 0 ) {
					cell.custom.content_types = [ 'text' ];
				}
				cell.custom.arrangement = pickDefaultArrangement( cell.custom.content_types, cell.custom.arrangement );
			},

			/* ----------------------------------------------------------
			 * Image picker for custom cells
			 * ---------------------------------------------------------- */

			openCustomImagePicker: function ( rowIdx, colIdx ) {
				var self = this;
				openWpMediaFrame( function ( attachment ) {
					var cell = self.featureCellAt( rowIdx, colIdx );
					if ( ! cell ) {
						return;
					}
					cell.custom.image = attachmentToImage( attachment );
					if ( cell.custom.content_types.indexOf( 'image' ) === -1 ) {
						cell.custom.content_types.push( 'image' );
					}
					cell.custom.arrangement = pickDefaultArrangement( cell.custom.content_types, cell.custom.arrangement );
				} );
			},

			removeCustomImage: function ( rowIdx, colIdx ) {
				var cell = this.featureCellAt( rowIdx, colIdx );
				if ( ! cell ) {
					return;
				}
				cell.custom.image = { id: 0, url: '', alt: '' };
				cell.custom.content_types = cell.custom.content_types.filter( function ( t ) {
					return t !== 'image';
				} );
				if ( cell.custom.content_types.length === 0 ) {
					cell.custom.content_types = [ 'text' ];
				}
				cell.custom.arrangement = pickDefaultArrangement( cell.custom.content_types, cell.custom.arrangement );
			}
		};
	}

	/* ==================================================================
	 * Alpine registration
	 * ================================================================== */

	document.addEventListener( 'alpine:init', function () {
		window.Alpine.data( 'kdnaTablesGeneralEditor', kdnaTablesGeneralEditor );
		window.Alpine.data( 'kdnaTablesComparisonEditor', kdnaTablesComparisonEditor );
	} );

	window.kdnaTablesGeneralEditor = kdnaTablesGeneralEditor;
	window.kdnaTablesComparisonEditor = kdnaTablesComparisonEditor;

	/* ==================================================================
	 * Structural preview (separate meta box)
	 *
	 * Subscribes to the 'kdna:state' custom event the editor dispatches on
	 * every state change and renders a low-fidelity HTML table into the
	 * preview meta box. Pure DOM, no Alpine, no styling.
	 * ================================================================== */

	function escapeHtml( s ) {
		return String( s == null ? '' : s )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	function piecesOrder( arrangement ) {
		if ( ! arrangement ) { return []; }
		return arrangement.split( '-' ).filter( function ( p ) {
			return p === 'icon' || p === 'text' || p === 'image';
		} );
	}

	function cellPiecesHtml( cell, isCustom ) {
		var src   = isCustom ? cell.custom : cell;
		if ( ! src ) { return ''; }
		var types = src.content_types || [];
		var order = piecesOrder( src.arrangement );
		if ( ! order.length ) { order = types.slice(); }
		var parts = [];
		order.forEach( function ( piece ) {
			if ( types.indexOf( piece ) === -1 ) { return; }
			if ( piece === 'text' && src.text ) {
				parts.push( '<span class="kdna-preview__cell-piece">' + escapeHtml( src.text ) + '</span>' );
			} else if ( piece === 'icon' && src.icon && src.icon.value ) {
				parts.push( '<span class="kdna-preview__cell-piece"><i class="' + escapeHtml( src.icon.value ) + '" aria-hidden="true"></i></span>' );
			} else if ( piece === 'image' && src.image && src.image.url ) {
				parts.push( '<span class="kdna-preview__cell-piece"><img src="' + escapeHtml( src.image.url ) + '" alt="' + escapeHtml( src.image.alt || '' ) + '"></span>' );
			}
		} );
		return parts.join( ' ' );
	}

	function buildGeneralPreviewHtml( state ) {
		var data = state.general || {};
		var cols = data.columns || [];
		var rows = data.rows || [];
		var html = '<table>';
		if ( state.caption ) {
			html += '<caption>' + escapeHtml( state.caption ) + '</caption>';
		}
		if ( ! cols.length ) {
			html += '</table><p class="kdna-preview__empty">' + escapeHtml( 'No columns yet.' ) + '</p>';
			return html;
		}
		var bodyRows = rows.slice();
		if ( data.first_row_is_header && bodyRows.length ) {
			var headRow = bodyRows.shift();
			html += '<thead><tr>';
			cols.forEach( function ( col, idx ) {
				var cell = ( headRow.cells || [] )[ idx ] || {};
				html += '<th>' + ( cellPiecesHtml( cell, false ) || '&nbsp;' ) + '</th>';
			} );
			html += '</tr></thead>';
		}
		html += '<tbody>';
		bodyRows.forEach( function ( row ) {
			html += '<tr>';
			cols.forEach( function ( col, idx ) {
				var cell = ( row.cells || [] )[ idx ] || {};
				var tag  = ( idx === 0 && data.first_column_is_header ) ? 'th' : 'td';
				html += '<' + tag + '>' + ( cellPiecesHtml( cell, false ) || '&nbsp;' ) + '</' + tag + '>';
			} );
			html += '</tr>';
		} );
		html += '</tbody></table>';
		return html;
	}

	function buildComparisonPreviewHtml( state ) {
		var data  = state.comparison || {};
		var items = data.items || [];
		var rows  = data.feature_rows || [];
		if ( items.length === 0 ) {
			return '<p class="kdna-preview__empty">' + escapeHtml( 'No items yet.' ) + '</p>';
		}
		var highlight = parseInt( data.highlighted_item_index, 10 );
		var html = '<table>';
		if ( state.caption ) {
			html += '<caption>' + escapeHtml( state.caption ) + '</caption>';
		}
		html += '<thead><tr><th></th>';
		items.forEach( function ( item, idx ) {
			var label = escapeHtml( item.label || '' );
			if ( item.sublabel ) {
				label += ' <small>' + escapeHtml( item.sublabel ) + '</small>';
			}
			if ( idx === highlight && data.badge_text ) {
				label += ' <span class="kdna-preview__badge">' + escapeHtml( data.badge_text ) + '</span>';
			}
			html += '<th>' + label + '</th>';
		} );
		html += '</tr></thead><tbody>';
		rows.forEach( function ( row ) {
			html += '<tr><td><strong>' + escapeHtml( row.label || '' ) + '</strong>';
			if ( row.description ) {
				html += '<br><small>' + escapeHtml( row.description ) + '</small>';
			}
			html += '</td>';
			items.forEach( function ( _item, idx ) {
				var cell = ( row.cells || [] )[ idx ] || { state: 'available' };
				var inner = '';
				if ( cell.state === 'available' ) {
					inner = '<span class="kdna-preview__cell-state">&#10003;</span>';
				} else if ( cell.state === 'unavailable' ) {
					inner = '<span class="kdna-preview__cell-state">&times;</span>';
				} else if ( cell.state === 'custom' ) {
					inner = cellPiecesHtml( cell, true ) || '&nbsp;';
				}
				html += '<td>' + inner + '</td>';
			} );
			html += '</tr>';
		} );
		html += '</tbody></table>';
		return html;
	}

	function renderPreview( state ) {
		var el = document.getElementById( 'kdna-preview-content' );
		if ( ! el ) { return; }
		if ( ! state || ! state.type ) {
			el.innerHTML = '<p class="kdna-preview__empty">' + escapeHtml( 'No data yet.' ) + '</p>';
			return;
		}
		if ( state.type === 'general' ) {
			el.innerHTML = buildGeneralPreviewHtml( state );
		} else if ( state.type === 'comparison' ) {
			el.innerHTML = buildComparisonPreviewHtml( state );
		} else {
			el.innerHTML = '';
		}
	}

	document.addEventListener( 'kdna:state', function ( e ) {
		renderPreview( e.detail );
	} );

	// Initial render from the seed in case the preview meta box mounts
	// before the editor dispatches its first event.
	function initialPreviewRender() {
		var seed = window.kdnaTablesInitialState;
		if ( seed ) { renderPreview( seed ); }
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initialPreviewRender );
	} else {
		initialPreviewRender();
	}

}() );
