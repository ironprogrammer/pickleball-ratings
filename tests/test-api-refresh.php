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
					return array(
						'response' => array( 'code' => 200 ),
						'headers'  => array(),
						'body'     => json_encode( array( 'result' => array( 'accessToken' => 'new', 'refreshToken' => 'newrefresh' ) ) ),
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
		$this->assertSame( 'new', get_option( 'pickleball_ratings_dupr_auth_token' ) );
		$this->assertSame( 'newrefresh', get_option( 'pickleball_ratings_dupr_auth_refresh_token' ) );
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
					return array(
						'response' => array( 'code' => 200 ),
						'headers'  => array(),
						'body'     => json_encode( array( 'result' => array( 'accessToken' => 'new2', 'refreshToken' => 'newrefresh2' ) ) ),
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
		$this->assertSame( 'new2', get_option( 'pickleball_ratings_dupr_auth_token' ) );
		$this->assertSame( 'newrefresh2', get_option( 'pickleball_ratings_dupr_auth_refresh_token' ) );
		$this->assertSame( 1, $calls['search'] );
		$this->assertSame( 1, $calls['refresh'] );
		$this->assertSame( 2, $calls['player'] );
	}
}
