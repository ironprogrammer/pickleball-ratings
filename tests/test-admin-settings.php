<?php
/**
 * Admin settings tests.
 */

class PBR_Admin_Settings_Test extends WP_UnitTestCase {
	public function test_sanitize_cache_ttl_clamps_and_converts() {
		$admin = new PBR_Admin_Settings();
		// Below minimum -> 1 hour => 3600 seconds
		$this->assertSame( 3600, $admin->sanitize_cache_ttl( 0 ) );
		// Above maximum -> 168 hours => 604800 seconds
		$this->assertSame( 604800, $admin->sanitize_cache_ttl( 9999 ) );
		// Normal value -> 24 hours => 86400 seconds
		$this->assertSame( 86400, $admin->sanitize_cache_ttl( 24 ) );
	}

	public function test_settings_registered_on_admin_init() {
		$admin = new PBR_Admin_Settings();
		// Call the settings registrar directly to avoid side effects from other hooks.
		$admin->init_settings();
		$this->assertNotFalse( get_registered_settings()['pickleball_ratings_cache_ttl'] ?? false );
	}

	/**
	 * Build a refresh token JWT expiring at the given offset from now.
	 *
	 * @param int $offset Seconds from now.
	 * @return string Token.
	 */
	private function refresh_token_expiring_in( $offset ) {
		$encode = function ( $data ) {
			return rtrim( strtr( base64_encode( wp_json_encode( $data ) ), '+/', '-_' ), '=' );
		};

		return $encode( array( 'alg' => 'RS512' ) ) . '.' . $encode( array( 'exp' => time() + $offset ) ) . '.signature';
	}

	/**
	 * Capture whatever admin_notices prints.
	 *
	 * @return string Markup.
	 */
	private function capture_notices() {
		$admin = new PBR_Admin_Settings();

		ob_start();
		$admin->admin_notices();
		return ob_get_clean();
	}

	public function test_expired_session_prints_error_notice() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		update_option( 'pickleball_ratings_dupr_auth_token', 'access_token' );
		update_option( 'pickleball_ratings_dupr_auth_refresh_token', $this->refresh_token_expiring_in( -HOUR_IN_SECONDS ) );

		$output = $this->capture_notices();

		$this->assertStringContainsString( 'notice-error', $output );
		$this->assertStringContainsString( 'no longer updating', $output );
	}

	public function test_session_expiring_soon_prints_warning_notice() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		update_option( 'pickleball_ratings_dupr_auth_token', 'access_token' );
		update_option( 'pickleball_ratings_dupr_auth_refresh_token', $this->refresh_token_expiring_in( 3 * DAY_IN_SECONDS ) );

		$output = $this->capture_notices();

		$this->assertStringContainsString( 'notice-warning', $output );
		$this->assertStringContainsString( 'expires in', $output );
	}

	public function test_healthy_session_prints_no_notice() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		update_option( 'pickleball_ratings_dupr_auth_token', 'access_token' );
		update_option( 'pickleball_ratings_dupr_auth_refresh_token', $this->refresh_token_expiring_in( 60 * DAY_IN_SECONDS ) );

		$this->assertSame( '', $this->capture_notices() );
	}

	public function test_non_admin_sees_no_session_notice() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		update_option( 'pickleball_ratings_dupr_auth_token', 'access_token' );
		update_option( 'pickleball_ratings_dupr_auth_refresh_token', $this->refresh_token_expiring_in( -HOUR_IN_SECONDS ) );

		$this->assertSame( '', $this->capture_notices() );
	}

	public function test_disconnected_site_prints_no_session_notice() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		delete_option( 'pickleball_ratings_dupr_auth_token' );
		update_option( 'pickleball_ratings_dupr_auth_refresh_token', $this->refresh_token_expiring_in( -HOUR_IN_SECONDS ) );

		$this->assertSame( '', $this->capture_notices() );
	}
}
