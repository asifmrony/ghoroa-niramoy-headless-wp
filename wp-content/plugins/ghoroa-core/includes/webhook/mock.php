<?php
/**
 * A fake deploy hook for local testing only (wp_get_environment_type() === 'local'):
 * POST /wp-json/gn/v1/mock-build-hook records each call; with the option gn_mock_hook_fail set it
 * answers 500, to exercise the retries.
 *
 * To watch real deliveries locally, set in .wp-env.override.json (git-ignored):
 *   { "config": { "GN_BUILD_HOOK_URL": "http://localhost:8888/wp-json/gn/v1/mock-build-hook" } }
 * The hook is called from WP-Cron inside the WordPress container, where WordPress also answers on
 * port 8888.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'rest_api_init',
	static function () {
		if ( 'local' !== wp_get_environment_type() ) {
			return;
		}
		register_rest_route(
			'gn/v1',
			'/mock-build-hook',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => static function () {
					$hits = (array) get_option( 'gn_mock_hook_hits', array() );
					array_unshift( $hits, time() );
					update_option( 'gn_mock_hook_hits', array_slice( $hits, 0, 20 ), false );
					return get_option( 'gn_mock_hook_fail' )
						? new WP_REST_Response( array( 'mock' => 'failing on purpose' ), 500 )
						: new WP_REST_Response( array( 'mock' => 'build queued' ), 200 );
				},
			)
		);
	}
);
