<?php
/**
 * Shared helpers for the §5 blocks.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

/** The custom ACF blocks in blocks/<slug>/ (spec §5). */
const GN_BLOCKS = array( 'numbered-heading', 'takeaways', 'recipe', 'callout-tip', 'callout-caution', 'references', 'ad-slot' );

/**
 * Latin digits → Bengali digits (১ ২ ৩ …), for numbers inside Bengali text.
 */
function gn_bn_digits( $value ): string {
	return strtr( (string) $value, array_combine( range( 0, 9 ), array( '০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯' ) ) );
}

/**
 * Inline HTML allowed inside block text fields (§5.12): links, bold, italic. No classes or styles.
 */
function gn_inline_html( ?string $html ): string {
	return trim( wp_kses( (string) $html, array( 'a' => array( 'href' => true ), 'strong' => array(), 'em' => array(), 'br' => array() ) ) );
}

/**
 * Icons for the callouts (§5.7, §5.8), exactly as in the spec.
 */
function gn_callout_icon( string $kind ): string {
	$paths = 'caution' === $kind
		? '<path d="M12 3 2 20h20L12 3z"/><path d="M12 10v4M12 17h.01"/>'
		: '<path d="M5 19C5 11 10 6 19 5c0 9-5 14-14 14z"/><path d="M5 19 13 11"/>';
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
}

/**
 * Shown in the editor only, in place of blocks that render nothing visible (e.g. the ad marker).
 */
function gn_editor_placeholder( string $text ): string {
	return '<div style="padding:12px;border:1px dashed currentColor;opacity:.6;text-align:center;font-size:14px">' . esc_html( $text ) . '</div>';
}
