<?php
/*
Plugin Name: Tumblr Importer
Plugin URI: http://wordpress.org/extend/plugins/tumblr-importer/
Description: Import posts from a Tumblr blog.
Author: wordpressdotorg
Author URI: http://wordpress.org/
Version: 1.1
License: GPL v2 - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
Text Domain: tumblr-importer
Domain Path: /languages
*/

if ( ! defined( 'WP_LOAD_IMPORTERS' ) && ! defined( 'DOING_CRON' ) ) {
	return;
}

require_once ABSPATH . 'wp-admin/includes/import.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

require_once 'class-wp-importer-cron.php';
require_once 'class-tumblr-import.php';

/**
 * Tumblr Importer Initialisation routines
 *
 * @package WordPress
 * @subpackage Importer
 */
function tumblr_importer_init() {
	global $tumblr_import;
	load_plugin_textdomain( 'tumblr-importer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	$tumblr_import = new Tumblr_Import();
	register_importer( 'tumblr', __( 'Tumblr', 'tumblr-importer' ), __( 'Import posts from a Tumblr blog.', 'tumblr-importer' ), array( $tumblr_import, 'start' ) );
	if ( ! defined( 'TUMBLR_MAX_IMPORT' ) ) {
		define( 'TUMBLR_MAX_IMPORT', 20 );
	}
}

add_action( 'init', 'tumblr_importer_init' );
