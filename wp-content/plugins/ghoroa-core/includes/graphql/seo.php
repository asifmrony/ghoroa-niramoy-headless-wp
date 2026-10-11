<?php
/**
 * gnSeo on Post, Page, Video, Category and Tag (spec §9).
 *
 * Reads Rank Math's stored meta directly and resolves its %variables% with Rank Math's own helper,
 * so we don't depend on a third-party GraphQL bridge. Every field is nullable; Astro falls back to
 * its own title, dek and featured image.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * SEO values for a post or term.
 *
 * @param WP_Post|WP_Term $object Content object.
 * @return array{title: ?string, description: ?string, canonical: ?string, noindex: bool, ogTitle: ?string, ogDescription: ?string, ogImage: ?string}
 */
function gn_seo( $object ): array {
	// Rank Math sets up its variables on the `wp` hook, which GraphQL and WP-CLI requests never reach.
	if ( function_exists( 'rank_math' ) && isset( rank_math()->variables ) ) {
		rank_math()->variables->setup();
	}
	$is_term = $object instanceof WP_Term;
	$meta    = static fn( string $key ) => $is_term
		? get_term_meta( $object->term_id, $key, true )
		: get_post_meta( $object->ID, $key, true );
	$text    = static function ( string $key ) use ( $meta, $object ): ?string {
		$value = trim( (string) $meta( $key ) );
		if ( '' !== $value && class_exists( '\RankMath\Helper' ) ) {
			$value = \RankMath\Helper::replace_vars( $value, $object );
		}
		return gn_plain_text( $value );
	};

	$canonical = trim( (string) $meta( 'rank_math_canonical_url' ) );
	$image     = trim( (string) $meta( 'rank_math_facebook_image' ) );

	return array(
		'title'         => $text( 'rank_math_title' ),
		'description'   => $text( 'rank_math_description' ),
		'canonical'     => '' !== $canonical ? $canonical : gn_public_permalink( $object ),
		'noindex'       => in_array( 'noindex', (array) $meta( 'rank_math_robots' ), true ),
		'ogTitle'       => $text( 'rank_math_facebook_title' ),
		'ogDescription' => $text( 'rank_math_facebook_description' ),
		'ogImage'       => '' !== $image ? $image : null,
	);
}

add_action(
	'graphql_register_types',
	static function () {
		register_graphql_object_type(
			'GnSeo',
			array(
				'description' => 'SEO fields from Rank Math (§9). All nullable: Astro falls back to its own values.',
				'fields'      => array(
					'title'         => array(
						'type'        => 'String',
						'description' => 'SEO title with Rank Math variables resolved.',
					),
					'description'   => array( 'type' => 'String' ),
					'canonical'     => array(
						'type'        => 'String',
						'description' => 'Rank Math canonical, else the public Astro URL (§6).',
					),
					'noindex'       => array( 'type' => 'Boolean' ),
					'ogTitle'       => array( 'type' => 'String' ),
					'ogDescription' => array( 'type' => 'String' ),
					'ogImage'       => array(
						'type'        => 'String',
						'description' => 'Absolute image URL.',
					),
				),
			)
		);

		$post_seo = static function ( $node ) {
			$post = get_post( (int) $node->databaseId );
			return $post ? gn_seo( $post ) : null;
		};
		$term_seo = static function ( $node ) {
			$term = get_term( (int) $node->databaseId );
			return $term instanceof WP_Term ? gn_seo( $term ) : null;
		};

		foreach ( array( 'Post', 'Page', 'Video' ) as $type ) {
			register_graphql_field( $type, 'gnSeo', array( 'type' => 'GnSeo', 'resolve' => $post_seo ) );
		}
		foreach ( array( 'Category', 'Tag' ) as $type ) {
			register_graphql_field( $type, 'gnSeo', array( 'type' => 'GnSeo', 'resolve' => $term_seo ) );
		}
	}
);
