<?php
/**
 * Plain-text excerpt and caption (decision D3 in docs/astro-handoff.md).
 *
 * WPGraphQL returns `excerpt` and `caption` as HTML (`<p>…</p>\n`), and their `format: RAW` is null
 * for anonymous clients such as the Astro build. These fields return plain text instead:
 * - excerptText on every type with an excerpt (Post, Video): card excerpt / video description.
 * - captionText on MediaItem: the article lead caption (`leadCaption`).
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'graphql_register_types',
	static function () {
		register_graphql_field(
			'NodeWithExcerpt',
			'excerptText',
			array(
				'type'        => 'String',
				'description' => 'The excerpt as plain text (no HTML). Falls back to the start of the content when no excerpt is set.',
				'resolve'     => static function ( $node ) {
					$post = get_post( (int) ( $node->databaseId ?? 0 ) );
					if ( ! $post || post_password_required( $post ) ) {
						return null;
					}
					if ( has_excerpt( $post ) ) {
						return gn_plain_text( $post->post_excerpt );
					}
					return gn_plain_text( wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 40, '…' ) );
				},
			)
		);

		register_graphql_field(
			'MediaItem',
			'captionText',
			array(
				'type'        => 'String',
				'description' => 'The caption as plain text (no HTML).',
				'resolve'     => static function ( $node ) {
					$post = get_post( (int) ( $node->databaseId ?? 0 ) );
					return $post ? gn_plain_text( $post->post_excerpt ) : null;
				},
			)
		);
	}
);
