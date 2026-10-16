<?php
/**
 * The editorial safeguards from spec §7, as one pure function: gn_publish_problems( $state ).
 * Enforcement (REST, classic saves, WP-CLI, the editor panel) lives in enforce.php.
 *
 * A "state" describes a post as it would be published:
 *   type, slug, title, excerpt, content (block markup), categories (int[]), featured (attachment id),
 *   dek, is_health (bool|null: null = category default), claims_reviewed (bool).
 *
 * Messages are Bengali (চলিত, আপনি), shown to editors as-is.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

/** Slugs Astro uses for its own routes and files (§7), plus the four category slugs (added below). */
const GN_RESERVED_SLUGS = array( 'about', 'contact', 'privacy', 'medical-disclaimer', 'newsletter', 'search', 'latest', 'video', 'tag', 'author', 'shop', 'page', '404', 'robots.txt', 'sitemap-index.xml', 'sitemap-0.xml', 'pagefind', 'scripts', 'fonts', 'images', '_astro', 'preview' );

/** Info pages (§4.8) are the one use of these reserved slugs: as pages. */
const GN_INFO_PAGE_SLUGS = array( 'about', 'contact', 'privacy', 'medical-disclaimer', 'newsletter' );

/**
 * Cure-claim phrases (§7), editable under Posts → নিরাময়-দাবির শব্দ (settings.php).
 *
 * @return string[]
 */
function gn_cure_phrases(): array {
	$saved = get_option( 'gn_cure_phrases' );
	return is_array( $saved ) && $saved ? $saved : gn_default_cure_phrases();
}

/**
 * @return string[]
 */
function gn_default_cure_phrases(): array {
	return array( 'সারিয়ে দেয়', 'সারিয়ে তোলে', 'নিরাময় করে', 'রোগ সারায়', 'চিরতরে', '১০০%', 'গ্যারান্টি', 'ওষুধের দরকার নেই' );
}

/**
 * True for a valid public slug: lowercase ASCII letters, digits and hyphens (§4.1).
 */
function gn_is_latin_slug( string $slug ): bool {
	return (bool) preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug );
}

/**
 * The slug WordPress will use: the given one, else the one it generates from the title.
 */
function gn_effective_slug( string $slug, string $title ): string {
	return '' !== $slug ? $slug : sanitize_title( $title );
}

/**
 * Problems that block publishing (errors) and ones to show before publishing (warnings).
 *
 * @param array $s State, see the file header.
 * @return array{errors: array<int, array{code: string, message: string}>, warnings: array<int, array{code: string, message: string}>}
 */
function gn_publish_problems( array $s ): array {
	$errors   = array();
	$warnings = array();
	$error    = static function ( string $code, string $message ) use ( &$errors ) {
		$errors[] = compact( 'code', 'message' );
	};
	$warn     = static function ( string $code, string $message ) use ( &$warnings ) {
		$warnings[] = compact( 'code', 'message' );
	};

	$type  = $s['type'] ?? 'post';
	$title = (string) ( $s['title'] ?? '' );
	$slug  = gn_effective_slug( (string) ( $s['slug'] ?? '' ), $title );

	// Slugs (posts, videos, pages).
	if ( ! gn_is_latin_slug( $slug ) ) {
		$error(
			'slug_latin',
			sprintf( 'স্লাগ শুধু ইংরেজি ছোট হাতের অক্ষর, সংখ্যা আর হাইফেন দিয়ে লিখুন, যেমন thankuni-patar-upokarita (এখন: “%s”)।', urldecode( $slug ) )
		);
	}
	$reserved = array_merge( GN_RESERVED_SLUGS, array_keys( gn_categories() ) );
	if ( 'page' === $type ) {
		$reserved = array_diff( $reserved, GN_INFO_PAGE_SLUGS );
	}
	if ( in_array( $slug, $reserved, true ) ) {
		$error( 'slug_reserved', sprintf( '“%s” স্লাগটি সাইটের নিজস্ব পাতার জন্য সংরক্ষিত। অন্য স্লাগ দিন।', $slug ) );
	}

	if ( 'post' !== $type ) {
		return compact( 'errors', 'warnings' );
	}

	// Exactly one of the four categories.
	$categories = array_values( array_filter( array_map( 'intval', (array) ( $s['categories'] ?? array() ) ) ) );
	$fixed      = array_filter( $categories, static fn( $id ) => ( $term = get_term( $id, 'category' ) ) instanceof WP_Term && isset( gn_categories()[ $term->slug ] ) );
	if ( 1 !== count( $categories ) || 1 !== count( $fixed ) ) {
		$error( 'category_count', sprintf( 'চারটি বিভাগের ঠিক একটি বেছে নিন (এখন %sটি)।', gn_bn_digits( count( $categories ) ) ) );
	}

	// Excerpt, dek, featured image with alt text.
	$excerpt = trim( (string) ( $s['excerpt'] ?? '' ) );
	$dek     = trim( (string) ( $s['dek'] ?? '' ) );
	if ( '' === $excerpt ) {
		$error( 'excerpt_missing', 'সারাংশ (excerpt) লিখুন: পাঠক কী জানবেন, ১–২ বাক্যে।' );
	}
	if ( '' === $dek ) {
		$error( 'dek_missing', 'ডেক লিখুন: শিরোনামের নিচের ১–২ বাক্য।' );
	}
	$featured = (int) ( $s['featured'] ?? 0 );
	if ( ! $featured || ! wp_attachment_is_image( $featured ) ) {
		$error( 'image_missing', 'ফিচার্ড ছবি দিন।' );
	} elseif ( '' === trim( (string) get_post_meta( $featured, '_wp_attachment_image_alt', true ) ) ) {
		$error( 'image_alt', 'ফিচার্ড ছবির বিকল্প লেখা (alt text) বাংলায় দিন: মিডিয়া লাইব্রেরিতে ছবিটি খুলে “Alternative Text” ঘরে লিখুন।' );
	}

	// Body structure.
	$content = (string) ( $s['content'] ?? '' );
	$blocks  = array_values( array_filter( parse_blocks( $content ), static fn( $b ) => null !== $b['blockName'] ) );
	$names   = array_column( $blocks, 'blockName' );

	$is_health = $s['is_health'] ?? null;
	if ( null === $is_health ) {
		$first     = $fixed ? get_term( reset( $fixed ), 'category' ) : null;
		$is_health = $first instanceof WP_Term ? gn_category_is_health( $first->slug ) : true;
	}
	if ( $is_health ) {
		if ( ! in_array( 'gn/callout-caution', $names, true ) ) {
			$error( 'caution_missing', 'স্বাস্থ্য-বিষয়ক লেখায় “কারা এড়িয়ে চলবেন” ব্লক আবশ্যক।' );
		}
		if ( empty( $s['claims_reviewed'] ) ) {
			$error( 'claims_unreviewed', 'স্বাস্থ্য-দাবি যাচাই বাকি: একজন এডিটর “স্বাস্থ্য-দাবি যাচাই করা হয়েছে” টিক দিলে তবেই প্রকাশ করা যাবে।' );
		}
		if ( ! in_array( 'gn/references', $names, true ) ) {
			$warn( 'refs_missing', 'স্বাস্থ্য-বিষয়ক লেখায় “সূত্র” ব্লক দিন, আসল সূত্রের লিংকসহ।' );
		}
	}

	// In-article ad: at most one, after the second H2, never right after a heading (§5.11).
	$ads = array_keys( $names, 'gn/ad-slot', true );
	if ( count( $ads ) > 1 ) {
		$error( 'ad_count', 'বিজ্ঞাপনের জায়গা একটির বেশি দেওয়া যাবে না।' );
	}
	if ( $ads ) {
		$ad  = $ads[0];
		$h2s = array_keys(
			array_filter( $blocks, static fn( $b ) => 'core/heading' === $b['blockName'] && 2 === (int) ( $b['attrs']['level'] ?? 2 ) )
		);
		if ( count( $h2s ) < 2 || $ad < $h2s[1] ) {
			$error( 'ad_too_early', 'বিজ্ঞাপনের জায়গা দ্বিতীয় H2 শিরোনামের পরে রাখুন।' );
		}
		if ( $ad > 0 && in_array( $names[ $ad - 1 ], array( 'core/heading', 'gn/numbered-heading' ), true ) ) {
			$error( 'ad_after_heading', 'বিজ্ঞাপনের জায়গা কোনো শিরোনামের ঠিক পরে রাখা যাবে না; শিরোনামের পরে আগে একটি অনুচ্ছেদ দিন।' );
		}
	}
	// Every H2 having a unique id is fixed automatically on save (includes/blocks/numbering.php).

	// Wording warnings: cure claims (title, excerpt, dek, body), Latin digits in a Bengali title, emoji.
	$texts = array(
		'শিরোনাম' => $title,
		'সারাংশ'  => $excerpt,
		'ডেক'     => $dek,
		'লেখা'    => wp_strip_all_tags( do_blocks( $content ) ),
	);
	foreach ( gn_cure_phrases() as $phrase ) {
		foreach ( $texts as $field => $text ) {
			$count = mb_substr_count( $text, $phrase );
			if ( $count ) {
				$warn(
					'cure_claim',
					sprintf( '%s-এ নিরাময়ের দাবি: “%s”%s। “সাহায্য করতে পারে” ধরনের ভাষা লিখুন।', $field, $phrase, $count > 1 ? sprintf( ' (%sবার)', gn_bn_digits( $count ) ) : '' )
				);
			}
		}
	}
	if ( preg_match( '/\p{Bengali}/u', $title ) && preg_match_all( '/\d+/', $title, $digits ) ) {
		$warn( 'title_latin_digits', sprintf( 'শিরোনামে ইংরেজি অঙ্ক (%s): বাংলা অঙ্ক লিখুন, যেমন ১০টি।', implode( ', ', $digits[0] ) ) );
	}
	$emoji = '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}]/u';
	foreach ( array_slice( $texts, 0, 3, true ) as $field => $text ) {
		if ( preg_match( $emoji, $text ) ) {
			$warn( 'emoji', "{$field}-এ ইমোজি আছে: বাদ দিন।" );
		}
	}

	return compact( 'errors', 'warnings' );
}

/**
 * The state of a saved post, with optional overrides (e.g. unsaved edits from a REST request).
 */
function gn_publish_state( WP_Post $post, array $override = array() ): array {
	$is_health = get_post_meta( $post->ID, 'is_health', true );
	return array_merge(
		array(
			'type'            => $post->post_type,
			'slug'            => $post->post_name,
			'title'           => $post->post_title,
			'excerpt'         => $post->post_excerpt,
			'content'         => $post->post_content,
			'categories'      => wp_get_post_categories( $post->ID ),
			'featured'        => (int) get_post_thumbnail_id( $post ),
			'dek'             => (string) get_post_meta( $post->ID, 'dek', true ),
			'is_health'       => '' === $is_health ? null : (bool) $is_health,
			'claims_reviewed' => (bool) get_post_meta( $post->ID, 'health_claims_reviewed', true ),
		),
		$override
	);
}

/**
 * Errors as one Bengali sentence list, for error notices.
 */
function gn_publish_error_message( array $problems ): string {
	$lines = array_map( static fn( $p ) => '• ' . $p['message'], $problems['errors'] );
	return "প্রকাশ করা যায়নি:\n" . implode( "\n", $lines );
}
