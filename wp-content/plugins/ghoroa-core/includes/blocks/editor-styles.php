<?php
/**
 * The block editor uses the site's own CSS, so articles look as they will on the Astro site.
 *
 * assets/editor/ holds copies of design-system/tokens/tokens.css, design-system/css/ghoroa.css and
 * design-system/fonts/Kalpurush.woff2. Re-copy them when the design system changes.
 *
 * ghoroa.css styles article bodies under `.gn-prose`; in the editor the body is `.is-root-container`,
 * so the selector is swapped when the CSS is loaded.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Editor CSS: tokens (with the font path fixed) + ghoroa.css scoped to the editor canvas.
 */
function gn_editor_css(): string {
	$dir    = GN_CORE_DIR . 'assets/editor/';
	$tokens = (string) file_get_contents( $dir . 'tokens.css' );
	$tokens = str_replace( '../fonts/Kalpurush.woff2', GN_CORE_URL . 'assets/editor/Kalpurush.woff2', $tokens );
	$site   = (string) file_get_contents( $dir . 'ghoroa.css' );
	$site   = preg_replace( '/^@import[^;]+;\s*/m', '', $site ); // Loaded as its own stylesheet below.
	$site   = str_replace( '.gn-prose', '.is-root-container', $site );
	return $tokens . "\n" . $site;
}

/**
 * The Google Fonts URL that ghoroa.css @imports (Hind Siliguri, Poppins).
 */
function gn_editor_font_url(): ?string {
	$site = (string) file_get_contents( GN_CORE_DIR . 'assets/editor/ghoroa.css' );
	return preg_match( '/^@import url\("([^"]+)"\)/m', $site, $m ) ? $m[1] : null;
}

// enqueue_block_assets reaches the editor's iframe; is_admin() keeps it off the front end.
add_action(
	'enqueue_block_assets',
	static function () {
		if ( ! is_admin() ) {
			return;
		}
		$fonts = gn_editor_font_url();
		if ( $fonts ) {
			wp_enqueue_style( 'gn-editor-fonts', $fonts, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- versioned by Google.
		}
		wp_register_style( 'gn-editor', false, array(), GN_CORE_VERSION );
		wp_enqueue_style( 'gn-editor' );
		wp_add_inline_style( 'gn-editor', gn_editor_css() );
	}
);
