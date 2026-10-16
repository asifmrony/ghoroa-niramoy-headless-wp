<?php
/**
 * Hardening from spec §3: comments, pingbacks and trackbacks off site-wide (and out of the admin),
 * XML-RPC off. Editorial discussion uses the block editor's Notes, which WordPress keeps separate
 * from comments and which stay available.
 *
 * Login rate limiting, the WAF and DISALLOW_FILE_EDIT live at the edge and in wp-config (§3, M7).
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open', '__return_false', 20 );
add_filter( 'comments_array', '__return_empty_array', 20 );

add_action(
	'init',
	static function () {
		foreach ( get_post_types() as $type ) {
			remove_post_type_support( $type, 'comments' );
			remove_post_type_support( $type, 'trackbacks' );
		}
	},
	100
);

// Remove the comment UI from the admin.
add_action( 'admin_menu', static fn() => remove_menu_page( 'edit-comments.php' ) );
add_action(
	'admin_init',
	static function () {
		global $pagenow;
		if ( 'edit-comments.php' === $pagenow || 'options-discussion.php' === $pagenow ) {
			wp_safe_redirect( admin_url() );
			exit;
		}
		remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
	}
);
add_action( 'admin_bar_menu', static fn( $bar ) => $bar->remove_node( 'comments' ), 100 );

// XML-RPC and pingbacks off. xmlrpc_enabled alone still answers system.* calls, so the endpoint is
// refused outright (Cloudflare blocks /shop/xmlrpc.php too in production, §3).
if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
	status_header( 403 );
	exit;
}
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', '__return_empty_array' );
add_filter(
	'wp_headers',
	static function ( array $headers ): array {
		unset( $headers['X-Pingback'] );
		return $headers;
	}
);
remove_action( 'wp_head', 'rsd_link' );
