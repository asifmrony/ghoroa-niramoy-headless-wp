<?php
/**
 * What queues a rebuild (spec §10.1). Only changes the public site can show; draft saves don't.
 *
 * - posts, videos, pages: publish, update while published, unpublish, trash, delete; a scheduled
 *   post going live (future → publish)
 * - homepage options saved
 * - category or tag created, edited, deleted
 * - author profile edited (authors with published posts)
 * - image alt text, caption or credit edited, when published content uses the image
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * "post kalojira"-style label for the log.
 */
function gn_rebuild_label( WP_Post $post ): string {
	return $post->post_type . ' ' . ( '' !== $post->post_name ? urldecode( $post->post_name ) : "#{$post->ID}" );
}

add_action(
	'transition_post_status',
	static function ( string $new, string $old, WP_Post $post ) {
		if ( ! in_array( $post->post_type, GN_CHECKED_TYPES, true ) || ( 'publish' !== $new && 'publish' !== $old ) ) {
			return;
		}
		$event = match ( true ) {
			'publish' === $new && 'future' === $old  => 'scheduled post live',
			'publish' === $new && 'publish' === $old => 'update',
			'publish' === $new                       => 'publish',
			'trash' === $new                         => 'trash',
			default                                  => 'unpublish',
		};
		gn_queue_rebuild( $event, gn_rebuild_label( $post ) );
	},
	10,
	3
);

add_action(
	'before_delete_post',
	static function ( int $post_id, WP_Post $post ) {
		if ( in_array( $post->post_type, GN_CHECKED_TYPES, true ) && 'publish' === $post->post_status ) {
			gn_queue_rebuild( 'delete', gn_rebuild_label( $post ) );
		}
	},
	10,
	2
);

add_action(
	'acf/save_post',
	static function ( $post_id ) {
		if ( 'options' === $post_id || 'option' === $post_id ) {
			gn_queue_rebuild( 'homepage', 'options' );
		}
	},
	20
);

foreach ( array( 'created_term' => 'create', 'edited_term' => 'edit', 'delete_term' => 'delete' ) as $gn_hook => $gn_event ) {
	add_action(
		$gn_hook,
		static function ( $term_id, $tt_id, $taxonomy ) use ( $gn_event ) {
			if ( in_array( $taxonomy, array( 'category', 'post_tag' ), true ) ) {
				$term = get_term( (int) $term_id, $taxonomy );
				gn_queue_rebuild( $gn_event, ( 'post_tag' === $taxonomy ? 'tag ' : 'category ' ) . ( $term instanceof WP_Term ? $term->slug : "#{$term_id}" ) );
			}
		},
		10,
		3
	);
}

add_action(
	'profile_update',
	static function ( int $user_id ) {
		if ( count_user_posts( $user_id, 'post', true ) > 0 ) {
			gn_queue_rebuild( 'author profile', 'author ' . get_userdata( $user_id )->user_nicename );
		}
	}
);

/**
 * True when a published post, video or page shows this image (featured or inside the content).
 */
function gn_attachment_in_published_content( int $attachment_id ): bool {
	global $wpdb;
	$types = "'" . implode( "','", GN_CHECKED_TYPES ) . "'";
	// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed type list.
	$featured = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT 1 FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id
			 WHERE m.meta_key = '_thumbnail_id' AND m.meta_value = %d AND p.post_status = 'publish' AND p.post_type IN ({$types}) LIMIT 1",
			$attachment_id
		)
	);
	$inline = $featured ? null : $wpdb->get_var(
		$wpdb->prepare(
			"SELECT 1 FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ({$types})
			 AND ( post_content LIKE %s OR post_content LIKE %s ) LIMIT 1",
			'%"id":' . $attachment_id . '%',
			'%wp-image-' . $attachment_id . '"%'
		)
	);
	// phpcs:enable
	return (bool) ( $featured || $inline );
}

add_action(
	'attachment_updated',
	static function ( int $post_id, WP_Post $after, WP_Post $before ) {
		if ( $after->post_excerpt !== $before->post_excerpt && gn_attachment_in_published_content( $post_id ) ) {
			gn_queue_rebuild( 'image caption', "attachment #{$post_id}" );
		}
	},
	10,
	3
);

foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $gn_hook ) {
	add_action(
		$gn_hook,
		static function ( $meta_id, $object_id, $meta_key ) {
			if ( in_array( $meta_key, array( '_wp_attachment_image_alt', 'credit' ), true )
				&& 'attachment' === get_post_type( (int) $object_id ) && gn_attachment_in_published_content( (int) $object_id ) ) {
				gn_queue_rebuild( '_wp_attachment_image_alt' === $meta_key ? 'image alt' : 'image credit', "attachment #{$object_id}" );
			}
		},
		10,
		3
	);
}
