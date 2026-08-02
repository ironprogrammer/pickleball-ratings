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
class PBR_Player_REST_Controller extends WP_REST_Controller {

	/**
	 * The namespace for the REST API.
	 *
	 * @var string
	 */
	protected $namespace = 'pickleball-ratings/v1';

	/**
	 * The base of this controller's route.
	 *
	 * @var string
	 */
	protected $rest_base = 'player';

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\w-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'                => array(
					'id' => array(
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
				'args'                => array(
					'include_raw_tokens' => $this->get_raw_tokens_arg(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/diagnostics',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_diagnostics' ),
				'permission_callback' => array( $this, 'test_connection_permissions_check' ),
				'args'                => array(
					'include_raw_tokens' => $this->get_raw_tokens_arg(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/probe-refresh',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'probe_refresh' ),
				'permission_callback' => array( $this, 'test_connection_permissions_check' ),
			)
		);
	}

	/**
	 * Shared argument definition for the raw token opt-in.
	 *
	 * @return array Argument definition.
	 */
	private function get_raw_tokens_arg() {
		return array(
			'description' => __( 'Include raw token strings in the diagnostic report.', 'pickleball-ratings' ),
			'type'        => 'boolean',
			'default'     => false,
		);
	}

	/**
	 * Return the DUPR diagnostic report.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Response object.
	 */
	public function get_diagnostics( $request ) {
		$api = new PBR_DUPR_API();

		return new WP_REST_Response(
			$api->get_diagnostics( (bool) $request->get_param( 'include_raw_tokens' ) ),
			200
		);
	}

	/**
	 * Probe the DUPR refresh endpoint with several request shapes.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function probe_refresh( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		$api    = new PBR_DUPR_API();
		$result = $api->probe_refresh_endpoint();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Test API connection.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function test_connection( $request ) {
		$api         = new PBR_DUPR_API();
		$result      = $api->test_connection();
		$include_raw = (bool) $request->get_param( 'include_raw_tokens' );

		// Diagnostics are collected after the test so they reflect the outcome,
		// including the response body of any refresh attempt it triggered.
		$diagnostics = $api->get_diagnostics( $include_raw );

		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			if ( ! is_array( $data ) ) {
				$data = array();
			}
			if ( ! isset( $data['status'] ) ) {
				$data['status'] = 500;
			}
			$data['diagnostics'] = $diagnostics;
			$result->add_data( $data, $result->get_error_code() );

			return $result;
		}

		$result['diagnostics'] = $diagnostics;

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
	public function get_item( $request ) {
		$dupr_id = $request->get_param( 'id' );
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

	/**
	 * Get the schema for a single player, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'player',
			'type'       => 'object',
			'properties' => array(
				'dupr_id'             => array(
					'description' => __( 'The DUPR ID of the player.', 'pickleball-ratings' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'name'                => array(
					'description' => __( 'The name of the player.', 'pickleball-ratings' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'profile_image'       => array(
					'description' => __( 'The URL of the player\'s profile image.', 'pickleball-ratings' ),
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),
				'doubles_rating'      => array(
					'description' => __( 'The player\'s doubles rating.', 'pickleball-ratings' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'singles_rating'      => array(
					'description' => __( 'The player\'s singles rating.', 'pickleball-ratings' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'doubles_reliability' => array(
					'description' => __( 'The reliability of the player\'s doubles rating.', 'pickleball-ratings' ),
					'type'        => array( 'integer', 'null' ),
					'readonly'    => true,
				),
				'singles_reliability' => array(
					'description' => __( 'The reliability of the player\'s singles rating.', 'pickleball-ratings' ),
					'type'        => array( 'integer', 'null' ),
					'readonly'    => true,
				),
				'last_updated'        => array(
					'description' => __( 'The date and time the player data was last updated.', 'pickleball-ratings' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'readonly'    => true,
				),
			),
		);
	}
}
