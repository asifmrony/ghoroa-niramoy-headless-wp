<?php
/**
 * Builds serialized block markup (what the block editor saves) for the seed importer and the
 * HTML → blocks converter. Core blocks are written exactly as their save() functions produce them,
 * so the editor opens them without "unexpected content" warnings.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Block markup builders.
 */
class GN_Block_Markup {

	public static function paragraph( string $inner ): string {
		return "<!-- wp:paragraph -->\n<p>{$inner}</p>\n<!-- /wp:paragraph -->";
	}

	/**
	 * @param int    $level 2 or 3.
	 * @param string $id    Anchor; required for H2 (§5.1), optional for H3.
	 */
	public static function heading( int $level, string $inner, string $id = '' ): string {
		$attrs = 2 === $level ? '' : ' ' . serialize_block_attributes( array( 'level' => $level ) );
		$id    = '' !== $id ? ' id="' . esc_attr( $id ) . '"' : '';
		return "<!-- wp:heading{$attrs} -->\n<h{$level} class=\"wp-block-heading\"{$id}>{$inner}</h{$level}>\n<!-- /wp:heading -->";
	}

	/**
	 * @param string[] $items Inner HTML of each <li>.
	 */
	public static function list( array $items, bool $ordered = false ): string {
		$tag   = $ordered ? 'ol' : 'ul';
		$attrs = $ordered ? ' ' . serialize_block_attributes( array( 'ordered' => true ) ) : '';
		$lis   = array_map( static fn( $li ) => "<!-- wp:list-item -->\n<li>{$li}</li>\n<!-- /wp:list-item -->", $items );
		return "<!-- wp:list{$attrs} -->\n<{$tag} class=\"wp-block-list\">" . implode( "\n\n", $lis ) . "</{$tag}>\n<!-- /wp:list -->";
	}

	public static function quote( string $text, string $cite = '' ): string {
		$cite = '' !== $cite ? "<cite>{$cite}</cite>" : '';
		return "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\">" . self::paragraph( $text ) . "{$cite}</blockquote>\n<!-- /wp:quote -->";
	}

	/**
	 * Core table (rendered as the §5.5 table; numeric columns are detected at render time).
	 *
	 * @param string[]   $head    Header cells (inner HTML).
	 * @param string[][] $rows    Body rows of cells (inner HTML).
	 * @param string     $caption Optional caption.
	 */
	public static function table( array $head, array $rows, string $caption = '' ): string {
		$thead = '<thead><tr>' . implode( '', array_map( static fn( $c ) => "<th>{$c}</th>", $head ) ) . '</tr></thead>';
		$tbody = '<tbody>' . implode(
			'',
			array_map( static fn( $row ) => '<tr>' . implode( '', array_map( static fn( $c ) => "<td>{$c}</td>", $row ) ) . '</tr>', $rows )
		) . '</tbody>';
		$cap   = '' !== $caption ? "<figcaption class=\"wp-element-caption\">{$caption}</figcaption>" : '';
		return "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table class=\"has-fixed-layout\">{$thead}{$tbody}</table>{$cap}</figure>\n<!-- /wp:table -->";
	}

	/**
	 * A custom gn/* ACF block with its field values.
	 *
	 * @param string $slug   Block slug, e.g. 'takeaways' for gn/takeaways.
	 * @param array  $values Field name => value; a list of rows for repeaters.
	 */
	public static function acf( string $slug, array $values = array() ): string {
		$attrs = array(
			'name' => "gn/{$slug}",
			'data' => self::acf_data( $slug, $values ),
			'mode' => 'preview',
		);
		return "<!-- wp:gn/{$slug} " . serialize_block_attributes( $attrs ) . ' /-->';
	}

	/**
	 * Field values in the flat shape SCF stores in a block's `data` (name => value, _name => field key;
	 * repeater rows as name_0_sub). Keys follow gn_field(): field_gn_block_<slug>_<name>.
	 */
	private static function acf_data( string $slug, array $values ): array {
		$scope = 'block_' . str_replace( '-', '_', $slug );
		$data  = array();
		foreach ( $values as $name => $value ) {
			$key = "field_gn_{$scope}_{$name}";
			if ( is_array( $value ) ) {
				$data[ $name ]       = count( $value );
				$data[ "_{$name}" ] = $key;
				foreach ( array_values( $value ) as $i => $row ) {
					foreach ( $row as $sub => $sub_value ) {
						$data[ "{$name}_{$i}_{$sub}" ]  = $sub_value;
						$data[ "_{$name}_{$i}_{$sub}" ] = "field_gn_{$scope}_{$name}_{$sub}";
					}
				}
			} else {
				$data[ $name ]       = $value;
				$data[ "_{$name}" ] = $key;
			}
		}
		return $data;
	}
}
