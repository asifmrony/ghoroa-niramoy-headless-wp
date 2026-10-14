<?php
/**
 * gn/numbered-heading (spec §5.2):
 *   <h3 id="b1"><span class="gn-num">১</span><span>হজমে সাহায্য করতে পারে</span></h3>
 *
 * The number and id are assigned on save by includes/blocks/numbering.php (stored in the block's
 * data as gn_number / gn_anchor). A heading added since the last save previews as "#".
 *
 * @package GhoroaCore
 * @var array $block      SCF block settings and data.
 * @var bool  $is_preview Whether this is the editor preview.
 */

defined( 'ABSPATH' ) || exit;

$text   = gn_inline_html( get_field( 'text' ) );
$number = (int) ( $block['data']['gn_number'] ?? 0 );
$anchor = (string) ( $block['data']['gn_anchor'] ?? '' );

if ( '' === $text && ! $is_preview ) {
	return;
}
?>
<h3<?php echo '' !== $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?>><span class="gn-num"><?php echo esc_html( $number ? gn_bn_digits( $number ) : '#' ); ?></span><span><?php echo '' !== $text ? $text : esc_html( 'শিরোনাম লিখুন…' ); // phpcs:ignore WordPress.Security.EscapeOutput -- gn_inline_html() is kses-filtered. ?></span></h3>
