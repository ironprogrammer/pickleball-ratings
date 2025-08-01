<?php
/**
 * AJAX Handler Class
 *
 * Handles AJAX requests for the DUPR Rating plugin.
 *
 * @package Dupr_Rating
 * @since 0.2.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX Handler Class
 */
class DUPR_Ajax_Handler {

	/**
	 * API instance
	 *
	 * @var DUPR_API
	 */
	private $api;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Ensure the API class is available
		if ( ! class_exists( 'DUPR_API' ) ) {
			require_once DUPR_RATING_PLUGIN_DIR . 'includes/class-dupr-api.php';
		}
		
		$this->api = new DUPR_API();
		add_action( 'wp_ajax_dupr_test_connection', array( $this, 'test_connection' ) );
		add_action( 'wp_ajax_dupr_get_player_data', array( $this, 'get_player_data' ) );
	}

	/**
	 * Test API connection
	 */
	public function test_connection() {
		// Check nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'dupr_test_connection' ) ) {
			wp_send_json_error( 'Security check failed' );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		// Add some debugging
		error_log( 'DUPR: Test connection called' );

		$result = $this->api->test_connection();

		if ( is_wp_error( $result ) ) {
			error_log( 'DUPR: Test connection failed: ' . $result->get_error_message() );
			wp_send_json_error( $result->get_error_message() );
		} else {
			error_log( 'DUPR: Test connection successful' );
			wp_send_json_success( $result );
		}
	}

	/**
	 * Get player data via AJAX
	 */
	public function get_player_data() {
		// Check nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'dupr_get_player_data' ) ) {
			wp_die( 'Security check failed' );
		}

		// Check permissions (allow for logged-in users)
		if ( ! is_user_logged_in() ) {
			wp_die( 'Authentication required' );
		}

		$dupr_id = sanitize_text_field( $_POST['dupr_id'] );

		if ( empty( $dupr_id ) ) {
			wp_send_json_error( 'DUPR ID is required' );
		}

		$result = $this->api->get_player_data( $dupr_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		} else {
			wp_send_json_success( $result );
		}
	}
} 