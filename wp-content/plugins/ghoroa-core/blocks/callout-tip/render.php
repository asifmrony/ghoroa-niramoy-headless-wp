<?php
/**
 * gn/callout-tip (spec §5.7). The text may contain links; it is inline (no <p>) after the title.
 *
 * @package GhoroaCore
 * @var bool $is_preview Whether this is the editor preview.
 */

defined( 'ABSPATH' ) || exit;

$title = (string) get_field( 'title' );
// The callout wants inline text after the title, so paragraphs (blank lines or <p>) become line breaks.
$text = gn_inline_html(
	preg_replace(
		array( '#^\s*<p>#i', '#</p>\s*$#i', '#</p>\s*<p>#i', '#\n\s*\n#' ),
		array( '', '', '<br>', '<br>' ),
		trim( (string) get_field( 'text', false, false ) )
	)
);

if ( '' === $text && ! $is_preview ) {
	return;
}
?>
<div class="gn-callout gn-callout--tip"><?php echo gn_callout_icon( 'tip' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><div><?php if ( '' !== $title ) : ?><span class="gn-callout__title"><?php echo esc_html( $title ); ?></span><?php endif; ?><?php echo $text; // phpcs:ignore WordPress.Security.EscapeOutput -- kses-filtered. ?></div></div>
