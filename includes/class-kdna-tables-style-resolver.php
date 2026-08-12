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

	/**
	 * Generation counter, part of every transient key.
	 *
	 * Bumping this invalidates every cached table at once. The alternative
	 * — deleting the transients one by one when the global option changes
	 * — means either knowing every table id or running a LIKE query across
	 * the options table, and on a site with object caching enabled there
	 * are no rows to sweep at all. A counter in the key sidesteps both:
	 * the old entries are simply never asked for again, and expire on
	 * their own.
	 */
	const GENERATION_OPTION = 'kdna_tables_style_generation';

	/** Transient key prefix. */
	const CACHE_PREFIX = 'kdna_style_';

	/**
	 * How long a cached style attribute lives: a week, as a literal
	 * rather than as WEEK_IN_SECONDS, because a class constant defined
	 * from another constant is resolved when the class is loaded and
	 * would tie this file to WordPress having booted first.
	 *
	 * The TTL is a backstop, not the invalidation mechanism — saving
	 * moves the generation on immediately.
	 */
	const CACHE_TTL = 604800;

	/** Per-request memo, table id => properties. */
	private static $memo = array();

	/** Per-request memo of the rendered attribute, table id => string. */
	private static $attribute_memo = array();

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

	/* ─── Caching ───────────────────────────────────────────────────── */

	/**
	 * The style attribute for a table, cached.
	 *
	 * This is the render path's entry point, and the reason the cache
	 * exists: resolve() walks seventy-odd control definitions, merges
	 * three layers leaf by leaf and formats a hundred-odd CSS values, and
	 * it does that once per shortcode. A page listing eight tables pays
	 * for it eight times, for a result that only changes when someone
	 * saves.
	 *
	 * The string is cached rather than the array because the string is
	 * what the render needs: caching the array would still leave
	 * to_style_attribute()'s per-property validation to run every time.
	 *
	 * @param int $table_id Table post id, 0 for the global set.
	 * @return string Style attribute value, unescaped.
	 */
	public static function style_attribute_for( $table_id = 0 ) {
		$table_id = (int) $table_id;

		if ( isset( self::$attribute_memo[ $table_id ] ) ) {
			return self::$attribute_memo[ $table_id ];
		}

		$key    = self::cache_key( $table_id );
		$cached = get_transient( $key );

		// A cached empty string is legitimate — every control on inherit
		// with no schema defaults would produce one — so the miss is
		// tested against false, not against emptiness.
		if ( is_string( $cached ) ) {
			self::$attribute_memo[ $table_id ] = $cached;
			return $cached;
		}

		$attribute = self::to_style_attribute( self::resolve( $table_id ) );

		if ( self::caching_enabled() ) {
			set_transient( $key, $attribute, self::CACHE_TTL );
		}

		self::$attribute_memo[ $table_id ] = $attribute;

		return $attribute;
	}

	/**
	 * Whether resolved styles are cached at all.
	 *
	 * On by default. The filter is for debugging a site where the styles
	 * look stale, which is the one situation where being able to turn a
	 * cache off without editing code is worth having.
	 */
	private static function caching_enabled() {
		/**
		 * Filter whether resolved style attributes are cached.
		 *
		 * @param bool $enabled Default true.
		 */
		return (bool) apply_filters( 'kdna_tables_cache_styles', true );
	}

	/*
	 * ── Why the plugin version is part of the key ─────────────────────
	 *
	 * What is cached here is the RESOLVED output of the schema — every
	 * default baked into a string. So a plugin update that changes a
	 * default, adds a control or fixes which variable a control writes
	 * makes every cached string wrong, and nothing in the old key said
	 * so: the generation only moves when someone saves on the settings
	 * page, and the TTL is a week.
	 *
	 * That is why an upgraded site could show a live table that did not
	 * match its own preview. The preview resolves from the schema on
	 * every keystroke; the front end was serving a week-old string built
	 * by the schema of the version before. Both were "working".
	 *
	 * With the version in the key, an update misses every entry and each
	 * table rebuilds once on first view. The old entries are left to
	 * expire on their TTL rather than swept — a LIKE query over the
	 * options table is expensive, and an external object cache would not
	 * see it anyway.
	 */
	private static function cache_key( $table_id ) {
		$version = defined( 'KDNA_TABLES_VERSION' ) ? KDNA_TABLES_VERSION : '0';

		return self::CACHE_PREFIX
			. str_replace( '.', '', (string) $version ) . '_'
			. self::generation() . '_'
			. (int) $table_id;
	}

	private static function generation() {
		return (int) get_option( self::GENERATION_OPTION, 1 );
	}

	/**
	 * Invalidate everything, by moving the generation on.
	 *
	 * Called when the global defaults change, which can affect any table.
	 */
	public static function invalidate_all() {
		update_option( self::GENERATION_OPTION, self::generation() + 1 );
		self::flush_cache();
	}

	/**
	 * Invalidate one table, whose overrides changed.
	 *
	 * The global set is left alone: a per-table override cannot affect
	 * what any other table resolves to.
	 */
	public static function invalidate_table( $table_id ) {
		$table_id = (int) $table_id;
		delete_transient( self::cache_key( $table_id ) );
		self::flush_cache( $table_id );
	}

	/**
	 * Drop the per-request memo. Called after a save, and by tests.
	 *
	 * This is the in-request half only; the transients are addressed by
	 * invalidate_all() and invalidate_table().
	 *
	 * @param int|null $table_id Table to forget, or null for all.
	 */
	public static function flush_cache( $table_id = null ) {
		if ( null === $table_id ) {
			self::$memo           = array();
			self::$attribute_memo = array();
			return;
		}
		$table_id = (int) $table_id;
		unset( self::$memo[ $table_id ], self::$attribute_memo[ $table_id ] );
	}

	/**
	 * Watch for writes this plugin did not make.
	 *
	 * The settings page invalidates directly after saving, so these hooks
	 * are for everything else: WP-CLI, an importer, a migration, another
	 * plugin writing the option. Without them a site can be left rendering
	 * a stale cached string with no visible cause and no way to clear it
	 * from the admin.
	 */
	public static function register_invalidation() {
		add_action( 'update_option_' . self::OPTION_KEY, array( __CLASS__, 'invalidate_all' ) );
		add_action( 'add_option_' . self::OPTION_KEY, array( __CLASS__, 'invalidate_all' ) );
		add_action( 'delete_option_' . self::OPTION_KEY, array( __CLASS__, 'invalidate_all' ) );

		foreach ( array( 'updated_post_meta', 'added_post_meta', 'deleted_post_meta' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'on_meta_change' ), 10, 3 );
		}
	}

	/**
	 * Invalidate a table whose style meta was written.
	 *
	 * @param int    $meta_id  Unused.
	 * @param int    $post_id  Post the meta belongs to.
	 * @param string $meta_key Meta key written.
	 */
	public static function on_meta_change( $meta_id, $post_id, $meta_key ) {
		if ( self::META_KEY !== $meta_key ) {
			return;
		}
		self::invalidate_table( $post_id );
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
