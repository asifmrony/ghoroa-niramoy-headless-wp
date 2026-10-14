<?php
/**
 * Front-end output of the core blocks we allow, rewritten to the §5 markup:
 * - heading, paragraph, list, list-item: no classes or inline styles (§5.1, §5.12). H2 ids are set on save (numbering.php).
 * - quote → <blockquote>text<cite>…</cite></blockquote>, no inner <p>, no class (§5.4).
 * - table → <figure class="gn-table-wrap"><table>…</table><figcaption>…</figcaption></figure>, one header
 *   row, class="num" on numeric columns (§5.5). This is the "gn/table" of the spec (decision M4-a).
 * - image → <figure class="gn-figure"><img … loading="lazy" srcset sizes><figcaption>caption <span>credit</span></figcaption></figure> (§5.10).
 *
 * Only what WordPress renders changes (GraphQL `content`); what the editor saves stays core markup.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Loads an HTML fragment into a DOM; returns the wrapper element holding it.
 */
function gn_dom_fragment( string $html ): DOMElement {
	$dom = new DOMDocument();
	libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	return $dom->getElementsByTagName( 'div' )->item( 0 );
}

/**
 * Inner HTML of a DOM node.
 */
function gn_dom_inner( ?DOMNode $node ): string {
	if ( ! $node ) {
		return '';
	}
	$html = '';
	foreach ( $node->childNodes as $child ) {
		$html .= $node->ownerDocument->saveHTML( $child );
	}
	return trim( $html );
}

/**
 * Removes class and style from the block's outer tag.
 */
function gn_strip_outer_classes( string $html ): string {
	$tags = new WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag() ) {
		$tags->remove_attribute( 'class' );
		$tags->remove_attribute( 'style' );
	}
	return $tags->get_updated_html();
}

foreach ( array( 'core/heading', 'core/paragraph', 'core/list', 'core/list-item' ) as $gn_block ) {
	add_filter( "render_block_{$gn_block}", 'gn_strip_outer_classes' );
}

add_filter(
	'render_block_core/quote',
	static function ( string $html ): string {
		$root  = gn_dom_fragment( $html );
		$quote = $root->getElementsByTagName( 'blockquote' )->item( 0 );
		if ( ! $quote ) {
			return $html;
		}
		$cite = '';
		$text = array();
		foreach ( iterator_to_array( $quote->childNodes ) as $child ) {
			if ( $child instanceof DOMElement && 'cite' === $child->tagName ) {
				$cite = gn_dom_inner( $child );
			} elseif ( $child instanceof DOMElement && 'p' === $child->tagName ) {
				$text[] = gn_dom_inner( $child );
			} elseif ( '' !== trim( $child->textContent ) ) {
				$text[] = trim( $quote->ownerDocument->saveHTML( $child ) );
			}
		}
		return '<blockquote>' . implode( ' ', $text ) . ( '' !== $cite ? "<cite>{$cite}</cite>" : '' ) . '</blockquote>';
	}
);

/**
 * True when a table cell holds a Latin-numeral quantity such as "≈ 48 mg", "5.6", "10–20 %" (§5.5).
 */
function gn_is_numeric_cell( string $text ): bool {
	return (bool) preg_match( '/^[≈~<>±+−–-]?\s*\d[\d.,]*\s*(?:[–-]\s*\d[\d.,]*)?\s*[a-zA-Zµ°%\/]*$/u', $text );
}

add_filter(
	'render_block_core/table',
	static function ( string $html ): string {
		$root  = gn_dom_fragment( $html );
		$table = $root->getElementsByTagName( 'table' )->item( 0 );
		if ( ! $table ) {
			return $html;
		}

		$cell_align = static function ( DOMElement $cell ): string {
			$align = $cell->getAttribute( 'data-align' );
			if ( '' === $align && preg_match( '/has-text-align-(left|center|right)/', $cell->getAttribute( 'class' ), $m ) ) {
				$align = $m[1];
			}
			return $align;
		};

		$head = array();
		$rows = array();
		foreach ( $table->getElementsByTagName( 'tr' ) as $tr ) {
			$cells = array();
			foreach ( $tr->childNodes as $cell ) {
				if ( $cell instanceof DOMElement && in_array( $cell->tagName, array( 'th', 'td' ), true ) ) {
					$cells[] = array(
						'html'  => gn_dom_inner( $cell ),
						'text'  => trim( html_entity_decode( $cell->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ),
						'align' => $cell_align( $cell ),
					);
				}
			}
			$section = $tr->parentNode instanceof DOMElement ? $tr->parentNode->tagName : 'tbody';
			if ( 'thead' === $section ) {
				$head = $head ?: $cells; // One header row (§5.5).
			} elseif ( 'tbody' === $section || 'table' === $section ) {
				$rows[] = $cells;
			}
		}

		// Numeric columns: every filled body cell is a Latin-numeral quantity. Editors can override per
		// column with the table's column alignment: right = numeric, left = not numeric.
		$numeric = array();
		$columns = max( count( $head ), ...array_map( 'count', $rows ?: array( array() ) ) );
		for ( $i = 0; $i < $columns; $i++ ) {
			$column = array_filter( array_map( static fn( $row ) => $row[ $i ] ?? null, $rows ) );
			$aligns = array_unique( array_filter( array_column( $column, 'align' ) ) );
			if ( in_array( 'right', $aligns, true ) ) {
				$numeric[ $i ] = true;
			} elseif ( in_array( 'left', $aligns, true ) ) {
				$numeric[ $i ] = false;
			} else {
				$filled        = array_filter( $column, static fn( $c ) => '' !== $c['text'] );
				$numeric[ $i ] = $filled && count( $filled ) === count( array_filter( $filled, static fn( $c ) => gn_is_numeric_cell( $c['text'] ) ) );
			}
		}

		$out = '<figure class="gn-table-wrap"><table>';
		if ( $head ) {
			$out .= '<thead><tr>' . implode( '', array_map( static fn( $c ) => "<th>{$c['html']}</th>", $head ) ) . '</tr></thead>';
		}
		$out .= '<tbody>';
		foreach ( $rows as $row ) {
			$out .= '<tr>';
			foreach ( $row as $i => $cell ) {
				$out .= ( $numeric[ $i ] ?? false ) ? "<td class=\"num\">{$cell['html']}</td>" : "<td>{$cell['html']}</td>";
			}
			$out .= '</tr>';
		}
		$out    .= '</tbody></table>';
		$caption = gn_dom_inner( $root->getElementsByTagName( 'figcaption' )->item( 0 ) );
		return $out . ( '' !== $caption ? "<figcaption>{$caption}</figcaption>" : '' ) . '</figure>';
	}
);

// Keep the §5.10 <img> exactly as specified: WordPress would otherwise add decoding="async" and
// prefix sizes with "auto, " when it filters content images.
add_filter( 'wp_img_tag_add_auto_sizes', '__return_false' );
add_filter( 'wp_img_tag_add_decoding_attr', '__return_false' );

add_filter(
	'render_block_core/image',
	static function ( string $html, array $block ): string {
		$root = gn_dom_fragment( $html );
		$img  = $root->getElementsByTagName( 'img' )->item( 0 );
		if ( ! $img ) {
			return $html;
		}
		$id      = (int) ( $block['attrs']['id'] ?? 0 );
		$alt     = $img->getAttribute( 'alt' );
		$caption = gn_dom_inner( $root->getElementsByTagName( 'figcaption' )->item( 0 ) );
		$src     = $img->getAttribute( 'src' );
		$width   = $img->getAttribute( 'width' );
		$height  = $img->getAttribute( 'height' );
		$srcset  = '';
		$credit  = '';

		if ( $id && wp_attachment_is_image( $id ) ) {
			$image = wp_get_attachment_image_src( $id, 'large' );
			if ( $image ) {
				[ $src, $width, $height ] = $image;
			}
			$srcset = (string) wp_get_attachment_image_srcset( $id, 'large' );
			$alt    = '' !== $alt ? $alt : (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
			$credit = (string) get_field( 'credit', $id );
		}

		$attrs = sprintf( 'src="%s" alt="%s"', esc_url( $src ), esc_attr( $alt ) );
		if ( $width && $height ) {
			$attrs .= sprintf( ' width="%d" height="%d"', $width, $height );
		}
		$attrs .= ' loading="lazy"';
		if ( '' !== $srcset ) {
			$attrs .= sprintf( ' srcset="%s" sizes="(max-width: 720px) 100vw, 680px"', esc_attr( $srcset ) );
		}

		$figcaption = '';
		if ( '' !== $caption || '' !== $credit ) {
			$figcaption = '<figcaption>' . $caption . ( '' !== $credit ? ( '' !== $caption ? ' ' : '' ) . '<span>' . esc_html( $credit ) . '</span>' : '' ) . '</figcaption>';
		}
		return "<figure class=\"gn-figure\"><img {$attrs}>{$figcaption}</figure>";
	},
	10,
	2
);
