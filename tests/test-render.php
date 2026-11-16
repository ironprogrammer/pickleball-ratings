<?php
/**
 * Player ratings block render tests.
 *
 * @package Pickleball_Ratings
 */

class PBR_Render_Test extends WP_UnitTestCase {

	/**
	 * Render the block with the given attributes.
	 *
	 * @param array $attrs Block attributes.
	 * @return string Rendered HTML.
	 */
	private function render( array $attrs ) {
		$block = new WP_Block(
			array(
				'blockName' => 'pickleball-ratings/player-ratings',
				'attrs'     => $attrs,
			)
		);

		return $block->render();
	}

	public function test_block_without_ratings_renders_nothing() {
		$this->assertSame(
			'',
			trim(
				$this->render(
					array(
						'name'   => 'Alex Rivera',
						'duprId' => 'ABC123',
					)
				)
			)
		);
	}

	public function test_only_provided_ratings_render() {
		$html = $this->render( array( 'doublesRating' => '4.125' ) );

		$this->assertStringContainsString( 'pbr-doubles', $html );
		$this->assertStringNotContainsString( 'pbr-singles', $html );
	}

	/**
	 * @dataProvider data_normalize_rating
	 *
	 * @param mixed  $input    Raw rating.
	 * @param string $expected Normalized rating.
	 */
	public function test_normalize_rating( $input, $expected ) {
		$this->assertSame( $expected, pickleball_ratings_normalize_rating( $input ) );
	}

	public function data_normalize_rating() {
		return array(
			'three decimals' => array( '4.125', '4.125' ),
			'padded'         => array( '3.5', '3.500' ),
			'rounded'        => array( '3.28951', '3.290' ),
			'not rated'      => array( 'nr', 'NR' ),
			'empty'          => array( '', '' ),
			'text'           => array( 'abc', '' ),
			'zero'           => array( '0', '' ),
			'hex'            => array( '0x4', '' ),
			'exponent'       => array( '1e3', '' ),
		);
	}

	public function test_reliability_ring_renders_clamped_score() {
		$html = $this->render(
			array(
				'doublesRating'      => '4.125',
				'doublesReliability' => 150,
			)
		);

		$this->assertStringContainsString( 'aria-label="Reliability: 100 of 100"', $html );
	}

	public function test_reliability_ring_hidden_without_score() {
		$html = $this->render( array( 'doublesRating' => '4.125' ) );

		$this->assertStringNotContainsString( 'pbr-reliability', $html );
	}

	public function test_copy_button_requires_valid_dupr_id() {
		$valid   = $this->render(
			array(
				'doublesRating' => '4.125',
				'duprId'        => 'ABC123',
			)
		);
		$invalid = $this->render(
			array(
				'doublesRating' => '4.125',
				'duprId'        => 'abc',
			)
		);

		$this->assertStringContainsString( 'data-dupr-id="ABC123"', $valid );
		$this->assertStringNotContainsString( 'pbr-copy', $invalid );
	}

	public function test_profile_image_renders_from_media_library() {
		$attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );

		$with    = $this->render(
			array(
				'doublesRating' => '4.125',
				'mediaId'       => $attachment_id,
			)
		);
		$without = $this->render( array( 'doublesRating' => '4.125' ) );

		$this->assertStringContainsString( 'pbr-avatar', $with );
		$this->assertStringNotContainsString( 'pbr-avatar', $without );
	}

	public function test_updated_date_renders_time_element() {
		$html = $this->render(
			array(
				'doublesRating' => '4.125',
				'updated'       => '2026-11-14T18:00:00.000Z',
			)
		);

		$this->assertStringContainsString( '<time datetime="2026-11-14T18:00:00+00:00">', $html );
	}

	public function test_color_support_classes_render() {
		$html = $this->render(
			array(
				'doublesRating'   => '4.125',
				'backgroundColor' => 'primary',
			)
		);

		$this->assertStringContainsString( 'has-primary-background-color', $html );
	}
}
