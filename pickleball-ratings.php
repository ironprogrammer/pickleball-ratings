<?php
/**
 * Plugin Name:     Pickleball Ratings
 * Plugin URI:      https://github.com/ironprogrammer/pickleball-ratings
 * Description:     Display pickleball player ratings using a customizable block. Uses the official DUPR API for data.
 * Author:          Brian Alexander
 * Author URI:      https://brianalexander.com
 * License:         GPLv2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:     pickleball-ratings
 * Version:         0.5.0
 * Requires at least: 6.1
 * Tested up to: 7.0.4
 * Requires PHP: 7.4
 *
 * @package         Pickleball_Ratings
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'PICKLEBALL_RATINGS_VERSION', '0.5.0' );
define( 'PICKLEBALL_RATINGS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PICKLEBALL_RATINGS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Get allowed SVG tags and attributes for sanitization.
 *
 * This whitelist explicitly defines what is permitted, removing potential
 * malicious code such as scripts or data URIs.
 *
 * @return array Allowed SVG tags and attributes.
 */
function pbr_get_allowed_svg_tags() {
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
function pbr_normalize_rating( $value ) {
	$rating = strtoupper( trim( (string) $value ) );

	if ( 'NR' === $rating ) {
		return 'NR';
	}

	if ( '' === $rating || ! is_numeric( $rating ) || (float) $rating <= 0 ) {
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
function pbr_normalize_reliability( $value ) {
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
function pbr_is_valid_dupr_id( $dupr_id ) {
	return is_string( $dupr_id ) && 1 === preg_match( '/^[A-Z0-9]{6}$/', $dupr_id );
}


/**
 * Plugin debug logger.
 *
 * Logs messages when:
 * - PBR_DEBUG and WP_DEBUG are both true, or
 * - PBR_DEBUG_FORCE is true (overrides WP_DEBUG requirement)
 *
 * @param string       $message Log message.
 * @param array|string $context Optional context data.
 */
function pbr_log( $message, $context = array() ) {
	$allow = ( defined( 'PBR_DEBUG_FORCE' ) && PBR_DEBUG_FORCE )
		|| ( defined( 'PBR_DEBUG' ) && PBR_DEBUG && defined( 'WP_DEBUG' ) && WP_DEBUG );
	if ( ! $allow ) {
		return;
	}

	$prefix = '[Pickleball Ratings] ';
	if ( ! empty( $context ) ) {
		if ( is_array( $context ) || is_object( $context ) ) {
			$message .= ' | context=' . wp_json_encode( $context );
		} else {
			$message .= ' | context=' . (string) $context;
		}
	}
    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	error_log( $prefix . $message );
}

// Include required files.
require_once PICKLEBALL_RATINGS_PLUGIN_DIR . 'includes/class-pbr-dupr-api.php';
require_once PICKLEBALL_RATINGS_PLUGIN_DIR . 'includes/class-pbr-player-rest-controller.php';
require_once PICKLEBALL_RATINGS_PLUGIN_DIR . 'admin/class-pbr-admin-settings.php';

/**
 * Initialize the plugin.
 */
function pickleball_ratings_init() {
	// Add theme support for gradients.
	add_theme_support(
		'editor-gradient-presets',
		array(
			array(
				'name'     => __( 'DUPR Blue Gradient', 'pickleball-ratings' ),
				'gradient' => 'linear-gradient(45deg, #001762 0%, #0f4299 50%, #187ae8 100%)',
				'slug'     => 'dupr-blue-gradient',
			),
		)
	);

	// Register the Gutenberg block.
	pickleball_ratings_register_block();
}
add_action( 'init', 'pickleball_ratings_init' );

/**
 * Initialize admin functionality.
 */
function pickleball_ratings_admin_init() {
	if ( is_admin() ) {
		new PBR_Admin_Settings();
	}
}
add_action( 'init', 'pickleball_ratings_admin_init' );

/**
 * Initialize the REST API.
 */
function pickleball_ratings_rest_api_init() {
	$controller = new PBR_Player_REST_Controller();
	$controller->register_routes();
}
add_action( 'rest_api_init', 'pickleball_ratings_rest_api_init' );

/**
 * Add settings link to plugin list.
 *
 * @param array $links Existing plugin action links.
 * @return array Modified links with settings link first.
 */
function pickleball_ratings_add_settings_link( $links ) {
	$settings_link = '<a href="' . admin_url( 'options-general.php?page=pickleball-ratings-settings' ) . '">' . __( 'Settings', 'pickleball-ratings' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'pickleball_ratings_add_settings_link' );

/**
 * Register the Gutenberg block.
 */
function pickleball_ratings_register_block() {
	// Register the player-ratings block using block.json metadata.
	register_block_type(
		PICKLEBALL_RATINGS_PLUGIN_DIR . 'build/player-ratings'
	);

	// Register the round-robin block using block.json metadata.
	register_block_type(
		PICKLEBALL_RATINGS_PLUGIN_DIR . 'build/round-robin'
	);
}
