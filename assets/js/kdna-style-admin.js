/*
 * KDNA Tables, Shortcode Styles settings page.
 *
 * Reads its seed from window.KDNATablesStyles (emitted by
 * KDNA_Tables_Style_Admin) and saves through the kdna-tables/v1/styles
 * REST route.
 *
 * ── Shaping ───────────────────────────────────────────────────────────
 *
 * Alpine's x-model needs an assignable path: binding to
 * values['header_padding']['mobile']['top'] throws if any link in that
 * chain is undefined, and the stored option is deliberately sparse — an
 * unset control is absent, not present and empty, because absent is what
 * "inherit" means everywhere downstream. So the seed is expanded into a
 * full skeleton before Alpine binds, and collapsed back to a sparse
 * object on save. The empties never reach the server, and the server
 * drops any that do.
 *
 * ── Addressing ────────────────────────────────────────────────────────
 *
 * Everything below takes the same three arguments: a control key, a
 * field key that is empty except inside a typography, border or
 * background group, and a device that is empty for a flat control. That
 * triple is enough to reach any leaf in state, so the markup never has
 * to hand the component a path to eval.
 *
 * ── One-way bindings ──────────────────────────────────────────────────
 *
 * The native colour input and the range input are bound with :value and
 * @input rather than x-model. Neither can represent "unset": a colour
 * input shows #000000 for an empty value and a range parks its thumb
 * somewhere regardless. Two-way binding would write those placeholder
 * positions into state on first paint and quietly turn every untouched
 * control into a set one.
 */

( function () {
	'use strict';

	var DEVICES = [ 'desktop', 'tablet', 'mobile' ];
	var SIDES = [ 'top', 'right', 'bottom', 'left' ];

	function boot() {
		return window.KDNATablesStyles || {
			schema: {}, sections: {}, devices: DEVICES, values: {},
			context: 'global', tableId: 0, inherited: {},
			restUrl: '', nonce: '', strings: {}
		};
	}

	function isGroup( definition ) {
		return definition && (
			'typography' === definition.type ||
			'border' === definition.type ||
			'background' === definition.type
		);
	}

	/** A blank value in this leaf type's storage shape. */
	function emptyLeaf( definition ) {
		var units = ( definition && definition.units ) || [];

		if ( 'dimensions' === definition.type ) {
			return { top: '', right: '', bottom: '', left: '', unit: units[ 0 ] || '', linked: true };
		}
		if ( 'slider' === definition.type ) {
			return { size: '', unit: units[ 0 ] || '' };
		}
		return '';
	}

	/** Merge a stored leaf value over a blank one, so every key exists. */
	function shapeLeaf( definition, stored ) {
		var blank = emptyLeaf( definition );

		if ( 'object' !== typeof blank || null === blank ) {
			return ( null === stored || undefined === stored ) ? blank : stored;
		}

		var out = Object.assign( {}, blank );
		if ( stored && 'object' === typeof stored ) {
			Object.keys( blank ).forEach( function ( k ) {
				if ( undefined !== stored[ k ] && null !== stored[ k ] ) {
					out[ k ] = stored[ k ];
				}
			} );
		}
		return out;
	}

	function shapeControl( definition, stored ) {
		if ( isGroup( definition ) ) {
			var group = {};
			Object.keys( definition.fields || {} ).forEach( function ( fieldKey ) {
				group[ fieldKey ] = shapeControl(
					definition.fields[ fieldKey ],
					stored && stored[ fieldKey ]
				);
			} );
			return group;
		}

		if ( definition.responsive ) {
			var byDevice = {};
			DEVICES.forEach( function ( device ) {
				byDevice[ device ] = shapeLeaf( definition, stored && stored[ device ] );
			} );
			return byDevice;
		}

		return shapeLeaf( definition, stored );
	}

	/** Expand the sparse stored option into a fully populated skeleton. */
	function shapeAll( schema, values ) {
		var shaped = {};
		Object.keys( schema ).forEach( function ( key ) {
			shaped[ key ] = shapeControl( schema[ key ], values && values[ key ] );
		} );
		return shaped;
	}

	/* ── Collapsing back down ─────────────────────────────────────── */

	function leafIsEmpty( definition, value ) {
		if ( null === value || undefined === value ) { return true; }

		if ( 'object' === typeof value ) {
			return ! Object.keys( value ).some( function ( k ) {
				// unit and linked are settings about the value, not the
				// value: a dimensions control holding nothing but a unit
				// and a link state is still unset.
				if ( 'unit' === k || 'linked' === k ) { return false; }
				return '' !== String( value[ k ] ).trim();
			} );
		}

		return '' === String( value ).trim();
	}

	function collapseControl( definition, value ) {
		if ( isGroup( definition ) ) {
			var group = {};
			Object.keys( definition.fields || {} ).forEach( function ( fieldKey ) {
				var field = collapseControl( definition.fields[ fieldKey ], value && value[ fieldKey ] );
				if ( undefined !== field ) { group[ fieldKey ] = field; }
			} );
			return Object.keys( group ).length ? group : undefined;
		}

		if ( definition.responsive ) {
			var byDevice = {};
			DEVICES.forEach( function ( device ) {
				var leaf = value && value[ device ];
				if ( ! leafIsEmpty( definition, leaf ) ) { byDevice[ device ] = leaf; }
			} );
			return Object.keys( byDevice ).length ? byDevice : undefined;
		}

		return leafIsEmpty( definition, value ) ? undefined : value;
	}

	/** Strip everything blank, so the payload carries only real values. */
	function collapseAll( schema, values ) {
		var out = {};
		Object.keys( schema ).forEach( function ( key ) {
			var collapsed = collapseControl( schema[ key ], values[ key ] );
			if ( undefined !== collapsed ) { out[ key ] = collapsed; }
		} );
		return out;
	}

	/* ── Colour helpers ───────────────────────────────────────────── */

	/**
	 * A #rrggbb the native colour input can display.
	 *
	 * Short hex is expanded, since the input rejects three-digit form.
	 * rgb() and rgba() are converted, dropping the alpha the input
	 * cannot show. Anything else — unset, a keyword, nonsense mid-typing
	 * — parks the swatch on black without that ever being written to
	 * state.
	 */
	function toSwatch( value ) {
		var v = String( value == null ? '' : value ).trim().toLowerCase();

		if ( /^#[0-9a-f]{3}$/.test( v ) ) {
			return '#' + v[ 1 ] + v[ 1 ] + v[ 2 ] + v[ 2 ] + v[ 3 ] + v[ 3 ];
		}
		if ( /^#[0-9a-f]{6}$/.test( v ) ) {
			return v;
		}

		var rgb = v.match( /^rgba?\(([^)]+)\)$/ );
		if ( rgb ) {
			var parts = rgb[ 1 ].split( ',' ).map( function ( p ) { return parseFloat( p.trim() ); } );
			if ( parts.length >= 3 && parts.slice( 0, 3 ).every( function ( n ) { return ! isNaN( n ); } ) ) {
				return '#' + parts.slice( 0, 3 ).map( function ( n ) {
					var b = Math.max( 0, Math.min( 255, Math.round( n ) ) );
					return ( b < 16 ? '0' : '' ) + b.toString( 16 );
				} ).join( '' );
			}
		}

		return '#000000';
	}

	/* ── Group summaries ──────────────────────────────────────────── */

	/**
	 * A short, CSS-shaped token for one leaf value: what the field will
	 * actually contribute, not a prose description of it. Four equal
	 * dimension sides collapse to one number, because "16px" reads and
	 * scans better than "16 16 16 16px".
	 */
	function leafToken( definition, value ) {
		if ( leafIsEmpty( definition, value ) ) { return ''; }

		if ( 'dimensions' === definition.type ) {
			var unit = value.unit || '';
			var sides = [ 'top', 'right', 'bottom', 'left' ].map( function ( side ) {
				var part = value[ side ];
				return ( '' === part || null === part || undefined === part ) ? '0' : String( part );
			} );
			var allEqual = sides.every( function ( s ) { return s === sides[ 0 ]; } );
			return allEqual ? sides[ 0 ] + unit : sides.join( ' ' ) + unit;
		}

		if ( 'slider' === definition.type ) {
			return String( value.size ) + ( value.unit || '' );
		}

		if ( 'number' === definition.type ) {
			return String( value ) + ( definition.suffix || '' );
		}

		return String( value );
	}

	/**
	 * One field's contribution to its group's summary. A responsive
	 * field shows the first breakpoint that carries a value, with a +
	 * when others do too — enough to tell that a breakpoint override
	 * exists without opening the group to find it.
	 */
	function fieldToken( definition, value ) {
		if ( ! definition.responsive ) {
			return leafToken( definition, value );
		}

		var set = DEVICES.filter( function ( device ) {
			return value && ! leafIsEmpty( definition, value[ device ] );
		} );
		if ( ! set.length ) { return ''; }

		var token = leafToken( definition, value[ set[ 0 ] ] );
		return set.length > 1 ? token + '+' : token;
	}

	/* ── The resolver, ported ─────────────────────────────────────────
	 *
	 * The live preview writes custom properties straight onto the wrapper
	 * inside the iframe, which means the browser has to be told the same
	 * thing PHP would have written at render time. That is a second
	 * implementation of KDNA_Tables_Style_Resolver, and second
	 * implementations drift.
	 *
	 * Two things hold it in place. It is driven by the same schema object
	 * the PHP reads, so anything expressible in a schema entry — type,
	 * units, responsive, css_var, value_map, default — needs no code here
	 * at all. And the pair is checked by an executable parity test that
	 * runs both over the same value sets and compares the property maps,
	 * so a divergence is a failing test rather than a preview that
	 * quietly lies.
	 *
	 * Every function below is a transliteration of its PHP counterpart,
	 * named the same, in the same order.
	 */

	function isNumeric( value ) {
		if ( 'number' === typeof value ) { return isFinite( value ); }
		if ( 'string' !== typeof value ) { return false; }
		return /^\s*[+-]?(\d+(\.\d*)?|\.\d+)([eE][+-]?\d+)?\s*$/.test( value );
	}

	/** KDNA_Tables_Style_Resolver::is_inherit() */
	function isInherit( value ) {
		if ( null === value || undefined === value ) { return true; }

		if ( 'string' === typeof value ) {
			var trimmed = value.trim();
			return '' === trimmed || 'inherit' === trimmed.toLowerCase();
		}

		if ( 'object' === typeof value ) {
			var keys = Object.keys( value );
			if ( ! keys.length ) { return true; }
			if ( value.inherit ) { return true; }
			return ! keys.some( function ( k ) {
				// unit and the UI-only linked flag alone are not a value.
				if ( 'unit' === k || 'linked' === k ) { return false; }
				return ! isInherit( value[ k ] );
			} );
		}

		// Numbers, including 0, are values. Booleans are not.
		return 'boolean' === typeof value;
	}

	/** KDNA_Tables_Style_Resolver::number() */
	function cssNumber( value ) {
		var f = parseFloat( value );
		if ( ! isFinite( f ) ) { return '0'; }
		// PHP casts an integral float to int; otherwise it formats to four
		// decimal places and trims the trailing zeros, which is what
		// rounding to 1e-4 and stringifying gives here.
		if ( Math.floor( f ) === f ) { return String( f === 0 ? 0 : f ); }
		return String( Math.round( f * 10000 ) / 10000 );
	}

	/** KDNA_Tables_Style_Resolver::resolve_unit() */
	function resolveUnit( definition, value ) {
		var units = ( definition && definition.units ) || [];
		var unit = ( value && 'object' === typeof value && undefined !== value.unit )
			? String( value.unit )
			: null;

		if ( null !== unit && -1 !== units.indexOf( unit ) ) { return unit; }
		return units.length ? String( units[ 0 ] ) : '';
	}

	/** KDNA_Tables_Style_Resolver::dimensions_value() */
	function dimensionsValue( definition, value ) {
		if ( ! value || 'object' !== typeof value ) { return ''; }

		var unit = resolveUnit( definition, value );
		var sides = [];
		var any = false;

		SIDES.forEach( function ( side ) {
			var part = undefined === value[ side ] ? '' : value[ side ];
			if ( 'string' === typeof part ) { part = part.trim(); }
			if ( '' === part || null === part || ! isNumeric( part ) ) {
				// A side left blank counts as 0, so a partially filled
				// control still produces valid CSS.
				sides.push( '0' + unit );
				return;
			}
			any = true;
			sides.push( cssNumber( part ) + unit );
		} );

		return any ? sides.join( ' ' ) : '';
	}

	/** KDNA_Tables_Style_Resolver::slider_value() */
	function sliderValue( definition, value ) {
		if ( isNumeric( value ) ) { value = { size: value }; }
		if ( ! value || 'object' !== typeof value || undefined === value.size ) { return ''; }

		var size = value.size;
		if ( 'string' === typeof size ) { size = size.trim(); }
		if ( '' === size || null === size || ! isNumeric( size ) ) { return ''; }

		return cssNumber( size ) + resolveUnit( definition, value );
	}

	/** KDNA_Tables_Style_Resolver::css_value() */
	function cssValue( definition, value ) {
		if ( isInherit( value ) ) { return ''; }

		switch ( definition.type ) {
			case 'dimensions':
				return dimensionsValue( definition, value );

			case 'slider':
				return sliderValue( definition, value );

			case 'number':
				if ( ! isNumeric( value ) ) { return ''; }
				return cssNumber( value ) + ( definition.suffix || '' );

			case 'select':
				var key = ( null === value || 'object' === typeof value ) ? '' : String( value );
				if ( '' === key ) { return ''; }
				// A select can store a key that is not the CSS value, e.g. an
				// alignment that resolves to a margin shorthand.
				if ( definition.value_map && undefined !== definition.value_map[ key ] ) {
					return String( definition.value_map[ key ] );
				}
				return key;

			default:
				return ( null === value || 'object' === typeof value ) ? '' : String( value ).trim();
		}
	}

	/** KDNA_Tables_Style_Resolver::properties_for() */
	function propertiesFor( definition, value, out ) {
		if ( isInherit( value ) ) { return out; }

		if ( isGroup( definition ) ) {
			if ( ! value || 'object' !== typeof value ) { return out; }
			Object.keys( definition.fields || {} ).forEach( function ( fieldKey ) {
				if ( ! Object.prototype.hasOwnProperty.call( value, fieldKey ) ) { return; }
				propertiesFor( definition.fields[ fieldKey ], value[ fieldKey ], out );
			} );
			return out;
		}

		var cssVar = definition.css_var || '';
		if ( '' === cssVar ) { return out; }

		if ( ! definition.responsive ) {
			var flat = cssValue( definition, value );
			if ( '' !== flat ) { out[ cssVar ] = flat; }
			return out;
		}

		var byDevice = ( value && 'object' === typeof value ) ? value : { desktop: value };
		DEVICES.forEach( function ( device ) {
			if ( ! Object.prototype.hasOwnProperty.call( byDevice, device ) ) { return; }
			var css = cssValue( definition, byDevice[ device ] );
			// Absent, not empty: the stylesheet's var() fallback chain only
			// falls through on an undefined property.
			if ( '' === css ) { return; }
			out[ 'desktop' === device ? cssVar : cssVar + '-' + device ] = css;
		} );

		return out;
	}

	/** KDNA_Tables_Style_Schema::default_value_for() */
	function defaultValueFor( control ) {
		if ( isGroup( control ) ) {
			var value = {};
			Object.keys( control.fields || {} ).forEach( function ( fieldKey ) {
				var fieldDefault = defaultValueFor( control.fields[ fieldKey ] );
				if ( null !== fieldDefault ) { value[ fieldKey ] = fieldDefault; }
			} );
			return Object.keys( value ).length ? value : null;
		}

		if ( undefined === control.default || null === control.default ) { return null; }
		if ( control.responsive ) { return { desktop: control.default }; }
		return control.default;
	}

	/** KDNA_Tables_Style_Resolver::merge_value() */
	function mergeValue( current, incoming, definition ) {
		if ( isInherit( incoming ) ) { return null; }

		if ( isGroup( definition ) ) {
			if ( ! incoming || 'object' !== typeof incoming ) { return null; }
			var merged = ( current && 'object' === typeof current ) ? Object.assign( {}, current ) : {};
			Object.keys( definition.fields || {} ).forEach( function ( fieldKey ) {
				if ( ! Object.prototype.hasOwnProperty.call( incoming, fieldKey ) ) { return; }
				var fieldMerged = mergeValue(
					undefined === merged[ fieldKey ] ? null : merged[ fieldKey ],
					incoming[ fieldKey ],
					definition.fields[ fieldKey ]
				);
				if ( null === fieldMerged ) { return; }
				merged[ fieldKey ] = fieldMerged;
			} );
			return Object.keys( merged ).length ? merged : null;
		}

		if ( definition.responsive ) {
			if ( ! incoming || 'object' !== typeof incoming ) { incoming = { desktop: incoming }; }
			var byDevice = ( current && 'object' === typeof current ) ? Object.assign( {}, current ) : {};
			DEVICES.forEach( function ( device ) {
				if ( ! Object.prototype.hasOwnProperty.call( incoming, device ) ) { return; }
				// Skipped, not cleared: inherit means "let the layer beneath
				// show through" at every level.
				if ( isInherit( incoming[ device ] ) ) { return; }
				byDevice[ device ] = incoming[ device ];
			} );
			return Object.keys( byDevice ).length ? byDevice : null;
		}

		return incoming;
	}

	/** KDNA_Tables_Style_Resolver::sanitize_css_value() */
	function sanitizeCssValue( value ) {
		if ( null === value || 'object' === typeof value ) { return ''; }

		var out = String( value ).trim();
		if ( '' === out ) { return ''; }

		out = out.replace( /[\x00-\x1F\x7F]/g, '' );
		if ( '' === out ) { return ''; }
		if ( out.length > 200 ) { return ''; }
		if ( /[;{}<>"'\\]|\/\*|\*\//.test( out ) ) { return ''; }
		if ( /(url|expression|image-set|-moz-binding|javascript|@import)\s*[:(]/i.test( out ) ) { return ''; }

		return out;
	}

	/**
	 * KDNA_Tables_Style_Resolver::resolve(), for one layer over the schema
	 * defaults — which is exactly what the global settings page edits.
	 *
	 * @param {Object} schema The control schema.
	 * @param {Object} layer  Sparse control key => value, as saved.
	 * @return {Object} Custom property name => CSS value.
	 */
	function resolveProperties( schema, layer ) {
		var values = {};
		Object.keys( schema ).forEach( function ( key ) {
			var fallback = defaultValueFor( schema[ key ] );
			if ( null !== fallback ) { values[ key ] = fallback; }
		} );

		Object.keys( layer || {} ).forEach( function ( key ) {
			if ( ! schema[ key ] ) { return; }
			var merged = mergeValue(
				undefined === values[ key ] ? null : values[ key ],
				layer[ key ],
				schema[ key ]
			);
			if ( null === merged ) { return; }
			values[ key ] = merged;
		} );

		var raw = {};
		Object.keys( schema ).forEach( function ( key ) {
			if ( ! Object.prototype.hasOwnProperty.call( values, key ) ) { return; }
			propertiesFor( schema[ key ], values[ key ], raw );
		} );

		// to_style_attribute()'s output check, applied here for the same
		// reason: the preview should show what the front end would render,
		// including a value the front end would drop.
		var properties = {};
		Object.keys( raw ).forEach( function ( name ) {
			if ( ! /^--[A-Za-z0-9_-]+$/.test( name ) ) { return; }
			var clean = sanitizeCssValue( raw[ name ] );
			if ( '' === clean ) { return; }
			properties[ name ] = clean;
		} );

		return properties;
	}

	/* ── Component ────────────────────────────────────────────────── */

	function kdnaTablesStyleAdmin() {
		var seed = boot();

		return {
			schema: seed.schema,
			strings: seed.strings || {},
			section: Object.keys( seed.sections || {} )[ 0 ] || 'wrapper',
			context: seed.context || 'global',
			values: {},
			device: {},
			open: {},
			/*
			 * Which controls the user has taken off inherit. Mostly this
			 * tracks hasValue, but not always: overriding a control whose
			 * inherited value is itself empty has to leave the inputs
			 * showing even though nothing is stored yet, or Override would
			 * look like it did nothing.
			 */
			overridden: {},
			saving: false,
			dirty: false,
			status: '',
			statusClass: '',
			_baseline: '',
			/*
			 * What was last written into the iframe, so a repaint only
			 * touches what changed. It is reset whenever the document is
			 * rewritten: a fresh document has a fresh wrapper carrying
			 * nothing, and a stale record here would convince the repaint
			 * that every property was already in place and skip the lot.
			 */
			_painted: {},

			/* Live preview. Null on the per-table panel, which has no pane. */
			preview: seed.preview || null,
			previewTable: 0,
			previewDevice: 'desktop',
			previewMode: 'card_stack',
			previewBreakpoint: 'tablet_and_mobile',
			previewSticky: false,
			previewLoading: false,
			previewError: '',
			previewEmpty: false,

			init: function () {
				this.values = shapeAll( this.schema, seed.values );
				this.device = this.initialDevices();
				this.overridden = this.initialOverrides();
				this._baseline = JSON.stringify( collapseAll( this.schema, this.values ) );

				// A deep watch on the whole tree is what keeps the save bar
				// honest without wiring a handler onto every input.
				this.$watch( 'values', function () {
					this.dirty = JSON.stringify( collapseAll( this.schema, this.values ) ) !== this._baseline;
					if ( this.dirty ) { this.status = ''; this.statusClass = ''; }
					// Same watch drives the preview: every edit repaints, and
					// repainting is a few dozen setProperty calls on one
					// element, with no fetch and no reflow of the markup.
					this.paintPreview();
				}.bind( this ) );

				if ( this.preview ) {
					this.previewTable = this.preview.tableId;
					this.$nextTick( function () { this.loadPreview(); }.bind( this ) );
				}
			},

			/* ── Live preview ────────────────────────────────────────
			 * The pane is an iframe with no src, so its document is
			 * about:blank and same-origin: contentDocument is reachable,
			 * and the whole update path is DOM writes into it.
			 *
			 * It has to be an iframe rather than an inline block because
			 * the responsive layouts key off viewport media queries. Only
			 * a real viewport of 390px makes the mobile query fire; an
			 * inline preview would need those rules restated as container
			 * queries, which is a second copy of the breakpoint logic to
			 * keep in step with the first.
			 */

			previewFrame: function () {
				return this.$refs ? this.$refs.previewFrame : null;
			},

			previewDoc: function () {
				var frame = this.previewFrame();
				try {
					return frame ? frame.contentDocument : null;
				} catch ( e ) {
					return null;
				}
			},

			previewWidth: function () {
				var widths = ( this.preview && this.preview.widths ) || {};
				return widths[ this.previewDevice ] || 1200;
			},

			setPreviewDevice: function ( key ) {
				this.previewDevice = key;
				// A narrower viewport is a taller table, sometimes by a lot:
				// card stack turns four rows into four cards.
				this.fitPreview();
			},

			/**
			 * Match the frame's height to its content, so the preview is not
			 * a letterbox with the caption cut off. Bounded at both ends: a
			 * tiny table should still look like a preview pane, and a
			 * hundred-row one should not push the controls off the screen.
			 */
			fitPreview: function () {
				var self = this;
				window.requestAnimationFrame( function () {
					var frame = self.previewFrame();
					var doc = self.previewDoc();
					if ( ! frame || ! doc || ! doc.body ) { return; }

					/*
					 * The body, not the documentElement. The root element's
					 * scrollHeight is never less than the viewport, which is
					 * the height we are about to set — so measuring it would
					 * ratchet: the frame could grow but never shrink again.
					 */
					var height = doc.body.scrollHeight;
					frame.style.height = Math.min( 900, Math.max( 220, height + 4 ) ) + 'px';
				} );
			},

			/** The chosen table, for the overrides notice. */
			previewTableInfo: function () {
				var id = parseInt( this.previewTable, 10 );
				return ( ( this.preview && this.preview.tables ) || [] ).filter( function ( t ) {
					return t.id === id;
				} )[ 0 ] || null;
			},

			previewHasOverrides: function () {
				var info = this.previewTableInfo();
				return !! ( info && info.hasOverrides );
			},

			/**
			 * The iframe's document shell: the front-end stylesheets and an
			 * empty root to drop markup into.
			 *
			 * Written once and then left alone. Rewriting it per fetch would
			 * throw away the loaded stylesheets and re-request them, and the
			 * new document would paint the table unstyled until they came
			 * back — a flash on every table change, for nothing. Swapping
			 * the markup inside the root has neither problem.
			 */
			ensurePreviewShell: function () {
				var doc = this.previewDoc();
				if ( ! doc ) { return null; }
				if ( doc.getElementById( 'kdna-preview-root' ) ) { return doc; }

				var links = ( this.preview.css || [] ).map( function ( href ) {
					return '<link rel="stylesheet" href="' + href.replace( /"/g, '&quot;' ) + '">';
				} ).join( '' );

				doc.open();
				doc.write(
					'<!doctype html><html><head><meta charset="utf-8">' +
					'<meta name="viewport" content="width=device-width, initial-scale=1">' +
					links +
					'<style>html,body{margin:0;padding:16px;background:#fff;' +
					'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;}</style>' +
					'</head><body><div id="kdna-preview-root"></div></body></html>'
				);
				doc.close();

				return doc;
			},

			previewWrapper: function () {
				var doc = this.previewDoc();
				return doc ? doc.querySelector( '.kdna-table__wrapper' ) : null;
			},

			/**
			 * Fetch the markup. Only the table and the sticky toggle change
			 * it: sticky wraps the table in a scroll container, which is
			 * structure rather than style. Mode and breakpoint are wrapper
			 * data attributes, so they are written in place like the
			 * variables are.
			 */
			loadPreview: function () {
				if ( ! this.preview ) { return; }

				var id = parseInt( this.previewTable, 10 );
				if ( ! id ) { return; }

				var self = this;
				var url = this.preview.restUrl + id + ( this.previewSticky ? '?sticky=1' : '' );

				this.previewLoading = true;
				this.previewError = '';

				window.fetch( url, {
					method: 'GET',
					credentials: 'same-origin',
					headers: { 'X-WP-Nonce': seed.nonce }
				} ).then( function ( response ) {
					return response.json().then( function ( body ) {
						return { ok: response.ok, body: body };
					} );
				} ).then( function ( result ) {
					self.previewLoading = false;

					if ( ! result.ok || ! result.body || undefined === result.body.html ) {
						self.previewError = ( result.body && result.body.message )
							? result.body.message
							: ( self.strings.previewFailed || 'Could not load the preview.' );
						return;
					}

					self.previewEmpty = !! result.body.empty;

					var doc = self.ensurePreviewShell();
					if ( ! doc ) { return; }

					var root = doc.getElementById( 'kdna-preview-root' );
					// The markup is our own render templates' output, fetched
					// from an authenticated route on this origin, and it is
					// exactly what the front end would print.
					root.innerHTML = result.body.html;

					// A new wrapper element, carrying nothing. Without this
					// the repaint would compare against what it wrote to the
					// PREVIOUS wrapper, conclude everything was already in
					// place, and leave the new one bare.
					self._painted = {};
					self.paintPreview();
				} ).catch( function () {
					self.previewLoading = false;
					self.previewError = self.strings.previewFailed || 'Could not load the preview.';
				} );
			},

			/**
			 * Push the current form state into the iframe: the resolved
			 * custom properties, and the two layout attributes.
			 *
			 * Properties that resolve to nothing are REMOVED rather than
			 * set empty, for the same reason the render path omits them —
			 * the stylesheet's var() fallback chains only fall through on a
			 * property that is not there.
			 */
			paintPreview: function () {
				if ( ! this.preview ) { return; }

				var wrapper = this.previewWrapper();
				if ( ! wrapper ) { return; }

				var properties = this.previewProperties();
				var previous = this._painted || {};

				Object.keys( previous ).forEach( function ( name ) {
					if ( ! Object.prototype.hasOwnProperty.call( properties, name ) ) {
						wrapper.style.removeProperty( name );
					}
				} );

				Object.keys( properties ).forEach( function ( name ) {
					if ( previous[ name ] !== properties[ name ] ) {
						wrapper.style.setProperty( name, properties[ name ] );
					}
				} );

				this._painted = properties;

				wrapper.setAttribute( 'data-responsive-mode', this.previewMode );
				wrapper.setAttribute( 'data-responsive-breakpoint', this.previewBreakpoint );

				this.fitPreview();
			},

			/** What the preview is about to write, for the tests. */
			previewProperties: function () {
				return resolveProperties( this.schema, collapseAll( this.schema, this.values ) );
			},

			/**
			 * Every responsive slot starts on desktop. The keys match the
			 * switcher markup: the control key, or control.field inside a
			 * group.
			 */
			initialDevices: function () {
				var map = {};
				var schema = this.schema;
				Object.keys( schema ).forEach( function ( key ) {
					var definition = schema[ key ];
					if ( isGroup( definition ) ) {
						Object.keys( definition.fields || {} ).forEach( function ( fieldKey ) {
							if ( definition.fields[ fieldKey ].responsive ) {
								map[ key + '.' + fieldKey ] = 'desktop';
							}
						} );
						return;
					}
					if ( definition.responsive ) { map[ key ] = 'desktop'; }
				} );
				return map;
			},

			/* ── Inherit and override, per-table panel only ──────────
			 * On the global page every control is simply set or unset.
			 * On a table each control is additionally inheriting or
			 * overriding, and the two are not the same question: an
			 * override can hold a value, or can be an empty control the
			 * user has just taken off inherit and not yet filled in.
			 */

			isTable: function () {
				return 'table' === this.context;
			},

			slotKey: function ( key, fieldKey ) {
				return fieldKey ? key + '.' + fieldKey : key;
			},

			/** Everything already stored counts as overridden on load. */
			initialOverrides: function () {
				var map = {};
				var self = this;
				Object.keys( this.schema ).forEach( function ( key ) {
					var definition = self.schema[ key ];
					if ( isGroup( definition ) ) {
						Object.keys( definition.fields || {} ).forEach( function ( fieldKey ) {
							if ( self.hasValue( key, fieldKey ) ) { map[ key + '.' + fieldKey ] = true; }
						} );
						return;
					}
					if ( self.hasValue( key, '' ) ) { map[ key ] = true; }
				} );
				return map;
			},

			isOverridden: function ( key, fieldKey ) {
				if ( ! this.isTable() ) { return true; }
				return !! this.overridden[ this.slotKey( key, fieldKey ) ] || this.hasValue( key, fieldKey );
			},

			/**
			 * Take a control off inherit, seeded with the value it was
			 * inheriting — so the user starts from what they could see,
			 * not from blank.
			 */
			override: function ( key, fieldKey ) {
				var definition = this.definitionFor( key, fieldKey );
				if ( ! definition ) { return; }

				var source = ( seed.inherited || {} )[ key ];
				if ( fieldKey ) { source = source ? source[ fieldKey ] : undefined; }

				var shaped = shapeControl( definition, source );
				if ( fieldKey ) {
					this.values[ key ][ fieldKey ] = shaped;
				} else {
					this.values[ key ] = shaped;
				}

				this.overridden[ this.slotKey( key, fieldKey ) ] = true;
			},

			/** Drop the override and let the global show through again. */
			revert: function ( key, fieldKey ) {
				this.resetControl( key, fieldKey );
				delete this.overridden[ this.slotKey( key, fieldKey ) ];
			},

			/** What an inherited control is inheriting, for the greyed row. */
			inheritedLabel: function ( key, fieldKey ) {
				var definition = this.definitionFor( key, fieldKey );
				if ( ! definition ) { return ''; }

				var source = ( seed.inherited || {} )[ key ];
				if ( fieldKey ) { source = source ? source[ fieldKey ] : undefined; }

				if ( isGroup( definition ) ) {
					var tokens = [];
					Object.keys( definition.fields || {} ).forEach( function ( f ) {
						var token = fieldToken( definition.fields[ f ], source && source[ f ] );
						if ( token ) { tokens.push( token ); }
					} );
					if ( tokens.length ) { return tokens.join( ' · ' ); }
				} else {
					var token = fieldToken( definition, source );
					if ( token ) { return token; }
				}

				return this.strings.default || 'the plugin default';
			},

			/* ── Section and whole-table resets ─────────────────────── */

			sectionHasOverrides: function ( section ) {
				var self = this;
				return Object.keys( this.schema ).some( function ( key ) {
					if ( self.schema[ key ].section !== section ) { return false; }
					return self.isOverridden( key, '' ) ||
						Object.keys( self.schema[ key ].fields || {} ).some( function ( f ) {
							return self.isOverridden( key, f );
						} );
				} );
			},

			resetSection: function ( section ) {
				var self = this;
				Object.keys( this.schema ).forEach( function ( key ) {
					if ( self.schema[ key ].section !== section ) { return; }
					self.revert( key, '' );
					Object.keys( self.schema[ key ].fields || {} ).forEach( function ( f ) {
						self.revert( key, f );
					} );
				} );
			},

			anyOverrides: function () {
				var self = this;
				return Object.keys( this.schema ).some( function ( key ) {
					return self.isOverridden( key, '' ) ||
						Object.keys( self.schema[ key ].fields || {} ).some( function ( f ) {
							return self.isOverridden( key, f );
						} );
				} );
			},

			/**
			 * Drop every override on the table. Confirmed first: it is the
			 * one action on this panel that another click cannot undo.
			 */
			resetAll: function () {
				var message = this.strings.confirm ||
					'Drop every style override on this table?';
				if ( ! window.confirm( message ) ) { return; }

				this.values = shapeAll( this.schema, {} );
				this.overridden = {};
			},

			/* ── Groups ──────────────────────────────────────────────
			 * Groups start closed. A section with three groups in it is
			 * seventeen fields open and three rows closed, and all but a
			 * couple of those fields are normally inherit — so closed is
			 * the state that keeps the panel readable, and the summary
			 * is what makes closed safe.
			 */

			isOpen: function ( key ) {
				return !! this.open[ key ];
			},

			toggleGroup: function ( key ) {
				this.open[ key ] = ! this.open[ key ];
			},

			/**
			 * What a closed group shows: every field that holds a value,
			 * as the value itself. Nothing set reads as Inherit, which is
			 * the truth about what the group contributes.
			 */
			groupSummary: function ( key ) {
				var definition = this.schema[ key ];
				if ( ! definition || ! definition.fields ) { return ''; }

				var values = this.values[ key ] || {};
				var tokens = [];

				Object.keys( definition.fields ).forEach( function ( fieldKey ) {
					var token = fieldToken( definition.fields[ fieldKey ], values[ fieldKey ] );
					if ( token ) { tokens.push( token ); }
				} );

				if ( ! tokens.length ) {
					return this.strings.inherit || 'Inherit';
				}
				if ( tokens.length > 4 ) {
					return tokens.slice( 0, 4 ).join( ' · ' ) + ' …';
				}
				return tokens.join( ' · ' );
			},

			/* ── Reaching a leaf ─────────────────────────────────────
			 * definitionFor and holderFor are the only two places that
			 * know how state is nested. Everything else goes through
			 * them.
			 */

			definitionFor: function ( key, fieldKey ) {
				var definition = this.schema[ key ];
				if ( ! definition ) { return null; }
				return fieldKey ? ( definition.fields || {} )[ fieldKey ] || null : definition;
			},

			/** [ object holding the leaf, property name ] or null. */
			holderFor: function ( key, fieldKey, device ) {
				var container = this.values[ key ];
				if ( undefined === container ) { return null; }

				if ( fieldKey ) {
					if ( ! container[ fieldKey ] ) { return null; }
					return device
						? [ container[ fieldKey ], device ]
						: [ container, fieldKey ];
				}

				return device ? [ container, device ] : [ this.values, key ];
			},

			leaf: function ( key, fieldKey, device ) {
				var holder = this.holderFor( key, fieldKey, device );
				return holder ? holder[ 0 ][ holder[ 1 ] ] : undefined;
			},

			setLeaf: function ( key, fieldKey, device, value ) {
				var holder = this.holderFor( key, fieldKey, device );
				if ( holder ) { holder[ 0 ][ holder[ 1 ] ] = value; }
			},

			/* ── Emptiness, for the clear and reset affordances ────── */

			/** Whether this breakpoint (or this flat control) holds anything. */
			hasDeviceValue: function ( key, fieldKey, device ) {
				var definition = this.definitionFor( key, fieldKey );
				if ( ! definition ) { return false; }
				return ! leafIsEmpty( definition, this.leaf( key, fieldKey, device ) );
			},

			/** Whether the control holds anything at any breakpoint. */
			hasValue: function ( key, fieldKey ) {
				var definition = this.definitionFor( key, fieldKey );
				if ( ! definition ) { return false; }
				var value = fieldKey ? ( this.values[ key ] || {} )[ fieldKey ] : this.values[ key ];
				return undefined !== collapseControl( definition, value );
			},

			/** Clear one breakpoint, or a flat control. */
			clearLeaf: function ( key, fieldKey, device ) {
				var definition = this.definitionFor( key, fieldKey );
				if ( ! definition ) { return; }
				this.setLeaf( key, fieldKey, device, emptyLeaf( definition ) );
			},

			/** Clear the control at every breakpoint, back to inherit. */
			resetControl: function ( key, fieldKey ) {
				var definition = this.definitionFor( key, fieldKey );
				if ( ! definition ) { return; }
				var blank = shapeControl( definition, null );
				if ( fieldKey ) {
					this.values[ key ][ fieldKey ] = blank;
					return;
				}
				this.values[ key ] = blank;
			},

			/* ── Type-specific bindings ──────────────────────────────── */

			colorSwatch: function ( key, fieldKey, device ) {
				return toSwatch( this.leaf( key, fieldKey, device ) );
			},

			/** Where an unset range parks its thumb: at its minimum. */
			sliderPosition: function ( key, fieldKey, device, min ) {
				var value = this.leaf( key, fieldKey, device );
				var size = value && 'object' === typeof value ? value.size : value;
				return ( '' === size || null === size || undefined === size ) ? min : size;
			},

			setSize: function ( key, fieldKey, device, size ) {
				var value = this.leaf( key, fieldKey, device );
				if ( value && 'object' === typeof value ) { value.size = size; }
			},

			isLinked: function ( key, fieldKey, device ) {
				var value = this.leaf( key, fieldKey, device );
				return !! ( value && 'object' === typeof value && value.linked );
			},

			toggleLink: function ( key, fieldKey, device ) {
				var value = this.leaf( key, fieldKey, device );
				if ( ! value || 'object' !== typeof value ) { return; }
				value.linked = ! value.linked;
				if ( value.linked ) { this.syncLinked( key, fieldKey, device, value.top ); }
			},

			/**
			 * Copy the edited side across when the sides are linked.
			 *
			 * The value comes from the event target rather than from
			 * state, so this does not depend on whether Alpine's own
			 * x-model listener has run first.
			 */
			syncLinked: function ( key, fieldKey, device, edited ) {
				var value = this.leaf( key, fieldKey, device );
				if ( ! value || 'object' !== typeof value || ! value.linked ) { return; }
				SIDES.forEach( function ( side ) { value[ side ] = edited; } );
			},

			/* ── Saving ──────────────────────────────────────────────── */

			save: function () {
				if ( this.saving ) { return; }

				var payload = collapseAll( this.schema, this.values );
				var sent = JSON.stringify( payload );
				var self = this;

				this.saving = true;
				this.status = this.strings.saving || 'Saving…';
				this.statusClass = '';

				window.fetch( seed.restUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': seed.nonce
					},
					body: JSON.stringify( { values: payload } )
				} ).then( function ( response ) {
					return response.json().then( function ( body ) {
						return { ok: response.ok, body: body };
					} );
				} ).then( function ( result ) {
					self.saving = false;

					if ( ! result.ok || ! result.body || ! result.body.saved ) {
						self.status = ( result.body && result.body.message )
							? result.body.message
							: ( self.strings.failed || 'Could not save' );
						self.statusClass = 'is-error';
						return;
					}

					// Re-seed from what was actually stored rather than from
					// what was typed, so anything the sanitiser rejected
					// disappears from the form instead of sitting there
					// looking saved.
					var stored = result.body.values || {};
					self.values = shapeAll( self.schema, stored );
					self._baseline = JSON.stringify( collapseAll( self.schema, self.values ) );
					self.dirty = false;

					var kept = JSON.stringify( stored );
					self.status = ( kept === sent )
						? ( self.strings.saved || 'Saved' )
						: ( self.strings.discarded || 'Some values were not valid and were discarded.' );
					self.statusClass = ( kept === sent ) ? 'is-ok' : 'is-warning';
				} ).catch( function () {
					self.saving = false;
					self.status = self.strings.failed || 'Could not save';
					self.statusClass = 'is-error';
				} );
			}
		};
	}

	document.addEventListener( 'alpine:init', function () {
		window.Alpine.data( 'kdnaTablesStyleAdmin', kdnaTablesStyleAdmin );
	} );

	window.kdnaTablesStyleAdmin = kdnaTablesStyleAdmin;

	// Exposed for the round-trip tests, and for Stages 6 and 8 to reuse
	// rather than reimplement.
	window.kdnaTablesStyleAdminInternals = {
		shapeAll: shapeAll,
		collapseAll: collapseAll,
		shapeControl: shapeControl,
		collapseControl: collapseControl,
		toSwatch: toSwatch,
		leafToken: leafToken,
		fieldToken: fieldToken,
		// Exposed for the Stage 9 parity test, which runs this against the
		// PHP resolver over the same value sets.
		resolveProperties: resolveProperties
	};
}() );
