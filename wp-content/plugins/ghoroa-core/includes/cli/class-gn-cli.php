<?php
/**
 * WP-CLI commands: `wp gn …`.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * GhoroaNiramoy site tasks.
 */
class GN_CLI {

	/**
	 * Applies the spec §3 install settings and the four categories (§4.2). Safe to run repeatedly.
	 *
	 * Sets the timezone (Asia/Dhaka), permalinks (/%postname%/), site language (bn_BD) and the four
	 * categories. On a local environment the admin user keeps an English admin screen.
	 *
	 * ## EXAMPLES
	 *
	 *     wp gn setup
	 */
	public function setup(): void {
		foreach ( gn_dependency_problems() as $problem ) {
			WP_CLI::warning( $problem );
		}

		foreach ( gn_apply_site_settings() as $line ) {
			WP_CLI::log( $line );
		}

		if ( 'bn_BD' !== get_option( 'WPLANG' ) ) {
			WP_CLI::runcommand( 'language core install bn_BD --activate', array( 'exit_error' => false ) );
		}

		// Local only: keep the admin screens in English for the developer; content and site stay Bengali.
		if ( 'local' === wp_get_environment_type() ) {
			$admin = get_user_by( 'login', 'admin' );
			if ( $admin && '' === get_user_meta( $admin->ID, 'locale', true ) ) {
				update_user_meta( $admin->ID, 'locale', 'en_US' );
				WP_CLI::log( 'Local: admin user locale set to en_US.' );
			}
		}

		WP_CLI::success( 'Site settings and categories are in place.' );
	}

	/**
	 * Imports the seed content (spec §11). Idempotent by slug: safe to run again after editing the file.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the seed JSON, relative to the WordPress root (docs/ is mapped there by wp-env).
	 *
	 * ## EXAMPLES
	 *
	 *     wp gn seed docs/seed/sample-content.json
	 *
	 * @param string[] $args Positional arguments.
	 */
	public function seed( array $args ): void {
		$file = $args[0];
		if ( ! is_readable( $file ) ) {
			WP_CLI::error( "Can't read {$file}. Under wp-env, docs/ is mapped to the WordPress root." );
		}
		$data = json_decode( (string) file_get_contents( $file ), true );
		if ( ! is_array( $data ) ) {
			WP_CLI::error( "{$file} isn't valid JSON: " . json_last_error_msg() );
		}

		// Run as an administrator without kses, so markup such as the callout <svg> is kept as-is.
		$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
		if ( ! $admins ) {
			WP_CLI::error( 'No administrator account exists.' );
		}
		wp_set_current_user( (int) $admins[0] );
		kses_remove_filters();

		require_once GN_CORE_DIR . 'includes/cli/class-gn-seeder.php';
		$seeder = new GN_Seeder( $data );
		$seeder->run();

		$rows = array();
		foreach ( $seeder->counts() as $type => $c ) {
			$rows[] = array( 'type' => $type ) + $c;
		}
		WP_CLI\Utils\format_items( 'table', $rows, array( 'type', 'created', 'updated' ) );

		foreach ( $seeder->warnings() as $warning ) {
			WP_CLI::warning( $warning );
		}

		$hero = get_field( 'hero', 'option', false );
		WP_CLI\Utils\format_items(
			'table',
			array(
				array( 'what' => 'published posts', 'total' => (int) wp_count_posts( 'post' )->publish ),
				array( 'what' => 'published videos', 'total' => (int) wp_count_posts( 'video' )->publish ),
				array( 'what' => 'tags', 'total' => (int) wp_count_terms( array( 'taxonomy' => 'post_tag', 'hide_empty' => false ) ) ),
				array( 'what' => 'authors', 'total' => count( get_users( array( 'role' => 'author', 'fields' => 'ID' ) ) ) ),
				array( 'what' => 'homepage hero', 'total' => $hero ? get_post_field( 'post_name', $hero ) : '(not set)' ),
			),
			array( 'what', 'total' )
		);

		WP_CLI::success( "Seeded from {$file}." );
	}

	/**
	 * §5 acceptance: the article's GraphQL `content` matches the reference HTML structurally
	 * (elements, classes, ids, links, comments and text; whitespace ignored).
	 *
	 * ## OPTIONS
	 *
	 * [--file=<file>]
	 * : Reference HTML, relative to the WordPress root.
	 * ---
	 * default: docs/reference/thankuni-patar-upokarita.html
	 * ---
	 *
	 * [--slug=<slug>]
	 * : Post to compare.
	 * ---
	 * default: thankuni-patar-upokarita
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp gn check-reference
	 *
	 * @subcommand check-reference
	 *
	 * @param string[] $args       Positional arguments.
	 * @param string[] $assoc_args Options.
	 */
	public function check_reference( array $args, array $assoc_args ): void {
		$diff = $this->reference_difference( $assoc_args['file'], $assoc_args['slug'] );
		if ( null !== $diff ) {
			WP_CLI::error( "{$assoc_args['slug']} differs from {$assoc_args['file']}:\n{$diff}" );
		}
		WP_CLI::success( "{$assoc_args['slug']} matches {$assoc_args['file']} structurally." );
	}

	/**
	 * Null when the post's public GraphQL content matches the reference file, else what differs.
	 */
	private function reference_difference( string $file, string $slug ): ?string {
		if ( ! is_readable( $file ) ) {
			return "can't read {$file}";
		}
		$user = get_current_user_id();
		wp_set_current_user( 0 );
		$result = graphql(
			array(
				'query'     => 'query ($slug: ID!) { post(id: $slug, idType: SLUG) { content } }',
				'variables' => array( 'slug' => $slug ),
			)
		);
		wp_set_current_user( $user );
		$content = $result['data']['post']['content'] ?? null;
		return null === $content ? "post {$slug} not found or not public" : gn_html_difference( $content, (string) file_get_contents( $file ) );
	}

	/**
	 * Day-one check (spec §3, "Plugin set"): every SCF field type we use resolves in WPGraphQL.
	 *
	 * Creates throwaway content, writes a value into each field, reads it back through an anonymous
	 * (public) GraphQL query, checks that private fields stay out of the schema and that the four
	 * categories are locked (§4.2), then deletes everything it created and restores the homepage options.
	 *
	 * ## EXAMPLES
	 *
	 *     wp gn graphql-check
	 *
	 * @subcommand graphql-check
	 */
	public function graphql_check(): void {
		$created   = array();
		$tag_id    = 0;
		$home_keys = array( 'hero', 'video_picks', 'popular_picks', 'promo', 'popular_searches' );
		$home_was  = array();
		foreach ( $home_keys as $key ) {
			$home_was[ $key ] = get_field( $key, 'option', false );
		}

		try {
			$tag    = wp_insert_term( 'gn-check-tag', 'post_tag', array( 'slug' => 'gn-check-tag' ) );
			$tag_id = is_wp_error( $tag ) ? (int) get_term_by( 'slug', 'gn-check-tag', 'post_tag' )->term_id : (int) $tag['term_id'];

			$post = wp_insert_post(
				array(
					'post_type'     => 'post',
					'post_status'   => 'publish',
					'post_title'    => 'gn-check post',
					'post_name'     => 'gn-check-post',
					'post_date'     => '2026-10-02 08:00:00',
					'post_excerpt'  => '<strong>চেক</strong> &amp; সারাংশ',
					'post_category' => array( (int) get_term_by( 'slug', 'chhadbagan', 'category' )->term_id ),
				),
				true
			);
			$video = wp_insert_post( array( 'post_type' => 'video', 'post_status' => 'publish', 'post_title' => 'gn-check video', 'post_name' => 'gn-check-video' ), true );
			$page  = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'gn-check page', 'post_name' => 'gn-check-page' ), true );
			foreach ( array( $post, $video, $page ) as $id ) {
				if ( is_wp_error( $id ) ) {
					WP_CLI::error( $id->get_error_message() );
				}
				$created[] = $id;
			}
			$media     = wp_insert_attachment(
				array( 'post_title' => 'gn-check image', 'post_excerpt' => 'চেক ক্যাপশন', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit' ),
				'gn-check.jpg',
				$post
			);
			$created[] = $media;

			update_field( 'dek', 'চেক ডেক', $post );
			update_field( 'is_health', 1, $post );
			update_field( 'health_claims_reviewed', 1, $post );
			update_field( 'medically_reviewed', 1, $post );
			update_field( 'featured', 1, $post );
			update_field( 'content_updated', '2026-10-03 09:00:00', $post );
			update_field( 'reading_minutes_override', 7, $post );
			update_field( 'card_tag', $tag_id, $post );
			update_field( 'toc', array( array( 'anchor' => 'benefits', 'label' => 'উপকারিতা' ) ), $post );
			update_field( 'youtube_id', 'https://youtu.be/dQw4w9WgXcQ?si=abc', $video );
			update_field( 'duration', '11:30', $video );
			update_field( 'draft', 1, $page );
			update_field( 'credit', 'Photo: GhoroaNiramoy', $media );
			update_field( 'hero', $post, 'option' );
			update_field( 'video_picks', array( $video ), 'option' );
			update_field( 'popular_picks', array( $post ), 'option' );
			update_field( 'promo', array( 'label' => 'নতুন:', 'text' => 'চেক', 'url' => '/chhadbagan/' ), 'option' );
			update_field( 'popular_searches', array( array( 'term' => 'থানকুনি' ) ), 'option' );
			update_post_meta( $post, 'rank_math_title', '%title% %sep% %sitename%' );
			update_post_meta( $post, 'rank_math_robots', array( 'noindex', 'nofollow' ) );
			update_post_meta( $post, 'rank_math_facebook_image', 'https://example.invalid/og.jpg' );

			// Read back as an anonymous visitor, the way the Astro build sees published content.
			wp_set_current_user( 0 );
			$result = graphql(
				array(
					'query'     => 'query ($post: ID!, $video: ID!, $page: ID!, $media: ID!) {
						tag(id: "gn-check-tag", idType: SLUG) { gnSeo { canonical noindex } }
						post(id: $post, idType: DATABASE_ID) { date excerptText readingMinutes
							gnSeo { title canonical noindex ogImage }
							articleFields {
							dek isHealth medicallyReviewed featured contentUpdated readingMinutesOverride
							cardTag { slug } toc { anchor label } } }
						video(id: $video, idType: DATABASE_ID) { videoFields { youtubeId duration } }
						page(id: $page, idType: DATABASE_ID) { pageFields { draft } }
						mediaItem(id: $media, idType: DATABASE_ID) { captionText mediaFields { credit } }
						homepageOptions {
							hero { ... on Post { databaseId } } videoPicks { ... on Video { databaseId } }
							popularPicks { ... on Post { databaseId } } promo { label text url } popularSearches { term } }
					}',
					'variables' => array(
						'post'  => $post,
						'video' => $video,
						'page'  => $page,
						'media' => $media,
					),
				)
			);
			$d = $result['data'] ?? array();
			$a = $d['post']['articleFields'] ?? array();
			$h = $d['homepageOptions'] ?? array();

			$rows   = array();
			$expect = static function ( string $type, string $path, $actual, $expected ) use ( &$rows ) {
				$rows[] = array(
					'type'   => $type,
					'path'   => $path,
					'value'  => wp_json_encode( $actual, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
					'result' => $actual === $expected ? 'PASS' : 'FAIL (expected ' . wp_json_encode( $expected, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ')',
				);
			};

			$expect( 'textarea', 'post.articleFields.dek', $a['dek'] ?? null, 'চেক ডেক' );
			$expect( 'true/false', 'post.articleFields.isHealth', $a['isHealth'] ?? null, true );
			$expect( 'true/false', 'post.articleFields.medicallyReviewed', $a['medicallyReviewed'] ?? null, true );
			$expect( 'true/false', 'post.articleFields.featured', $a['featured'] ?? null, true );
			$expect( 'date-time (D2)', 'post.articleFields.contentUpdated', $a['contentUpdated'] ?? null, '2026-10-03T09:00:00+06:00' );
			$expect( 'core date (D2)', 'post.date', $d['post']['date'] ?? null, '2026-10-02T08:00:00+06:00' );
			$expect( 'plain text (D3)', 'post.excerptText', $d['post']['excerptText'] ?? null, 'চেক & সারাংশ' );
			$expect( 'plain text (D3)', 'mediaItem.captionText', $d['mediaItem']['captionText'] ?? null, 'চেক ক্যাপশন' );
			$expect( 'number', 'post.articleFields.readingMinutesOverride', $a['readingMinutesOverride'] ?? null, 7.0 );
			$expect( 'taxonomy (D1)', 'post.articleFields.cardTag.slug', $a['cardTag']['slug'] ?? null, 'gn-check-tag' );

			// M4: blocks (§5).
			$registered = array_filter( GN_BLOCKS, static fn( $slug ) => WP_Block_Type_Registry::get_instance()->is_registered( "gn/{$slug}" ) );
			$expect( 'blocks', 'gn/* blocks registered', count( $registered ), count( GN_BLOCKS ) );
			$allowed = apply_filters( 'allowed_block_types_all', true, new WP_Block_Editor_Context( array( 'post' => get_post( $post ) ) ) );
			$expect( 'blocks', 'allowlist excludes core/html, core/columns', is_array( $allowed ) && ! array_intersect( array( 'core/html', 'core/columns' ), $allowed ), true );
			if ( get_page_by_path( 'thankuni-patar-upokarita', OBJECT, 'post' ) && is_readable( 'docs/reference/thankuni-patar-upokarita.html' ) ) {
				$diff = $this->reference_difference( 'docs/reference/thankuni-patar-upokarita.html', 'thankuni-patar-upokarita' );
				$expect( 'blocks', 'reference article = docs/reference (DOM)', $diff ?? 'identical', 'identical' );
				wp_set_current_user( 0 );
			}

			// M3: reading time, SEO, images, Rank Math settings (§4.1, §4.5, §9).
			$seo = $d['post']['gnSeo'] ?? array();
			$expect( 'readingMinutes', 'post.readingMinutes (override)', $d['post']['readingMinutes'] ?? null, 7 );
			$expect( 'readingMinutes', '361 words → minutes', gn_reading_minutes( new WP_Post( (object) array( 'ID' => 0, 'post_content' => str_repeat( 'শব্দ ', 361 ) ) ) ), 3 );
			$expect( 'gnSeo', 'post.gnSeo.title (variables resolved)', $seo['title'] ?? null, 'gn-check post - ' . get_bloginfo( 'name' ) );
			$expect( 'gnSeo', 'post.gnSeo.canonical (Astro URL)', $seo['canonical'] ?? null, gn_public_url() . '/gn-check-post/' );
			$expect( 'gnSeo', 'post.gnSeo.noindex', $seo['noindex'] ?? null, true );
			$expect( 'gnSeo', 'post.gnSeo.ogImage', $seo['ogImage'] ?? null, 'https://example.invalid/og.jpg' );
			$expect( 'gnSeo', 'tag.gnSeo.canonical (Astro URL)', $d['tag']['gnSeo']['canonical'] ?? null, gn_public_url() . '/tag/gn-check-tag/' );
			$expect( 'gnSeo', 'tag.gnSeo.noindex (unset)', $d['tag']['gnSeo']['noindex'] ?? null, false );
			$expect( 'images', 'JPEG sub-sizes saved as WebP', apply_filters( 'image_editor_output_format', array() )['image/jpeg'] ?? null, 'image/webp' );
			$sizes = array_diff( array_keys( gn_image_sizes() ), get_intermediate_image_sizes() );
			$expect( 'images', '§4.1 crops registered', array_values( $sizes ), array() );
			if ( defined( 'RANK_MATH_VERSION' ) ) {
				$modules = (array) get_option( 'rank_math_modules' );
				$expect( 'rank math', 'schema, redirections, 404, indexing modules off', array_values( array_intersect( array( 'rich-snippet', 'redirections', '404-monitor', 'instant-indexing' ), $modules ) ), array() );
				$sitemap = (array) get_option( 'rank-math-options-sitemap' );
				$keys    = array( 'pt_post_sitemap', 'pt_page_sitemap', 'pt_video_sitemap', 'tax_category_sitemap', 'tax_post_tag_sitemap', 'authors_sitemap', 'html_sitemap' );
				$expect( 'rank math', 'content sitemaps off', array_map( static fn( $k ) => $sitemap[ $k ] ?? '(unset)', $keys ), array_fill( 0, count( $keys ), 'off' ) );
			}
			$expect( 'repeater', 'post.articleFields.toc', $a['toc'] ?? null, array( array( 'anchor' => 'benefits', 'label' => 'উপকারিতা' ) ) );
			$expect( 'text (URL→ID)', 'video.videoFields.youtubeId', $d['video']['videoFields']['youtubeId'] ?? null, 'dQw4w9WgXcQ' );
			$expect( 'text', 'video.videoFields.duration', $d['video']['videoFields']['duration'] ?? null, '11:30' );
			$expect( 'true/false', 'page.pageFields.draft', $d['page']['pageFields']['draft'] ?? null, true );
			$expect( 'text', 'mediaItem.mediaFields.credit', $d['mediaItem']['mediaFields']['credit'] ?? null, 'Photo: GhoroaNiramoy' );
			$expect( 'options + post object (D1)', 'homepageOptions.hero', $h['hero']['databaseId'] ?? null, $post );
			$expect( 'relationship (D1)', 'homepageOptions.videoPicks', array_column( $h['videoPicks'] ?? array(), 'databaseId' ), array( $video ) );
			$expect( 'relationship (D1)', 'homepageOptions.popularPicks', array_column( $h['popularPicks'] ?? array(), 'databaseId' ), array( $post ) );
			$expect( 'group', 'homepageOptions.promo', $h['promo'] ?? null, array( 'label' => 'নতুন:', 'text' => 'চেক', 'url' => '/chhadbagan/' ) );
			$expect( 'repeater', 'homepageOptions.popularSearches', $h['popularSearches'] ?? null, array( array( 'term' => 'থানকুনি' ) ) );

			// Private fields must not be in the schema at all (§4.5).
			$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
			wp_set_current_user( (int) ( $admins[0] ?? 0 ) );
			$schema  = graphql( array( 'query' => '{ __type(name: "ArticleFields") { fields { name } } }' ) );
			$exposed = array_column( $schema['data']['__type']['fields'] ?? array(), 'name' );
			foreach ( array( 'healthClaimsReviewed', 'medicalReviewer', 'medicalReviewDate' ) as $private ) {
				$expect( 'private', "ArticleFields.{$private} not in schema", in_array( $private, $exposed, true ), false );
			}

			// Category lock (§4.2).
			$fifth = wp_insert_term( 'পঞ্চম', 'category', array( 'slug' => 'gn-check-fifth' ) );
			if ( ! is_wp_error( $fifth ) ) {
				wp_delete_term( $fifth['term_id'], 'category' );
			}
			$expect( 'category lock', 'insert a fifth category', is_wp_error( $fifth ) ? 'blocked' : 'created', 'blocked' );
			$editor   = $this->editor_stub();
			$category = get_term_by( 'slug', 'chhadbagan', 'category' )->term_id;
			$expect( 'category lock', 'editor can add categories', user_can( $editor, get_taxonomy( 'category' )->cap->edit_terms ), false );
			$expect( 'category lock', 'editor can rename a category', user_can( $editor, 'edit_term', $category ), false );
			$expect( 'category lock', 'editor can assign categories', user_can( $editor, 'assign_term', $category ), true );
			$expect( 'category lock', 'delete a fixed category', current_user_can( 'delete_term', get_term_by( 'slug', 'chhadbagan', 'category' )->term_id ), false );

			if ( ! empty( $result['errors'] ) ) {
				WP_CLI::warning( 'GraphQL errors: ' . wp_json_encode( $result['errors'], JSON_UNESCAPED_UNICODE ) );
			}

			WP_CLI\Utils\format_items( 'table', $rows, array( 'type', 'path', 'value', 'result' ) );

			$failed = count( array_filter( $rows, static fn( $r ) => 'PASS' !== $r['result'] ) );
			$total  = count( $rows );
		} finally {
			foreach ( $created as $id ) {
				wp_delete_post( $id, true );
			}
			if ( $tag_id ) {
				wp_delete_term( $tag_id, 'post_tag' );
			}
			foreach ( $home_was as $key => $value ) {
				if ( null === $value || false === $value ) {
					delete_field( $key, 'option' );
				} else {
					update_field( $key, $value, 'option' );
				}
			}
			if ( isset( $GLOBALS['gn_check_editor'] ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
				wp_delete_user( $GLOBALS['gn_check_editor'] );
			}
		}

		// After cleanup: WP_CLI::error() exits, which would skip the finally block.
		if ( $failed ) {
			WP_CLI::error( "{$failed} of {$total} checks failed." );
		}
		WP_CLI::success( "All {$total} checks passed." );
	}

	/**
	 * A temporary editor-role user for capability checks; removed by graphql_check().
	 */
	private function editor_stub(): int {
		$id = wp_insert_user(
			array(
				'user_login' => 'gn-check-editor-' . wp_generate_password( 6, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => 'editor',
			)
		);
		$GLOBALS['gn_check_editor'] = $id;
		return (int) $id;
	}
}

WP_CLI::add_command( 'gn', 'GN_CLI' );
