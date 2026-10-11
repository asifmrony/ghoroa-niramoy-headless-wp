<?php
/**
 * videoFields on the `video` post type (spec §4.6).
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

/** Duration format from §4.6: "mm:ss" or "h:mm:ss". */
const GN_DURATION_PATTERN = '/^\d{1,2}(:\d{2}){1,2}$/';

/**
 * Extracts the 11-character YouTube ID from a bare ID or any common YouTube URL; null if none.
 */
function gn_youtube_id( string $input ): ?string {
	$input = trim( $input );
	if ( preg_match( '/^[A-Za-z0-9_-]{11}$/', $input ) ) {
		return $input;
	}
	if ( preg_match( '~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/))([A-Za-z0-9_-]{11})(?![A-Za-z0-9_-])~', $input, $m ) ) {
		return $m[1];
	}
	return null;
}

gn_on_fields(
	static function () {
		$s = 'video';

		acf_add_local_field_group(
			array(
				'key'                                  => 'group_gn_video',
				'title'                                => 'ভিডিওর তথ্য (videoFields)',
				'location'                             => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'video',
						),
					),
				),
				'position'                             => 'acf_after_title',
				'show_in_rest'                         => 0,
				'show_in_graphql'                      => 1,
				'graphql_field_name'                   => 'videoFields',
				'map_graphql_types_from_location_rules' => 0,
				'graphql_types'                        => array( 'Video' ),
				'fields'                               => array(
					gn_field(
						$s,
						'youtube_id',
						'YouTube ভিডিও',
						'text',
						array(
							'instructions' => 'YouTube লিংক বা ১১ অক্ষরের ভিডিও আইডি দিন। লিংক দিলে শুধু আইডিটি রাখা হবে।',
							'placeholder'  => 'https://www.youtube.com/watch?v=…',
							'wrapper'      => array( 'width' => '70' ),
						)
					),
					gn_field(
						$s,
						'duration',
						'দৈর্ঘ্য',
						'text',
						array(
							'instructions' => 'mm:ss বা h:mm:ss (Latin অঙ্কে), যেমন 11:30',
							'placeholder'  => '11:30',
							'maxlength'    => 8,
							'wrapper'      => array( 'width' => '30' ),
						)
					),
					// Later: WooCommerce products (§4.6). Exists now so the data model is ready; exposed to
					// GraphQL once the `product` post type exists.
					gn_field(
						$s,
						'related_products',
						'সংশ্লিষ্ট পণ্য (শপ চালু হলে)',
						'relationship',
						array(
							'post_type'       => array( 'product' ),
							'filters'         => array( 'search' ),
							'return_format'   => 'id',
							'show_in_graphql' => 0,
						)
					),
				),
			)
		);
	}
);

add_filter(
	'acf/validate_value/key=field_gn_video_youtube_id',
	static function ( $valid, $value ) {
		if ( true === $valid && '' !== trim( (string) $value ) && null === gn_youtube_id( (string) $value ) ) {
			return 'সঠিক YouTube লিংক বা ১১ অক্ষরের ভিডিও আইডি দিন।';
		}
		return $valid;
	},
	10,
	2
);

add_filter(
	'acf/update_value/key=field_gn_video_youtube_id',
	static function ( $value ) {
		return gn_youtube_id( (string) $value ) ?? trim( (string) $value );
	}
);

add_filter(
	'acf/validate_value/key=field_gn_video_duration',
	static function ( $valid, $value ) {
		if ( true === $valid && '' !== trim( (string) $value ) && ! preg_match( GN_DURATION_PATTERN, trim( (string) $value ) ) ) {
			return 'দৈর্ঘ্য mm:ss বা h:mm:ss আকারে দিন, যেমন 11:30 বা 1:05:00।';
		}
		return $valid;
	},
	10,
	2
);
