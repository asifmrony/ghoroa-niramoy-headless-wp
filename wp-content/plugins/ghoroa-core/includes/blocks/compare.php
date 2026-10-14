<?php
/**
 * Structural HTML comparison for the §5 acceptance test: "compare by DOM, not string".
 *
 * Compared: element names and nesting, class lists (any order), id, href, comments and text
 * (whitespace collapsed). Ignored: whitespace between tags and every other attribute (e.g. the
 * spec's aria-hidden on callout icons, which the reference file omits).
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Flattens HTML into structural tokens.
 *
 * @return string[]
 */
function gn_html_tokens( string $html ): array {
	$tokens = array();
	$walk   = static function ( DOMNode $node ) use ( &$walk, &$tokens ) {
		foreach ( $node->childNodes as $child ) {
			if ( $child instanceof DOMComment ) {
				$tokens[] = '<!--' . trim( $child->data ) . '-->';
			} elseif ( $child instanceof DOMText ) {
				$text = trim( (string) preg_replace( '/\s+/u', ' ', $child->data ) );
				if ( '' !== $text ) {
					$tokens[] = $text;
				}
			} elseif ( $child instanceof DOMElement ) {
				$attrs = '';
				$class = preg_split( '/\s+/', trim( $child->getAttribute( 'class' ) ), -1, PREG_SPLIT_NO_EMPTY );
				sort( $class );
				if ( $class ) {
					$attrs .= ' class="' . implode( ' ', $class ) . '"';
				}
				foreach ( array( 'id', 'href' ) as $name ) {
					if ( $child->hasAttribute( $name ) ) {
						$attrs .= " {$name}=\"" . $child->getAttribute( $name ) . '"';
					}
				}
				$tokens[] = "<{$child->tagName}{$attrs}>";
				$walk( $child );
				$tokens[] = "</{$child->tagName}>";
			}
		}
	};
	$walk( gn_dom_fragment( $html ) );
	return $tokens;
}

/**
 * Null when both are structurally identical, else a description of the first difference.
 */
function gn_html_difference( string $actual, string $expected ): ?string {
	$a = gn_html_tokens( $actual );
	$e = gn_html_tokens( $expected );
	$count = max( count( $a ), count( $e ) );
	for ( $i = 0; $i < $count; $i++ ) {
		if ( ( $a[ $i ] ?? null ) !== ( $e[ $i ] ?? null ) ) {
			$context = static fn( array $t ) => implode( ' ', array_slice( $t, max( 0, $i - 3 ), 7 ) );
			return sprintf( "token %d of %d/%d\n  got:      %s\n  expected: %s", $i, count( $a ), count( $e ), $context( $a ), $context( $e ) );
		}
	}
	return null;
}
