<?php
/**
 * Upgrade routine tests.
 *
 * @package Pickleball_Ratings
 */

class PBR_Upgrade_Test extends WP_UnitTestCase {

	/**
	 * Post content as saved by 0.5.0, with one gradient block and one plain block.
	 *
	 * @var string
	 */
	const LEGACY_CONTENT = '<!-- wp:paragraph --><p>Intro</p><!-- /wp:paragraph -->'
		. '<!-- wp:pickleball-ratings/player-ratings {"duprId":"PW24RQ","useLightLogo":true,"textColor":"base","gradient":"dupr-blue-gradient","style":{"elements":{"link":{"color":{"text":"var:preset|color|base"}}}}} /-->'
		. '<!-- wp:group --><div class="wp-block-group"><!-- wp:pickleball-ratings/player-ratings {"duprId":"8WZ4ML","showProfilePic":false} /--></div><!-- /wp:group -->';

	public function set_up() {
		parent::set_up();

		update_option(
			'pbr_dupr_player_PW24RQ',
			array(
				'name'                => 'Pat Player',
				'profile_image'       => 'https://example.com/pat.jpg',
				'doubles_rating'      => '3.578',
				'singles_rating'      => 'NR',
				'doubles_reliability' => 50,
				'singles_reliability' => null,
				'last_updated'        => '2026-09-01 14:01:09',
			)
		);
		update_option(
			'pbr_dupr_player_8WZ4ML',
			array(
				'name'           => 'Sam Smash',
				'profile_image'  => 'https://example.com/sam.jpg',
				'doubles_rating' => '7.066',
				'singles_rating' => '6.353',
			)
		);
	}

	/**
	 * Create a post holding legacy blocks, last modified well in the past.
	 *
	 * @return int Post ID.
	 */
	private function create_legacy_post() {
		return self::factory()->post->create(
			array(
				'post_content' => self::LEGACY_CONTENT,
				'post_date'    => '2026-01-01 00:00:00',
			)
		);
	}

	/**
	 * Find the attributes of every player ratings block in a post, in order.
	 *
	 * @param int $post_id Post ID.
	 * @return array[] Block attributes.
	 */
	private function block_attrs( $post_id ) {
		$found = array();
		$walk  = static function ( $blocks ) use ( &$walk, &$found ) {
			foreach ( $blocks as $block ) {
				if ( 'pickleball-ratings/player-ratings' === $block['blockName'] ) {
					$found[] = $block['attrs'];
				}
				$walk( $block['innerBlocks'] );
			}
		};
		$walk( parse_blocks( get_post_field( 'post_content', $post_id ) ) );

		return $found;
	}

	/**
	 * Serve a real JPEG for any image download.
	 */
	public function serve_test_image( $preempt, $args ) {
		copy( DIR_TESTDATA . '/images/canola.jpg', $args['filename'] );

		return array(
			'headers'  => array( 'content-type' => 'image/jpeg' ),
			'body'     => '',
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => $args['filename'],
		);
	}

	public function test_legacy_blocks_are_filled_from_cache() {
		add_filter( 'pre_http_request', array( $this, 'serve_test_image' ), 10, 2 );
		$post_id = $this->create_legacy_post();

		pickleball_ratings_migrate_legacy_blocks();
		list( $gradient, $plain ) = $this->block_attrs( $post_id );

		$this->assertSame( 'Pat Player', $gradient['name'] );
		$this->assertSame( '3.578', $gradient['doublesRating'] );
		$this->assertSame( 50, $gradient['doublesReliability'] );
		$this->assertSame( 'NR', $gradient['singlesRating'] );
		$this->assertArrayNotHasKey( 'singlesReliability', $gradient );
		$this->assertNotEmpty( $gradient['updated'] );
		$this->assertGreaterThan( 0, $gradient['mediaId'] );
		$this->assertArrayNotHasKey( 'mediaId', $plain, 'Blocks that hid the profile picture stay imageless.' );
	}

	public function test_legacy_styling_maps_to_variations() {
		add_filter( 'pre_http_request', array( $this, 'serve_test_image' ), 10, 2 );
		$post_id = $this->create_legacy_post();

		pickleball_ratings_migrate_legacy_blocks();
		list( $gradient, $plain ) = $this->block_attrs( $post_id );

		foreach ( array( 'gradient', 'textColor', 'style', 'useLightLogo' ) as $removed ) {
			$this->assertArrayNotHasKey( $removed, $gradient );
		}
		$this->assertArrayNotHasKey( 'className', $gradient );
		$this->assertSame( 'is-style-white', $plain['className'] );
	}

	public function test_migration_preserves_modified_date_and_other_blocks() {
		add_filter( 'pre_http_request', array( $this, 'serve_test_image' ), 10, 2 );
		$post_id = $this->create_legacy_post();

		pickleball_ratings_migrate_legacy_blocks();

		$this->assertSame( '2026-01-01 00:00:00', get_post_field( 'post_modified', $post_id ) );
		$this->assertStringContainsString( '<!-- wp:paragraph --><p>Intro</p><!-- /wp:paragraph -->', get_post_field( 'post_content', $post_id ) );
	}

	public function test_failed_image_download_leaves_block_without_image() {
		add_filter(
			'pre_http_request',
			static function () {
				return new WP_Error( 'http_request_failed', 'Offline' );
			}
		);
		$post_id = $this->create_legacy_post();

		pickleball_ratings_migrate_legacy_blocks();
		list( $gradient ) = $this->block_attrs( $post_id );

		$this->assertSame( '3.578', $gradient['doublesRating'] );
		$this->assertArrayNotHasKey( 'mediaId', $gradient );
	}

	public function test_failed_post_update_keeps_cache_for_retry() {
		add_filter( 'pre_http_request', array( $this, 'serve_test_image' ), 10, 2 );
		add_filter( 'query', array( $this, 'break_post_updates' ) );
		$this->create_legacy_post();
		delete_option( 'pickleball_ratings_version' );

		pickleball_ratings_maybe_upgrade();
		remove_filter( 'query', array( $this, 'break_post_updates' ) );

		$this->assertIsArray( get_option( 'pbr_dupr_player_PW24RQ' ) );
		$this->assertFalse( get_option( 'pickleball_ratings_version' ) );
	}

	public function test_post_saved_during_migration_is_not_overwritten() {
		$post_id = $this->create_legacy_post();
		$edited  = '<!-- wp:paragraph --><p>Edited mid-migration</p><!-- /wp:paragraph -->';
		add_filter(
			'pre_http_request',
			static function () use ( $post_id, $edited ) {
				global $wpdb;
				$wpdb->update( $wpdb->posts, array( 'post_content' => $edited ), array( 'ID' => $post_id ) );
				return new WP_Error( 'http_request_failed', 'Offline' );
			}
		);

		$this->assertFalse( pickleball_ratings_migrate_legacy_blocks() );

		clean_post_cache( $post_id );
		$this->assertSame( $edited, get_post_field( 'post_content', $post_id ) );
	}

	/**
	 * Make UPDATE queries against the posts table fail.
	 *
	 * @param string $query SQL query.
	 * @return string Original query, or one that errors for post updates.
	 */
	public function break_post_updates( $query ) {
		global $wpdb;

		return 0 === strpos( $query, "UPDATE `{$wpdb->posts}`" ) ? 'UPDATE pbr_missing_table SET x = 1' : $query;
	}

	public function test_upgrade_deletes_legacy_options_and_records_version() {
		update_option( 'pickleball_ratings_dupr_auth_token', 'secret' );
		delete_option( 'pickleball_ratings_version' );

		pickleball_ratings_maybe_upgrade();

		$this->assertFalse( get_option( 'pickleball_ratings_dupr_auth_token' ) );
		$this->assertFalse( get_option( 'pbr_dupr_player_PW24RQ' ) );
		$this->assertSame( PICKLEBALL_RATINGS_VERSION, get_option( 'pickleball_ratings_version' ) );
		$this->assertFalse( get_option( 'pickleball_ratings_upgrading' ) );
	}
}
