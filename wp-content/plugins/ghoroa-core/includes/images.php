<?php
/**
 * Image crops and WebP output (spec §4.1).
 *
 * WPGraphQL turns each size into a MediaItemSizeEnum value: gn-card → GN_CARD, gn-lead → GN_LEAD, …
 * Sub-sizes are written as WebP; the uploaded original keeps its format. Astro does no image
 * processing, so these files are what readers download.
 *
 * After changing a size, regenerate: `wp media regenerate --yes`.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Crops from §4.1: name => [ width, height ]. All hard-cropped.
 *
 * @return array<string, array{0: int, 1: int}>
 */
function gn_image_sizes(): array {
	return array(
		'gn-card'    => array( 800, 500 ),   // 16:10 cards, video thumbs.
		'gn-card-sm' => array( 400, 250 ),   // Small cards.
		'gn-hero'    => array( 1200, 900 ),  // 4:3 homepage hero.
		'gn-lead'    => array( 1600, 800 ),  // 2:1 article lead.
		'gn-thumb'   => array( 176, 176 ),   // 1:1 side-list thumbs.
		'gn-og'      => array( 1200, 630 ),  // Social share image.
	);
}

add_action(
	'after_setup_theme',
	static function () {
		foreach ( gn_image_sizes() as $name => [ $width, $height ] ) {
			add_image_size( $name, $width, $height, true );
		}
	}
);

// JPEG and PNG sub-sizes are saved as WebP.
add_filter(
	'image_editor_output_format',
	static function ( array $formats ): array {
		$formats['image/jpeg'] = 'image/webp';
		$formats['image/png']  = 'image/webp';
		return $formats;
	}
);

// Compress on upload (§4.1): WebP at 80 looks the same as JPEG at WordPress's default 82, and is smaller.
add_filter(
	'wp_editor_set_quality',
	static fn( $quality, $mime_type ) => 'image/webp' === $mime_type ? 80 : $quality,
	10,
	2
);
