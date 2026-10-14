<?php
/**
 * gn/ad-slot (spec §5.11): renders only the marker comment; Astro swaps it for the ad container.
 * Blocks skip wpautop and wptexturize leaves comments alone, so the comment reaches GraphQL intact.
 *
 * @package GhoroaCore
 * @var bool $is_preview Whether this is the editor preview.
 */

defined( 'ABSPATH' ) || exit;

if ( $is_preview ) {
	echo gn_editor_placeholder( 'বিজ্ঞাপনের জায়গা (পাঠক এখানে বিজ্ঞাপন দেখবেন)' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
	return;
}
echo '<!-- gn-ad -->';
