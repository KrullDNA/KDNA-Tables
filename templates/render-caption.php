<?php
/**
 * The table caption, rendered as a heading above the table.
 *
 * ── Why a heading and not the <caption> element ───────────────────────
 *
 * A <caption> lives inside the <table>, which makes it very hard to
 * style and very easy to lose. It cannot take a margin that separates it
 * from the frame, it sits inside the wrapper's overflow clipping, and in
 * the responsive modes the table itself becomes display:block or a grid
 * — at which point the caption's own layout is whatever the browser
 * decides. It was also only ever rendered by the general template: a
 * comparison table collected a caption in the editor, saved it, and
 * dropped it silently at render.
 *
 * A heading above the table is a normal block. Every ordinary style
 * control works on it, it survives every responsive mode unchanged, and
 * both table types get the same one.
 *
 * ── Accessibility ─────────────────────────────────────────────────────
 *
 * Moving the caption out of the table would leave the table unnamed, so
 * the heading carries an id and the template that includes this labels
 * its table with aria-labelledby. A screen reader announces the same
 * text it did before, once.
 *
 * Empty caption, no markup at all — not an empty heading with a margin,
 * which would leave an unexplained gap above the table.
 *
 * @var array $settings Table settings. Reads 'caption' and 'caption_tag'.
 *
 * @package KDNA_Tables
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kdna_caption = isset( $settings['caption'] ) ? trim( (string) $settings['caption'] ) : '';

/*
 * Opt-in on the Elementor widget, always on for shortcodes. An Elementor
 * page usually already has a Heading widget above the table, so a second
 * one appearing the day the plugin updates would be a regression on every
 * existing instance. A shortcode has no such editor, so it shows the
 * caption whenever the table has one. Both callers set this explicitly;
 * absent, it does not show.
 */
if ( empty( $settings['show_caption'] ) ) {
	$kdna_caption_id = '';
	return;
}

if ( '' === $kdna_caption ) {
	// Nothing to show. $kdna_caption_id stays unset, and the table that
	// includes this omits aria-labelledby rather than pointing at an
	// element that is not there.
	$kdna_caption_id = '';
	return;
}

/*
 * An allow-list, not the stored string. This value is interpolated into
 * a tag name, so it can never be whatever happened to be in the option:
 * a stray value there would be markup injection, and the sanitiser on
 * the way in is not the place to rely on for that.
 */
$kdna_caption_tags = array( 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div' );
$kdna_caption_tag  = isset( $settings['caption_tag'] ) ? strtolower( (string) $settings['caption_tag'] ) : '';
if ( ! in_array( $kdna_caption_tag, $kdna_caption_tags, true ) ) {
	$kdna_caption_tag = 'h3';
}

/*
 * Unique per table on the page, so aria-labelledby cannot cross-link two
 * tables. A global rather than a static: this file is included from
 * inside a closure bound to the renderer, so a static here would restart
 * at zero for each binding and hand two tables the same id.
 */
if ( ! isset( $GLOBALS['kdna_caption_seq'] ) ) {
	$GLOBALS['kdna_caption_seq'] = 0;
}
++$GLOBALS['kdna_caption_seq'];
$kdna_caption_id = 'kdna-table-caption-' . $GLOBALS['kdna_caption_seq'];

printf(
	'<%1$s class="kdna-table__caption kdna-table__caption--heading" id="%2$s">%3$s</%1$s>',
	esc_attr( $kdna_caption_tag ),
	esc_attr( $kdna_caption_id ),
	esc_html( $kdna_caption )
);
