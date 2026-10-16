<?php
/**
 * Preview on the Astro site (spec §10.2).
 *
 * The editor's Preview button opens GN_PUBLIC_URL/preview/?type=<post type>&id=<id>&token=<token>.
 * The Astro preview route verifies the token with the same secret and fetches the draft through
 * GraphQL as the gn_build user (roles.php).
 *
 * Token: "<expires>.<signature>"
 *   expires    Unix time, 10 minutes after the link was made
 *   signature  hex HMAC-SHA256 of "<id>.<expires>" with GN_PREVIEW_SECRET
 *
 * Without GN_PREVIEW_SECRET (or GN_PUBLIC_URL) WordPress's own preview is kept.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

const GN_PREVIEW_TTL = 600;

/**
 * Signed preview token for a post, valid for GN_PREVIEW_TTL seconds.
 */
function gn_preview_token( int $post_id, ?int $expires = null ): string {
	$expires ??= time() + GN_PREVIEW_TTL;
	return $expires . '.' . hash_hmac( 'sha256', "{$post_id}.{$expires}", gn_preview_secret() );
}

/**
 * True when the token is valid for this post and not expired (the Astro route does the same check).
 */
function gn_verify_preview_token( int $post_id, string $token ): bool {
	[ $expires, $signature ] = array_pad( explode( '.', $token, 2 ), 2, '' );
	if ( '' === gn_preview_secret() || ! ctype_digit( $expires ) || (int) $expires < time() ) {
		return false;
	}
	return hash_equals( hash_hmac( 'sha256', "{$post_id}.{$expires}", gn_preview_secret() ), $signature );
}

add_filter(
	'preview_post_link',
	static function ( string $link, WP_Post $post ): string {
		if ( '' === gn_preview_secret() || '' === gn_public_url() || ! in_array( $post->post_type, GN_CHECKED_TYPES, true ) ) {
			return $link;
		}
		return add_query_arg(
			array(
				'type'  => $post->post_type,
				'id'    => $post->ID,
				'token' => gn_preview_token( $post->ID ),
			),
			gn_public_url() . '/preview/'
		);
	},
	20,
	2
);

// The build bot may also read the autosave of a published post, which is what a preview of
// unsaved changes shows (WPGraphQL: asPreview: true).
add_filter(
	'graphql_data_is_private',
	static function ( $is_private, $model_name, $data ) {
		if ( $is_private && 'PostObject' === $model_name && $data instanceof WP_Post && 'revision' === $data->post_type
			&& current_user_can( 'gn_read_drafts' ) && in_array( get_post_type( $data->post_parent ), GN_CHECKED_TYPES, true ) ) {
			return false;
		}
		return $is_private;
	},
	10,
	3
);
