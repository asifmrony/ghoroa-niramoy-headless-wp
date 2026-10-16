<?php
/**
 * Site settings from spec §3 "Install" that live in the database, applied idempotently by
 * `wp gn setup` (and on wp-env start). Production runs the same command.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Applies the settings that don't need WP-CLI. Language packs are handled by the CLI command.
 *
 * @return string[] Log lines.
 */
function gn_apply_site_settings(): array {
	$log = array();

	$settings = array(
		'timezone_string' => 'Asia/Dhaka',
		'blogname'        => 'ঘরোয়া নিরাময়',
		'date_format'     => 'j F Y',
		'time_format'     => 'g:i a',
		'start_of_week'   => 6,
		// Comments and pings off (§3); hardening.php also closes them in code.
		'default_comment_status' => 'closed',
		'default_ping_status'    => 'closed',
	);
	foreach ( $settings as $option => $value ) {
		if ( (string) get_option( $option ) !== (string) $value ) {
			update_option( $option, $value );
			$log[] = "Set {$option}.";
		}
	}

	if ( '/%postname%/' !== get_option( 'permalink_structure' ) ) {
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		$log[] = 'Set permalinks to /%postname%/.';
	}
	flush_rewrite_rules( false );

	// WordPress's starter content would otherwise reach Astro as a real article. Only removed if never edited.
	foreach ( array( 'post' => 'hello-world', 'page' => 'sample-page' ) as $type => $slug ) {
		$starter = get_page_by_path( $slug, OBJECT, $type );
		if ( $starter && $starter->post_modified === $starter->post_date ) {
			wp_delete_post( $starter->ID, true );
			$log[] = "Removed WordPress starter {$type} '{$slug}'.";
		}
	}

	return array_merge( $log, gn_configure_rank_math(), gn_ensure_categories(), gn_ensure_roles() );
}

register_activation_hook(
	GN_CORE_DIR . 'ghoroa-core.php',
	static function () {
		update_option( 'gn_core_flush_rewrites', 1 );
	}
);

// Flush after activation once the video post type is registered.
add_action(
	'init',
	static function () {
		if ( get_option( 'gn_core_flush_rewrites' ) ) {
			delete_option( 'gn_core_flush_rewrites' );
			flush_rewrite_rules( false );
		}
	},
	99
);
