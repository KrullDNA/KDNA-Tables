<?php
/**
 * Cell rendering, shared by the Elementor widget and the shortcode.
 *
 * The render templates call four methods on whatever object they are
 * bound to — kdna_render_comparison_value(), kdna_cell_modifier_class(),
 * kdna_resolve_cell_alignment() and kdna_render_cell_inner() — plus the
 * private helpers those call in turn. They used to live on
 * KDNA_Tables_Widget, which extends \Elementor\Widget_Base, so the
 * shortcode could only render by instantiating a widget. With Elementor
 * deactivated that class does not exist and merely including its file is
 * a fatal, so the shortcode had to guard the include and degrade to
 * rendering nothing at all.
 *
 * Moving them here fixes that at the root: KDNA_Tables_Cell_Renderer is
 * a plain object with no parent and no Elementor dependency, and the
 * shortcode binds the templates to one of those. The widget uses the same
 * trait, so there is one implementation and the two paths cannot drift.
 *
 * ── The two Elementor utilities ───────────────────────────────────────
 *
 * Icon and image rendering went through \Elementor\Icons_Manager and
 * \Elementor\Group_Control_Image_Size. Both are wrapped below and fall
 * back to a plain equivalent when the class is absent. With Elementor
 * active the call is delegated unchanged, so the widget's output is
 * byte-for-byte what it was.
 *
 * @package KDNA_Tables
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait KDNA_Tables_Cell_Renderer_Trait {

	/**
	 * Render an icon control value.
	 *
	 * Elementor's Icons_Manager handles the SVG library, its own icon
	 * sets and Font Awesome alike, so it is used whenever it is there.
	 * Without it, an icon is either a class string — which is what every
	 * Font Awesome pick is, and what the shortcode's own defaults are —
	 * or an uploaded SVG carrying a url. Both have a plain equivalent;
	 * anything else renders nothing rather than guessing.
	 *
	 * @param array $icon Elementor icon control value.
	 * @return string HTML, already escaped.
	 */
	protected function kdna_render_icon( $icon ) {
		if ( empty( $icon ) || ! is_array( $icon ) ) {
			return '';
		}

		if ( class_exists( '\\Elementor\\Icons_Manager' ) ) {
			ob_start();
			\Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
			return ob_get_clean();
		}

		$value = isset( $icon['value'] ) ? $icon['value'] : '';

		// An uploaded SVG: { value: { url, id }, library: 'svg' }.
		if ( is_array( $value ) ) {
			$url = isset( $value['url'] ) ? (string) $value['url'] : '';
			if ( '' === $url ) {
				return '';
			}
			return '<img class="kdna-table__icon-svg" src="' . esc_url( $url ) . '" alt="" aria-hidden="true" />';
		}

		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		return '<i class="' . esc_attr( $value ) . '" aria-hidden="true"></i>';
	}

	/**
	 * Render a cell's image at the configured size.
	 *
	 * Elementor's image-size group control resolves the size the user
	 * chose, including its custom-dimensions option, so it is used when
	 * present. Without it the stored size name is passed to
	 * wp_get_attachment_image(), which understands the registered sizes
	 * and ignores the rest; an image with a url but no attachment id —
	 * an external or already-deleted one — falls back to a plain img.
	 *
	 * Argument order matches Elementor's own so the delegation is a
	 * straight pass-through.
	 *
	 * @param array  $settings  Settings carrying the image and size keys.
	 * @param string $size_key  Image-size control key.
	 * @param string $image_key Media control key.
	 * @return string HTML.
	 */
	protected function kdna_render_attachment_image( $settings, $size_key = 'cell_image_size', $image_key = 'cell_image' ) {
		if ( class_exists( '\\Elementor\\Group_Control_Image_Size' ) ) {
			return \Elementor\Group_Control_Image_Size::get_attachment_image_html( $settings, $size_key, $image_key );
		}

		$image = isset( $settings[ $image_key ] ) && is_array( $settings[ $image_key ] ) ? $settings[ $image_key ] : array();
		$size  = isset( $settings[ $size_key . '_size' ] ) ? (string) $settings[ $size_key . '_size' ] : 'full';
		if ( '' === $size ) {
			$size = 'full';
		}

		$id = isset( $image['id'] ) ? (int) $image['id'] : 0;
		if ( $id > 0 ) {
			$html = wp_get_attachment_image( $id, $size );
			if ( '' !== $html ) {
				return $html;
			}
		}

		$url = isset( $image['url'] ) ? (string) $image['url'] : '';
		if ( '' === $url ) {
			return '';
		}

		return '<img src="' . esc_url( $url ) . '" alt="" />';
	}

	/*
	 * Comparison render helpers. Public where the template files need to
	 * call them via $this->method().
	 */

	public function kdna_render_comparison_value( $feature_row, $slot, $settings ) {
		$indicator_key = 'cell_' . $slot . '_indicator';
		$indicator     = isset( $feature_row[ $indicator_key ] ) ? $feature_row[ $indicator_key ] : 'available';

		if ( 'available' === $indicator ) {
			return $this->kdna_render_available_indicator( $settings );
		}

		if ( 'unavailable' === $indicator ) {
			return $this->kdna_render_unavailable_indicator( $settings );
		}

		if ( 'custom' === $indicator ) {
			$arrangement = $feature_row[ 'cell_' . $slot . '_arrangement' ] ?? 'icon-text';
			$cell_type   = $feature_row[ 'cell_' . $slot . '_custom_type' ] ?? 'text';

			// Widget-level override: when set, swap icon/text order globally
			// for every mixed cell. Only applies to two-piece icon+text mixes
			// to avoid mangling three-piece arrangements that include image.
			$position_override = isset( $settings['cmp_cell_icon_position'] ) ? (string) $settings['cmp_cell_icon_position'] : 'inherit';
			if ( 'mixed' === $cell_type && in_array( $position_override, array( 'before', 'after' ), true ) ) {
				if ( 'icon-text' === $arrangement || 'text-icon' === $arrangement ) {
					$arrangement = ( 'before' === $position_override ) ? 'icon-text' : 'text-icon';
				}
			}

			$normalized = array(
				'cell_type'        => $cell_type,
				'cell_text'        => $feature_row[ 'cell_' . $slot . '_text' ] ?? '',
				'cell_icon'        => $feature_row[ 'cell_' . $slot . '_icon' ] ?? array(),
				'cell_image'       => $feature_row[ 'cell_' . $slot . '_image' ] ?? array(),
				'cell_image_size'  => $feature_row[ 'cell_' . $slot . '_image_size' ] ?? 'medium',
				'cell_arrangement' => $arrangement,
			);
			return $this->kdna_render_cell_inner( $normalized );
		}

		return '';
	}

	public function kdna_render_available_indicator( $settings ) {
		$icon = isset( $settings['available_icon'] ) ? $settings['available_icon'] : null;
		if ( empty( $icon ) || ( empty( $icon['value'] ) && empty( $icon['library'] ) ) ) {
			return '';
		}

		ob_start();
		echo '<span class="kdna-comparison__indicator kdna-comparison__indicator--available">';
		echo $this->kdna_render_icon( $icon );
		echo '<span class="kdna-table__sr-only">' . esc_html__( 'Available', 'kdna-tables' ) . '</span>';
		echo '</span>';
		return ob_get_clean();
	}

	public function kdna_render_unavailable_indicator( $settings ) {
		$mode = isset( $settings['unavailable_mode'] ) ? $settings['unavailable_mode'] : 'icon';

		if ( 'hidden' === $mode ) {
			return '<span class="kdna-table__sr-only">' . esc_html__( 'Not available', 'kdna-tables' ) . '</span>';
		}

		if ( 'icon' === $mode ) {
			$icon       = isset( $settings['unavailable_icon'] ) ? $settings['unavailable_icon'] : null;
			$icon_empty = empty( $icon ) || ( empty( $icon['value'] ) && empty( $icon['library'] ) );

			ob_start();
			echo '<span class="kdna-comparison__indicator kdna-comparison__indicator--unavailable">';

			if ( $icon_empty ) {
				// No icon picked. Try the plugin-bundled default SVG first
				// (assets/icons/cross.svg) so the visual default is the KDNA
				// glyph. Fall back to fas fa-minus when the file is missing
				// so behaviour stays safe between deploys.
				$bundled = self::get_bundled_default_unavailable_svg();
				if ( '' !== $bundled ) {
					echo $bundled;
				} else {
					echo $this->kdna_render_icon( array( 'value' => 'fas fa-minus', 'library' => 'fa-solid' ) );
				}
			} else {
				echo $this->kdna_render_icon( $icon );
			}

			echo '<span class="kdna-table__sr-only">' . esc_html__( 'Not available', 'kdna-tables' ) . '</span>';
			echo '</span>';
			return ob_get_clean();
		}

		if ( 'text' === $mode ) {
			$text = isset( $settings['unavailable_text'] ) ? (string) $settings['unavailable_text'] : '-';
			return '<span class="kdna-comparison__indicator kdna-comparison__indicator--unavailable kdna-comparison__indicator--text" aria-label="' . esc_attr__( 'Not available', 'kdna-tables' ) . '">'
				. esc_html( $text )
				. '</span>';
		}

		return '';
	}

	/**
	 * Load the plugin-bundled default Unavailable SVG and inline it. The
	 * file lives at assets/cross.svg and is expected to use
	 * fill="currentColor" so the Style > Unavailable Indicator > Colour
	 * cascades into it. Returns an empty string if the file is missing,
	 * so callers can fall back to a Font Awesome glyph.
	 *
	 * Cached statically per request, and only sanitised lightly with
	 * wp_kses so embedded <script> or external xlink:href refs cannot
	 * leak in if someone replaces the bundled file.
	 */
	public static function get_bundled_default_unavailable_svg() {
		static $cached = null;
		if ( null !== $cached ) {
			return $cached;
		}
		$path = KDNA_TABLES_PATH . 'assets/cross.svg';
		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			$cached = '';
			return $cached;
		}
		$raw = file_get_contents( $path );
		if ( false === $raw || '' === trim( $raw ) ) {
			$cached = '';
			return $cached;
		}
		$allowed = array(
			'svg'      => array(
				'xmlns'       => true,
				'viewbox'     => true,
				'fill'        => true,
				'stroke'      => true,
				'aria-hidden' => true,
				'role'        => true,
				'focusable'   => true,
				'class'       => true,
				'width'       => true,
				'height'      => true,
				'preserveaspectratio' => true,
			),
			'path'     => array( 'd' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'fill-rule' => true, 'clip-rule' => true, 'opacity' => true ),
			'g'        => array( 'fill' => true, 'stroke' => true, 'opacity' => true, 'transform' => true ),
			'rect'     => array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true ),
			'circle'   => array( 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true ),
			'line'     => array( 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true ),
			'polyline' => array( 'points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true ),
			'polygon'  => array( 'points' => true, 'fill' => true, 'stroke' => true ),
			'title'    => array(),
			'desc'     => array(),
		);
		$svg = wp_kses( $raw, $allowed );
		// Drop any width / height attribute on the root <svg> so the CSS
		// shape size controls the rendered footprint, not whatever the
		// designer baked into the file.
		$svg = preg_replace( '/<svg([^>]*)\s(width|height)="[^"]*"/i', '<svg$1', $svg );
		// Tag the svg with the same e-font-icon-svg-style class set the
		// available indicator uses so existing CSS (width:1em; height:1em)
		// applies without a special case.
		if ( false === stripos( $svg, 'class="' ) ) {
			$svg = preg_replace( '/<svg\b/i', '<svg class="kdna-comparison__indicator-svg" aria-hidden="true"', $svg, 1 );
		} else {
			$svg = preg_replace( '/<svg([^>]*\bclass=")([^"]*)"/i', '<svg$1$2 kdna-comparison__indicator-svg"', $svg, 1 );
		}
		$cached = $svg;
		return $cached;
	}

	/*
	 * Cell render helpers. Public so the template files can call them via
	 * $this->method() while included from the render method.
	 */

	public function kdna_cell_modifier_class( $cell ) {
		$type = isset( $cell['cell_type'] ) ? $cell['cell_type'] : '';
		if ( '' === $type ) {
			return '';
		}
		return 'kdna-table__cell--' . sanitize_html_class( $type );
	}

	public function kdna_resolve_cell_alignment( $cell, $column ) {
		$override = isset( $cell['cell_alignment_override'] ) ? $cell['cell_alignment_override'] : 'inherit';
		if ( in_array( $override, array( 'left', 'center', 'right' ), true ) ) {
			return $override;
		}
		$col_align = isset( $column['column_alignment'] ) ? $column['column_alignment'] : 'left';
		if ( ! in_array( $col_align, array( 'left', 'center', 'right' ), true ) ) {
			$col_align = 'left';
		}
		return $col_align;
	}

	public function kdna_render_cell_inner( $cell ) {
		if ( empty( $cell ) || empty( $cell['cell_type'] ) ) {
			return '';
		}

		$type   = $cell['cell_type'];
		$pieces = array();

		if ( 'text' === $type ) {
			$pieces['text'] = $this->kdna_render_cell_text_piece( $cell );
		} elseif ( 'icon' === $type ) {
			$pieces['icon'] = $this->kdna_render_cell_icon_piece( $cell );
		} elseif ( 'image' === $type ) {
			$pieces['image'] = $this->kdna_render_cell_image_piece( $cell );
		} elseif ( 'mixed' === $type ) {
			$arrangement = isset( $cell['cell_arrangement'] ) ? $cell['cell_arrangement'] : 'icon-text';
			foreach ( $this->kdna_arrangement_order( $arrangement ) as $piece ) {
				if ( 'text' === $piece ) {
					$pieces['text'] = $this->kdna_render_cell_text_piece( $cell );
				} elseif ( 'icon' === $piece ) {
					$pieces['icon'] = $this->kdna_render_cell_icon_piece( $cell );
				} elseif ( 'image' === $piece ) {
					$pieces['image'] = $this->kdna_render_cell_image_piece( $cell );
				}
			}
		}

		return implode( '', array_filter( $pieces ) );
	}

	protected function kdna_arrangement_order( $arrangement ) {
		switch ( $arrangement ) {
			case 'text-icon':
				return array( 'text', 'icon' );
			case 'icon-text-image':
				return array( 'icon', 'text', 'image' );
			case 'image-text-icon':
				return array( 'image', 'text', 'icon' );
			case 'icon-text':
			default:
				return array( 'icon', 'text' );
		}
	}

	protected function kdna_render_cell_text_piece( $cell ) {
		$text = isset( $cell['cell_text'] ) ? $cell['cell_text'] : '';
		if ( '' === $text ) {
			return '';
		}
		return '<span class="kdna-table__cell-text">' . wp_kses_post( $text ) . '</span>';
	}

	protected function kdna_render_cell_icon_piece( $cell ) {
		if ( empty( $cell['cell_icon'] ) ) {
			return '';
		}
		$icon = $cell['cell_icon'];
		if ( empty( $icon['value'] ) && empty( $icon['library'] ) ) {
			return '';
		}
		ob_start();
		echo '<span class="kdna-table__cell-icon">';
		echo $this->kdna_render_icon( $icon );
		echo '</span>';
		return ob_get_clean();
	}

	protected function kdna_render_cell_image_piece( $cell ) {
		if ( empty( $cell['cell_image']['id'] ) && empty( $cell['cell_image']['url'] ) ) {
			return '';
		}
		$image_html = $this->kdna_render_attachment_image( $cell );
		if ( '' === $image_html ) {
			return '';
		}
		return '<span class="kdna-table__cell-image">' . $image_html . '</span>';
	}}
