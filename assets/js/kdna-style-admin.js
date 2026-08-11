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

	/* ── Component ────────────────────────────────────────────────── */

	function kdnaTablesStyleAdmin() {
		var seed = boot();

		return {
			schema: seed.schema,
			strings: seed.strings || {},
			section: Object.keys( seed.sections || {} )[ 0 ] || 'wrapper',
			values: {},
			device: {},
			open: {},
			saving: false,
			dirty: false,
			status: '',
			statusClass: '',
			_baseline: '',

			init: function () {
				this.values = shapeAll( this.schema, seed.values );
				this.device = this.initialDevices();
				this._baseline = JSON.stringify( collapseAll( this.schema, this.values ) );

				// A deep watch on the whole tree is what keeps the save bar
				// honest without wiring a handler onto every input.
				this.$watch( 'values', function () {
					this.dirty = JSON.stringify( collapseAll( this.schema, this.values ) ) !== this._baseline;
					if ( this.dirty ) { this.status = ''; this.statusClass = ''; }
				}.bind( this ) );
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
		fieldToken: fieldToken
	};
}() );
