<?php
/**
 * Render callback tests.
 */

class PBR_Render_Test extends WP_UnitTestCase {

	/**
	 * Test that blocks with empty or invalid DUPR IDs return empty strings.
	 *
	 * @dataProvider data_empty_or_invalid_dupr_ids
	 * @param string $dupr_id The DUPR ID to test.
	 * @param string $test_name The name of the test case.
	 * @param string $error_message The error message to display if test fails.
	 */
	public function test_render_empty_or_invalid_id_returns_empty( $dupr_id, $test_name, $error_message ) {
		// Create block attributes with the test DUPR ID.
		$attributes = array( 'duprId' => $dupr_id );
		
		// Create a block instance.
		$block = new WP_Block(
			array(
				'blockName' => 'pickleball-ratings/player-ratings',
				'attrs'      => $attributes,
			)
		);
		
		// Render the block.
		$html = $block->render();
		
		// Assert that the block returns empty string.
		$this->assertEmpty( $html, $error_message );
	}

	/**
	 * Data provider for empty or invalid DUPR ID tests.
	 *
	 * @return array Array of test cases with DUPR ID, test name, and error message.
	 */
	public function data_empty_or_invalid_dupr_ids() {
		return array(
			'empty_dupr_id' => array(
				'dupr_id'      => '',
				'test_name'    => 'Empty DUPR ID',
				'error_message' => 'Block should return empty string when no DUPR ID is provided',
			),
			'invalid_dupr_id_format' => array(
				'dupr_id'      => 'abc',
				'test_name'    => 'Invalid DUPR ID format',
				'error_message' => 'Block should return empty string when DUPR ID format is invalid',
			),
		);
	}

	public function test_render_color_classes_and_styles() {
		$attrs = array(
			'duprId' => '8WZ4ML',
			'backgroundColor' => 'primary',
			'textColor' => 'secondary',
			'gradient' => 'dupr-blue-gradient',
			'fontSize' => 'large',
		);
		// Configure auth token to bypass 'no_auth' branch.
		update_option( 'pickleball_ratings_dupr_auth_token', 'test-token' );
		// Stub API to avoid external calls by filtering before render.
		add_filter( 'pre_http_request', array( $this, 'stub_http_ok' ), 10, 3 );
		
		// Create a block instance.
		$block = new WP_Block(
			array(
				'blockName' => 'pickleball-ratings/player-ratings',
				'attrs'      => $attrs,
			)
		);
		
		// Render the block.
		$html = $block->render();
		
		remove_filter( 'pre_http_request', array( $this, 'stub_http_ok' ), 10 );

		$this->assertStringContainsString( 'has-background', $html );
		$this->assertStringContainsString( 'has-primary-background-color', $html );
		$this->assertStringContainsString( 'has-secondary-color', $html );
		$this->assertStringContainsString( 'has-dupr-blue-gradient-gradient-background', $html );
		$this->assertStringContainsString( 'has-large-font-size', $html );
	}

	public function stub_http_ok( $preempt, $args, $url ) {
		// Two-step flow: search by DUPR ID, then player fetch.
		if ( false !== strpos( $url, '/player/search/byDuprId' ) ) {
			return array(
				'response' => array( 'code' => 200 ),
				'headers'  => array(),
				'body'     => json_encode( array( 'results' => array( array( 'userId' => '123' ) ) ) ),
			);
		}
		if ( false !== strpos( $url, '/player/v3/' ) ) {
			return array(
				'response' => array( 'code' => 200 ),
				'headers'  => array(),
				'body'     => json_encode( array( 'result' => array(
					'duprId'   => '8WZ4ML',
					'fullName' => 'JW Johnson',
					'ratings'  => array( 'doubles' => '6.99', 'singles' => '6.80' ),
				) ) ),
			);
		}
		if ( false !== strpos( $url, '/auth/v3/validate' ) ) {
			return array(
				'response' => array( 'code' => 200 ),
				'headers'  => array(),
				'body'     => json_encode( array( 'status' => 'SUCCESS' ) ),
			);
		}
		return $preempt;
	}
}
