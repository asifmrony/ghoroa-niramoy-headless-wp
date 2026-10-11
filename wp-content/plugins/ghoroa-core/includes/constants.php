<?php
/**
 * Environment constants (spec §3.1a).
 *
 * Both are defined in wp-config.php, never in options and never hard-coded:
 * - GN_PUBLIC_URL      Public Astro site URL. Local: http://localhost:4321, production: https://domain.com.
 * - GN_BUILD_HOOK_URL  Workers Builds deploy hook (§10.1). Left undefined locally, so the webhook only logs.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Public Astro site URL without a trailing slash, or '' when GN_PUBLIC_URL isn't defined.
 */
function gn_public_url(): string {
	return defined( 'GN_PUBLIC_URL' ) ? untrailingslashit( (string) GN_PUBLIC_URL ) : '';
}

/**
 * Build hook URL, or '' when GN_BUILD_HOOK_URL isn't defined (log-only mode).
 */
function gn_build_hook_url(): string {
	return defined( 'GN_BUILD_HOOK_URL' ) ? trim( (string) GN_BUILD_HOOK_URL ) : '';
}

add_action(
	'admin_notices',
	static function () {
		if ( '' !== gn_public_url() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Ghoroa Core: GN_PUBLIC_URL is not defined in wp-config.php. Public links cannot be built (spec §3.1a).', 'ghoroa-core' )
		);
	}
);
