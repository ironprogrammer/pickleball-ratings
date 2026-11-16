<?php
/**
 * Plugin Name:     Pickleball Ratings
 * Plugin URI:      https://github.com/ironprogrammer/pickleball-ratings
 * Description:     Display pickleball player ratings using a customizable block.
 * Author:          Brian Alexander
 * Author URI:      https://brianalexander.com
 * License:         GPLv2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:     pickleball-ratings
 * Version:         1.0.1
 * Requires at least: 6.3
 * Requires PHP: 7.4
 *
 * @package         Pickleball_Ratings
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'PICKLEBALL_RATINGS_VERSION', '1.0.1' );
define( 'PICKLEBALL_RATINGS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once PICKLEBALL_RATINGS_PLUGIN_DIR . 'includes/upgrade.php';

/**
 * Get allowed SVG tags and attributes for sanitization.
 *
 * This whitelist explicitly defines what is permitted, removing potential
 * malicious code such as scripts or data URIs.
 *
 * @return array Allowed SVG tags and attributes.
 */
function pickleball_ratings_allowed_svg_tags() {
	return array(
		'svg'    => array(
			'xmlns'       => true,
			'viewbox'     => true,
			'width'       => true,
			'height'      => true,
			'aria-hidden' => true,
			'role'        => true,
			'fill'        => true,
			'stroke'      => true,
			'class'       => true,
			'style'       => true,
			'data-name'   => true,
			'overflow'    => true,
			'transform'   => true,
		),
		'path'   => array(
			'd'         => true,
			'fill'      => true,
			'stroke'    => true,
			'data-name' => true,
		),
		'g'      => array(
			'fill'      => true,
			'stroke'    => true,
			'data-name' => true,
		),
		'title'  => array(),
		'circle' => array(
			'cx'     => true,
			'cy'     => true,
			'r'      => true,
			'fill'   => true,
			'stroke' => true,
		),
		'rect'   => array(
			'x'      => true,
			'y'      => true,
			'width'  => true,
			'height' => true,
			'fill'   => true,
			'stroke' => true,
		),
	);
}


/**
 * Normalize a rating to three decimals, or "NR" when the player is not rated.
 *
 * Mirrors normalizeRating() in the block's utils.js.
 *
 * @param mixed $value Raw rating value.
 * @return string Normalized rating, or an empty string when invalid.
 */
function pickleball_ratings_normalize_rating( $value ) {
	$rating = strtoupper( trim( (string) $value ) );

	if ( 'NR' === $rating ) {
		return 'NR';
	}

	if ( ! preg_match( '/^(?:\d+(?:\.\d*)?|\.\d+)$/', $rating ) || (float) $rating <= 0 ) {
		return '';
	}

	return number_format( (float) $rating, 3, '.', '' );
}

/**
 * Clamp a reliability score to a whole number from 0 to 100.
 *
 * Mirrors normalizeReliability() in the block's utils.js.
 *
 * @param mixed $value Raw reliability value.
 * @return int|null Normalized score, or null when not provided.
 */
function pickleball_ratings_normalize_reliability( $value ) {
	if ( null === $value || '' === $value || ! is_numeric( $value ) ) {
		return null;
	}

	return max( 0, min( 100, (int) round( (float) $value ) ) );
}

/**
 * Check whether a DUPR ID uses the 6-character uppercase alphanumeric format.
 *
 * @param mixed $dupr_id DUPR ID to check.
 * @return bool True when valid.
 */
function pickleball_ratings_is_valid_dupr_id( $dupr_id ) {
	return is_string( $dupr_id ) && 1 === preg_match( '/^[A-Z0-9]{6}$/', $dupr_id );
}

/**
 * Register the plugin's blocks from their block.json metadata.
 */
function pickleball_ratings_register_blocks() {
	register_block_type( PICKLEBALL_RATINGS_PLUGIN_DIR . 'build/player-ratings' );
	register_block_type( PICKLEBALL_RATINGS_PLUGIN_DIR . 'build/round-robin' );
}
add_action( 'init', 'pickleball_ratings_register_blocks' );
