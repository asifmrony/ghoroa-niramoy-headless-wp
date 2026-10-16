<?php
/**
 * WordPress never renders the public site (spec §6). Any front-end page it would render for
 * content 301s to the Astro URL: single posts, pages, videos and attachments, category, tag,
 * author and date archives, search, and the front page. Feeds return 404 (until Astro publishes
 * RSS), and WordPress's own sitemaps are off (§9; Rank Math's are limited in rank-math.php).
 *
 * Untouched: wp-admin, wp-login.php, REST (wp-json), GraphQL, previews (M6, §10.2), robots.txt,
 * and later the shop.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Where the current front-end request should go, or null to let WordPress handle it.
 */
function gn_frontend_redirect_target(): ?string {
	$base = gn_public_url();
	if ( '' === $base || is_preview() || is_robots() || is_favicon() || is_feed() || is_404() ) {
		return null;
	}

	if ( is_attachment() ) {
		$parent = get_post( (int) wp_get_post_parent_id( get_queried_object_id() ) );
		return ( $parent ? gn_public_permalink( $parent ) : null ) ?? "{$base}/";
	}
	if ( is_singular() ) {
		return gn_public_permalink( get_queried_object() ) ?? "{$base}/";
	}
	if ( is_category() || is_tag() ) {
		$url  = gn_public_permalink( get_queried_object() ) ?? "{$base}/";
		$page = (int) get_query_var( 'paged' );
		// Astro paginates categories as /<category>/<n>/ (§6); tags keep page 1.
		return ( is_category() && $page > 1 ) ? "{$url}{$page}/" : $url;
	}
	if ( is_author() ) {
		return gn_public_permalink( get_queried_object() ) ?? "{$base}/";
	}
	if ( is_search() ) {
		return "{$base}/search/?q=" . rawurlencode( get_search_query( false ) );
	}
	if ( is_date() ) {
		return "{$base}/latest/";
	}
	if ( is_home() || is_front_page() ) {
		return "{$base}/";
	}
	return null;
}

// On `wp` (priority 0), ahead of plugins that redirect on their own: Rank Math, for one, would send
// date archives to the WordPress front page first.
add_action(
	'wp',
	static function () {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( is_feed() ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			exit;
		}
		$target = gn_frontend_redirect_target();
		if ( null !== $target ) {
			wp_redirect( $target, 301, 'GhoroaNiramoy' ); // phpcs:ignore WordPress.Security.SafeRedirect -- the Astro site is a different host by design.
			exit;
		}
	},
	0
);

// No feed links in <head> (WordPress pages aren't public anyway).
remove_action( 'wp_head', 'feed_links', 2 );
remove_action( 'wp_head', 'feed_links_extra', 3 );

// WordPress core sitemaps would list posts, pages, users and terms: Astro owns the sitemap (§9).
add_filter( 'wp_sitemaps_enabled', '__return_false' );
