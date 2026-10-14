<?php
/**
 * gn/references (spec §5.9):
 *   <div class="gn-refs"><ol><li>সূত্রের শিরোনাম, প্রকাশক, সাল। <a href="https://…">লিংক</a></li></ol></div>
 *
 * @package GhoroaCore
 * @var bool $is_preview Whether this is the editor preview.
 */

defined( 'ABSPATH' ) || exit;

$refs = array_filter( (array) get_field( 'refs' ), static fn( $row ) => '' !== trim( (string) ( $row['text'] ?? '' ) ) );
if ( ! $refs && ! $is_preview ) {
	return;
}
?>
<div class="gn-refs"><ol>
<?php foreach ( $refs as $row ) : ?>
	<li><?php echo esc_html( trim( $row['text'] ) ); ?><?php if ( ! empty( $row['url'] ) ) : ?> <a href="<?php echo esc_url( $row['url'] ); ?>"><?php echo esc_html( '' !== trim( (string) ( $row['link_text'] ?? '' ) ) ? $row['link_text'] : 'লিংক' ); ?></a><?php endif; ?></li>
<?php endforeach; ?>
</ol></div>
