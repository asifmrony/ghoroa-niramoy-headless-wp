<?php
/**
 * Helpers for registering SCF field groups in code (acf_add_local_field_group).
 *
 * Field names (meta keys) are snake_case; their GraphQL names are the camelCase names from spec §4.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * snake_case → camelCase.
 */
function gn_camel( string $name ): string {
	return lcfirst( str_replace( ' ', '', ucwords( str_replace( '_', ' ', $name ) ) ) );
}

/**
 * One SCF field definition with a stable key (`field_gn_<scope>_<name>`) and a GraphQL name.
 *
 * @param string $scope  Group scope, e.g. 'article'. Sub-fields use '<scope>_<parent>'.
 * @param string $name   snake_case field name; also the meta key.
 * @param string $label  Label shown to editors (Bengali).
 * @param string $type   SCF field type.
 * @param array  $extra  Any other SCF settings; they override the defaults.
 */
function gn_field( string $scope, string $name, string $label, string $type, array $extra = array() ): array {
	return array_merge(
		array(
			'key'                => "field_gn_{$scope}_{$name}",
			'name'               => $name,
			'label'              => $label,
			'type'               => $type,
			'show_in_graphql'    => 1,
			'graphql_field_name' => gn_camel( $name ),
		),
		$extra
	);
}

/**
 * Registers field groups once SCF is ready. Does nothing if SCF isn't active.
 *
 * @param callable $register Callback that calls acf_add_local_field_group().
 */
function gn_on_fields( callable $register ): void {
	add_action(
		'acf/include_fields',
		static function () use ( $register ) {
			if ( function_exists( 'acf_add_local_field_group' ) ) {
				$register();
			}
		}
	);
}
