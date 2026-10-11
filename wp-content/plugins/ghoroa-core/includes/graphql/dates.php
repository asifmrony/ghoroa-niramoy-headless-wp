<?php
/**
 * Dates with the site offset (spec §4.1, decision D2 in docs/astro-handoff.md).
 *
 * WPGraphQL returns `date` and `modified` in site time without an offset (2026-10-02T08:00:00).
 * This returns them as RFC 3339 with the offset (2026-10-02T08:00:00+06:00) for every post type,
 * page and media item. `dateGmt` and `modifiedGmt` are left as WPGraphQL returns them.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'graphql_model_prepare_fields',
	static function ( $fields, $model_name, $data ) {
		if ( ! $data instanceof WP_Post || ! is_array( $fields ) ) {
			return $fields;
		}
		foreach ( array(
			'date'     => 'post_date',
			'modified' => 'post_modified',
		) as $field => $column ) {
			if ( isset( $fields[ $field ] ) ) {
				$fields[ $field ] = static fn() => gn_graphql_date( $data->{$column} );
			}
		}
		return $fields;
	},
	10,
	3
);
