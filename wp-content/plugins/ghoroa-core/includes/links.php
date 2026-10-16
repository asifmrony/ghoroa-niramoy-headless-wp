<?php
/**
 * Every link WordPress produces points at the public Astro site (spec §6): permalinks of posts,
 * videos, pages, categories, tags and authors, and links inside article bodies. This covers the
 * admin "View" links, REST/GraphQL `link` and `uri`, and SEO canonicals.
 *
 * URLs come from gn_public_path() (includes/urls.php) on top of GN_PUBLIC_URL; without that
 * constant nothing is rewritten. The editor's slug field keeps working: sample permalinks keep
 * their %postname% / %pagename% / %video% placeholder.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Public URL for a post, or its sample form with the slug placeholder (for the editor's slug field).
 */
function gn_public_post_link( string $link, WP_Post $post, bool $sample ): string {
	if ( '' === gn_public_url() ) {
		return $link;
	}
	if ( $sample ) {
		$placeholder = array(
			'post'  => '/%postname%/',
			'page'  => '/%pagename%/',
			'video' => '/video/%video%/',
		)[ $post->post_type ] ?? null;
		return null === $placeholder ? $link : gn_public_url() . $placeholder;
	}
	return gn_public_permalink( $post ) ?? $link;
}

add_filter( 'post_link', static fn( $link, $post, $leavename = false ) => gn_public_post_link( $link, $post, (bool) $leavename ), 20, 3 );
add_filter(
	'post_type_link',
	static fn( $link, $post, $leavename = false, $sample = false ) => 'video' === $post->post_type ? gn_public_post_link( $link, $post, $leavename || $sample ) : $link,
	20,
	4
);
add_filter( 'page_link', static fn( $link, $post_id, $sample = false ) => gn_public_post_link( $link, get_post( $post_id ), (bool) $sample ), 20, 3 );
add_filter( 'term_link', static fn( $link, $term ) => gn_public_permalink( $term ) ?? $link, 20, 2 );
add_filter(
	'author_link',
	static function ( $link, $author_id ) {
		$user = get_userdata( (int) $author_id );
		return $user ? ( gn_public_permalink( $user ) ?? $link ) : $link;
	},
	20,
	2
);

/**
 * The Astro URL for a link that points at this WordPress install's own front end, or null if it
 * doesn't (external links, admin, uploads, anchors).
 */
function gn_public_url_for_wp_link( string $href ): ?string {
	$home = untrailingslashit( home_url() );
	if ( '' === gn_public_url() || ! str_starts_with( $href, $home . '/' ) ) {
		return null;
	}
	$path = (string) wp_parse_url( $href, PHP_URL_PATH );
	$home_path = (string) wp_parse_url( $home, PHP_URL_PATH );
	$path = '/' . trim( substr( $path, strlen( $home_path ) ), '/' ) . '/';
	if ( preg_match( '#^/(wp-admin|wp-content|wp-includes|wp-json|graphql)/#', $path ) ) {
		return null;
	}

	$post_id = url_to_postid( $href );
	if ( $post_id ) {
		return gn_public_permalink( get_post( $post_id ) );
	}
	if ( preg_match( '#^/(category|tag|author)/([^/]+)/$#', $path, $m ) ) {
		$object = 'author' === $m[1]
			? get_user_by( 'slug', $m[2] )
			: get_term_by( 'slug', $m[2], 'category' === $m[1] ? 'category' : 'post_tag' );
		return $object ? gn_public_permalink( $object ) : null;
	}
	return null;
}

/**
 * Links inside rendered content: WordPress URLs become Astro URLs, and internal links never open
 * in a new tab (§5.12).
 */
function gn_rewrite_content_links( string $html ): string {
	if ( ! str_contains( $html, '<a' ) ) {
		return $html;
	}
	$public = gn_public_url();
	$tags   = new WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag( 'a' ) ) {
		$href = (string) $tags->get_attribute( 'href' );
		$new  = gn_public_url_for_wp_link( $href );
		if ( null !== $new ) {
			$tags->set_attribute( 'href', $new );
			$href = $new;
		}
		$internal = str_starts_with( $href, '/' ) || str_starts_with( $href, '#' ) || ( '' !== $public && str_starts_with( $href, $public . '/' ) );
		if ( $internal ) {
			$tags->remove_attribute( 'target' );
			$tags->remove_attribute( 'rel' );
		}
	}
	return $tags->get_updated_html();
}

add_filter( 'the_content', 'gn_rewrite_content_links', 100 );
