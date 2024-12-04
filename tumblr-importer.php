<?php
/*
Plugin Name: Tumblr Importer
Plugin URI: http://wordpress.org/extend/plugins/tumblr-importer/
Description: Import posts from a Tumblr blog.
Author: wordpressdotorg
Author URI: http://wordpress.org/
Version: 1.2
License: GPL v2 - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
Text Domain: tumblr-importer
Domain Path: /languages
*/
defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_LOAD_IMPORTERS' ) && ! defined( 'DOING_CRON' ) ) {
	return;
}

define( 'TUMBLR_IMPORTER_PATH', plugin_dir_path( __FILE__ ) );

// Load the autoloader.
if ( ! is_file( TUMBLR_IMPORTER_PATH . '/vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		static function () {
			$message      = __( 'It seems like <strong>Tumblr Importer</strong> is corrupted. Please reinstall!', 'tumblr-importer' );
			$html_message = wp_sprintf( '<div class="error notice tumblr-importer-error">%s</div>', wpautop( $message ) );
			echo wp_kses_post( $html_message );
		}
	);
	return;
}
require_once TUMBLR_IMPORTER_PATH . '/vendor/autoload.php';

/** WordPress Import Administration API */
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( ! class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) ) {
		require $class_wp_importer;
	}
}

if ( ! defined( 'WP_ADMIN' ) ) {
	require_once ABSPATH . 'wp-admin/includes/admin.php';
}

use WordPress\TumblrImporter\Tumblr_Import;

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
