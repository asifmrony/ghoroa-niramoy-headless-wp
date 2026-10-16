<?php
/**
 * Where the §7 checks run.
 *
 * - Block editor (REST): rest_pre_insert_{post,page,video}. Publishing or scheduling with errors is
 *   refused with the Bengali message, which the editor shows as an error notice. The SCF fields live
 *   in meta boxes that save after the post, so the editor script sends their current values along as
 *   `gn_acf` (see assets/editor/publish-checks.js).
 * - Everything else (classic screens, Quick Edit, WP-CLI): wp_insert_post_data saves the post as a
 *   draft instead and says why.
 * - Health articles by users who can't publish them (authors, §8) go to Pending instead of being
 *   published; the checks then run when an editor publishes.
 * - POST /gn/v1/publish-check: what the editor's pre-publish panel shows.
 * - Tags and users: Latin slugs only.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

const GN_CHECKED_TYPES = array( 'post', 'page', 'video' );

/**
 * True while a trusted bulk import runs (the seed importer), which checks its result afterwards.
 */
function gn_publish_checks_suspended(): bool {
	return ! empty( $GLOBALS['gn_suspend_publish_checks'] );
}

/**
 * True when this save would leave a health article published by someone who may not publish health
 * articles, i.e. it must go to Pending instead (§8).
 */
function gn_needs_health_review( array $state ): bool {
	if ( 'post' !== ( $state['type'] ?? '' ) || current_user_can( 'gn_publish_health' ) ) {
		return false;
	}
	$is_health = $state['is_health'] ?? null;
	if ( null === $is_health ) {
		$term      = get_term( (int) ( $state['categories'][0] ?? 0 ), 'category' );
		$is_health = $term instanceof WP_Term ? gn_category_is_health( $term->slug ) : true;
	}
	return (bool) $is_health;
}

/**
 * Overrides from a REST request: unsaved edits sent by the block editor.
 */
function gn_state_overrides_from_request( WP_REST_Request $request, stdClass $prepared ): array {
	$override = array();
	foreach ( array( 'post_title' => 'title', 'post_excerpt' => 'excerpt', 'post_content' => 'content', 'post_name' => 'slug' ) as $field => $key ) {
		if ( isset( $prepared->{$field} ) ) {
			$override[ $key ] = wp_unslash( $prepared->{$field} );
		}
	}
	if ( isset( $request['categories'] ) ) {
		$override['categories'] = array_map( 'intval', (array) $request['categories'] );
	}
	if ( isset( $request['featured_media'] ) ) {
		$override['featured'] = (int) $request['featured_media'];
	}
	$acf = is_array( $request['gn_acf'] ?? null ) ? $request['gn_acf'] : array();
	if ( array_key_exists( 'dek', $acf ) ) {
		$override['dek'] = (string) $acf['dek'];
	}
	if ( array_key_exists( 'is_health', $acf ) ) {
		$override['is_health'] = (bool) $acf['is_health'];
	}
	if ( array_key_exists( 'claims_reviewed', $acf ) ) {
		$override['claims_reviewed'] = (bool) $acf['claims_reviewed'];
	}
	return $override;
}

foreach ( GN_CHECKED_TYPES as $gn_type ) {
	add_filter(
		"rest_pre_insert_{$gn_type}",
		static function ( $prepared, WP_REST_Request $request ) use ( $gn_type ) {
			if ( is_wp_error( $prepared ) || gn_publish_checks_suspended() ) {
				return $prepared;
			}
			$existing = ! empty( $prepared->ID ) ? get_post( $prepared->ID ) : null;
			$status   = $prepared->post_status ?? ( $existing->post_status ?? 'draft' );
			if ( ! in_array( $status, array( 'publish', 'future' ), true ) ) {
				return $prepared;
			}

			$base  = $existing ?? new WP_Post( (object) array( 'ID' => 0, 'post_type' => $gn_type, 'post_name' => '', 'post_title' => '', 'post_excerpt' => '', 'post_content' => '' ) );
			$state = gn_publish_state( $base, array( 'type' => $gn_type ) + gn_state_overrides_from_request( $request, $prepared ) );

			if ( gn_needs_health_review( $state ) ) {
				$prepared->post_status = 'pending';
				// Core reads these for new pending posts (class-wp-rest-posts-controller.php) without defaults.
				$prepared->id          = $prepared->ID ?? 0;
				$prepared->post_parent = $prepared->post_parent ?? 0;
			} else {
				$problems = gn_publish_problems( $state );
				if ( $problems['errors'] ) {
					return new WP_Error( 'gn_publish_blocked', gn_publish_error_message( $problems ), array( 'status' => 400, 'problems' => $problems ) );
				}
			}
			// Checked with the full request; the wp_insert_post_data check below must not re-check
			// this save without the request's categories and field values.
			$GLOBALS['gn_publish_checked'] = true;
			return $prepared;
		},
		10,
		2
	);
}

// Accept `gn_acf` (current SCF field values) on post saves from the block editor; it is read above.
add_action(
	'rest_api_init',
	static function () {
		register_rest_field(
			GN_CHECKED_TYPES,
			'gn_acf',
			array(
				'get_callback'    => static fn() => null,
				'update_callback' => static fn() => true,
				'schema'          => array(
					'description' => 'Unsaved SCF values (dek, is_health, claims_reviewed) for the publish checks.',
					'type'        => array( 'object', 'null' ),
					'context'     => array( 'edit' ),
				),
			)
		);

		register_rest_route(
			'gn/v1',
			'/publish-check',
			array(
				'methods'             => 'POST',
				'permission_callback' => static fn( WP_REST_Request $r ) => current_user_can( 'edit_post', (int) $r['id'] ),
				'callback'            => static function ( WP_REST_Request $r ) {
					$post  = get_post( (int) $r['id'] );
					$acf   = (array) ( $r['acf'] ?? array() );
					$state = gn_publish_state(
						$post,
						array_filter(
							array(
								'title'           => $r['title'],
								'slug'            => $r['slug'],
								'excerpt'         => $r['excerpt'],
								'content'         => $r['content'],
								'categories'      => $r['categories'],
								'featured'        => $r['featured_media'],
								'dek'             => $acf['dek'] ?? null,
								'is_health'       => isset( $acf['is_health'] ) ? (bool) $acf['is_health'] : null,
								'claims_reviewed' => isset( $acf['claims_reviewed'] ) ? (bool) $acf['claims_reviewed'] : null,
							),
							static fn( $v ) => null !== $v
						)
					);
					return gn_publish_problems( $state ) + array( 'goesToReview' => gn_needs_health_review( $state ) );
				},
			)
		);
	}
);

// Non-REST saves (classic screens, Quick Edit, WP-CLI): publishing with errors saves a draft instead.
add_filter(
	'wp_insert_post_data',
	static function ( array $data, array $postarr ): array {
		if ( ! empty( $GLOBALS['gn_publish_checked'] ) ) {
			$GLOBALS['gn_publish_checked'] = false; // Already checked by the REST filter above.
			return $data;
		}
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || gn_publish_checks_suspended() || ! in_array( $data['post_type'], GN_CHECKED_TYPES, true ) ) {
			return $data;
		}
		if ( ! in_array( $data['post_status'], array( 'publish', 'future' ), true ) ) {
			return $data;
		}
		$existing = ! empty( $postarr['ID'] ) ? get_post( (int) $postarr['ID'] ) : null;
		$base     = $existing ?? new WP_Post( (object) array( 'ID' => 0, 'post_type' => $data['post_type'] ) );
		$override = array(
			'type'    => $data['post_type'],
			'slug'    => $data['post_name'],
			'title'   => wp_unslash( $data['post_title'] ),
			'excerpt' => wp_unslash( $data['post_excerpt'] ),
			'content' => wp_unslash( $data['post_content'] ),
		);
		if ( isset( $postarr['post_category'] ) ) {
			$override['categories'] = array_map( 'intval', (array) $postarr['post_category'] );
		}
		$acf = isset( $_POST['acf'] ) && is_array( $_POST['acf'] ) ? wp_unslash( $_POST['acf'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification -- read-only; the save itself is nonce-checked by WordPress.
		foreach ( array( 'dek' => 'field_gn_article_dek', 'is_health' => 'field_gn_article_is_health', 'claims_reviewed' => 'field_gn_article_health_claims_reviewed' ) as $key => $field ) {
			if ( array_key_exists( $field, $acf ) ) {
				$override[ $key ] = 'dek' === $key ? (string) $acf[ $field ] : (bool) $acf[ $field ];
			}
		}
		$state = gn_publish_state( $base, $override );

		if ( gn_needs_health_review( $state ) ) {
			$data['post_status'] = 'pending';
			return $data;
		}
		$problems = gn_publish_problems( $state );
		if ( $problems['errors'] ) {
			$data['post_status'] = 'draft';
			$message             = gn_publish_error_message( $problems );
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				WP_CLI::warning( "Saved as draft. {$message}" );
			} else {
				set_transient( 'gn_publish_blocked_' . get_current_user_id(), $message, 60 );
			}
		}
		return $data;
	},
	30,
	2
);

add_action(
	'admin_notices',
	static function () {
		$message = get_transient( 'gn_publish_blocked_' . get_current_user_id() );
		if ( $message ) {
			delete_transient( 'gn_publish_blocked_' . get_current_user_id() );
			printf( '<div class="notice notice-error"><p>%s</p></div>', nl2br( esc_html( $message ) ) );
		}
	}
);

// Tags: Latin slugs only (§7). Categories are fixed (categories.php).
add_filter(
	'pre_insert_term',
	static function ( $term, string $taxonomy, $args = array() ) {
		if ( 'post_tag' !== $taxonomy || is_wp_error( $term ) ) {
			return $term;
		}
		$slug = gn_effective_slug( (string) ( $args['slug'] ?? '' ), (string) $term );
		if ( ! gn_is_latin_slug( $slug ) ) {
			return new WP_Error( 'gn_tag_slug', 'ট্যাগের স্লাগ ইংরেজিতে দিন (যেমন হজম → hojom)। পোস্ট → ট্যাগ পাতায় নাম বাংলায় আর স্লাগ ইংরেজিতে লিখে ট্যাগটি তৈরি করুন।', array( 'status' => 400 ) );
		}
		return $term;
	},
	10,
	3
);
add_action(
	'edit_terms',
	static function ( $term_id, string $taxonomy, $args = array() ) {
		if ( 'post_tag' === $taxonomy && isset( $args['slug'] ) && ! gn_is_latin_slug( sanitize_title( (string) $args['slug'] ) ) ) {
			wp_die( esc_html( 'ট্যাগের স্লাগ শুধু ইংরেজি ছোট হাতের অক্ষর, সংখ্যা আর হাইফেন দিয়ে লিখুন।' ), '', array( 'response' => 400, 'back_link' => true ) );
		}
	},
	10,
	3
);

// Users: Latin login (the nicename/slug comes from it) (§7).
add_action(
	'user_profile_update_errors',
	static function ( WP_Error $errors, bool $update, $user ) {
		$slug = $update ? (string) ( $user->user_nicename ?? '' ) : sanitize_title( (string) ( $user->user_login ?? '' ) );
		if ( '' !== $slug && ! gn_is_latin_slug( $slug ) ) {
			$errors->add( 'gn_user_slug', 'ইউজারনেম ইংরেজি ছোট হাতের অক্ষর, সংখ্যা আর হাইফেন দিয়ে দিন: লেখকের পাতার ঠিকানা এটি দিয়েই তৈরি হয় (যেমন nusrat-jahan)।' );
		}
	},
	10,
	3
);
