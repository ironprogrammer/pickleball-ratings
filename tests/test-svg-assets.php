<?php
/**
 * SVG Assets tests.
 */

class PBR_SVG_Assets_Test extends WP_UnitTestCase {

	public function test_svg_assets_loads_paddle_icon() {
		$svg_assets = require PICKLEBALL_RATINGS_PLUGIN_DIR . 'build/svg-assets.php';
		
		// Test that we can load and use the paddle SVG
		$paddle_svg = $svg_assets['pickleball-paddle'];
		$this->assertStringContainsString( '<svg', $paddle_svg, 'Paddle SVG should contain SVG tag' );
	}
}