<?php
/**
 * Numbers and ids written into the content on save (spec §5.1, §5.2):
 * - every H2 gets a unique Latin id: the editor's own anchor, else s1, s2, … (duplicates get -2, -3);
 * - each gn/numbered-heading gets its number (restarting after every H2) and a unique id b1, b2, ….
 *   They're stored in the block's data (gn_number, gn_anchor) and rendered by its template.
 *
 * Runs on every save of a post or page, so the numbering always follows the current order.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Applies H2 ids and heading numbers to serialized block content.
 */
function gn_number_blocks( string $content ): string {
	if ( ! has_blocks( $content ) ) {
		return $content;
	}
	$blocks = parse_blocks( $content );
	$used   = array();
	$auto   = 0;
	$number = 0;
	$item   = 0;

	$unique = static function ( string $id ) use ( &$used ): string {
		$candidate = $id;
		for ( $n = 2; isset( $used[ $candidate ] ); $n++ ) {
			$candidate = "{$id}-{$n}";
		}
		$used[ $candidate ] = true;
		return $candidate;
	};

	foreach ( $blocks as &$block ) {
		if ( 'core/heading' === $block['blockName'] && 2 === (int) ( $block['attrs']['level'] ?? 2 ) ) {
			$number = 0;
			$html   = $block['innerHTML'];
			preg_match( '/<h2\b[^>]*\bid="([^"]*)"/', $html, $m );
			$id = sanitize_title( $m[1] ?? '' );
			// Ids must be Latin (§5.1); a Bengali anchor would come out percent-encoded, so it's replaced.
			$id = $unique( preg_match( '/^[a-z][a-z0-9-]*$/', $id ) ? $id : 's' . ( ++$auto ) );

			$tags = new WP_HTML_Tag_Processor( $html );
			if ( $tags->next_tag( 'h2' ) ) {
				$tags->set_attribute( 'id', $id );
				$html = $tags->get_updated_html();
			}
			$block['innerHTML']    = $html;
			$block['innerContent'] = array( $html );
		} elseif ( 'gn/numbered-heading' === $block['blockName'] ) {
			$block['attrs']['data']['gn_number'] = ++$number;
			$block['attrs']['data']['gn_anchor'] = $unique( 'b' . ( ++$item ) );
		}
	}
	unset( $block );

	return serialize_blocks( $blocks );
}

add_filter(
	'wp_insert_post_data',
	static function ( array $data ): array {
		if ( in_array( $data['post_type'] ?? '', array( 'post', 'page' ), true ) && ! empty( $data['post_content'] ) ) {
			$data['post_content'] = wp_slash( gn_number_blocks( wp_unslash( $data['post_content'] ) ) );
		}
		return $data;
	},
	20
);
