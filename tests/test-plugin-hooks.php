<?php
/**
 * Plugin hooks and block registration tests.
 */

class PBR_Plugin_Hooks_Test extends WP_UnitTestCase {
	public function test_block_type_is_registered() {
		$block = WP_Block_Type_Registry::get_instance()->get_registered( 'pickleball-ratings/player-ratings' );
		$this->assertNotNull( $block, 'Block should be registered.' );
		$this->assertIsArray( $block->attributes );
		$this->assertArrayHasKey( 'duprId', $block->attributes );
	}

	public function test_scripts_and_styles_registered() {
		global $wp_scripts, $wp_styles;
		$this->assertArrayHasKey( 'pickleball-ratings-block', $wp_scripts->registered );
		$this->assertArrayHasKey( 'pickleball-ratings-block-frontend', $wp_scripts->registered );
		$this->assertArrayHasKey( 'pickleball-ratings-block-style', $wp_styles->registered );
	}

	public function test_settings_link_filter_adds_link() {
		$links   = array();
		$filtered = pickleball_ratings_add_settings_link( $links );
		$this->assertNotEmpty( $filtered );
		$this->assertStringContainsString( 'options-general.php?page=pickleball-ratings-settings', $filtered[0] );
	}
}
