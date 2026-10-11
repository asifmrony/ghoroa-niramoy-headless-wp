<?php
/**
 * articleFields entries that WPGraphQL for ACF can't produce in the §4.9 shape
 * (decisions D1 and D2 in docs/astro-handoff.md). Their SCF fields have show_in_graphql off.
 *
 * - cardTag: a single Tag, not a connection.
 * - contentUpdated: RFC 3339 with the site offset. WPGraphQL for ACF parses the stored site time
 *   as UTC, which puts it 6 hours off.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'graphql_register_types',
	static function () {
		register_graphql_field(
			'ArticleFields',
			'cardTag',
			array(
				'type'        => 'Tag',
				'description' => 'Optional card label shown instead of the category (§4.5).',
				'resolve'     => static function ( $root, $args, $context ) {
					$id = gn_graphql_acf_id( $root );
					$tag = $id ? (int) get_field( 'card_tag', $id, false ) : 0;
					return $tag ? $context->get_loader( 'term' )->load_deferred( $tag ) : null;
				},
			)
		);

		register_graphql_field(
			'ArticleFields',
			'contentUpdated',
			array(
				'type'        => 'String',
				'description' => 'Set by hand for substantive updates only; RFC 3339 with the site offset, e.g. 2026-10-03T09:00:00+06:00 (§4.5).',
				'resolve'     => static function ( $root ) {
					$id = gn_graphql_acf_id( $root );
					return $id ? gn_graphql_date( (string) get_field( 'content_updated', $id, false ) ) : null;
				},
			)
		);
	},
	20
);
