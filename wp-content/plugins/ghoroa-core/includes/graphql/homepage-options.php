<?php
/**
 * RootQuery.homepageOptions in the §4.9 shape (decision D1 in docs/astro-handoff.md):
 *
 *   homepageOptions { hero { ... on Post { slug } } videoPicks { ... on Video { slug } }
 *     popularPicks { ... on Post { slug } } promo { label text url } popularSearches { term } }
 *
 * Only published posts and videos are returned; picks keep the editor's order.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'graphql_register_types',
	static function () {
		register_graphql_object_type(
			'HomepageOptionsPromo',
			array(
				'description' => 'Header promo link (§4.7).',
				'fields'      => array(
					'label' => array( 'type' => 'String' ),
					'text'  => array( 'type' => 'String' ),
					'url'   => array(
						'type'        => 'String',
						'description' => 'Usually a site-relative path such as /chhadbagan/.',
					),
				),
			)
		);

		register_graphql_object_type(
			'HomepageOptionsPopularSearch',
			array(
				'description' => 'A chip in the search overlay (§4.7).',
				'fields'      => array(
					'term' => array( 'type' => 'String' ),
				),
			)
		);

		$posts = static fn( string $type ) => static function ( $root, $args, $context ) use ( $type ) {
			$key = 'video' === $type ? 'video_picks' : 'popular_picks';
			return array_map(
				static fn( $id ) => $context->get_loader( 'post' )->load_deferred( $id ),
				gn_published_ids( $root[ $key ] ?? array(), $type )
			);
		};

		register_graphql_object_type(
			'HomepageOptions',
			array(
				'description' => 'Homepage options edited under "হোমপেজ" in the admin (§4.7).',
				'fields'      => array(
					'hero'            => array(
						'type'        => 'Post',
						'description' => 'The HeroFeature article. Null if it is not published.',
						'resolve'     => static function ( $root, $args, $context ) {
							$id = gn_published_ids( $root['hero'] ?? null, 'post' )[0] ?? null;
							return $id ? $context->get_loader( 'post' )->load_deferred( $id ) : null;
						},
					),
					'videoPicks'      => array(
						'type'        => array( 'list_of' => 'Video' ),
						'description' => 'Ghoroa Originals band, max 8, in order. Empty means "newest 6".',
						'resolve'     => $posts( 'video' ),
					),
					'popularPicks'    => array(
						'type'        => array( 'list_of' => 'Post' ),
						'description' => '"জনপ্রিয়" rail on article pages, max 4, in order.',
						'resolve'     => $posts( 'post' ),
					),
					'promo'           => array(
						'type'    => 'HomepageOptionsPromo',
						'resolve' => static function ( $root ) {
							$promo = ( $root['promo'] ?? null ) ?: array();
							return array_filter( $promo ) ? $promo : null;
						},
					),
					'popularSearches' => array(
						'type'        => array( 'list_of' => 'HomepageOptionsPopularSearch' ),
						'description' => 'Max 6.',
						'resolve'     => static fn( $root ) => array_values( ( $root['popular_searches'] ?? null ) ?: array() ),
					),
				),
			)
		);

		register_graphql_field(
			'RootQuery',
			'homepageOptions',
			array(
				'type'        => 'HomepageOptions',
				'description' => 'Homepage options (§4.7).',
				'resolve'     => static function () {
					$values = array();
					foreach ( array( 'hero', 'video_picks', 'popular_picks', 'promo', 'popular_searches' ) as $name ) {
						$values[ $name ] = get_field( $name, 'option', 'promo' === $name || 'popular_searches' === $name );
					}
					return $values;
				},
			)
		);
	}
);
