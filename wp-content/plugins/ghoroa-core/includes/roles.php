<?php
/**
 * Roles and capabilities (spec §8).
 *
 * | Role              | Here                                                                         |
 * |-------------------|------------------------------------------------------------------------------|
 * | administrator     | + gn_review_health_claims, gn_publish_health                                 |
 * | editor            | + gn_review_health_claims, gn_publish_health; edits the homepage options (M1) |
 * | author            | core role; health articles go to Pending (publish-checks/enforce.php)        |
 * | contributor       | core role (drafts only)                                                      |
 * | medical_reviewer  | new: reads and adds Notes to posts in review; the only role with             |
 * |                   | gn_medical_review (medicallyReviewed, medicalReviewer, review date). Content |
 * |                   | changes they save are discarded. Profile: practitioner name + registration no.|
 * | gn_build          | new: reads drafts through GraphQL with an Application Password; no admin UI  |
 *
 * Roles live in the database, so they're (re)applied when GN_ROLES_VERSION changes and by `wp gn setup`.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

const GN_ROLES_VERSION = '1';

/**
 * Creates or updates the roles and capabilities above. Idempotent.
 *
 * @return string[] Log lines.
 */
function gn_ensure_roles(): array {
	$log = array();

	foreach ( array( 'administrator', 'editor' ) as $name ) {
		$role = get_role( $name );
		foreach ( array( 'gn_review_health_claims', 'gn_publish_health' ) as $cap ) {
			if ( $role && ! $role->has_cap( $cap ) ) {
				$role->add_cap( $cap );
				$log[] = "Role {$name}: added {$cap}.";
			}
		}
	}

	$roles = array(
		'medical_reviewer' => array(
			'name' => 'মেডিক্যাল রিভিউয়ার',
			'caps' => array( 'read', 'edit_posts', 'edit_others_posts', 'read_private_posts', 'gn_medical_review' ),
		),
		'gn_build'         => array(
			'name' => 'Build bot (Astro)',
			'caps' => array( 'read', 'gn_read_drafts' ),
		),
	);
	foreach ( $roles as $slug => $def ) {
		$caps = array_fill_keys( $def['caps'], true );
		$role = get_role( $slug );
		if ( ! $role ) {
			add_role( $slug, $def['name'], $caps );
			$log[] = "Role {$slug} created.";
			continue;
		}
		foreach ( array_diff_key( $role->capabilities, $caps ) as $cap => $granted ) {
			$role->remove_cap( $cap );
		}
		foreach ( $caps as $cap => $granted ) {
			$role->add_cap( $cap );
		}
	}

	update_option( 'gn_core_roles_version', GN_ROLES_VERSION, false );
	return $log;
}

add_action(
	'init',
	static function () {
		if ( get_option( 'gn_core_roles_version' ) !== GN_ROLES_VERSION ) {
			gn_ensure_roles();
		}
	},
	20
);

/**
 * True for a medical reviewer who isn't also an editor or admin.
 */
function gn_is_reviewer_only( ?int $user_id = null ): bool {
	$user = $user_id ? get_userdata( $user_id ) : wp_get_current_user();
	return $user && user_can( $user, 'gn_medical_review' ) && ! user_can( $user, 'gn_publish_health' );
}

// ---------- Field permissions ----------

/** Article fields only some roles may change: field key => capability. */
const GN_PROTECTED_FIELDS = array(
	'field_gn_article_health_claims_reviewed' => 'gn_review_health_claims',
	'field_gn_article_medically_reviewed'     => 'gn_medical_review',
	'field_gn_article_medical_reviewer'       => 'gn_medical_review',
	'field_gn_article_medical_review_date'    => 'gn_medical_review',
);

foreach ( GN_PROTECTED_FIELDS as $gn_key => $gn_cap ) {
	// Shown read-only to everyone else.
	add_filter(
		"acf/prepare_field/key={$gn_key}",
		static function ( $field ) use ( $gn_cap ) {
			if ( $field && ! current_user_can( $gn_cap ) ) {
				$field['disabled'] = 1;
				$field['readonly'] = 1;
			}
			return $field;
		}
	);
	// And enforced on save: the stored value stays. Only the seed importer is trusted.
	add_filter(
		"acf/update_value/key={$gn_key}",
		static function ( $value, $post_id, $field ) use ( $gn_cap ) {
			if ( current_user_can( $gn_cap ) || gn_publish_checks_suspended() ) {
				return $value;
			}
			return get_post_meta( (int) $post_id, $field['name'], true );
		},
		10,
		3
	);
}

// When a reviewer ticks "medically reviewed", record who and when (§4.5: kept for the record).
add_action(
	'acf/save_post',
	static function ( $post_id ) {
		if ( ! is_numeric( $post_id ) || ! current_user_can( 'gn_medical_review' ) || ! get_field( 'medically_reviewed', $post_id ) ) {
			return;
		}
		if ( ! get_field( 'medical_reviewer', $post_id, false ) ) {
			update_field( 'medical_reviewer', get_current_user_id(), $post_id );
		}
		if ( ! get_field( 'medical_review_date', $post_id, false ) ) {
			update_field( 'medical_review_date', wp_date( 'Ymd' ), $post_id );
		}
	},
	20
);

// Reviewers review, they don't edit: whatever they change in the text is discarded on save.
add_filter(
	'wp_insert_post_data',
	static function ( array $data, array $postarr ): array {
		if ( empty( $postarr['ID'] ) || ! gn_is_reviewer_only() ) {
			return $data;
		}
		$saved = get_post( (int) $postarr['ID'] );
		if ( $saved && 'post' === $saved->post_type && get_current_user_id() !== (int) $saved->post_author ) {
			foreach ( array( 'post_title', 'post_content', 'post_excerpt', 'post_name', 'post_status' ) as $field ) {
				$data[ $field ] = wp_slash( $saved->{$field} );
			}
		}
		return $data;
	},
	5,
	2
);

// Reviewer profile: practitioner name and registration number (§8). Private: not in GraphQL.
gn_on_fields(
	static function () {
		acf_add_local_field_group(
			array(
				'key'             => 'group_gn_medical_reviewer',
				'title'           => 'চিকিৎসকের তথ্য',
				'location'        => array(
					array(
						array(
							'param'    => 'user_role',
							'operator' => '==',
							'value'    => 'medical_reviewer',
						),
					),
				),
				'show_in_rest'    => 0,
				'show_in_graphql' => 0,
				'fields'          => array(
					gn_field( 'reviewer', 'practitioner_name', 'নিবন্ধিত চিকিৎসকের নাম', 'text', array( 'required' => 1, 'show_in_graphql' => 0 ) ),
					gn_field( 'reviewer', 'registration_number', 'রেজিস্ট্রেশন নম্বর (BMDC)', 'text', array( 'required' => 1, 'show_in_graphql' => 0 ) ),
				),
			)
		);
	}
);

// ---------- Build bot ----------

// gn_build sees drafts and pending posts in GraphQL (for the Astro preview, §10.2) without being able
// to edit anything: WPGraphQL would otherwise require edit rights to show unpublished content.
add_filter(
	'graphql_data_is_private',
	static function ( $is_private, $model_name, $data ) {
		if ( $is_private && 'PostObject' === $model_name && $data instanceof WP_Post && current_user_can( 'gn_read_drafts' )
			&& in_array( $data->post_type, GN_CHECKED_TYPES, true ) && in_array( $data->post_status, array( 'draft', 'pending', 'future', 'private' ), true ) ) {
			return false;
		}
		return $is_private;
	},
	10,
	3
);
add_filter(
	'graphql_object_visibility',
	static fn( $visibility, $model_name, $data ) => ( 'PostObject' === $model_name && $data instanceof WP_Post && current_user_can( 'gn_read_drafts' ) && in_array( $data->post_type, GN_CHECKED_TYPES, true ) ) ? 'public' : $visibility,
	10,
	3
);

// No admin screens or toolbar for the build bot.
add_action(
	'admin_init',
	static function () {
		if ( current_user_can( 'gn_read_drafts' ) && ! current_user_can( 'edit_posts' ) && ! wp_doing_ajax() ) {
			wp_die( esc_html( 'This account is for the Astro build only.' ), '', array( 'response' => 403 ) );
		}
	}
);
add_filter( 'show_admin_bar', static fn( $show ) => current_user_can( 'gn_read_drafts' ) && ! current_user_can( 'edit_posts' ) ? false : $show );
