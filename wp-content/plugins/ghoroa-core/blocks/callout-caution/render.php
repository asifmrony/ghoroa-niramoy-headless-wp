<?php
/**
 * gn/callout-caution (spec §5.8). Required on health articles. The amber style is reserved for this
 * block and the site disclaimer.
 *   <div class="gn-callout gn-callout--caution"><svg…/><div><span class="gn-callout__title">কারা এড়িয়ে চলবেন</span><ul><li>…</li></ul>closing sentence</div></div>
 *
 * @package GhoroaCore
 * @var bool $is_preview Whether this is the editor preview.
 */

defined( 'ABSPATH' ) || exit;

$title   = (string) get_field( 'title' );
$items   = array_filter( array_map( static fn( $row ) => gn_inline_html( $row['text'] ?? '' ), (array) get_field( 'items' ) ) );
$closing = gn_inline_html( get_field( 'closing' ) );
?>
<div class="gn-callout gn-callout--caution"><?php echo gn_callout_icon( 'caution' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><div><span class="gn-callout__title"><?php echo esc_html( '' !== $title ? $title : 'কারা এড়িয়ে চলবেন' ); ?></span><?php if ( $items ) : ?><ul>
<?php foreach ( $items as $item ) : ?>
	<li><?php echo $item; // phpcs:ignore WordPress.Security.EscapeOutput -- kses-filtered. ?></li>
<?php endforeach; ?>
</ul><?php endif; ?><?php echo $closing; // phpcs:ignore WordPress.Security.EscapeOutput -- kses-filtered. ?></div></div>
