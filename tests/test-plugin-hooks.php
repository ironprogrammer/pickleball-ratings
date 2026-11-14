<?php
/**
 * Plugin hooks and block registration tests.
 */

class PBR_Plugin_Hooks_Test extends WP_UnitTestCase {
	public function test_block_type_is_registered() {
		$block = WP_Block_Type_Registry::get_instance()->get_registered( 'pickleball-ratings/player-ratings' );
		$this->assertNotNull( $block, 'Block should be registered.' );
		$this->assertIsArray( $block->attributes );
		$this->assertArrayHasKey( 'doublesRating', $block->attributes );
		$this->assertSame(
			array( 'gradient', 'white', 'black' ),
			wp_list_pluck( $block->styles, 'name' )
		);
	}
}
