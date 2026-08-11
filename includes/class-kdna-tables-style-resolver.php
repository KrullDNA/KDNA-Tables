<?php
/**
 * Style resolver: schema defaults + global option + per-table overrides,
 * flattened into CSS custom properties for the wrapper's inline style
 * attribute.
 *
 * ── Resolution order ──────────────────────────────────────────────────
 *
 *   1. the schema default
 *   2. the global option kdna_tables_style_defaults
 *   3. the per-table post meta _kdna_table_style_overrides
 *
 * Later wins, and the merge happens at the LEAF, not at the control. A
 * global that sets a header font size and a per-table override that sets
 * only the mobile padding produce one result carrying both — replacing
 * whole controls would silently drop the global's other breakpoints and
 * a typography group's other fields.
 *
 * A value left in its inherit state is skipped entirely rather than
 * written as an empty value, so the layer beneath shows through. The
 * same rule is applied to the global layer as to the per-table one: an
 * unset global falls back to the schema default, which is the only
 * reading of "later wins" that leaves the schema default meaningful.
 *
 * ── Responsive properties ─────────────────────────────────────────────
 *
 * A responsive control emits up to three properties: the base name for
 * desktop, then the same name suffixed -tablet and -mobile. A breakpoint
 * with no value emits NOTHING — not an empty property — because the
 * stylesheet's fallback chain depends on the property being absent:
 *
 *   --_x: var(--x-tablet, var(--x, <fallback>));
 *
 * Writing '--x-tablet: ;' there would not be absent, and the chain would
 * resolve to an empty value instead of falling through.
 *
 * ── Why inline, and not a style block in wp_head ──────────────────────
 *
 * A shortcode inside a JetEngine repeater field is invisible to
 * has_shortcode(), which only reads post_content — exactly the case this
 * build exists to serve. Writing the resolved variables onto the wrapper
 * at render time works wherever the shortcode lands, cannot arrive after
 * the markup, and needs no page scanning.
 *
 * @package KDNA_Tables
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KDNA_Tables_Style_Resolver {

	/** Global defaults, one option holding the whole set. */
	const OPTION_KEY = 'kdna_tables_style_defaults';

	/** Per-table overrides. */
	const META_KEY = '_kdna_table_style_overrides';

	/**
	 * Longest custom property value accepted. Values are authored through
	 * the settings page, so this is a backstop against a hand-edited
	 * option rather than an expected limit.
	 */
	const MAX_VALUE_LENGTH = 200;

	/** Per-request memo, table id => properties. Stage 10 adds a transient. */
	private static $memo = array();

	/**
	 * Resolved CSS custom properties for a table.
	 *
	 * @param int $table_id Table post id, or 0 for the global result with
	 *                      no per-table overrides applied.
	 * @return array Custom property name => CSS value.
	 */
	public static function resolve( $table_id = 0 ) {
		$table_id = (int) $table_id;

		if ( isset( self::$memo[ $table_id ] ) ) {
			return self::$memo[ $table_id ];
		}

		$properties = self::flatten( self::resolve_values( $table_id ) );

		/**
		 * Filter the resolved custom properties before they are rendered.
		 *
		 * @param array $properties Property name => value.
		 * @param int   $table_id   Table post id, 0 for the global set.
		 */
		$properties = apply_filters( 'kdna_tables_style_properties', $properties, $table_id );

		self::$memo[ $table_id ] = $properties;

		return $properties;
	}

	/**
	 * The merged values behind resolve(), in storage shape rather than as
	 * CSS. The admin UI needs this to show what a per-table control is
	 * inheriting, so it is public.
	 *
	 * @param int $table_id Table post id, 0 to stop after the global layer.
	 * @return array Control key => value.
	 */
	public static function resolve_values( $table_id = 0 ) {
		$controls = KDNA_Tables_Style_Schema::get();
		$values   = KDNA_Tables_Style_Schema::get_defaults();

		foreach ( self::layers( (int) $table_id ) as $layer ) {
			if ( ! is_array( $layer ) ) {
				continue;
			}
			foreach ( $layer as $key => $incoming ) {
				// A key with no schema entry is discarded. Schema entries
				// are removed over time; stored values for them are not.
				if ( ! isset( $controls[ $key ] ) ) {
					continue;
				}
				$current = isset( $values[ $key ] ) ? $values[ $key ] : null;
				$merged  = self::merge_value( $current, $incoming, $controls[ $key ] );
				if ( null === $merged ) {
					continue;
				}
				$values[ $key ] = $merged;
			}
		}

		return $values;
	}

	/**
	 * Render properties as the value of an inline style attribute.
	 *
	 * Returns the attribute VALUE, without the style="" wrapper and
	 * without escaping: the caller is expected to pass it through
	 * esc_attr(). Accepts a table id as a convenience so a caller that
	 * only has an id does not have to resolve first.
	 *
	 * @param array|int $properties Resolved properties, or a table id.
	 * @return string e.g. '--kdna-table-header-bg: #000000; --kdna-table-header-color: #ffffff;'
	 */
	public static function to_style_attribute( $properties = array() ) {
		if ( ! is_array( $properties ) ) {
			$properties = self::resolve( (int) $properties );
		}

		$declarations = array();
		foreach ( $properties as $name => $value ) {
			$name  = self::sanitize_property_name( $name );
			$value = self::sanitize_css_value( $value );
			if ( '' === $name || '' === $value ) {
				continue;
			}
			$declarations[] = $name . ': ' . $value . ';';
		}

		return implode( ' ', $declarations );
	}

	/**
	 * Drop the memo. Called after a save, and by tests.
	 *
	 * @param int|null $table_id Table to forget, or null for all.
	 */
	public static function flush_cache( $table_id = null ) {
		if ( null === $table_id ) {
			self::$memo = array();
			return;
		}
		unset( self::$memo[ (int) $table_id ] );
	}

	/* ─── Merging ───────────────────────────────────────────────────── */

	/**
	 * The stored layers above the schema default, in application order.
	 */
	private static function layers( $table_id ) {
		$layers = array();

		$global = get_option( self::OPTION_KEY, array() );
		if ( is_array( $global ) ) {
			$layers[] = $global;
		}

		if ( $table_id > 0 ) {
			$overrides = get_post_meta( $table_id, self::META_KEY, true );
			if ( is_array( $overrides ) ) {
				$layers[] = $overrides;
			}
		}

		return $layers;
	}

	/**
	 * Merge one incoming value over the current one, leaf by leaf, using
	 * the control definition to know the shape. Returns null when the
	 * incoming value contributes nothing, so the caller can leave the
	 * current value untouched.
	 */
	private static function merge_value( $current, $incoming, array $definition ) {
		if ( self::is_inherit( $incoming ) ) {
			return null;
		}

		// Group control: recurse per field, so setting one field does not
		// wipe the others.
		if ( KDNA_Tables_Style_Schema::is_group_type( $definition['type'] ) ) {
			if ( ! is_array( $incoming ) ) {
				return null;
			}
			$merged = is_array( $current ) ? $current : array();
			foreach ( $definition['fields'] as $field_key => $field ) {
				if ( ! array_key_exists( $field_key, $incoming ) ) {
					continue;
				}
				$field_current = isset( $merged[ $field_key ] ) ? $merged[ $field_key ] : null;
				$field_merged  = self::merge_value( $field_current, $incoming[ $field_key ], $field );
				if ( null === $field_merged ) {
					continue;
				}
				$merged[ $field_key ] = $field_merged;
			}
			return empty( $merged ) ? null : $merged;
		}

		// Responsive control: recurse per breakpoint, so a per-table
		// mobile override does not discard the global's desktop value.
		if ( ! empty( $definition['responsive'] ) ) {
			if ( ! is_array( $incoming ) ) {
				// Tolerate a bare value written without a device map by
				// treating it as desktop.
				$incoming = array( 'desktop' => $incoming );
			}
			$merged = is_array( $current ) ? $current : array();
			foreach ( KDNA_Tables_Style_Schema::DEVICES as $device ) {
				if ( ! array_key_exists( $device, $incoming ) ) {
					continue;
				}
				if ( self::is_inherit( $incoming[ $device ] ) ) {
					// Skipped, not cleared. Inherit means "let the layer
					// beneath show through" at every level, so one
					// breakpoint left inherit keeps the global's value for
					// that breakpoint while its siblings still override.
					// The consequence is that a per-table override cannot
					// subtract a global value, only replace it — which is
					// what makes Stage 8's revert-to-global button the
					// single, predictable way back.
					continue;
				}
				$merged[ $device ] = $incoming[ $device ];
			}
			return empty( $merged ) ? null : $merged;
		}

		return $incoming;
	}

	/**
	 * Whether a stored value means "inherit", i.e. contributes nothing.
	 *
	 * Covers the states the admin UI can produce: never set (null), the
	 * literal marker, an emptied text or colour field, and a dimensions
	 * or slider value whose numeric parts were all cleared but whose unit
	 * remains.
	 */
	private static function is_inherit( $value ) {
		if ( null === $value ) {
			return true;
		}

		if ( is_string( $value ) ) {
			$value = trim( $value );
			return '' === $value || 'inherit' === strtolower( $value );
		}

		if ( is_array( $value ) ) {
			if ( empty( $value ) ) {
				return true;
			}
			if ( ! empty( $value['inherit'] ) ) {
				return true;
			}
			// 'unit' and the UI-only 'linked' flag alone are not a value.
			foreach ( $value as $key => $part ) {
				if ( 'unit' === $key || 'linked' === $key ) {
					continue;
				}
				if ( ! self::is_inherit( $part ) ) {
					return false;
				}
			}
			return true;
		}

		// Numbers, including 0, are values.
		return false;
	}

	/* ─── Flattening to CSS ─────────────────────────────────────────── */

	/**
	 * Turn merged values into custom property name => CSS value.
	 */
	private static function flatten( array $values ) {
		$properties = array();

		foreach ( KDNA_Tables_Style_Schema::get() as $key => $control ) {
			if ( ! array_key_exists( $key, $values ) ) {
				continue;
			}
			$properties = array_merge(
				$properties,
				self::properties_for( $control, $values[ $key ] )
			);
		}

		return $properties;
	}

	/**
	 * The properties one control contributes.
	 */
	private static function properties_for( array $definition, $value ) {
		if ( self::is_inherit( $value ) ) {
			return array();
		}

		if ( KDNA_Tables_Style_Schema::is_group_type( $definition['type'] ) ) {
			$properties = array();
			if ( ! is_array( $value ) ) {
				return $properties;
			}
			foreach ( $definition['fields'] as $field_key => $field ) {
				if ( ! array_key_exists( $field_key, $value ) ) {
					continue;
				}
				$properties = array_merge(
					$properties,
					self::properties_for( $field, $value[ $field_key ] )
				);
			}
			return $properties;
		}

		$css_var = isset( $definition['css_var'] ) ? $definition['css_var'] : '';
		if ( '' === $css_var || null === $css_var ) {
			return array();
		}

		if ( empty( $definition['responsive'] ) ) {
			$css = self::css_value( $definition, $value );
			return '' === $css ? array() : array( $css_var => $css );
		}

		$properties = array();
		$devices    = is_array( $value ) ? $value : array( 'desktop' => $value );

		foreach ( KDNA_Tables_Style_Schema::DEVICES as $device ) {
			if ( ! array_key_exists( $device, $devices ) ) {
				continue;
			}
			$css = self::css_value( $definition, $devices[ $device ] );
			if ( '' === $css ) {
				// Absent, not empty: the stylesheet's var() fallback chain
				// only falls through on an undefined property.
				continue;
			}
			$name = ( 'desktop' === $device ) ? $css_var : $css_var . '-' . $device;
			$properties[ $name ] = $css;
		}

		return $properties;
	}

	/**
	 * One stored value as a CSS value string. Returns '' when the value
	 * contributes nothing, which the caller reads as "omit the property".
	 */
	private static function css_value( array $definition, $value ) {
		if ( self::is_inherit( $value ) ) {
			return '';
		}

		$type = isset( $definition['type'] ) ? $definition['type'] : '';

		switch ( $type ) {
			case 'dimensions':
				return self::dimensions_value( $definition, $value );

			case 'slider':
				return self::slider_value( $definition, $value );

			case 'number':
				if ( ! is_numeric( $value ) ) {
					return '';
				}
				$suffix = isset( $definition['suffix'] ) ? (string) $definition['suffix'] : '';
				return self::number( $value ) . $suffix;

			case 'select':
				$value = is_scalar( $value ) ? (string) $value : '';
				if ( '' === $value ) {
					return '';
				}
				// A select can store a key that is not the CSS value, e.g.
				// an alignment that resolves to a margin shorthand.
				if ( isset( $definition['value_map'][ $value ] ) ) {
					return (string) $definition['value_map'][ $value ];
				}
				return $value;

			case 'color':
			default:
				return is_scalar( $value ) ? trim( (string) $value ) : '';
		}
	}

	/**
	 * Four sides plus a unit as a CSS shorthand. A side left blank counts
	 * as 0 so a partially filled control still produces valid CSS.
	 */
	private static function dimensions_value( array $definition, $value ) {
		if ( ! is_array( $value ) ) {
			return '';
		}

		$unit  = self::resolve_unit( $definition, $value );
		$sides = array();
		$any   = false;

		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			$part = isset( $value[ $side ] ) ? $value[ $side ] : '';
			if ( is_string( $part ) ) {
				$part = trim( $part );
			}
			if ( '' === $part || null === $part || ! is_numeric( $part ) ) {
				$sides[] = '0' . ( '' === $unit ? '' : $unit );
				continue;
			}
			$any     = true;
			$sides[] = self::number( $part ) . $unit;
		}

		return $any ? implode( ' ', $sides ) : '';
	}

	/**
	 * A slider's size plus unit. The empty unit is legitimate — a
	 * unitless line height, for one — so only the size may be missing.
	 */
	private static function slider_value( array $definition, $value ) {
		if ( is_numeric( $value ) ) {
			$value = array( 'size' => $value );
		}
		if ( ! is_array( $value ) || ! isset( $value['size'] ) ) {
			return '';
		}

		$size = $value['size'];
		if ( is_string( $size ) ) {
			$size = trim( $size );
		}
		if ( '' === $size || null === $size || ! is_numeric( $size ) ) {
			return '';
		}

		return self::number( $size ) . self::resolve_unit( $definition, $value );
	}

	/**
	 * The unit to use: the stored one when the schema allows it,
	 * otherwise the schema's first unit, otherwise none.
	 */
	private static function resolve_unit( array $definition, $value ) {
		$units = isset( $definition['units'] ) && is_array( $definition['units'] )
			? $definition['units']
			: array();

		$unit = isset( $value['unit'] ) ? (string) $value['unit'] : null;

		if ( null !== $unit && in_array( $unit, $units, true ) ) {
			return $unit;
		}

		return empty( $units ) ? '' : (string) $units[0];
	}

	/**
	 * Format a number without a trailing '.0' or locale decimal comma.
	 */
	private static function number( $value ) {
		$float = (float) $value;
		if ( (float) (int) $float === $float ) {
			return (string) (int) $float;
		}
		return rtrim( rtrim( number_format( $float, 4, '.', '' ), '0' ), '.' );
	}

	/* ─── Output hardening ──────────────────────────────────────────── */

	/**
	 * Property names are schema-authored, but the schema is filterable,
	 * so validate rather than trust.
	 */
	private static function sanitize_property_name( $name ) {
		$name = trim( (string) $name );
		return preg_match( '/^--[A-Za-z0-9_-]+$/', $name ) ? $name : '';
	}

	/**
	 * Keep a value safe to sit inside a style attribute.
	 *
	 * Stage 4 sanitises on save against the schema, but post meta can be
	 * written by anything with the capability, so the render path checks
	 * again rather than assuming. Anything that could close the
	 * declaration or the attribute, or fetch a remote resource, drops the
	 * property entirely rather than being escaped into something valid.
	 */
	private static function sanitize_css_value( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		// Strip control characters, including the newlines a pasted value
		// can carry.
		$value = preg_replace( '/[\x00-\x1F\x7F]/', '', $value );
		if ( null === $value || '' === $value ) {
			return '';
		}

		if ( strlen( $value ) > self::MAX_VALUE_LENGTH ) {
			return '';
		}

		// Attribute or declaration breakout, and comment syntax.
		if ( preg_match( '/[;{}<>"\'\\\\]|\/\*|\*\//', $value ) ) {
			return '';
		}

		// Remote fetches and legacy script vectors. Custom properties are
		// substituted verbatim into real declarations downstream, so a
		// url() here would become a live request.
		if ( preg_match( '/(url|expression|image-set|-moz-binding|javascript|@import)\s*[:(]/i', $value ) ) {
			return '';
		}

		return $value;
	}
}
