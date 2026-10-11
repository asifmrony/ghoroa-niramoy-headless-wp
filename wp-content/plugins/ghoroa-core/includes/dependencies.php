<?php
/**
 * Required plugins (spec §3, "Plugin set"): SCF and WPGraphQL must be active, ACF must never be.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Human-readable problems with the plugin set; empty when everything is in order.
 *
 * @return string[]
 */
function gn_dependency_problems(): array {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$problems = array();

	if ( ! is_plugin_active( 'secure-custom-fields/secure-custom-fields.php' ) ) {
		$problems[] = __( 'Secure Custom Fields is not active.', 'ghoroa-core' );
	}
	foreach ( array( 'advanced-custom-fields/acf.php', 'advanced-custom-fields-pro/acf.php' ) as $acf ) {
		if ( is_plugin_active( $acf ) ) {
			$problems[] = __( 'ACF is active. Never run ACF together with Secure Custom Fields; deactivate ACF.', 'ghoroa-core' );
		}
	}
	if ( ! class_exists( 'WPGraphQL' ) ) {
		$problems[] = __( 'WPGraphQL is not active.', 'ghoroa-core' );
	}
	if ( ! class_exists( 'WPGraphQLAcf' ) && ! defined( 'WPGRAPHQL_FOR_ACF_VERSION' ) ) {
		$problems[] = __( 'WPGraphQL for ACF is not active.', 'ghoroa-core' );
	}

	return $problems;
}

add_action(
	'admin_notices',
	static function () {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		foreach ( gn_dependency_problems() as $problem ) {
			printf( '<div class="notice notice-error"><p>Ghoroa Core: %s</p></div>', esc_html( $problem ) );
		}
	}
);
