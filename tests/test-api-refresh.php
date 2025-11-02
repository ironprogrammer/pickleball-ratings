<?php
/**
 * Token refresh flow tests for PBR_DUPR_API.
 */

class PBR_API_Refresh_Test extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();
		update_option( 'pickleball_ratings_dupr_auth_token', 'expired' );
		update_option( 'pickleball_ratings_dupr_auth_refresh_token', 'refresh123' );
	}

	public function test_refresh_on_search_401_then_success() {
		$api = new PBR_DUPR_API();

		$calls = array( 'search' => 0, 'refresh' => 0, 'player' => 0 );
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$calls ) {
				if ( false !== strpos( $url, '/player/search/byDuprId' ) ) {
					$calls['search']++;
					if ( 1 === $calls['search'] ) {
						return array( 'response' => array( 'code' => 401 ), 'headers' => array(), 'body' => '{}' );
					}
					return array(
						'response' => array( 'code' => 200 ),
						'headers'  => array(),
						'body'     => json_encode( array( 'results' => array( array( 'userId' => '123' ) ) ) ),
					);
				}
				if ( false !== strpos( $url, '/auth/v3/refresh' ) ) {
					$calls['refresh']++;
					// Verify GET request with x-refresh-token header.
					$this->assertSame( 'GET', $args['method'] ?? 'GET' );
					$this->assertArrayHasKey( 'x-refresh-token', $args['headers'] );
					$this->assertSame( 'refresh123', $args['headers']['x-refresh-token'] );
					// Return actual DUPR API response format.
					return array(
						'response' => array( 'code' => 200 ),
						'headers'  => array(),
						'body'     => json_encode( array( 'status' => 'SUCCESS', 'result' => 'new_access_token' ) ),
					);
				}
				if ( false !== strpos( $url, '/player/v3/' ) ) {
					$calls['player']++;
					return array(
						'response' => array( 'code' => 200 ),
						'headers'  => array(),
						'body'     => json_encode( array( 'result' => array( 'duprId' => '8WZ4ML', 'fullName' => 'JW Johnson', 'ratings' => array( 'doubles' => '6.99', 'singles' => '6.80' ) ) ) ),
					);
				}
				return $preempt;
			},
			10,
			3
		);

		$result = $api->get_player_data( '8WZ4ML' );
		$this->assertIsArray( $result );
		// Verify new access token was saved.
		$this->assertSame( 'new_access_token', get_option( 'pickleball_ratings_dupr_auth_token' ) );
		// Verify refresh token remains unchanged (non-rotating pattern).
		$this->assertSame( 'refresh123', get_option( 'pickleball_ratings_dupr_auth_refresh_token' ) );
		$this->assertSame( 2, $calls['search'] );
		$this->assertSame( 1, $calls['refresh'] );
		$this->assertSame( 1, $calls['player'] );
	}

	public function test_refresh_on_player_401_then_success() {
		$api = new PBR_DUPR_API();

		$calls = array( 'search' => 0, 'refresh' => 0, 'player' => 0 );
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$calls ) {
				if ( false !== strpos( $url, '/player/search/byDuprId' ) ) {
					$calls['search']++;
					return array(
						'response' => array( 'code' => 200 ),
						'headers'  => array(),
						'body'     => json_encode( array( 'results' => array( array( 'userId' => '123' ) ) ) ),
					);
				}
				if ( false !== strpos( $url, '/auth/v3/refresh' ) ) {
					$calls['refresh']++;
					// Verify GET request with x-refresh-token header.
					$this->assertSame( 'GET', $args['method'] ?? 'GET' );
					$this->assertArrayHasKey( 'x-refresh-token', $args['headers'] );
					$this->assertSame( 'refresh123', $args['headers']['x-refresh-token'] );
					// Return actual DUPR API response format.
					return array(
						'response' => array( 'code' => 200 ),
						'headers'  => array(),
						'body'     => json_encode( array( 'status' => 'SUCCESS', 'result' => 'new_access_token_2' ) ),
					);
				}
				if ( false !== strpos( $url, '/player/v3/' ) ) {
					$calls['player']++;
					if ( 1 === $calls['player'] ) {
						return array( 'response' => array( 'code' => 401 ), 'headers' => array(), 'body' => '{}' );
					}
					return array(
						'response' => array( 'code' => 200 ),
						'headers'  => array(),
						'body'     => json_encode( array( 'result' => array( 'duprId' => '8WZ4ML', 'fullName' => 'JW Johnson', 'ratings' => array( 'doubles' => '6.99', 'singles' => '6.80' ) ) ) ),
					);
				}
				return $preempt;
			},
			10,
			3
		);

		$result = $api->get_player_data( '8WZ4ML' );
		$this->assertIsArray( $result );
		// Verify new access token was saved.
		$this->assertSame( 'new_access_token_2', get_option( 'pickleball_ratings_dupr_auth_token' ) );
		// Verify refresh token remains unchanged (non-rotating pattern).
		$this->assertSame( 'refresh123', get_option( 'pickleball_ratings_dupr_auth_refresh_token' ) );
		$this->assertSame( 1, $calls['search'] );
		$this->assertSame( 1, $calls['refresh'] );
		$this->assertSame( 2, $calls['player'] );
	}
}
