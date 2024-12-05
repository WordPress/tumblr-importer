<?php
/**
 * WP_Importer_Cron base class
 *
 * Adds cron based importing functionality to WP_Importer
 */

namespace WordPress\TumblrImporter;

use WP_Importer;

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WP_Importer_Cron' ) ) {
	return;
}

class WP_Importer_Cron extends WP_Importer {

	/**
	 * Constructor
	 */
	public function __construct() {
		// start the clock
		$this->_importer_started   = time();
		$this->_max_execution_time = 30; // default to 30 seconds of time on the clock
		parent::__construct();

		// add the importer schedule
		add_filter( 'cron_schedules', array( $this, 'importer_schedule' ) );

		// add the importer hook
		add_action( 'wp_cron_importer_hook', array( $this, 'importer_callback' ) );

		//load the variables
		$options = get_option( get_class( $this ) );

		if ( is_array( $options ) ) {
			foreach ( $options as $key => $value ) {
				$this->$key = $value;
			}
		}
	}

	/**
	 * Checks to see if you have time remaining on the importer
	 *
	 * @return bool
	 */
	public function have_time() {
		if ( time() - $this->_importer_started > $this->_max_execution_time ) {
			return false;
		}
		return true;
	}

	/**
	 * Adds the once every three minute schedule
	 *
	 * @param array $schedules The schedules.
	 *
	 * @return array
	 */
	public function importer_schedule( $schedules ) {
		$schedules['everyminute'] = array(
			'interval' => 60,
			'display'  => __( 'Every Minute', 'tumblr-importer' ),
		);
		return $schedules;
	}

	/**
	 * Schedules an importer job
	 *
	 * @param string $function_name The function name.
	 * @param array  $args          The arguments.
	 */
	public function schedule_import_job( $function_name, $args = array() ) {
		$this->callback = $function_name;
		wp_schedule_event( time(), 'everyminute', 'wp_cron_importer_hook', $args );
	}

	/**
	 * Internal callback that checks for a finished import and clears old jobs out
	 *
	 * @param array $args The arguments.
	 */
	public function importer_callback( $args = array() ) {
		$args = (array) $args;

		if ( isset( $this->callback ) ) {
			$complete = call_user_func_array( array( $this, $this->callback ), $args );
		} else {
			// no callback, force the cron job to end
			$complete = true;
		}

		if ( $complete ) {
			// importer is finished, stop the scheduler
			wp_clear_scheduled_hook( 'wp_cron_importer_hook', $args );
		}
	}

	/**
	 * Saves the class variables
	 *
	 * @return bool
	 */
	public function save_vars() {
		$vars = get_object_vars( $this );
		foreach ( $vars as $var => $val ) {
			if ( '_' == $var[0] ) {
				unset( $vars[ $var ] );
			}
		}
		update_option( get_class( $this ), $vars );

		return ! empty( $vars );
	}
}
