<?php
/**
 * pageFields on core `page` (spec §4.8).
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

gn_on_fields(
	static function () {
		acf_add_local_field_group(
			array(
				'key'                                  => 'group_gn_page',
				'title'                                => 'পাতার তথ্য (pageFields)',
				'location'                             => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'page',
						),
					),
				),
				'position'                             => 'side',
				'show_in_rest'                         => 0,
				'show_in_graphql'                      => 1,
				'graphql_field_name'                   => 'pageFields',
				'map_graphql_types_from_location_rules' => 0,
				'graphql_types'                        => array( 'Page' ),
				'fields'                               => array(
					gn_field(
						'page',
						'draft',
						'খসড়া পাতা',
						'true_false',
						array(
							'instructions' => 'চালু থাকলে সাইটে "পাতাটি তৈরি হচ্ছে" দেখাবে এবং সার্চ ইঞ্জিন পাতাটি ইনডেক্স করবে না।',
							'ui'           => 1,
						)
					),
				),
			)
		);
	}
);
