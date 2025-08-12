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
}
