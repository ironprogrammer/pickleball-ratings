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

		// Clean up any cached player data from previous tests.
		global $wpdb;
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE 'pbr_dupr_player_%'" );
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

	public function test_cache_fresh_hit_returns_cached_data() {
		// Set up authentication.
		update_option( 'pickleball_ratings_dupr_auth_token', 'test_token' );

		// Create fresh cached data (recently updated).
		$fresh_cached_data = array(
			'dupr_id'             => 'ABC123',
			'name'                => 'Test Player',
			'profile_image'       => '',
			'doubles_rating'      => '4.5',
			'singles_rating'      => 'NR',
			'doubles_reliability' => 95,
			'singles_reliability' => null,
			'last_updated'        => current_time( 'mysql' ), // Just updated.
		);

		update_option( 'pbr_dupr_player_ABC123', $fresh_cached_data, false );

		$api = new PBR_DUPR_API();

		// Stub API to fail if called - it shouldn't be called for fresh cache.
		add_filter(
			'pre_http_request',
			function () {
				$this->fail( 'API should not be called for fresh cache hit' );
			},
			10,
			3
		);

		$result = $api->get_player_data( 'ABC123' );

		// Should return cached data.
		$this->assertIsArray( $result );
		$this->assertSame( 'Test Player', $result['name'] );
		$this->assertSame( '4.5', $result['doubles_rating'] );
	}

	public function test_cache_stale_with_successful_refresh() {
		// Set up authentication.
		update_option( 'pickleball_ratings_dupr_auth_token', 'test_token' );

		// Create stale cached data (updated 25 hours ago, default TTL is 24 hours).
		$stale_time = gmdate( 'Y-m-d H:i:s', time() - ( 25 * HOUR_IN_SECONDS ) );
		$stale_cached_data = array(
			'dupr_id'             => 'XYZ789',
			'name'                => 'Stale Player',
			'profile_image'       => '',
			'doubles_rating'      => '3.0',
			'singles_rating'      => 'NR',
			'doubles_reliability' => 80,
			'singles_reliability' => null,
			'last_updated'        => $stale_time,
		);

		update_option( 'pbr_dupr_player_XYZ789', $stale_cached_data, false );

		$api = new PBR_DUPR_API();

		// Stub API to return fresh data.
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if ( false !== strpos( $url, '/player/search/byDuprId' ) ) {
					return array(
						'response' => array( 'code' => 200 ),
						'headers'  => array(),
						'body'     => json_encode( array(
							'results' => array(
								array( 'userId' => '12345' ),
							),
						) ),
					);
				}
				if ( false !== strpos( $url, '/player/v3/12345' ) ) {
					return array(
						'response' => array( 'code' => 200 ),
						'headers'  => array(),
						'body'     => json_encode( array(
							'result' => array(
								'duprId'   => 'XYZ789',
								'fullName' => 'Fresh Player',
								'ratings'  => array(
									'doubles' => '4.0', // Updated rating.
									'singles' => 'NR',
									'doublesReliabilityScore' => 90,
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

		$result = $api->get_player_data( 'XYZ789' );

		// Should return fresh data from API.
		$this->assertIsArray( $result );
		$this->assertSame( 'Fresh Player', $result['name'] );
		$this->assertSame( '4.0', $result['doubles_rating'] );
	}

	public function test_cache_stale_with_failed_refresh_returns_stale_data() {
		// Set up authentication.
		update_option( 'pickleball_ratings_dupr_auth_token', 'test_token' );

		// Create stale cached data (updated 25 hours ago).
		$stale_time = gmdate( 'Y-m-d H:i:s', time() - ( 25 * HOUR_IN_SECONDS ) );
		$stale_cached_data = array(
			'dupr_id'             => 'DEF456',
			'name'                => 'Stale Fallback Player',
			'profile_image'       => '',
			'doubles_rating'      => '3.5',
			'singles_rating'      => 'NR',
			'doubles_reliability' => 85,
			'singles_reliability' => null,
			'last_updated'        => $stale_time,
		);

		update_option( 'pbr_dupr_player_DEF456', $stale_cached_data, false );

		$api = new PBR_DUPR_API();

		// Stub API to fail.
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if ( false !== strpos( $url, '/player/search/byDuprId' ) ) {
					return array(
						'response' => array( 'code' => 500 ),
						'headers'  => array(),
						'body'     => '{"error": "Server error"}',
					);
				}
				return $preempt;
			},
			10,
			3
		);

		$result = $api->get_player_data( 'DEF456' );

		// Should return stale cached data as fallback.
		$this->assertIsArray( $result );
		$this->assertSame( 'Stale Fallback Player', $result['name'] );
		$this->assertSame( '3.5', $result['doubles_rating'] );
		$this->assertSame( $stale_time, $result['last_updated'] );
	}

	public function test_cache_invalid_structure_is_ignored() {
		// Set up authentication.
		update_option( 'pickleball_ratings_dupr_auth_token', 'test_token' );

		// Create invalid cached data (missing last_updated field).
		$invalid_cached_data = array(
			'dupr_id'        => 'GHI789',
			'name'           => 'Invalid Cache Player',
			'doubles_rating' => '4.0',
			// Missing last_updated field.
		);

		update_option( 'pbr_dupr_player_GHI789', $invalid_cached_data, false );

		$api = new PBR_DUPR_API();

		// Stub API to return fresh data.
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if ( false !== strpos( $url, '/player/search/byDuprId' ) ) {
					return array(
						'response' => array( 'code' => 200 ),
						'headers'  => array(),
						'body'     => json_encode( array(
							'results' => array(
								array( 'userId' => '99999' ),
							),
						) ),
					);
				}
				if ( false !== strpos( $url, '/player/v3/99999' ) ) {
					return array(
						'response' => array( 'code' => 200 ),
						'headers'  => array(),
						'body'     => json_encode( array(
							'result' => array(
								'duprId'   => 'GHI789',
								'fullName' => 'Valid Fresh Player',
								'ratings'  => array(
									'doubles' => '4.5',
									'singles' => 'NR',
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

		$result = $api->get_player_data( 'GHI789' );

		// Should fetch from API because cached data was invalid.
		$this->assertIsArray( $result );
		$this->assertSame( 'Valid Fresh Player', $result['name'] );
		$this->assertSame( '4.5', $result['doubles_rating'] );
		$this->assertArrayHasKey( 'last_updated', $result );
	}
}
