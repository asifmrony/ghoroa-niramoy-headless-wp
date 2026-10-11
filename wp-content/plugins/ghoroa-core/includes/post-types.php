<?php
/**
 * `video` post type, "Ghoroa Originals" (spec §4.6).
 *
 * Public in GraphQL as video / videos. WordPress never renders it: the public URL is
 * GN_PUBLIC_URL/video/<slug>/ (link rewriting and redirects come with §6).
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		register_post_type(
			'video',
			array(
				'labels'              => array(
					'name'               => 'ভিডিও',
					'singular_name'      => 'ভিডিও',
					'menu_name'          => 'ভিডিও (Ghoroa Originals)',
					'add_new'            => 'নতুন ভিডিও',
					'add_new_item'       => 'নতুন ভিডিও যোগ করুন',
					'edit_item'          => 'ভিডিও সম্পাদনা',
					'new_item'           => 'নতুন ভিডিও',
					'view_item'          => 'ভিডিও দেখুন',
					'search_items'       => 'ভিডিও খুঁজুন',
					'not_found'          => 'কোনো ভিডিও পাওয়া যায়নি',
					'not_found_in_trash' => 'ট্র্যাশে কোনো ভিডিও নেই',
					'all_items'          => 'সব ভিডিও',
					'featured_image'     => 'থাম্বনেইল (খালি থাকলে YouTube থাম্বনেইল)',
					'set_featured_image' => 'থাম্বনেইল দিন',
				),
				'public'              => true,
				'has_archive'         => false,
				'exclude_from_search' => true,
				'show_in_rest'        => true,
				'show_in_graphql'     => true,
				'graphql_single_name' => 'video',
				'graphql_plural_name' => 'videos',
				'menu_icon'           => 'dashicons-video-alt3',
				'menu_position'       => 6,
				'supports'            => array( 'title', 'excerpt', 'thumbnail', 'author', 'revisions' ),
				'rewrite'             => array(
					'slug'       => 'video',
					'with_front' => false,
				),
			)
		);
	}
);
