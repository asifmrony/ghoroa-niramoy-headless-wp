<?php
/**
 * Loads the block-editor side of the publish checks (assets/editor/publish-checks.js): the
 * pre-publish panel and passing SCF field values along with each save.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'enqueue_block_editor_assets',
	static function () {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, GN_CHECKED_TYPES, true ) ) {
			return;
		}
		wp_enqueue_script(
			'gn-publish-checks',
			GN_CORE_URL . 'assets/editor/publish-checks.js',
			array( 'wp-plugins', 'wp-editor', 'wp-element', 'wp-data', 'wp-api-fetch', 'wp-dom-ready' ),
			(string) filemtime( GN_CORE_DIR . 'assets/editor/publish-checks.js' ),
			true
		);
		wp_add_inline_script(
			'gn-publish-checks',
			'window.gnPublishChecks = ' . wp_json_encode(
				array(
					'isReviewer' => gn_is_reviewer_only(),
					'fields'     => array(
						'dek'             => 'field_gn_article_dek',
						'is_health'       => 'field_gn_article_is_health',
						'claims_reviewed' => 'field_gn_article_health_claims_reviewed',
					),
				)
			) . ';',
			'before'
		);
	}
);
