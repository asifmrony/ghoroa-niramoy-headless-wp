<?php
/**
 * Post.readingMinutes (spec §4.5): words in the rendered content ÷ 180, rounded up, minimum 1.
 * articleFields.readingMinutesOverride wins when set.
 *
 * Words are runs of non-whitespace, which counts Bengali correctly (PHP's str_word_count doesn't).
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

const GN_WORDS_PER_MINUTE = 180;

/**
 * Reading time in whole minutes.
 */
function gn_reading_minutes( WP_Post $post ): int {
	$override = (int) get_field( 'reading_minutes_override', $post->ID, false );
	if ( $override > 0 ) {
		return $override;
	}
	$text  = html_entity_decode( wp_strip_all_tags( do_blocks( $post->post_content ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$words = preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
	return max( 1, (int) ceil( count( $words ) / GN_WORDS_PER_MINUTE ) );
}

add_action(
	'graphql_register_types',
	static function () {
		register_graphql_field(
			'Post',
			'readingMinutes',
			array(
				'type'        => array( 'non_null' => 'Int' ),
				'description' => 'Reading time in minutes: rendered words ÷ 180, rounded up, at least 1. articleFields.readingMinutesOverride wins when set.',
				'resolve'     => static function ( $node ) {
					$post = get_post( (int) $node->databaseId );
					return $post ? gn_reading_minutes( $post ) : 1;
				},
			)
		);
	}
);
