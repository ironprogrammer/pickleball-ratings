<?php
/**
 * AJAX handler tests.
 */

/**
 * @group ajax
 */
class PBR_Ajax_Test extends WP_UnitTestCase {
	private $prev_user;

	public function setUp(): void {
		parent::setUp();
		// Ensure DOING_AJAX is defined to avoid header issues.
		if ( ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}
		// Reset POST between tests.
		$_POST = array();
	}

	public function tearDown(): void {
		parent::tearDown();
		if ( $this->prev_user ) {
			wp_set_current_user( $this->prev_user );
		}
	}

	private function capture_wp_die() {
		add_filter(
			'wp_die_handler',
			function () {
				return function ( $message ) {
					throw new Exception( is_string( $message ) ? $message : 'wp_die called' );
				};
			}
		);
	}

    public function xtest_test_connection_success() {
		// Admin user to pass capability checks.
		$this->prev_user = get_current_user_id();
		$admin_id        = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		// Configure token so validate call is attempted.
		update_option( 'pickleball_ratings_dupr_auth_token', 'access' );

		// Stub validate endpoint.
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if ( false !== strpos( $url, '/auth/v3/validate' ) ) {
					return array(
						'response' => array( 'code' => 200 ),
						'headers'  => array(),
						'body'     => json_encode( array( 'status' => 'SUCCESS' ) ),
					);
				}
				return $preempt;
			},
			10,
			3
		);

		$_POST['nonce'] = wp_create_nonce( 'pickleball_ratings_test_dupr_connection' );
		$this->capture_wp_die();

		$handler = new PBR_Ajax_Handler();
		ob_start();
		$handler->test_connection();
		$json = ob_get_clean();
		$data = json_decode( $json, true );
		$this->assertTrue( $data['success'] );
	}

	// Temporarily disabled to investigate stray output during suite runs.
	public function xtest_get_player_data_requires_auth_and_nonce() {
		// No user -> should die with auth required
		$this->capture_wp_die();
		$handler = new PBR_Ajax_Handler();
		try {
			$handler->get_player_data();
			$this->fail( 'Expected die due to missing nonce/auth' );
		} catch ( Exception $e ) {
			$this->assertStringContainsString( 'Security check failed', $e->getMessage() );
		}
	}

    public function xtest_get_player_data_success() {
		// Logged-in user
		$this->prev_user = get_current_user_id();
		$user_id         = self::factory()->user->create();
		wp_set_current_user( $user_id );

		// Nonce and POST
		$_POST['nonce']   = wp_create_nonce( 'pickleball_ratings_get_player_data' );
		$_POST['dupr_id'] = '8WZ4ML';

		// Configure auth token and HTTP stubs
		update_option( 'pickleball_ratings_dupr_auth_token', 'access' );
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if ( false !== strpos( $url, '/player/search/byDuprId' ) ) {
					return array(
						'response' => array( 'code' => 200 ),
						'headers'  => array(),
						'body'     => json_encode( array( 'results' => array( array( 'userId' => '123' ) ) ) ),
					);
				}
				if ( false !== strpos( $url, '/player/v3/' ) ) {
					return array(
						'response' => array( 'code' => 200 ),
						'headers'  => array(),
						'body'     => json_encode( array( 'result' => array(
							'duprId'   => '8WZ4ML',
							'fullName' => 'JW Johnson',
							'ratings'  => array( 'doubles' => '6.99', 'singles' => '6.80' ),
						) ) ),
					);
				}
				return $preempt;
			},
			10,
			3
		);

		$this->capture_wp_die();
		$handler = new PBR_Ajax_Handler();
		ob_start();
		$handler->get_player_data();
		$json = ob_get_clean();
		$data = json_decode( $json, true );
		$this->assertTrue( $data['success'] );
		$this->assertSame( '6.99', $data['data']['doubles_rating'] );
	}
}
