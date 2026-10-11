<?php
/**
 * The four fixed categories (spec §4.2).
 *
 * - Exactly these four exist; nobody can add a fifth. "Uncategorized" is renamed into the first one.
 * - Only administrators can manage (rename, re-slug) them; editors can only assign them.
 * - None of the four can be deleted.
 * - Display order is fixed by brand rule and is the order of gn_categories().
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

/** Bump when the definitions below change, so gn_ensure_categories() runs again. */
const GN_CATEGORIES_VERSION = '1';

/**
 * Fixed categories in display order, keyed by slug.
 *
 * `is_health` is the default for articleFields.isHealth (§4.5): on for ঘরোয়া টোটকা and ভেষজ উদ্ভিদ,
 * off for ছাদবাগান. The spec doesn't name a default for রান্নাঘরের মসলা; it's on (the safer side,
 * since it triggers the health safeguards in §7) until decided otherwise.
 *
 * @return array<string, array{name: string, description: string, is_health: bool}>
 */
function gn_categories(): array {
	return array(
		'gharoa-totka'      => array(
			'name'        => 'ঘরোয়া টোটকা',
			'description' => 'ঘরের হাতের কাছের উপকরণ দিয়ে ছোটখাটো সমস্যায় প্রচলিত ঘরোয়া উপায়: কতটুকু কাজে দিতে পারে, আর কখন ডাক্তার দেখানো জরুরি।',
			'is_health'   => true,
		),
		'veshoj-udbhid'     => array(
			'name'        => 'ভেষজ উদ্ভিদ',
			'description' => 'উঠোন আর ছাদের ঔষধি গাছ চেনা, প্রচলিত ব্যবহার, গবেষণা কী বলে আর কারা এড়িয়ে চলবেন।',
			'is_health'   => true,
		),
		'rannaghorer-mosla' => array(
			'name'        => 'রান্নাঘরের মসলা',
			'description' => 'রোজকার মসলার পুষ্টিগুণ, রান্নায় ব্যবহার আর সুস্থতায় এদের সম্ভাব্য ভূমিকা।',
			'is_health'   => true,
		),
		'chhadbagan'        => array(
			'name'        => 'ছাদবাগান',
			'description' => 'টবে আর ছাদে সবজি, ফল ও ঔষধি গাছ চাষের হাতে-কলমে গাইড।',
			'is_health'   => false,
		),
	);
}

/**
 * Default for articleFields.isHealth given a category slug (§4.5).
 */
function gn_category_is_health( string $slug ): bool {
	return gn_categories()[ $slug ]['is_health'] ?? true;
}

/**
 * Creates any missing fixed category; renames "Uncategorized" into the first missing one.
 * Never overwrites a name or description an administrator has edited.
 *
 * @return string[] Log lines describing what changed.
 */
function gn_ensure_categories(): array {
	$log     = array();
	$default = get_term( (int) get_option( 'default_category' ), 'category' );

	$GLOBALS['gn_allow_category_insert'] = true;

	foreach ( gn_categories() as $slug => $cat ) {
		$term = get_term_by( 'slug', $slug, 'category' );

		if ( $term ) {
			if ( '' === $term->description ) {
				wp_update_term( $term->term_id, 'category', array( 'description' => $cat['description'] ) );
				$log[] = "Added description to {$slug}.";
			}
			continue;
		}

		$args = array(
			'name'        => $cat['name'],
			'slug'        => $slug,
			'description' => $cat['description'],
		);

		if ( $default instanceof WP_Term && ! isset( gn_categories()[ $default->slug ] ) ) {
			$result = wp_update_term( $default->term_id, 'category', $args );
			$log[]  = "Renamed '{$default->slug}' to {$slug}.";
			$default = null;
		} else {
			$result = wp_insert_term( $cat['name'], 'category', $args );
			$log[]  = "Created {$slug}.";
		}

		if ( is_wp_error( $result ) ) {
			$log[] = "  Failed: {$result->get_error_message()}";
		}
	}

	$GLOBALS['gn_allow_category_insert'] = false;

	// The default category must be one of the four.
	$current = get_term( (int) get_option( 'default_category' ), 'category' );
	if ( ! $current instanceof WP_Term || ! isset( gn_categories()[ $current->slug ] ) ) {
		$first = get_term_by( 'slug', array_key_first( gn_categories() ), 'category' );
		if ( $first ) {
			update_option( 'default_category', $first->term_id );
			$log[] = 'Default category set to ' . $first->slug . '.';
		}
	}

	$all = get_terms(
		array(
			'taxonomy'   => 'category',
			'hide_empty' => false,
			'fields'     => 'slugs',
		)
	);
	$extra = is_wp_error( $all ) ? array() : array_diff( $all, array_keys( gn_categories() ) );
	if ( $extra ) {
		$log[] = 'WARNING: categories outside the fixed four exist and must be removed by hand: ' . implode( ', ', $extra );
	}

	update_option( 'gn_core_categories_version', GN_CATEGORIES_VERSION, false );

	return $log;
}

add_action(
	'init',
	static function () {
		if ( get_option( 'gn_core_categories_version' ) !== GN_CATEGORIES_VERSION ) {
			gn_ensure_categories();
		}
	},
	20
);

// Only administrators manage categories; anyone who can edit posts can assign them.
add_filter(
	'register_taxonomy_args',
	static function ( array $args, string $taxonomy ): array {
		if ( 'category' === $taxonomy ) {
			$args['capabilities'] = array(
				'manage_terms' => 'manage_options',
				'edit_terms'   => 'manage_options',
				'delete_terms' => 'manage_options',
				'assign_terms' => 'edit_posts',
			);
		}
		return $args;
	},
	10,
	2
);

// No fifth category, for anyone (admin screen, block editor, REST, GraphQL or WP-CLI).
add_filter(
	'pre_insert_term',
	static function ( $term, string $taxonomy ) {
		if ( 'category' === $taxonomy && empty( $GLOBALS['gn_allow_category_insert'] ) ) {
			return new WP_Error( 'gn_category_locked', 'বিভাগ ঠিক চারটি, নতুন বিভাগ যোগ করা যায় না। (Categories are fixed; spec §4.2)' );
		}
		return $term;
	},
	10,
	2
);

// The four can't be deleted, not even by an administrator.
add_filter(
	'map_meta_cap',
	static function ( array $caps, string $cap, int $user_id, array $args ): array {
		if ( 'delete_term' === $cap && ! empty( $args[0] ) ) {
			$term = get_term( (int) $args[0] );
			if ( $term instanceof WP_Term && 'category' === $term->taxonomy && isset( gn_categories()[ $term->slug ] ) ) {
				$caps[] = 'do_not_allow';
			}
		}
		return $caps;
	},
	10,
	4
);

add_action(
	'pre_delete_term',
	static function ( int $term_id, string $taxonomy ) {
		$term = get_term( $term_id, $taxonomy );
		if ( 'category' === $taxonomy && $term instanceof WP_Term && isset( gn_categories()[ $term->slug ] ) ) {
			wp_die( esc_html( 'এই বিভাগটি মুছে ফেলা যায় না। (Fixed category; spec §4.2)' ), '', array( 'response' => 403 ) );
		}
	},
	10,
	2
);

// Hide the "Add New Category" form; it can only fail.
add_action(
	'category_pre_add_form',
	static function () {
		echo '<style>#col-left .form-wrap > h2, #addtag{display:none}</style>';
		echo '<p>' . esc_html__( 'The four categories are fixed (spec §4.2). They can be renamed by an administrator but not added or deleted.', 'ghoroa-core' ) . '</p>';
	}
);
