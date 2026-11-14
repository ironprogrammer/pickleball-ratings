<?php
/**
 * Upgrade routines.
 *
 * @package Pickleball_Ratings
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Run upgrade routines when the stored version is behind the plugin version.
 */
function pickleball_ratings_maybe_upgrade() {
	$installed = get_option( 'pickleball_ratings_version', '0' );

	if ( version_compare( $installed, PICKLEBALL_RATINGS_VERSION, '>=' ) ) {
		return;
	}

	// Skip if another request is already upgrading, unless that lock is stale.
	if ( ! add_option( 'pickleball_ratings_upgrading', time(), '', false ) ) {
		if ( (int) get_option( 'pickleball_ratings_upgrading' ) > time() - 10 * MINUTE_IN_SECONDS ) {
			return;
		}
		update_option( 'pickleball_ratings_upgrading', time(), false );
	}

	if ( version_compare( $installed, '1.0.0', '<' ) ) {
		pickleball_ratings_migrate_legacy_blocks();
		pickleball_ratings_delete_legacy_options();
	}

	update_option( 'pickleball_ratings_version', PICKLEBALL_RATINGS_VERSION );
	delete_option( 'pickleball_ratings_upgrading' );
}
add_action( 'init', 'pickleball_ratings_maybe_upgrade', 20 );

/**
 * Fill player ratings blocks saved before 1.0.0 from the old DUPR API cache.
 *
 * Earlier versions stored only a DUPR ID in the block and fetched everything
 * else from the API, caching the last response in `pbr_dupr_player_{id}`
 * options. Those cached values become the block's own attributes.
 */
function pickleball_ratings_migrate_legacy_blocks() {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time upgrade scan.
	$posts = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ID, post_content FROM {$wpdb->posts} WHERE post_type <> 'revision' AND post_status <> 'auto-draft' AND post_content LIKE %s",
			'%' . $wpdb->esc_like( '<!-- wp:pickleball-ratings/player-ratings' ) . '%'
		)
	);

	$images = array();

	foreach ( $posts as $post ) {
		$changed = false;
		$blocks  = pickleball_ratings_migrate_blocks( parse_blocks( $post->post_content ), (int) $post->ID, $images, $changed );

		if ( ! $changed ) {
			continue;
		}

		// Updating the row directly keeps post_modified intact and skips
		// revisions and save hooks, since no user edited the post.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cache is cleared below.
		$wpdb->update(
			$wpdb->posts,
			array( 'post_content' => serialize_blocks( $blocks ) ),
			array( 'ID' => $post->ID )
		);
		clean_post_cache( (int) $post->ID );
	}
}

/**
 * Migrate player ratings blocks in a block list, including nested blocks.
 *
 * @param array $blocks  Parsed blocks.
 * @param int   $post_id Post containing the blocks, used as the image parent.
 * @param array $images  Attachment IDs already downloaded, keyed by DUPR ID.
 * @param bool  $changed Set to true when any block changes.
 * @return array Migrated blocks.
 */
function pickleball_ratings_migrate_blocks( array $blocks, $post_id, array &$images, &$changed ) {
	foreach ( $blocks as $index => $block ) {
		if ( 'pickleball-ratings/player-ratings' === $block['blockName'] ) {
			$attrs = pickleball_ratings_migrate_block_attributes( $block['attrs'], $post_id, $images );

			if ( $attrs !== $block['attrs'] ) {
				$blocks[ $index ]['attrs'] = $attrs;
				$changed                   = true;
			}
		}

		if ( ! empty( $block['innerBlocks'] ) ) {
			$blocks[ $index ]['innerBlocks'] = pickleball_ratings_migrate_blocks( $block['innerBlocks'], $post_id, $images, $changed );
		}
	}

	return $blocks;
}

/**
 * Convert one legacy block's attributes to the 1.0.0 format.
 *
 * @param array $attrs   Legacy block attributes.
 * @param int   $post_id Post containing the block.
 * @param array $images  Attachment IDs already downloaded, keyed by DUPR ID.
 * @return array Migrated attributes.
 */
function pickleball_ratings_migrate_block_attributes( array $attrs, $post_id, array &$images ) {
	$show_image = $attrs['showProfilePic'] ?? true;
	unset( $attrs['showProfilePic'], $attrs['showPoweredBy'], $attrs['useLightLogo'] );

	if ( 'dupr-blue-gradient' === ( $attrs['gradient'] ?? '' ) ) {
		// The default Gradient variation replaces the removed preset, and the
		// text and link colors only existed to contrast with it.
		unset( $attrs['gradient'], $attrs['textColor'], $attrs['style']['elements']['link'] );
		if ( empty( $attrs['style']['elements'] ) ) {
			unset( $attrs['style']['elements'] );
		}
		if ( empty( $attrs['style'] ) ) {
			unset( $attrs['style'] );
		}
	} else {
		// Without the preset, legacy blocks rendered as a plain white card.
		$attrs['className'] = trim( ( $attrs['className'] ?? '' ) . ' is-style-white' );
	}

	$dupr_id = $attrs['duprId'] ?? '';
	$cached  = $dupr_id ? get_option( 'pbr_dupr_player_' . $dupr_id ) : false;

	if ( ! is_array( $cached ) ) {
		return $attrs;
	}

	$name   = sanitize_text_field( $cached['name'] ?? '' );
	$fields = array(
		'name'               => $name,
		'doublesRating'      => pbr_normalize_rating( $cached['doubles_rating'] ?? '' ),
		'doublesReliability' => pbr_normalize_reliability( $cached['doubles_reliability'] ?? null ),
		'singlesRating'      => pbr_normalize_rating( $cached['singles_rating'] ?? '' ),
		'singlesReliability' => pbr_normalize_reliability( $cached['singles_reliability'] ?? null ),
		'updated'            => pickleball_ratings_legacy_date( $cached['last_updated'] ?? '' ),
	);

	foreach ( $fields as $key => $value ) {
		if ( null !== $value && '' !== $value ) {
			$attrs[ $key ] = $value;
		}
	}

	if ( $show_image && ! empty( $cached['profile_image'] ) ) {
		if ( ! array_key_exists( $dupr_id, $images ) ) {
			$images[ $dupr_id ] = pickleball_ratings_sideload_image( $cached['profile_image'], $post_id, $name );
		}

		if ( $images[ $dupr_id ] ) {
			$attrs['mediaId'] = $images[ $dupr_id ];
		}
	}

	return $attrs;
}

/**
 * Convert a legacy cache timestamp (site-local MySQL format) to ISO 8601.
 *
 * @param string $value Legacy timestamp.
 * @return string ISO 8601 date, or an empty string when unparseable.
 */
function pickleball_ratings_legacy_date( $value ) {
	if ( ! is_string( $value ) || '' === $value ) {
		return '';
	}

	$date = date_create_immutable( $value, wp_timezone() );

	return $date ? $date->format( DATE_ATOM ) : '';
}

/**
 * Download a remote profile image into the media library.
 *
 * @param string $url     Image URL.
 * @param int    $post_id Post to attach the image to.
 * @param string $name    Player name, used as the attachment title.
 * @return int Attachment ID, or 0 on failure.
 */
function pickleball_ratings_sideload_image( $url, $post_id, $name ) {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$attachment_id = media_sideload_image( esc_url_raw( $url ), $post_id, $name, 'id' );

	return is_wp_error( $attachment_id ) ? 0 : (int) $attachment_id;
}

/**
 * Delete options left behind by the DUPR API integration.
 */
function pickleball_ratings_delete_legacy_options() {
	global $wpdb;

	$options = array(
		'pickleball_ratings_dupr_auth_token',
		'pickleball_ratings_dupr_auth_refresh_token',
		'pickleball_ratings_dupr_auth_user_name',
		'pickleball_ratings_dupr_auth_id',
		'pickleball_ratings_dupr_auth_email',
		'pickleball_ratings_dupr_last_refresh_attempt',
		'pickleball_ratings_cache_ttl',
		'pickleball_ratings_cache_salt',
	);

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time cleanup.
	$cached_players = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( 'pbr_dupr_player_' ) . '%'
		)
	);

	foreach ( array_merge( $options, $cached_players ) as $option ) {
		delete_option( $option );
	}
}
