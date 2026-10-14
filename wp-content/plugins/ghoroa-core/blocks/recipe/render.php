<?php
/**
 * gn/recipe (spec §5.6). Keep this exact structure: Astro parses it for HowTo structured data.
 * "Ingredients" and "Method" are fixed Latin labels; minutes and servings use Latin numerals.
 *
 * @package GhoroaCore
 * @var bool $is_preview Whether this is the editor preview.
 */

defined( 'ABSPATH' ) || exit;

$title       = (string) get_field( 'title' );
$minutes     = (int) get_field( 'minutes' );
$servings    = (int) get_field( 'servings' );
$ingredients = (array) get_field( 'ingredients' );
$steps       = (array) get_field( 'steps' );

if ( '' === $title && ! $is_preview ) {
	return;
}
?>
<div class="gn-recipe">
	<div class="gn-recipe__head"><h3 class="gn-recipe__title"><?php echo esc_html( $title ); ?></h3>
		<div class="gn-recipe__facts"><?php if ( $minutes ) : ?><span><?php echo esc_html( "{$minutes} min" ); ?></span><?php endif; ?><?php if ( $servings ) : ?><span><?php echo esc_html( "{$servings} servings" ); ?></span><?php endif; ?></div></div>
	<div class="gn-recipe__grid">
		<div><h4>Ingredients</h4><ul>
<?php foreach ( $ingredients as $row ) : ?>
			<li><span><?php echo esc_html( $row['name'] ?? '' ); ?></span><span><?php echo esc_html( $row['amount'] ?? '' ); ?></span></li>
<?php endforeach; ?>
		</ul></div>
		<div><h4>Method</h4><ol>
<?php foreach ( $steps as $row ) : ?>
			<li><?php echo gn_inline_html( $row['text'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput -- kses-filtered. ?></li>
<?php endforeach; ?>
		</ol></div>
	</div>
</div>
