<?php
/**
 * Rank Math settings from spec §9, applied by `wp gn setup`.
 *
 * - Rank Math free stays dormant (no variables, no modules) until an account is connected or its
 *   wizard's "Skip" is clicked. We set that same skip flag: no Rank Math account is needed, and the
 *   wizard's settings are made here in code instead.
 * - Off: schema/JSON-LD (Astro generates structured data), redirections (they live in ghoroa-core),
 *   the 404 monitor, breadcrumbs, and Instant Indexing (it would ping search engines with
 *   WordPress URLs on publish; Astro owns the public URLs).
 * - Sitemaps: Rank Math may only list shop URLs. Posts, pages, videos, categories, tags, authors,
 *   attachments and the HTML sitemap are off; products stay on for later.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Applies the §9 settings. Idempotent.
 *
 * @return string[] Log lines.
 */
function gn_configure_rank_math(): array {
	if ( ! defined( 'RANK_MATH_VERSION' ) ) {
		return array( 'Rank Math is not active; SEO settings skipped.' );
	}
	$log = array();

	foreach ( array( 'rank_math_registration_skip', 'rank_math_wizard_completed' ) as $flag ) {
		if ( ! get_option( $flag ) ) {
			update_option( $flag, true );
			$log[] = "Rank Math: set {$flag} (no account connection; settings live in code).";
		}
	}

	$modules = (array) get_option( 'rank_math_modules', array() );
	$active  = array_values( array_diff( $modules, array( 'rich-snippet', 'redirections', '404-monitor', 'instant-indexing' ) ) );
	if ( $active !== $modules ) {
		update_option( 'rank_math_modules', $active );
		$log[] = 'Rank Math: schema, redirections, 404 monitor and Instant Indexing modules turned off.';
	}

	$settings = array(
		'rank-math-options-sitemap' => array(
			'html_sitemap'          => 'off',
			'authors_sitemap'       => 'off',
			'pt_post_sitemap'       => 'off',
			'pt_page_sitemap'       => 'off',
			'pt_video_sitemap'      => 'off',
			'pt_attachment_sitemap' => 'off',
			'tax_category_sitemap'  => 'off',
			'tax_post_tag_sitemap'  => 'off',
		),
		'rank-math-options-general' => array(
			'breadcrumbs' => 'off',
		),
	);
	foreach ( $settings as $option => $wanted ) {
		$current = (array) get_option( $option, array() );
		$merged  = array_merge( $current, $wanted );
		if ( $merged !== $current ) {
			update_option( $option, $merged );
			$log[] = "Rank Math: updated {$option}.";
		}
	}

	return $log;
}
