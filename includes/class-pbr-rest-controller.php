<?php
/**
 * REST API Controller for the Pickleball Ratings plugin.
 *
 * @package Pickleball_Ratings
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PBR_REST_Controller.
 */
class PBR_REST_Controller {

	/**
	 * The namespace for the REST API.
	 *
	 * @var string
	 */
	protected $namespace = 'pickleball-ratings/v1';

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/player/(?P<dupr_id>[\w-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'                => array(
					'dupr_id' => array(
						'description'       => __( 'The DUPR ID of the player.', 'pickleball-ratings' ),
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return preg_match( '/^[A-Z0-9]{6}$/', $param );
						},
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/test-connection',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'test_connection' ),
				'permission_callback' => array( $this, 'test_connection_permissions_check' ),
			)
		);
	}

	/**
	 * Test API connection.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function test_connection( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		$api    = new PBR_DUPR_API();
		$result = $api->test_connection();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Check if a given request has access to test the connection.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function test_connection_permissions_check( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', esc_html__( 'You do not have permissions to test the DUPR connection.', 'pickleball-ratings' ), array( 'status' => 401 ) );
		}
		return true;
	}

	/**
	 * Get player data.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
		$dupr_id = $request->get_param( 'dupr_id' );
	public function get_item( $request ) {
		$api     = new PBR_DUPR_API();
		$data    = $api->get_player_data( $dupr_id );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Check if a given request has access to get player data.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_item_permissions_check( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'rest_forbidden', esc_html__( 'You do not have permissions to view player data.', 'pickleball-ratings' ), array( 'status' => 401 ) );
		}
		return true;
	}
}
