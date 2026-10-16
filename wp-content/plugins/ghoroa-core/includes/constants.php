<?php
/**
 * Environment constants (spec §3.1a).
 *
 * All are defined in wp-config.php, never in options and never hard-coded:
 * - GN_PUBLIC_URL      Public Astro site URL. Local: http://localhost:4321, production: https://domain.com.
 * - GN_BUILD_HOOK_URL  Workers Builds deploy hook (§10.1). Left undefined locally, so the webhook only logs.
 * - GN_PREVIEW_SECRET  Shared with the Astro preview route to sign preview tokens (§10.2).
 * - GN_REQUIRE_2FA     Require two-factor login for admins and editors (§3, §8). Production only.
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
	$url = defined( 'GN_BUILD_HOOK_URL' ) ? trim( (string) GN_BUILD_HOOK_URL ) : '';
	/**
	 * Filters the hook URL. Used by `wp gn graphql-check` and local tests to point at the fake
	 * endpoint (includes/webhook/mock.php); never stores a URL anywhere.
	 */
	return (string) apply_filters( 'gn/build_hook_url', $url );
}

/**
 * Preview signing secret, or '' when GN_PREVIEW_SECRET isn't defined (previews stay in WordPress).
 */
function gn_preview_secret(): string {
	return defined( 'GN_PREVIEW_SECRET' ) ? (string) GN_PREVIEW_SECRET : '';
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
