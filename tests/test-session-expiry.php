<?php
/**
 * DUPR session expiry tests.
 *
 * The session lasts as long as the refresh token. Once it lapses the access
 * token cannot be renewed, so the plugin should say so plainly and stop
 * spending requests on calls that cannot succeed.
 */

class PBR_Session_Expiry_Test extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();

		global $wpdb;
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE 'pbr_dupr_player_%'" );

		update_option( 'pickleball_ratings_dupr_auth_token', 'access_token' );
	}

	/**
	 * Build a JWT whose payload carries the given expiry.
	 *
	 * Only the payload segment is ever decoded, so the header and signature
	 * are placeholders.
	 *
	 * @param int|null $exp Expiry timestamp, or null to omit the claim.
	 * @return string Token.
	 */
	private function make_jwt( $exp ) {
		$claims = array( 'token_type' => 'REFRESH' );
		if ( null !== $exp ) {
			$claims['exp'] = $exp;
		}

		$encode = function ( $data ) {
			return rtrim( strtr( base64_encode( wp_json_encode( $data ) ), '+/', '-_' ), '=' );
		};

		return $encode( array( 'alg' => 'RS512' ) ) . '.' . $encode( $claims ) . '.signature';
	}

	/**
	 * Count outbound HTTP requests, failing any that are attempted.
	 *
	 * @param int $counter Reference used to record the call count.
	 */
	private function count_requests( &$counter ) {
		add_filter(
			'pre_http_request',
			function () use ( &$counter ) {
				++$counter;
				return array(
					'response' => array( 'code' => 400 ),
					'headers'  => array(),
					'body'     => wp_json_encode(
						array(
							'status'  => 'FAILURE',
							'message' => 'Session expired',
						)
					),
				);
			},
			10,
			3
		);
	}

	public function test_expired_refresh_token_reports_expired_session() {
		update_option( 'pickleball_ratings_dupr_auth_refresh_token', $this->make_jwt( time() - HOUR_IN_SECONDS ) );

		$api = new PBR_DUPR_API();

		$this->assertTrue( $api->is_session_expired() );
		$this->assertTrue( $api->get_session_status()['session_expired'] );
	}

	public function test_valid_refresh_token_reports_live_session() {
		update_option( 'pickleball_ratings_dupr_auth_refresh_token', $this->make_jwt( time() + 30 * DAY_IN_SECONDS ) );

		$api    = new PBR_DUPR_API();
		$status = $api->get_session_status();

		$this->assertFalse( $api->is_session_expired() );
		$this->assertFalse( $status['session_expiring_soon'] );
		$this->assertTrue( $status['session_expiry_known'] );
	}

	public function test_session_within_warning_window_is_expiring_soon() {
		update_option( 'pickleball_ratings_dupr_auth_refresh_token', $this->make_jwt( time() + 3 * DAY_IN_SECONDS ) );

		$status = ( new PBR_DUPR_API() )->get_session_status();

		$this->assertTrue( $status['session_expiring_soon'] );
		$this->assertFalse( $status['session_expired'] );
		$this->assertNotEmpty( $status['session_expires_in'] );
	}

	public function test_session_just_outside_warning_window_is_not_flagged() {
		update_option( 'pickleball_ratings_dupr_auth_refresh_token', $this->make_jwt( time() + 8 * DAY_IN_SECONDS ) );

		$this->assertFalse( ( new PBR_DUPR_API() )->get_session_status()['session_expiring_soon'] );
	}

	public function test_opaque_refresh_token_is_never_reported_expired() {
		update_option( 'pickleball_ratings_dupr_auth_refresh_token', 'not-a-jwt' );

		$api    = new PBR_DUPR_API();
		$status = $api->get_session_status();

		$this->assertFalse( $api->is_session_expired(), 'An unreadable token must not lock out a working site.' );
		$this->assertFalse( $status['session_expiry_known'] );
		$this->assertNull( $status['session_expires_at'] );
	}

	public function test_jwt_without_exp_claim_is_never_reported_expired() {
		update_option( 'pickleball_ratings_dupr_auth_refresh_token', $this->make_jwt( null ) );

		$this->assertFalse( ( new PBR_DUPR_API() )->is_session_expired() );
	}

	public function test_expired_session_skips_api_call_on_cache_miss() {
		update_option( 'pickleball_ratings_dupr_auth_refresh_token', $this->make_jwt( time() - HOUR_IN_SECONDS ) );

		$requests = 0;
		$this->count_requests( $requests );

		$result = ( new PBR_DUPR_API() )->get_player_data( '8WZ4ML' );

		$this->assertWPError( $result );
		$this->assertSame( 'session_expired', $result->get_error_code() );
		$this->assertSame( 0, $requests, 'No request should be attempted once the session is known to be dead.' );
	}

	public function test_expired_session_serves_stale_cache_without_api_call() {
		update_option( 'pickleball_ratings_dupr_auth_refresh_token', $this->make_jwt( time() - HOUR_IN_SECONDS ) );
		update_option(
			'pbr_dupr_player_8WZ4ML',
			array(
				'dupr_id'        => '8WZ4ML',
				'name'           => 'JW Johnson',
				'doubles_rating' => '6.99',
				'last_updated'   => gmdate( 'Y-m-d H:i:s', time() - YEAR_IN_SECONDS ),
			),
			false
		);

		$requests = 0;
		$this->count_requests( $requests );

		$result = ( new PBR_DUPR_API() )->get_player_data( '8WZ4ML' );

		$this->assertIsArray( $result );
		$this->assertSame( 'JW Johnson', $result['name'] );
		$this->assertSame( 0, $requests, 'Stale data should be served without burning requests on a dead session.' );
	}

	public function test_live_session_still_calls_api_on_cache_miss() {
		update_option( 'pickleball_ratings_dupr_auth_refresh_token', $this->make_jwt( time() + 30 * DAY_IN_SECONDS ) );

		$requests = 0;
		$this->count_requests( $requests );

		( new PBR_DUPR_API() )->get_player_data( '8WZ4ML' );

		$this->assertGreaterThan( 0, $requests, 'A live session must still reach the API.' );
	}

	public function test_auth_status_exposes_session_fields() {
		update_option( 'pickleball_ratings_dupr_auth_refresh_token', $this->make_jwt( time() + 2 * DAY_IN_SECONDS ) );

		$status = ( new PBR_DUPR_API() )->get_auth_status();

		$this->assertTrue( $status['authenticated'] );
		$this->assertTrue( $status['session_expiring_soon'] );
		$this->assertArrayHasKey( 'session_expires_at', $status );
	}
}
