<?php
/**
 * Homepage options page `homepageOptions` (spec §4.7). Editors can edit it (§8).
 *
 * There is deliberately no "category order" field: the order of the four categories is fixed (§4.2).
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

const GN_HOMEPAGE_OPTIONS_SLUG = 'gn-homepage';

add_action(
	'acf/init',
	static function () {
		if ( ! function_exists( 'acf_add_options_page' ) ) {
			return;
		}
		acf_add_options_page(
			array(
				'page_title'        => 'হোমপেজ',
				'menu_title'        => 'হোমপেজ',
				'menu_slug'         => GN_HOMEPAGE_OPTIONS_SLUG,
				'capability'        => 'edit_others_posts',
				'position'          => 3,
				'icon_url'          => 'dashicons-admin-home',
				'redirect'          => false,
				'autoload'          => true,
				'update_button'     => 'হালনাগাদ করুন',
				'updated_message'   => 'হোমপেজ হালনাগাদ হয়েছে।',
				// GraphQL `homepageOptions` is registered by includes/graphql/homepage-options.php in the
				// §4.9 shape; WPGraphQL for ACF would nest it under the field group and use connections.
				'show_in_graphql'   => false,
			)
		);
	}
);

gn_on_fields(
	static function () {
		$s = 'home';

		acf_add_local_field_group(
			array(
				'key'                => 'group_gn_homepage',
				'title'              => 'হোমপেজ (homepageOptions)',
				'location'           => array(
					array(
						array(
							'param'    => 'options_page',
							'operator' => '==',
							'value'    => GN_HOMEPAGE_OPTIONS_SLUG,
						),
					),
				),
				'style'              => 'seamless',
				'show_in_rest'       => 0,
				'show_in_graphql'    => 0,
				'fields'             => array(
					gn_field(
						$s,
						'hero',
						'হিরো লেখা',
						'post_object',
						array(
							'instructions'  => 'হোমপেজের সবচেয়ে বড় লেখা। শুধু প্রকাশিত লেখা। শিরোনাম ৪৫ অক্ষরের মধ্যে হলে ভালো।',
							'required'      => 1,
							'post_type'     => array( 'post' ),
							'post_status'   => array( 'publish' ),
							'allow_null'    => 0,
							'multiple'      => 0,
							'return_format' => 'id',
							'ui'            => 1,
						)
					),
					gn_field(
						$s,
						'video_picks',
						'Ghoroa Originals: বাছাই করা ভিডিও',
						'relationship',
						array(
							'instructions'  => 'সর্বোচ্চ ৮টি, এই ক্রমে দেখাবে। খালি রাখলে সর্বশেষ ৬টি ভিডিও দেখাবে।',
							'post_type'     => array( 'video' ),
							'post_status'   => array( 'publish' ),
							'filters'       => array( 'search' ),
							'max'           => 8,
							'return_format' => 'id',
						)
					),
					gn_field(
						$s,
						'popular_picks',
						'জনপ্রিয় লেখা',
						'relationship',
						array(
							'instructions'  => 'লেখার পাতার "জনপ্রিয়" অংশে সর্বোচ্চ ৪টি, এই ক্রমে।',
							'post_type'     => array( 'post' ),
							'post_status'   => array( 'publish' ),
							'filters'       => array( 'search', 'taxonomy' ),
							'max'           => 4,
							'return_format' => 'id',
						)
					),
					gn_field(
						$s,
						'promo',
						'হেডারের প্রোমো লিংক',
						'group',
						array(
							'layout'     => 'block',
							'sub_fields' => array(
								gn_field(
									"{$s}_promo",
									'label',
									'লেবেল',
									'text',
									array(
										'default_value' => 'নতুন:',
										'wrapper'       => array( 'width' => '20' ),
									)
								),
								gn_field(
									"{$s}_promo",
									'text',
									'লেখা',
									'text',
									array( 'wrapper' => array( 'width' => '50' ) )
								),
								// Text, not url: the value is usually a site-relative path such as /chhadbagan/.
								gn_field(
									"{$s}_promo",
									'url',
									'লিংক',
									'text',
									array(
										'placeholder' => '/chhadbagan/',
										'wrapper'     => array( 'width' => '30' ),
									)
								),
							),
						)
					),
					gn_field(
						$s,
						'popular_searches',
						'জনপ্রিয় খোঁজ',
						'repeater',
						array(
							'instructions' => 'সার্চ বক্সের নিচের চিপস, সর্বোচ্চ ৬টি।',
							'layout'       => 'table',
							'max'          => 6,
							'button_label' => 'খোঁজ যোগ করুন',
							'sub_fields'   => array(
								gn_field( "{$s}_popular_searches", 'term', 'শব্দ', 'text' ),
							),
						)
					),
				),
			)
		);
	}
);
