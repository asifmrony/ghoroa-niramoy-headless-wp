<?php
/**
 * articleFields on core `post` (spec §4.5).
 *
 * "Required to publish" rules (dek, healthClaimsReviewed on health articles, …) are enforced by the
 * publish checks in §7, not by SCF's `required`, so drafts can always be saved.
 * Who may tick medicallyReviewed / healthClaimsReviewed is enforced with the roles in §8.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

gn_on_fields(
	static function () {
		$s = 'article';

		acf_add_local_field_group(
			array(
				'key'                                  => 'group_gn_article',
				'title'                                => 'লেখার তথ্য (articleFields)',
				'location'                             => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'post',
						),
					),
				),
				'position'                             => 'normal',
				'style'                                => 'default',
				'menu_order'                           => 0,
				'show_in_rest'                         => 0,
				'show_in_graphql'                      => 1,
				'graphql_field_name'                   => 'articleFields',
				'map_graphql_types_from_location_rules' => 0,
				'graphql_types'                        => array( 'Post' ),
				'fields'                               => array(
					gn_field(
						$s,
						'dek',
						'ডেক (শিরোনামের নিচের লাইন)',
						'textarea',
						array(
							'instructions' => 'শিরোনামের নিচে ১–২ বাক্য। সারাংশ (excerpt) থেকে আলাদা হতে পারে। প্রকাশের জন্য আবশ্যক।',
							'rows'         => 2,
							'new_lines'    => '',
						)
					),
					gn_field(
						$s,
						'is_health',
						'স্বাস্থ্য-বিষয়ক লেখা',
						'true_false',
						array(
							'instructions'  => 'চালু থাকলে "কারা এড়িয়ে চলবেন" ব্লক আর স্বাস্থ্য-দাবি যাচাই ছাড়া প্রকাশ করা যাবে না। ঘরোয়া টোটকা আর ভেষজ উদ্ভিদে সাধারণত চালু; রান্নাঘরের মসলা আর ছাদবাগানে বন্ধ। লেখায় স্বাস্থ্য-দাবি থাকলে চালু করুন।',
							'ui'            => 1,
							'default_value' => 1,
						)
					),
					gn_field(
						$s,
						'health_claims_reviewed',
						'স্বাস্থ্য-দাবি যাচাই করা হয়েছে',
						'true_false',
						array(
							'instructions'    => 'শুধু এডিটর বা অ্যাডমিন টিক দেবেন। স্বাস্থ্য-বিষয়ক লেখা প্রকাশের আগে আবশ্যক।',
							'ui'              => 1,
							'show_in_graphql' => 0,
						)
					),
					gn_field(
						$s,
						'medically_reviewed',
						'চিকিৎসক দ্বারা পর্যালোচিত',
						'true_false',
						array(
							'instructions' => 'শুধু মেডিক্যাল রিভিউয়ার এটি দিতে পারবেন।',
							'ui'           => 1,
						)
					),
					gn_field(
						$s,
						'medical_reviewer',
						'পর্যালোচক চিকিৎসক',
						'user',
						array(
							'role'            => array( 'medical_reviewer' ),
							'allow_null'      => 1,
							'multiple'        => 0,
							'return_format'   => 'id',
							'show_in_graphql' => 0,
							'wrapper'         => array( 'width' => '50' ),
						)
					),
					gn_field(
						$s,
						'medical_review_date',
						'পর্যালোচনার তারিখ',
						'date_picker',
						array(
							'display_format'  => 'd/m/Y',
							'return_format'   => 'Y-m-d',
							'first_day'       => 6,
							'show_in_graphql' => 0,
							'wrapper'         => array( 'width' => '50' ),
						)
					),
					gn_field(
						$s,
						'content_updated',
						'বিষয়বস্তু হালনাগাদের সময়',
						'date_time_picker',
						array(
							'instructions'    => 'শুধু বড় ধরনের হালনাগাদে হাতে দিন। পাঠক "হালনাগাদ" তারিখ হিসেবে এটিই দেখবেন।',
							'display_format'  => 'd/m/Y g:i a',
							'return_format'   => 'Y-m-d H:i:s',
							'first_day'       => 6,
							'wrapper'         => array( 'width' => '50' ),
							// Exposed by includes/graphql/article-fields.php with the +06:00 offset (§4.1).
							'show_in_graphql' => 0,
						)
					),
					gn_field(
						$s,
						'reading_minutes_override',
						'পড়ার সময় (মিনিট)',
						'number',
						array(
							'instructions' => 'পাঠক এই সংখ্যাটিই দেখবেন। খালি রাখলে লেখার দৈর্ঘ্য থেকে হিসাব হবে (মিনিটে ১৮০ শব্দ)।',
							'min'          => 1,
							'step'         => 1,
							'wrapper'      => array( 'width' => '50' ),
						)
					),
					gn_field(
						$s,
						'card_tag',
						'কার্ডের লেবেল',
						'taxonomy',
						array(
							'instructions'  => 'ঐচ্ছিক। কার্ডে বিভাগের বদলে এই ট্যাগটি দেখাবে (যেমন গাছপালা)।',
							'taxonomy'      => 'post_tag',
							'field_type'    => 'select',
							'allow_null'    => 1,
							'add_term'      => 0,
							'save_terms'    => 0,
							'load_terms'    => 0,
							'return_format'   => 'object',
							'wrapper'         => array( 'width' => '50' ),
							// Exposed by includes/graphql/article-fields.php as a single Tag (§4.9).
							'show_in_graphql' => 0,
						)
					),
					gn_field(
						$s,
						'featured',
						'হিরোর জন্য প্রস্তাবিত',
						'true_false',
						array(
							'instructions' => 'হোমপেজের হিরো বাছাইয়ের তালিকায় রাখুন।',
							'ui'           => 1,
							'wrapper'      => array( 'width' => '50' ),
						)
					),
					gn_field(
						$s,
						'toc',
						'সূচিপত্র (ঐচ্ছিক)',
						'repeater',
						array(
							'instructions' => 'ছোট সূচি-লেবেল। খালি রাখলে H2 শিরোনামগুলোই সূচি হবে। anchor হলো H2-এর id (যেমন benefits)।',
							'layout'       => 'table',
							'button_label' => 'সূচিতে যোগ করুন',
							'sub_fields'   => array(
								gn_field( "{$s}_toc", 'anchor', 'anchor (Latin id)', 'text' ),
								gn_field( "{$s}_toc", 'label', 'লেবেল', 'text' ),
							),
						)
					),
				),
			)
		);
	}
);

/**
 * When a post has never stored isHealth (e.g. created over REST or WP-CLI), fall back to its
 * category's default (§4.5) rather than the field's static default.
 */
add_filter(
	'acf/load_value/key=field_gn_article_is_health',
	static function ( $value, $post_id ) {
		if ( ( null !== $value && '' !== $value ) || ! is_numeric( $post_id ) ) {
			return $value;
		}
		$terms = get_the_terms( (int) $post_id, 'category' );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return $value;
		}
		return gn_category_is_health( $terms[0]->slug ) ? 1 : 0;
	},
	10,
	2
);
