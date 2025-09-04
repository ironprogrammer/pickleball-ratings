<?php
/**
 * API class tests (error paths and caching).
 */

class PBR_API_Test extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();
		// Ensure a clean state for all auth options.
		delete_option( 'pickleball_ratings_dupr_auth_token' );
		delete_option( 'pickleball_ratings_dupr_auth_refresh_token' );
		delete_option( 'pickleball_ratings_dupr_auth_user_name' );
		delete_option( 'pickleball_ratings_dupr_auth_id' );
		delete_option( 'pickleball_ratings_dupr_auth_email' );
	}

	public function test_invalid_dupr_id_errors() {
		$api = new PBR_DUPR_API();
		$result = $api->get_player_data( 'bad' );
		$this->assertWPError( $result );
		$this->assertSame( 'invalid_dupr_id', $result->get_error_code() );
	}

	public function test_no_auth_errors() {
		$api = new PBR_DUPR_API();
		$result = $api->get_player_data( '8WZ4ML' );
		$this->assertWPError( $result );
		$this->assertSame( 'no_auth', $result->get_error_code() );
	}

	public function test_cache_salt_bump_on_clear_cache() {
		$api = new PBR_DUPR_API();
		$reflection = new ReflectionClass( $api );
		$method = $reflection->getMethod( 'get_cache_salt' );
		$method->setAccessible( true );
		$first = $method->invoke( $api );
		$api->clear_cache();
		$second = $method->invoke( $api );
		$this->assertNotSame( $first, $second );
	}

	public function test_authenticate_missing_credentials() {
		$api = new PBR_DUPR_API();
		
		// Test empty email.
		$result = $api->authenticate( '', 'password' );
		$this->assertWPError( $result );
		$this->assertSame( 'missing_credentials', $result->get_error_code() );
		
		// Test empty password.
		$result = $api->authenticate( 'test@example.com', '' );
		$this->assertWPError( $result );
		$this->assertSame( 'missing_credentials', $result->get_error_code() );
	}

	public function test_authenticate_success() {
		$api = new PBR_DUPR_API();

		// Stub the login endpoint.
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if ( false !== strpos( $url, '/auth/v3/login' ) ) {
					return array(
						'response' => array( 'code' => 200 ),
						'headers'  => array(),
						'body'     => json_encode( array(
							'result' => array(
								'accessToken'  => 'test_access_token',
								'refreshToken' => 'test_refresh_token',
								'user'         => array(
									'fullName'     => 'John Doe',
									'referralCode' => 'ABC123',
								),
							),
						) ),
					);
				}
				return $preempt;
			},
			10,
			3
		);

		$result = $api->authenticate( 'test@example.com', 'password' );
		
		// Verify successful authentication.
		$this->assertIsArray( $result );
		$this->assertSame( 'test_access_token', $result['token'] );
		$this->assertSame( 'John Doe', $result['user_name'] );
		$this->assertSame( 'ABC123', $result['dupr_id'] );
		
		// Verify options were saved.
		$this->assertSame( 'test_access_token', get_option( 'pickleball_ratings_dupr_auth_token' ) );
		$this->assertSame( 'John Doe', get_option( 'pickleball_ratings_dupr_auth_user_name' ) );
		
		// Verify API instance is updated.
		$this->assertTrue( $api->is_authenticated() );
		$this->assertSame( 'John Doe', $api->get_auth_user_name() );
		$this->assertSame( 'ABC123', $api->get_auth_dupr_id() );
		$this->assertSame( 'test@example.com', $api->get_auth_user_email() );
	}

	public function test_authenticate_failure() {
		$api = new PBR_DUPR_API();

		// Stub failed login.
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if ( false !== strpos( $url, '/auth/v3/login' ) ) {
					return array(
						'response' => array( 'code' => 401 ),
						'headers'  => array(),
						'body'     => '{"error": "Invalid credentials"}',
					);
				}
				return $preempt;
			},
			10,
			3
		);

		$result = $api->authenticate( 'test@example.com', 'wrongpassword' );
		
		$this->assertWPError( $result );
		$this->assertSame( 'auth_failed', $result->get_error_code() );
		$this->assertFalse( $api->is_authenticated() );
	}

	public function test_disconnect() {
		$api = new PBR_DUPR_API();
		
		// Set up authenticated state.
		update_option( 'pickleball_ratings_dupr_auth_token', 'test_token' );
		update_option( 'pickleball_ratings_dupr_auth_user_name', 'John Doe' );
		update_option( 'pickleball_ratings_dupr_auth_email', 'test@example.com' );
		
		// Create new API instance to load the options.
		$api = new PBR_DUPR_API();
		$this->assertTrue( $api->is_authenticated() );
		
		// Disconnect.
		$result = $api->disconnect();
		$this->assertTrue( $result );
		
		// Verify everything is cleared.
		$this->assertFalse( $api->is_authenticated() );
		$this->assertEmpty( $api->get_auth_user_name() );
		$this->assertEmpty( $api->get_auth_user_email() );
		$this->assertEmpty( get_option( 'pickleball_ratings_dupr_auth_token' ) );
	}

	public function test_auth_status_and_user_info() {
		$api = new PBR_DUPR_API();
		
		// Test unauthenticated state.
		$status = $api->get_auth_status();
		$this->assertFalse( $status['authenticated'] );
		$this->assertFalse( $status['user_info'] );
		$this->assertFalse( $api->get_user_info() );
		
		// Set up authenticated state.
		update_option( 'pickleball_ratings_dupr_auth_token', 'test_token' );
		update_option( 'pickleball_ratings_dupr_auth_refresh_token', 'test_refresh' );
		update_option( 'pickleball_ratings_dupr_auth_user_name', 'Jane Smith' );
		update_option( 'pickleball_ratings_dupr_auth_id', 'XYZ789' );
		update_option( 'pickleball_ratings_dupr_auth_email', 'jane@example.com' );
		
		// Create new API instance to load the options.
		$api = new PBR_DUPR_API();
		
		// Test authenticated state.
		$status = $api->get_auth_status();
		$this->assertTrue( $status['authenticated'] );
		$this->assertTrue( $status['has_token'] );
		$this->assertTrue( $status['has_refresh'] );
		$this->assertIsArray( $status['user_info'] );
		
		$user_info = $api->get_user_info();
		$this->assertSame( 'Jane Smith', $user_info['user_name'] );
		$this->assertSame( 'XYZ789', $user_info['dupr_id'] );
		$this->assertSame( 'jane@example.com', $user_info['email'] );
		
		// Test individual getters.
		$this->assertSame( 'Jane Smith', $api->get_auth_user_name() );
		$this->assertSame( 'XYZ789', $api->get_auth_dupr_id() );
		$this->assertSame( 'jane@example.com', $api->get_auth_user_email() );
	}
}
