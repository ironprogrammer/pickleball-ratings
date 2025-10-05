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
 * Version:         0.4.0
 * Requires at least: 5.6
 * Tested up to: 6.8
 * Requires PHP: 7.4
 *
 * @package         Pickleball_Ratings
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'PICKLEBALL_RATINGS_VERSION', '0.4.0' );
define( 'PICKLEBALL_RATINGS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PICKLEBALL_RATINGS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Feature flags.
define( 'PICKLEBALL_RATINGS_ENABLE_DUPR_BRANDING', false );

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
require_once PICKLEBALL_RATINGS_PLUGIN_DIR . 'includes/class-pbr-ajax-handler.php';
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
 * Initialize AJAX handler.
 */
function pickleball_ratings_ajax_init() {
	try {
		new PBR_Ajax_Handler();
	} catch ( Exception $e ) {
		pbr_log( 'AJAX handler error: ' . $e->getMessage() );
	}
}
add_action( 'wp_loaded', 'pickleball_ratings_ajax_init' );

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
		PICKLEBALL_RATINGS_PLUGIN_DIR . 'build/round-robin',
		array(
			'render_callback' => 'pickleball_ratings_render_round_robin_block',
		)
	);

	// Localize script for AJAX.
	wp_localize_script(
		'pickleball-ratings-player-ratings-editor-script',
		'duprRatingAjax',
		array(
			'nonce'              => wp_create_nonce( 'pickleball_ratings_get_player_data' ),
			'pluginUrl'          => PICKLEBALL_RATINGS_PLUGIN_URL,
			'enableDuprBranding' => PICKLEBALL_RATINGS_ENABLE_DUPR_BRANDING,
		)
	);
}


/**
 * Render callback for the round robin block.
 *
 * @param array $attributes Block attributes.
 * @return string Rendered HTML output.
 */
function pickleball_ratings_render_round_robin_block( $attributes ) {
	$players = isset( $attributes['players'] ) ? (int) $attributes['players'] : 8;
	$courts  = isset( $attributes['courts'] ) ? (int) $attributes['courts'] : 2;

	// Sanitize inputs.
	$players = max( 4, min( 32, $players ) );
	$courts  = max( 1, min( 8, $courts ) );

	// Build the output.
	$output  = '<div class="pbr-block pbr-block--round-robin">';
	$output .= '<div class="round-robin-container">';
	$output .= '<div class="input-section">';
	$output .= '<div class="input-group">';
	$output .= '<div class="form-group">';
	$output .= '<label for="pbr-players">' . esc_html__( 'Players', 'pickleball-ratings' ) . '</label>';
	$output .= '<input type="number" id="pbr-players" class="pbr-input" min="4" max="32" value="' . esc_attr( $players ) . '">';
	$output .= '</div>';
	$output .= '<div class="form-group">';
	$output .= '<label for="pbr-courts">' . esc_html__( 'Courts', 'pickleball-ratings' ) . '</label>';
	$output .= '<input type="number" id="pbr-courts" class="pbr-input" min="1" max="8" value="' . esc_attr( $courts ) . '">';
	$output .= '</div>';
	$output .= '<button class="pbr-generate-btn" type="button">' . esc_html__( 'Generate', 'pickleball-ratings' ) . '</button>';
	$output .= '<button class="pbr-cancel-btn" type="button" style="display: none;">' . esc_html__( 'Cancel', 'pickleball-ratings' ) . '</button>';
	$output .= '</div>';
	$output .= '</div>';

	$output .= '<button class="pbr-new-matchups-btn" type="button" style="display: none;">' . esc_html__( 'New Matchups', 'pickleball-ratings' ) . '</button>';

	$output .= '<div id="pbr-schedule-output" class="schedule-output"></div>';
	$output .= '<div id="pbr-stats-output" class="stats-output"></div>';
	$output .= '</div>';
	$output .= '</div>';

	return $output;
}
