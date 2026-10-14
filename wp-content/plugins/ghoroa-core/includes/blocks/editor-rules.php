<?php
/**
 * What editors can use in posts and pages (spec §5 "Block allowlist").
 *
 * Allowed: paragraph, heading (H2/H3), list, quote, image, table and the gn/* blocks. Everything
 * else (columns, buttons, cover, gallery, embeds, HTML, separators, spacers…) is unavailable, and
 * colours, font sizes, spacing, borders and alignment are switched off.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

/** Core blocks kept (list-item is the list's own child block). */
const GN_ALLOWED_CORE_BLOCKS = array( 'core/paragraph', 'core/heading', 'core/list', 'core/list-item', 'core/quote', 'core/image', 'core/table' );

add_filter(
	'allowed_block_types_all',
	static function ( $allowed, $context ) {
		$type = $context->post->post_type ?? '';
		if ( ! in_array( $type, array( 'post', 'page' ), true ) ) {
			return $allowed;
		}
		return array_merge( GN_ALLOWED_CORE_BLOCKS, array_map( static fn( $slug ) => "gn/{$slug}", GN_BLOCKS ) );
	},
	10,
	2
);

// Strip design controls from the core blocks we keep: no colours, typography, spacing, borders,
// alignment or custom classes. Headings keep their HTML anchor (the §5.1 id) and only offer H2/H3.
add_filter(
	'register_block_type_args',
	static function ( array $args, string $name ): array {
		if ( ! in_array( $name, GN_ALLOWED_CORE_BLOCKS, true ) ) {
			return $args;
		}
		$supports = $args['supports'] ?? array();
		// Explicit false (not unset): the editor merges these over its own bundled block definitions.
		foreach ( array( 'color', 'typography', 'spacing', '__experimentalBorder', 'border', 'shadow', 'dimensions', 'background', 'align', 'alignWide', 'layout', 'position', 'filter', 'textAlign', 'anchor', '__experimentalFontFamily' ) as $feature ) {
			$supports[ $feature ] = false;
		}
		$supports['customClassName'] = false;
		if ( 'core/heading' === $name ) {
			$supports['anchor']                                = true;
			$args['attributes']['levelOptions']['default'] = array( 2, 3 );
		}
		$args['supports'] = $supports;
		return $args;
	},
	10,
	2
);

// Turn off the global design tools (palette, font sizes, spacing presets…) for every block.
add_filter(
	'wp_theme_json_data_theme',
	static function ( $theme_json ) {
		return $theme_json->update_with(
			array(
				'version'  => 3,
				'settings' => array(
					'appearanceTools' => false,
					'color'           => array(
						'custom'           => false,
						'customDuotone'    => false,
						'customGradient'   => false,
						'defaultDuotone'   => false,
						'defaultGradients' => false,
						'defaultPalette'   => false,
						'palette'          => array(),
						'gradients'        => array(),
						'duotone'          => array(),
						'text'             => false,
						'background'       => false,
						'link'             => false,
					),
					'typography'      => array(
						'customFontSize'   => false,
						'defaultFontSizes' => false,
						'fontSizes'        => array(),
						'dropCap'          => false,
						'lineHeight'       => false,
						'letterSpacing'    => false,
						'textDecoration'   => false,
						'textTransform'    => false,
						'writingMode'      => false,
						'fontStyle'        => false,
						'fontWeight'       => false,
						'fontFamilies'     => array(),
					),
					'spacing'         => array(
						'padding'             => false,
						'margin'              => false,
						'blockGap'            => false,
						'customSpacingSize'   => false,
						'defaultSpacingSizes' => false,
						'spacingSizes'        => array(),
					),
					'border'          => array(
						'color'  => false,
						'radius' => false,
						'style'  => false,
						'width'  => false,
					),
					'shadow'          => array(
						'defaultPresets' => false,
						'presets'        => array(),
					),
				),
			)
		);
	}
);

// No block patterns: they're built from blocks editors can't use here.
add_action( 'after_setup_theme', static fn() => remove_theme_support( 'core-block-patterns' ), 20 );
add_filter( 'should_load_remote_block_patterns', '__return_false' );
