<?php
/**
 * Shared helpers for the GraphQL fields ghoroa-core registers itself.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Site-time 'Y-m-d H:i:s' → RFC 3339 with the site offset, e.g. 2026-10-03T09:00:00+06:00 (§4.1).
 */
function gn_graphql_date( ?string $local ): ?string {
	if ( ! $local || '0000-00-00 00:00:00' === $local ) {
		return null;
	}
	$date = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $local, wp_timezone() );
	return $date ? $date->format( DATE_RFC3339 ) : null;
}

/**
 * HTML or entity-encoded text → plain text with collapsed whitespace; null when empty.
 */
function gn_plain_text( ?string $html ): ?string {
	$text = html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	return '' === $text ? null : $text;
}

/**
 * The ID SCF reads values from for the node behind an ACF field-group type (handles previews).
 *
 * @param array $root Root passed by WPGraphQL for ACF to field-group fields: [ 'node' => Model ].
 */
function gn_graphql_acf_id( $root ): ?int {
	$node = is_array( $root ) ? ( $root['node'] ?? null ) : null;
	if ( ! $node ) {
		return null;
	}
	if ( class_exists( '\WPGraphQL\Acf\Utils' ) ) {
		$id = \WPGraphQL\Acf\Utils::get_node_acf_id( $node );
		return is_numeric( $id ) ? (int) $id : null;
	}
	return isset( $node->databaseId ) ? (int) $node->databaseId : null;
}

/**
 * Keeps only IDs of published posts of the given type, in order.
 *
 * @param mixed $ids Raw SCF value (ID, array of IDs, or empty).
 * @return int[]
 */
function gn_published_ids( $ids, string $type ): array {
	$out = array();
	foreach ( array_filter( (array) $ids ) as $id ) {
		$post = get_post( (int) $id );
		if ( $post && $type === $post->post_type && 'publish' === $post->post_status ) {
			$out[] = $post->ID;
		}
	}
	return $out;
}
