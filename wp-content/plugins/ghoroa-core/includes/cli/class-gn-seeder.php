<?php
/**
 * Seed importer (spec §11): loads docs/seed/sample-content.json. Idempotent by slug: running it
 * again updates what exists and creates only what's missing.
 *
 * Field names in the file follow the Astro types; they're mapped per §4:
 *   post.dek → articleFields.dek (else the excerpt)      post.updated → articleFields.contentUpdated
 *   post.isHealth → articleFields.isHealth (else the category default, §4.5)
 *   post.cardTag.slug → articleFields.cardTag             post.toc[].id → articleFields.toc[].anchor
 *   post.leadCaption / leadCredit → featured image caption / mediaFields.credit
 *   video.description → excerpt                           author.bio → user description
 *
 * Only the reference article has a body. Until the §5 blocks exist (M4) every body is stored as
 * one core/html block, so it renders byte-for-byte; M4 switches this importer to real blocks.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Imports one seed file.
 */
class GN_Seeder {

	/** Meta key that marks attachments created by the seeder. */
	const IMAGE_KEY = '_gn_seed_image';

	/** @var array<string, array{created: int, updated: int}> */
	private array $counts = array();

	/** @var string[] */
	private array $warnings = array();

	/** @var array<string, int> Author slug → user ID. */
	private array $authors = array();

	/** @var array<string, int> Tag slug → term ID. */
	private array $tags = array();

	/**
	 * @param array $data Decoded seed file.
	 */
	public function __construct( private array $data ) {}

	/**
	 * Runs every step in dependency order.
	 */
	public function run(): void {
		$this->categories();
		$this->tags();
		$this->authors();
		$placeholder = $this->image(
			'placeholder',
			array(
				'title' => 'নমুনা ছবি',
				'alt'   => 'ঘরোয়া নিরাময়ের নমুনা ছবি: সবুজ পাতার প্রতীক',
			)
		);
		$this->posts( $placeholder );
		$this->videos();
		$this->homepage();
	}

	/** @return array<string, array{created: int, updated: int}> */
	public function counts(): array {
		return $this->counts;
	}

	/** @return string[] */
	public function warnings(): array {
		return $this->warnings;
	}

	private function count( string $type, bool $created ): void {
		$this->counts[ $type ] ??= array(
			'created' => 0,
			'updated' => 0,
		);
		++$this->counts[ $type ][ $created ? 'created' : 'updated' ];
	}

	/**
	 * The four categories are owned by code (§4.2); the seed only has to agree with it.
	 */
	private function categories(): void {
		gn_ensure_categories();
		foreach ( $this->data['categories'] ?? array() as $cat ) {
			$fixed = gn_categories()[ $cat['slug'] ] ?? null;
			if ( ! $fixed ) {
				$this->warnings[] = "Seed category '{$cat['slug']}' isn't one of the four fixed categories; skipped.";
				continue;
			}
			if ( $fixed['name'] !== $cat['name'] || $fixed['description'] !== $cat['description'] ) {
				$this->warnings[] = "Seed category '{$cat['slug']}' differs from includes/categories.php; the code wins.";
			}
		}
	}

	private function tags(): void {
		foreach ( $this->data['tags'] ?? array() as $tag ) {
			$this->tag( $tag['slug'], $tag['name'] );
		}
	}

	/**
	 * Upserts a tag by slug and returns its ID.
	 */
	private function tag( string $slug, string $name ): int {
		if ( isset( $this->tags[ $slug ] ) ) {
			return $this->tags[ $slug ];
		}
		$term = get_term_by( 'slug', $slug, 'post_tag' );
		if ( $term ) {
			if ( $term->name !== $name ) {
				wp_update_term( $term->term_id, 'post_tag', array( 'name' => $name ) );
			}
			$this->count( 'tags', false );
			return $this->tags[ $slug ] = $term->term_id;
		}
		$result = wp_insert_term( $name, 'post_tag', array( 'slug' => $slug ) );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( "Tag {$slug}: {$result->get_error_message()}" );
		}
		$this->count( 'tags', true );
		return $this->tags[ $slug ] = (int) $result['term_id'];
	}

	/**
	 * Authors are users with the Latin slug as login and nicename (§4.4). Emails are placeholders on
	 * the reserved .invalid TLD, so nothing can ever be sent to them.
	 */
	private function authors(): void {
		foreach ( $this->data['authors'] ?? array() as $author ) {
			$slug = $author['slug'];
			$user = get_user_by( 'slug', $slug );
			$args = array(
				'user_nicename' => $slug,
				'display_name'  => $author['name'],
				'nickname'      => $author['name'],
				'description'   => $author['bio'] ?? '',
			);

			if ( $user ) {
				$id = wp_update_user( array( 'ID' => $user->ID ) + $args );
			} else {
				$id = wp_insert_user(
					$args + array(
						'user_login' => $slug,
						'user_email' => "{$slug}@example.invalid",
						'user_pass'  => wp_generate_password( 32 ),
						'role'       => 'author',
					)
				);
			}
			if ( is_wp_error( $id ) ) {
				WP_CLI::error( "Author {$slug}: {$id->get_error_message()}" );
			}
			$this->count( 'authors', ! $user );
			$this->authors[ $slug ] = (int) $id;
		}
	}

	/**
	 * Upserts a generated placeholder image (the seed has no images). 1600×1000 so every §4.1 crop
	 * can be cut from it. Returns the attachment ID.
	 *
	 * @param string $key  Stable key stored in IMAGE_KEY meta.
	 * @param array  $meta title, alt, and optionally caption and credit.
	 */
	private function image( string $key, array $meta ): int {
		$existing = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'meta_key'    => self::IMAGE_KEY,
				'meta_value'  => $key,
				'fields'      => 'ids',
				'numberposts' => 1,
			)
		);
		$id = (int) ( $existing[0] ?? 0 );

		if ( ! $id ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$tmp = wp_tempnam( "gn-seed-{$key}.jpg" );
			$this->draw_placeholder( $tmp );
			$id = media_handle_sideload(
				array(
					'name'     => "gn-seed-{$key}.jpg",
					'tmp_name' => $tmp,
				),
				0,
				$meta['title']
			);
			if ( is_wp_error( $id ) ) {
				WP_CLI::error( "Image {$key}: {$id->get_error_message()}" );
			}
			update_post_meta( $id, self::IMAGE_KEY, $key );
		}

		wp_update_post(
			array(
				'ID'           => $id,
				'post_title'   => $meta['title'],
				'post_excerpt' => $meta['caption'] ?? '',
			)
		);
		update_post_meta( $id, '_wp_attachment_image_alt', $meta['alt'] );
		update_field( 'credit', $meta['credit'] ?? 'Photo: GhoroaNiramoy', $id );

		$this->count( 'images', empty( $existing ) );
		return $id;
	}

	/**
	 * A plain brand-coloured leaf on the surface colour (design-system/tokens/tokens.css).
	 */
	private function draw_placeholder( string $path ): void {
		$w   = 1600;
		$h   = 1000;
		$img = imagecreatetruecolor( $w, $h );
		$rgb = static fn( string $hex ) => imagecolorallocate( $img, ...array_map( 'hexdec', str_split( ltrim( $hex, '#' ), 2 ) ) );

		imagefill( $img, 0, 0, $rgb( '#DCEFE3' ) );                                   // --leaf-tint
		imagefilledellipse( $img, $w / 2, $h / 2, 520, 300, $rgb( '#2D6A4F' ) );     // --primary
		imagesetthickness( $img, 10 );
		imageline( $img, $w / 2 - 240, $h / 2 + 40, $w / 2 + 240, $h / 2 - 40, $rgb( '#74C69D' ) ); // --secondary
		imagejpeg( $img, $path, 85 );
		imagedestroy( $img );
	}

	/**
	 * @param int $placeholder Shared featured image for posts without their own.
	 */
	private function posts( int $placeholder ): void {
		foreach ( $this->data['posts'] ?? array() as $p ) {
			$category = get_term_by( 'slug', $p['category'], 'category' );
			if ( ! $category ) {
				$this->warnings[] = "Post {$p['slug']}: unknown category '{$p['category']}'; skipped.";
				continue;
			}
			$author = $this->authors[ $p['author'] ] ?? 0;
			if ( ! $author ) {
				$this->warnings[] = "Post {$p['slug']}: unknown author '{$p['author']}'; using the current user.";
			}

			$is_health = (bool) ( $p['isHealth'] ?? gn_category_is_health( $p['category'] ) );
			$has_body  = ! empty( $p['contentHtml'] );
			$content   = $has_body ? $p['contentHtml'] : $this->placeholder_body( $p['excerpt'], $is_health );

			$id = $this->upsert(
				'post',
				$p['slug'],
				array(
					'post_title'    => $p['title'],
					'post_excerpt'  => $p['excerpt'],
					'post_content'  => $this->html_block( $content ),
					'post_author'   => $author ?: get_current_user_id(),
					'post_category' => array( $category->term_id ),
				) + $this->dates( $p['date'] )
			);

			wp_set_object_terms( $id, array_map( fn( $t ) => $this->tag( $t['slug'], $t['name'] ), $p['tags'] ?? array() ), 'post_tag' );

			// The reference article gets its own lead image so its caption and credit stay its own.
			$image = $placeholder;
			if ( isset( $p['leadCaption'] ) || isset( $p['leadCredit'] ) ) {
				$image = $this->image(
					"lead-{$p['slug']}",
					array(
						'title'   => $p['title'],
						'alt'     => $p['leadCaption'] ?? $p['title'],
						'caption' => $p['leadCaption'] ?? '',
						'credit'  => $p['leadCredit'] ?? 'Photo: GhoroaNiramoy',
					)
				);
			}
			set_post_thumbnail( $id, $image );

			update_field( 'dek', $p['dek'] ?? $p['excerpt'], $id );
			update_field( 'is_health', $is_health ? 1 : 0, $id );
			// Seed content counts as reviewed so it passes the §7 publish checks (spec §11).
			update_field( 'health_claims_reviewed', $is_health ? 1 : 0, $id );
			update_field( 'medically_reviewed', empty( $p['medicallyReviewed'] ) ? 0 : 1, $id );
			update_field( 'featured', ( $this->data['homepageOptions']['hero'] ?? '' ) === $p['slug'] ? 1 : 0, $id );
			update_field( 'content_updated', isset( $p['updated'] ) ? $this->local( $p['updated'] ) : '', $id );
			update_field( 'card_tag', isset( $p['cardTag'] ) ? $this->tag( $p['cardTag']['slug'], $p['cardTag']['name'] ) : '', $id );
			update_field(
				'toc',
				array_map(
					static fn( $t ) => array(
						'anchor' => $t['id'],
						'label'  => $t['label'],
					),
					$p['toc'] ?? array()
				),
				$id
			);
			// Placeholder bodies are too short to count; keep the seed's reading time for them (§4.5).
			update_field( 'reading_minutes_override', $has_body ? '' : (int) $p['readingMinutes'], $id );
		}
	}

	private function videos(): void {
		foreach ( $this->data['videos'] ?? array() as $v ) {
			$id = $this->upsert(
				'video',
				$v['slug'],
				array(
					'post_title'   => $v['title'],
					'post_excerpt' => $v['description'] ?? '',
				) + $this->dates( $v['date'] )
			);
			update_field( 'duration', $v['duration'], $id );
			// The seed has no IDs yet (docs/astro-handoff.md S1): never clear one an editor has entered.
			if ( ! empty( $v['youtubeId'] ) ) {
				update_field( 'youtube_id', $v['youtubeId'], $id );
			}
			// update_field() doesn't fire acf/save_post, so apply the §4.6 thumbnail fallback here.
			$thumb = gn_sync_youtube_thumbnail( $id );
			if ( 'download failed' === $thumb ) {
				$this->warnings[] = "Video {$v['slug']}: couldn't download the YouTube thumbnail.";
			}
			if ( ! empty( $v['image'] ) ) {
				$this->warnings[] = "Video {$v['slug']}: seed images aren't imported; set the thumbnail by hand.";
			}
		}
	}

	private function homepage(): void {
		$h = $this->data['homepageOptions'] ?? null;
		if ( ! $h ) {
			return;
		}
		update_field( 'hero', $this->ids( 'post', array( $h['hero'] ) )[0] ?? '', 'option' );
		update_field( 'video_picks', $this->ids( 'video', $h['videoPicks'] ?? array() ), 'option' );
		update_field( 'popular_picks', $this->ids( 'post', $h['popularPicks'] ?? array() ), 'option' );
		update_field(
			'promo',
			array(
				'label' => $h['promo']['label'] ?? '',
				'text'  => $h['promo']['text'] ?? '',
				'url'   => $h['promo']['url'] ?? '',
			),
			'option'
		);
		update_field( 'popular_searches', array_map( static fn( $t ) => array( 'term' => $t ), $h['popularSearches'] ?? array() ), 'option' );
		$this->count( 'homepage options', false );
	}

	/**
	 * Creates or updates a published post of the given type by slug. Returns its ID.
	 */
	private function upsert( string $type, string $slug, array $args ): int {
		$found = get_posts(
			array(
				'post_type'   => $type,
				'name'        => $slug,
				'post_status' => 'any',
				'fields'      => 'ids',
				'numberposts' => 1,
			)
		);
		$args += array(
			'post_type'   => $type,
			'post_name'   => $slug,
			'post_status' => 'publish',
		);
		if ( $found ) {
			$args['ID'] = $found[0];
		}

		// wp_insert_post() unslashes its input.
		$id = wp_insert_post( wp_slash( $args ), true );
		if ( is_wp_error( $id ) ) {
			WP_CLI::error( "{$type} {$slug}: {$id->get_error_message()}" );
		}
		if ( get_post_field( 'post_name', $id ) !== $slug ) {
			$this->warnings[] = "{$type} {$slug}: WordPress changed the slug to '" . get_post_field( 'post_name', $id ) . "' (slug taken?).";
		}
		$this->count( "{$type}s", ! $found );
		return (int) $id;
	}

	/**
	 * Slugs → IDs of published posts, in the given order; warns about any that are missing.
	 *
	 * @param string[] $slugs
	 * @return int[]
	 */
	private function ids( string $type, array $slugs ): array {
		$ids = array();
		foreach ( $slugs as $slug ) {
			$post = get_page_by_path( $slug, OBJECT, $type );
			if ( $post && 'publish' === $post->post_status ) {
				$ids[] = $post->ID;
			} else {
				$this->warnings[] = "Homepage options: no published {$type} '{$slug}'.";
			}
		}
		return $ids;
	}

	/**
	 * ISO 8601 → post_date (site time, Asia/Dhaka) and post_date_gmt.
	 */
	private function dates( string $iso ): array {
		$date = new DateTimeImmutable( $iso );
		return array(
			'post_date'     => $date->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' ),
			'post_date_gmt' => $date->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * ISO 8601 → the 'Y-m-d H:i:s' site-time format SCF stores for date-time fields.
	 */
	private function local( string $iso ): string {
		return ( new DateTimeImmutable( $iso ) )->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Wraps HTML in a core/html block: it renders unchanged and skips wpautop/wptexturize.
	 */
	private function html_block( string $html ): string {
		return "<!-- wp:html -->\n" . trim( $html ) . "\n<!-- /wp:html -->";
	}

	/**
	 * Short body for summary-level posts (§11). Health posts get the §5.8 caution callout so they
	 * pass the §7 checks. No cure claims: "সাহায্য করতে পারে" only.
	 */
	private function placeholder_body( string $excerpt, bool $is_health ): string {
		$html = '<p>' . esc_html( $excerpt ) . '</p>'
			. '<h2 id="s1">বিস্তারিত</h2>'
			. '<p>এটি পরীক্ষার জন্য রাখা নমুনা লেখা। পূর্ণ লেখা পরে যোগ করা হবে।</p>';

		if ( $is_health ) {
			$html .= '<div class="gn-callout gn-callout--caution"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 2 20h20L12 3z"/><path d="M12 10v4M12 17h.01"/></svg>'
				. '<div><span class="gn-callout__title">কারা এড়িয়ে চলবেন</span><ul>'
				. '<li>গর্ভবতী ও স্তন্যদানকারী মায়েরা</li>'
				. '<li>যাঁরা নিয়মিত ওষুধ খান</li>'
				. '</ul>নিয়মিত ব্যবহারের আগে চিকিৎসকের সঙ্গে কথা বলুন।</div></div>';
		}
		return $html;
	}
}
