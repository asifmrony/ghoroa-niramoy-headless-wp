<?php
/**
 * mediaFields on attachments (spec §4.5 `leadCredit`, §5.10 inline figure credit).
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

gn_on_fields(
	static function () {
		acf_add_local_field_group(
			array(
				'key'                                  => 'group_gn_media',
				'title'                                => 'ছবির তথ্য (mediaFields)',
				'location'                             => array(
					array(
						array(
							'param'    => 'attachment',
							'operator' => '==',
							'value'    => 'all',
						),
					),
				),
				'show_in_rest'                         => 0,
				'show_in_graphql'                      => 1,
				'graphql_field_name'                   => 'mediaFields',
				'map_graphql_types_from_location_rules' => 0,
				'graphql_types'                        => array( 'MediaItem' ),
				'fields'                               => array(
					gn_field(
						'media',
						'credit',
						'ছবির কৃতিত্ব (credit)',
						'text',
						array(
							'instructions' => 'যেমন: Photo: GhoroaNiramoy',
							'placeholder'  => 'Photo: GhoroaNiramoy',
						)
					),
				),
			)
		);
	}
);
