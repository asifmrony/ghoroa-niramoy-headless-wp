<?php
/**
 * Public (Astro) URLs from the spec §6 URL map, built on GN_PUBLIC_URL.
 *
 * Used by gnSeo.canonical (§9) now, and by the link filters and redirects in §6 (M5).
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Site-relative Astro path for a post, page, video, category, tag or author; null if it has none.
 *
 * @param WP_Post|WP_Term|WP_User $object Content object.
 */
function gn_public_path( $object ): ?string {
	if ( $object instanceof WP_Post ) {
		if ( '' === $object->post_name ) {
			return null; // Drafts that were never given a slug.
		}
		return match ( $object->post_type ) {
			'post'  => "/{$object->post_name}/",
			'page'  => '/' . get_page_uri( $object ) . '/',
			'video' => "/video/{$object->post_name}/",
			default => null,
		};
	}
	if ( $object instanceof WP_Term ) {
		return match ( $object->taxonomy ) {
			'category' => "/{$object->slug}/",
			'post_tag' => "/tag/{$object->slug}/",
			default    => null,
		};
	}
	if ( $object instanceof WP_User ) {
		return "/author/{$object->user_nicename}/";
	}
	return null;
}

/**
 * Absolute Astro URL (GN_PUBLIC_URL + path), or null without GN_PUBLIC_URL or a path.
 *
 * @param WP_Post|WP_Term|WP_User $object Content object.
 */
function gn_public_permalink( $object ): ?string {
	$path = gn_public_path( $object );
	$base = gn_public_url();
	return ( null === $path || '' === $base ) ? null : $base . $path;
}
