<?php
/**
 * YouTube thumbnail fallback for videos without a featured image (spec §4.6).
 *
 * The fallback is resolved when the video is saved rather than in the GraphQL resolver. The
 * YouTube thumbnail is downloaded into the media library and set as the featured image. That way
 * `featuredImage { node { sourceUrl(size: GN_CARD) altText } }` keeps its shape, gets the §4.1 crops
 * and WebP, and is served from our own uploads (§3.1) like every other image.
 *
 * - An image chosen by an editor always wins and is never replaced.
 * - A downloaded thumbnail is marked with GN_YOUTUBE_THUMB_META; when the YouTube ID changes it is
 *   swapped for the new video's thumbnail, and when the ID is cleared it is removed.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

const GN_YOUTUBE_THUMB_META = '_gn_youtube_thumb';

/**
 * Brings a video's fallback thumbnail in line with its YouTube ID.
 *
 * @return string What happened, for logs.
 */
function gn_sync_youtube_thumbnail( int $post_id ): string {
	if ( 'video' !== get_post_type( $post_id ) ) {
		return 'not a video';
	}
	$youtube = (string) get_field( 'youtube_id', $post_id, false );
	$thumb   = (int) get_post_thumbnail_id( $post_id );
	$auto    = $thumb ? (string) get_post_meta( $thumb, GN_YOUTUBE_THUMB_META, true ) : '';

	if ( $thumb && '' === $auto ) {
		return 'editor image kept';
	}
	if ( '' === $youtube ) {
		if ( $thumb ) {
			delete_post_thumbnail( $post_id );
			return 'fallback removed (no YouTube ID)';
		}
		return 'no YouTube ID';
	}
	if ( $auto === $youtube ) {
		return 'up to date';
	}

	$existing = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'meta_key'    => GN_YOUTUBE_THUMB_META,
			'meta_value'  => $youtube,
			'fields'      => 'ids',
			'numberposts' => 1,
		)
	);
	$attachment = (int) ( $existing[0] ?? 0 );
	if ( ! $attachment ) {
		$attachment = gn_download_youtube_thumbnail( $youtube, $post_id );
	}
	if ( ! $attachment ) {
		return 'download failed';
	}
	set_post_thumbnail( $post_id, $attachment );
	return 'fallback set';
}

/**
 * Downloads the best available YouTube thumbnail into the media library. Returns the attachment ID or 0.
 */
function gn_download_youtube_thumbnail( string $youtube, int $post_id ): int {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	// maxres (1280×720) isn't generated for every video; sd and hq are letterboxed 4:3, so they come last.
	foreach ( array( 'maxresdefault', 'sddefault', 'hqdefault' ) as $variant ) {
		$tmp = download_url( "https://i.ytimg.com/vi/{$youtube}/{$variant}.jpg", 15 );
		if ( is_wp_error( $tmp ) ) {
			continue;
		}
		$id = media_handle_sideload(
			array(
				'name'     => "youtube-{$youtube}.jpg",
				'tmp_name' => $tmp,
			),
			$post_id,
			get_the_title( $post_id )
		);
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			continue;
		}
		update_post_meta( $id, GN_YOUTUBE_THUMB_META, $youtube );
		update_post_meta( $id, '_wp_attachment_image_alt', get_the_title( $post_id ) );
		return (int) $id;
	}
	return 0;
}

// After SCF has saved the fields (priority 10), from the admin or REST.
add_action(
	'acf/save_post',
	static function ( $post_id ) {
		if ( is_numeric( $post_id ) && ! wp_is_post_revision( (int) $post_id ) ) {
			gn_sync_youtube_thumbnail( (int) $post_id );
		}
	},
	20
);
