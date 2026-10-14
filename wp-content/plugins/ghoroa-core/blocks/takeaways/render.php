<?php
/**
 * gn/takeaways (spec §5.3). The heading "Key takeaways" is fixed and Latin by design.
 *   <div class="gn-takeaways"><h4>Key takeaways</h4><ul><li>…</li><li>…</li><li>…</li></ul></div>
 *
 * @package GhoroaCore
 * @var bool $is_preview Whether this is the editor preview.
 */

defined( 'ABSPATH' ) || exit;

$items = array_filter( array_map( static fn( $row ) => gn_inline_html( $row['text'] ?? '' ), (array) get_field( 'items' ) ) );
if ( ! $items && ! $is_preview ) {
	return;
}
?>
<div class="gn-takeaways"><h4>Key takeaways</h4><ul>
<?php foreach ( $items as $item ) : ?>
	<li><?php echo $item; // phpcs:ignore WordPress.Security.EscapeOutput -- kses-filtered. ?></li>
<?php endforeach; ?>
</ul></div>
