<?php
/**
 * AJAX Handler Class.
 *
 * Handles AJAX requests for the Pickleball Ratings plugin.
 *
 * @package Pickleball_Ratings
 * @since 0.2.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX Handler Class.
 */
class PBR_Ajax_Handler {

	/**
	 * API instance.
	 *
	 * @var PBR_DUPR_API
	 */
	private $api;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Ensure the API class is available.
		if ( ! class_exists( 'PBR_DUPR_API' ) ) {
			require_once PICKLEBALL_RATINGS_PLUGIN_DIR . 'includes/class-pbr-dupr-api.php';
		}

		$this->api = new PBR_DUPR_API();
		add_action( 'wp_ajax_pickleball_ratings_test_dupr_connection', array( $this, 'test_connection' ) );
	}

	/**
	 * Test API connection.
	 */
	public function test_connection() {
		// Check nonce.
		if ( ! check_ajax_referer( 'pickleball_ratings_test_dupr_connection', 'nonce', false ) ) {
			wp_send_json_error( 'Security check failed' );
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		// Debug marker.
		if ( function_exists( 'pbr_log' ) ) {
			pbr_log( 'AJAX: test_connection called' );
		}

		$result = $this->api->test_connection();

		if ( is_wp_error( $result ) ) {
			if ( function_exists( 'pbr_log' ) ) {
				pbr_log( 'AJAX: test_connection failed', array( 'error' => $result->get_error_message() ) );
			}
			wp_send_json_error( $result->get_error_message() );
		} else {
			if ( function_exists( 'pbr_log' ) ) {
				pbr_log( 'AJAX: test_connection successful' );
			}
			wp_send_json_success( $result );
		}
	}
}
