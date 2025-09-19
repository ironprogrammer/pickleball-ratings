<?php
/**
 * Render callback tests.
 */

class PBR_Render_Test extends WP_UnitTestCase {

	public function test_render_empty_id_returns_empty() {
		$html = pickleball_ratings_render_block( array( 'duprId' => '' ) );
		$this->assertEmpty( $html, 'Empty DUPR ID should return empty string on frontend' );
	}

	public function test_render_invalid_id_format_returns_empty() {
		$html = pickleball_ratings_render_block( array( 'duprId' => 'abc' ) );
		$this->assertEmpty( $html, 'Invalid DUPR ID should return empty string on frontend' );
	}

	public function test_render_color_classes_and_styles() {
		$attrs = array(
			'duprId' => '8WZ4ML',
			'backgroundColor' => 'primary',
			'textColor' => 'secondary',
			'customBackgroundColor' => '#123456',
			'customTextColor' => '#abcdef',
			'gradient' => 'dupr-blue-gradient',
			'customGradient' => 'linear-gradient(45deg, #000, #fff)',
			'fontSize' => 'large',
		);
		// Configure auth token to bypass 'no_auth' branch.
		update_option( 'pickleball_ratings_dupr_auth_token', 'test-token' );
		// Stub API to avoid external calls by filtering before render.
		add_filter( 'pre_http_request', array( $this, 'stub_http_ok' ), 10, 3 );
		$html = pickleball_ratings_render_block( $attrs );
		remove_filter( 'pre_http_request', array( $this, 'stub_http_ok' ), 10 );

		$this->assertStringContainsString( 'has-background', $html );
		$this->assertStringContainsString( 'has-primary-background-color', $html );
		$this->assertStringContainsString( 'has-secondary-color', $html );
		$this->assertStringContainsString( 'has-dupr-blue-gradient-gradient-background', $html );
		$this->assertStringContainsString( 'has-large-font-size', $html );
		$this->assertStringContainsString( 'background-color: #123456', $html );
		$this->assertStringContainsString( 'color: #abcdef', $html );
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
