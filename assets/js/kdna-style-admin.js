/*
 * KDNA Tables, Shortcode Styles settings page.
 *
 * Reads its seed from window.KDNATablesStyles (emitted by
 * KDNA_Tables_Style_Admin) and saves through the kdna-tables/v1/styles
 * REST route.
 *
 * The component's one non-obvious job is shaping. Alpine's x-model needs
 * an assignable path: binding to values['header_padding']['mobile']['top']
 * throws if any link in that chain is undefined, and the stored option
 * is deliberately sparse — an unset control is absent, not present and
 * empty, because absent is what "inherit" means everywhere downstream.
 * So the seed is expanded into a full skeleton before Alpine binds, and
 * collapsed back to a sparse object on save. The empties never reach the
 * server, and the server drops any that do.
 *
 * State is keyed by control key throughout, matching the schema and the
 * stored option, so nothing needs renaming in either direction.
 */

( function () {
	'use strict';

	var DEVICES = [ 'desktop', 'tablet', 'mobile' ];

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
			return { top: '', right: '', bottom: '', left: '', unit: units[ 0 ] || '' };
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

	/* ── Component ────────────────────────────────────────────────── */

	function kdnaTablesStyleAdmin() {
		var seed = boot();

		return {
			schema: seed.schema,
			strings: seed.strings || {},
			section: Object.keys( seed.sections || {} )[ 0 ] || 'wrapper',
			values: {},
			saving: false,
			dirty: false,
			status: '',
			statusClass: '',
			_baseline: '',

			init: function () {
				this.values = shapeAll( this.schema, seed.values );
				this._baseline = JSON.stringify( collapseAll( this.schema, this.values ) );

				// Alpine's deep watcher on the whole tree is what makes the
				// save bar honest without wiring a handler onto sixty
				// inputs.
				this.$watch( 'values', function () {
					this.dirty = JSON.stringify( collapseAll( this.schema, this.values ) ) !== this._baseline;
					if ( this.dirty ) { this.status = ''; this.statusClass = ''; }
				}.bind( this ) );
			},

			/** Whether a control currently holds anything at all. */
			hasValue: function ( key ) {
				if ( ! this.schema[ key ] ) { return false; }
				return undefined !== collapseControl( this.schema[ key ], this.values[ key ] );
			},

			/** Clear a control back to inherit. */
			resetControl: function ( key ) {
				if ( ! this.schema[ key ] ) { return; }
				this.values[ key ] = shapeControl( this.schema[ key ], null );
			},

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

	// Exposed for the Stage 4 round-trip test, and for Stages 5 and 6 to
	// reuse rather than reimplement.
	window.kdnaTablesStyleAdminInternals = {
		shapeAll: shapeAll,
		collapseAll: collapseAll,
		shapeControl: shapeControl,
		collapseControl: collapseControl
	};
}() );
