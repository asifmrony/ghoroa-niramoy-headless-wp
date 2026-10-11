<?php
/**
 * Plugin Name:       Ghoroa Core
 * Description:       GhoroaNiramoy headless CMS: content model, fields, blocks, GraphQL contract and editorial rules (docs/wordpress-v1-requirements.md).
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            GhoroaNiramoy
 * Text Domain:       ghoroa-core
 * License:           GPL-2.0-or-later
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

define( 'GN_CORE_VERSION', '0.1.0' );
define( 'GN_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'GN_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once GN_CORE_DIR . 'includes/constants.php';
require_once GN_CORE_DIR . 'includes/dependencies.php';
require_once GN_CORE_DIR . 'includes/categories.php';
require_once GN_CORE_DIR . 'includes/post-types.php';
require_once GN_CORE_DIR . 'includes/urls.php';
require_once GN_CORE_DIR . 'includes/images.php';
require_once GN_CORE_DIR . 'includes/video-thumbnails.php';
require_once GN_CORE_DIR . 'includes/rank-math.php';
require_once GN_CORE_DIR . 'includes/fields/helpers.php';
require_once GN_CORE_DIR . 'includes/fields/article-fields.php';
require_once GN_CORE_DIR . 'includes/fields/video-fields.php';
require_once GN_CORE_DIR . 'includes/fields/media-fields.php';
require_once GN_CORE_DIR . 'includes/fields/page-fields.php';
require_once GN_CORE_DIR . 'includes/fields/homepage-options.php';
require_once GN_CORE_DIR . 'includes/graphql/helpers.php';
require_once GN_CORE_DIR . 'includes/graphql/dates.php';
require_once GN_CORE_DIR . 'includes/graphql/article-fields.php';
require_once GN_CORE_DIR . 'includes/graphql/homepage-options.php';
require_once GN_CORE_DIR . 'includes/graphql/plain-text.php';
require_once GN_CORE_DIR . 'includes/graphql/reading-minutes.php';
require_once GN_CORE_DIR . 'includes/graphql/seo.php';
require_once GN_CORE_DIR . 'includes/setup.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once GN_CORE_DIR . 'includes/cli/class-gn-cli.php';
}
