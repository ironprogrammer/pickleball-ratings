<?php
/**
 * API class tests (error paths and caching).
 */

class PBR_API_Test extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();
		// Ensure a clean state.
		delete_option( 'pickleball_ratings_dupr_auth_token' );
		delete_option( 'pickleball_ratings_dupr_auth_refresh_token' );
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
}
