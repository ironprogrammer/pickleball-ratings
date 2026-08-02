<?php
/**
 * Cache read tolerance tests.
 *
 * Cached entries are the fallback that keeps blocks rendering when the DUPR
 * API is unreachable, so a usable entry must never be discarded over a
 * missing or malformed timestamp.
 */

class PBR_Cache_Tolerance_Test extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();

		update_option( 'pickleball_ratings_dupr_auth_token', 'expired_token' );
		update_option( 'pickleball_ratings_dupr_auth_refresh_token', 'expired_refresh' );

		global $wpdb;
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE 'pbr_dupr_player_%' OR option_name LIKE '\_transient\_%pbr\_dupr\_player\_%'" );
	}

	/**
	 * Build a cached player payload.
	 *
	 * @param array $overrides Fields to override or remove.
	 * @return array Player data.
	 */
	private function player_payload( $overrides = array() ) {
		return array_merge(
			array(
				'dupr_id'        => '8WZ4ML',
				'name'           => 'JW Johnson',
				'doubles_rating' => '6.99',
				'singles_rating' => '6.80',
				'last_updated'   => current_time( 'mysql' ),
			),
			$overrides
		);
	}

	/**
	 * Force every outbound request to fail with an expired session, mirroring
	 * the state of a site whose DUPR refresh token has lapsed.
	 */
	private function force_expired_session() {
		add_filter(
			'pre_http_request',
			function () {
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

	public function test_stale_entry_without_timestamp_is_still_served() {
		$payload = $this->player_payload();
		unset( $payload['last_updated'] );
		update_option( 'pbr_dupr_player_8WZ4ML', $payload, false );

		$this->force_expired_session();

		$api    = new PBR_DUPR_API();
		$result = $api->get_player_data( '8WZ4ML' );

		$this->assertIsArray( $result, 'Entry lacking a timestamp should still be usable as a fallback.' );
		$this->assertSame( 'JW Johnson', $result['name'] );
	}

	public function test_stale_entry_with_unparseable_timestamp_is_still_served() {
		update_option( 'pbr_dupr_player_8WZ4ML', $this->player_payload( array( 'last_updated' => 'not a date' ) ), false );

		$this->force_expired_session();

		$api    = new PBR_DUPR_API();
		$result = $api->get_player_data( '8WZ4ML' );

		$this->assertIsArray( $result );
		$this->assertSame( 'JW Johnson', $result['name'] );
	}

	public function test_entry_without_player_fields_is_rejected() {
		update_option( 'pbr_dupr_player_8WZ4ML', array( 'last_updated' => current_time( 'mysql' ) ), false );

		$this->force_expired_session();

		$api    = new PBR_DUPR_API();
		$result = $api->get_player_data( '8WZ4ML' );

		$this->assertWPError( $result, 'An entry carrying no player fields is not a usable fallback.' );
	}

	public function test_cache_diagnostics_report_entries() {
		update_option( 'pbr_dupr_player_8WZ4ML', $this->player_payload(), false );
		update_option( 'pbr_dupr_player_ABC123', array( 'unusable' => true ), false );

		$api         = new PBR_DUPR_API();
		$diagnostics = $api->get_cache_diagnostics();

		$this->assertSame( 2, $diagnostics['entry_count'] );
		$this->assertSame( 1, $diagnostics['usable_entries'] );

		$ids = wp_list_pluck( $diagnostics['entries'], 'dupr_id' );
		$this->assertContains( '8WZ4ML', $ids );
	}
}
