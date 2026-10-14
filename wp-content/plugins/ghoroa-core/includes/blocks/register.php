<?php
/**
 * Registers the gn/* ACF blocks (blocks/<slug>/block.json) and their fields (spec §5).
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		if ( ! function_exists( 'acf_register_block_type' ) ) {
			return; // SCF missing; the dependency notice explains.
		}
		foreach ( GN_BLOCKS as $slug ) {
			register_block_type( GN_CORE_DIR . "blocks/{$slug}" );
		}
	}
);

/**
 * A field group shown on one gn/* block. Field keys follow GN_Block_Markup::acf_data().
 */
function gn_block_fields( string $slug, array $fields ): void {
	acf_add_local_field_group(
		array(
			'key'             => 'group_gn_block_' . str_replace( '-', '_', $slug ),
			'title'           => "gn/{$slug}",
			'location'        => array(
				array(
					array(
						'param'    => 'block',
						'operator' => '==',
						'value'    => "gn/{$slug}",
					),
				),
			),
			'show_in_rest'    => 0,
			'show_in_graphql' => 0,
			'fields'          => $fields,
		)
	);
}

gn_on_fields(
	static function () {
		$text = static fn( string $scope, string $name, string $label, array $extra = array() ) => gn_field( $scope, $name, $label, 'text', $extra + array( 'show_in_graphql' => 0 ) );
		$area = static fn( string $scope, string $name, string $label, array $extra = array() ) => gn_field(
			$scope,
			$name,
			$label,
			'textarea',
			$extra + array(
				'rows'            => 2,
				'new_lines'       => '',
				'show_in_graphql' => 0,
			)
		);
		$rows = static fn( string $scope, string $name, string $label, array $sub, array $extra = array() ) => gn_field(
			$scope,
			$name,
			$label,
			'repeater',
			$extra + array(
				'layout'          => 'table',
				'sub_fields'      => $sub,
				'show_in_graphql' => 0,
			)
		);

		$s = 'block_numbered_heading';
		gn_block_fields(
			'numbered-heading',
			array( $text( $s, 'text', 'শিরোনাম', array( 'required' => 1, 'instructions' => 'নম্বর আর id নিজে থেকে বসবে (প্রতিটি H2-এর পরে আবার ১ থেকে)।' ) ) )
		);

		$s = 'block_takeaways';
		gn_block_fields(
			'takeaways',
			array(
				$rows(
					$s,
					'items',
					'মূল কথা (ঠিক ৩টি)',
					array( $area( "{$s}_items", 'text', 'কথা' ) ),
					array(
						'min'          => 3,
						'max'          => 3,
						'required'     => 1,
						'button_label' => 'কথা যোগ করুন',
					)
				),
			)
		);

		$s = 'block_recipe';
		gn_block_fields(
			'recipe',
			array(
				$text( $s, 'title', 'রেসিপির নাম', array( 'required' => 1 ) ),
				gn_field( $s, 'minutes', 'সময় (মিনিট)', 'number', array( 'min' => 1, 'wrapper' => array( 'width' => '50' ), 'show_in_graphql' => 0 ) ),
				gn_field( $s, 'servings', 'কতজনের জন্য', 'number', array( 'min' => 1, 'wrapper' => array( 'width' => '50' ), 'show_in_graphql' => 0 ) ),
				$rows(
					$s,
					'ingredients',
					'উপকরণ',
					array(
						$text( "{$s}_ingredients", 'name', 'উপকরণ' ),
						$text( "{$s}_ingredients", 'amount', 'পরিমাণ (বাংলায়, যেমন ১ মুঠো)' ),
					),
					array(
						'min'          => 1,
						'button_label' => 'উপকরণ যোগ করুন',
					)
				),
				$rows(
					$s,
					'steps',
					'ধাপ',
					array( $area( "{$s}_steps", 'text', 'ধাপ' ) ),
					array(
						'min'          => 1,
						'layout'       => 'row',
						'button_label' => 'ধাপ যোগ করুন',
					)
				),
			)
		);

		$s = 'block_callout_tip';
		gn_block_fields(
			'callout-tip',
			array(
				$text( $s, 'title', 'শিরোনাম', array( 'default_value' => 'টিপস' ) ),
				gn_field(
					$s,
					'text',
					'লেখা (লিংক দেওয়া যাবে)',
					'wysiwyg',
					array(
						'required'        => 1,
						'tabs'            => 'visual',
						'toolbar'         => 'basic',
						'media_upload'    => 0,
						'show_in_graphql' => 0,
					)
				),
			)
		);

		$s = 'block_callout_caution';
		gn_block_fields(
			'callout-caution',
			array(
				$text( $s, 'title', 'শিরোনাম', array( 'default_value' => 'কারা এড়িয়ে চলবেন' ) ),
				$rows(
					$s,
					'items',
					'তালিকা',
					array( $area( "{$s}_items", 'text', 'কারা' ) ),
					array( 'button_label' => 'যোগ করুন' )
				),
				$area( $s, 'closing', 'শেষ বাক্য', array( 'default_value' => 'নিয়মিত খাওয়ার আগে চিকিৎসকের সঙ্গে কথা বলুন।' ) ),
			)
		);

		$s = 'block_references';
		gn_block_fields(
			'references',
			array(
				$rows(
					$s,
					'refs',
					'সূত্র',
					array(
						$area( "{$s}_refs", 'text', 'সূত্রের শিরোনাম, প্রকাশক, সাল।' ),
						gn_field( "{$s}_refs", 'url', 'লিংক (আসল সূত্র)', 'url', array( 'show_in_graphql' => 0 ) ),
						$text( "{$s}_refs", 'link_text', 'লিংকের লেখা', array( 'default_value' => 'লিংক' ) ),
					),
					array(
						'min'          => 1,
						'layout'       => 'row',
						'button_label' => 'সূত্র যোগ করুন',
					)
				),
			)
		);
	}
);
