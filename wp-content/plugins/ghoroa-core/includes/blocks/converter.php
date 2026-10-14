<?php
/**
 * Converts `.gn-prose` article HTML (the §5 vocabulary, e.g. docs/reference/thankuni-patar-upokarita.html)
 * into block markup: the reverse of what the blocks render. Used by the seed importer.
 *
 * Unknown top-level elements throw, so nothing is silently dropped.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * HTML → blocks.
 */
class GN_Block_Converter {

	/**
	 * @throws RuntimeException On markup outside the §5 vocabulary.
	 */
	public static function convert( string $html ): string {
		$root   = gn_dom_fragment( $html );
		$blocks = array();
		foreach ( $root->childNodes as $node ) {
			$block = self::node( $node );
			if ( null !== $block ) {
				$blocks[] = $block;
			}
		}
		return implode( "\n\n", $blocks );
	}

	private static function node( DOMNode $node ): ?string {
		if ( $node instanceof DOMComment ) {
			if ( 'gn-ad' === trim( $node->data ) ) {
				return GN_Block_Markup::acf( 'ad-slot' );
			}
			throw new RuntimeException( "Unknown HTML comment: <!--{$node->data}-->" );
		}
		if ( $node instanceof DOMText ) {
			if ( '' === trim( $node->data ) ) {
				return null;
			}
			throw new RuntimeException( 'Loose text outside a block: ' . mb_substr( trim( $node->data ), 0, 40 ) );
		}
		if ( ! $node instanceof DOMElement ) {
			return null;
		}

		$class = ' ' . $node->getAttribute( 'class' ) . ' ';
		switch ( true ) {
			case 'p' === $node->tagName:
				return GN_Block_Markup::paragraph( gn_dom_inner( $node ) );

			case 'h2' === $node->tagName:
				return GN_Block_Markup::heading( 2, gn_dom_inner( $node ), $node->getAttribute( 'id' ) );

			case 'h3' === $node->tagName && self::first( $node, 'span', 'gn-num' ):
				$spans = $node->getElementsByTagName( 'span' );
				return GN_Block_Markup::acf( 'numbered-heading', array( 'text' => gn_dom_inner( $spans->item( 1 ) ) ) );

			case 'h3' === $node->tagName:
				return GN_Block_Markup::heading( 3, gn_dom_inner( $node ), $node->getAttribute( 'id' ) );

			case in_array( $node->tagName, array( 'ul', 'ol' ), true ):
				return GN_Block_Markup::list( self::items( $node ), 'ol' === $node->tagName );

			case 'blockquote' === $node->tagName:
				$cite = $node->getElementsByTagName( 'cite' )->item( 0 );
				$cite_html = gn_dom_inner( $cite );
				if ( $cite ) {
					$node->removeChild( $cite );
				}
				return GN_Block_Markup::quote( gn_dom_inner( $node ), $cite_html );

			case str_contains( $class, ' gn-takeaways ' ):
				return GN_Block_Markup::acf(
					'takeaways',
					array( 'items' => array_map( static fn( $li ) => array( 'text' => $li ), self::items( self::first( $node, 'ul' ) ) ) )
				);

			case str_contains( $class, ' gn-table-wrap ' ):
				$head = array();
				$rows = array();
				foreach ( $node->getElementsByTagName( 'tr' ) as $tr ) {
					$cells = array();
					foreach ( $tr->childNodes as $cell ) {
						if ( $cell instanceof DOMElement ) {
							$cells[] = gn_dom_inner( $cell );
						}
					}
					if ( 'thead' === $tr->parentNode->nodeName ) {
						$head = $cells;
					} else {
						$rows[] = $cells;
					}
				}
				return GN_Block_Markup::table( $head, $rows, gn_dom_inner( self::first( $node, 'figcaption' ) ) );

			case str_contains( $class, ' gn-recipe ' ):
				$facts = array_map( static fn( $s ) => (int) $s->textContent, iterator_to_array( self::first( $node, 'div', 'gn-recipe__facts' )->getElementsByTagName( 'span' ) ) );
				$lists = $node->getElementsByTagName( 'li' );
				$ingredients = array();
				$steps       = array();
				foreach ( $lists as $li ) {
					if ( 'ul' === $li->parentNode->nodeName ) {
						$spans         = $li->getElementsByTagName( 'span' );
						$ingredients[] = array(
							'name'   => trim( $spans->item( 0 )->textContent ),
							'amount' => trim( $spans->item( 1 )->textContent ),
						);
					} else {
						$steps[] = array( 'text' => gn_dom_inner( $li ) );
					}
				}
				return GN_Block_Markup::acf(
					'recipe',
					array(
						'title'       => trim( self::first( $node, 'h3' )->textContent ),
						'minutes'     => $facts[0] ?? '',
						'servings'    => $facts[1] ?? '',
						'ingredients' => $ingredients,
						'steps'       => $steps,
					)
				);

			case str_contains( $class, ' gn-callout--tip ' ):
				[ $title, $body ] = self::callout( $node );
				return GN_Block_Markup::acf( 'callout-tip', array( 'title' => $title, 'text' => gn_dom_inner( $body ) ) );

			case str_contains( $class, ' gn-callout--caution ' ):
				[ $title, $body ] = self::callout( $node );
				$list  = self::first( $body, 'ul' );
				$items = $list ? self::items( $list ) : array();
				if ( $list ) {
					$body->removeChild( $list );
				}
				return GN_Block_Markup::acf(
					'callout-caution',
					array(
						'title'   => $title,
						'items'   => array_map( static fn( $li ) => array( 'text' => $li ), $items ),
						'closing' => gn_dom_inner( $body ),
					)
				);

			case str_contains( $class, ' gn-refs ' ):
				$refs = array();
				foreach ( self::first( $node, 'ol' )->getElementsByTagName( 'li' ) as $li ) {
					$link = $li->getElementsByTagName( 'a' )->item( 0 );
					$row  = array(
						'url'       => $link ? $link->getAttribute( 'href' ) : '',
						'link_text' => $link ? trim( $link->textContent ) : '',
					);
					if ( $link ) {
						$li->removeChild( $link );
					}
					$refs[] = array( 'text' => trim( $li->textContent ) ) + $row;
				}
				return GN_Block_Markup::acf( 'references', array( 'refs' => $refs ) );
		}

		throw new RuntimeException( "No block for <{$node->tagName} class=\"" . trim( $class ) . '">' );
	}

	/**
	 * Title text and the body element (title removed) of a callout's inner <div>.
	 *
	 * @return array{0: string, 1: DOMElement}
	 */
	private static function callout( DOMElement $node ): array {
		$body  = self::first( $node, 'div' );
		$title = self::first( $body, 'span', 'gn-callout__title' );
		$text  = $title ? trim( $title->textContent ) : '';
		if ( $title ) {
			$body->removeChild( $title );
		}
		return array( $text, $body );
	}

	/**
	 * Inner HTML of each direct <li> child.
	 *
	 * @return string[]
	 */
	private static function items( ?DOMElement $list ): array {
		$items = array();
		foreach ( $list ? $list->childNodes : array() as $li ) {
			if ( $li instanceof DOMElement && 'li' === $li->tagName ) {
				$items[] = gn_dom_inner( $li );
			}
		}
		return $items;
	}

	/**
	 * First descendant with the tag (and class, if given).
	 */
	private static function first( DOMElement $node, string $tag, string $class = '' ): ?DOMElement {
		foreach ( $node->getElementsByTagName( $tag ) as $el ) {
			if ( '' === $class || str_contains( ' ' . $el->getAttribute( 'class' ) . ' ', " {$class} " ) ) {
				return $el;
			}
		}
		return null;
	}
}
