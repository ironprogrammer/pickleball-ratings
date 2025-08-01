<?php
/**
 * Plugin Name:     DUPR Rating
 * Plugin URI:      https://github.com/ironprogrammer/dupr-rating
 * Description:     Display DUPR ratings for pickleball players using a customizable block.
 * Author:          Brian Alexander
 * Author URI:      https://brianalexander.com
 * Text Domain:     dupr-rating
 * Domain Path:     /languages
 * Version:         0.2.0
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 *
 * @package         Dupr_Rating
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'DUPR_RATING_VERSION', '0.2.0' );
define( 'DUPR_RATING_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DUPR_RATING_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include required files
require_once DUPR_RATING_PLUGIN_DIR . 'includes/class-dupr-api.php';
require_once DUPR_RATING_PLUGIN_DIR . 'includes/class-ajax-handler.php';
require_once DUPR_RATING_PLUGIN_DIR . 'admin/class-admin-settings.php';

// Initialize the plugin
function dupr_rating_init() {
	// Load text domain for internationalization
	load_plugin_textdomain( 'dupr-rating', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	
	// Register the Gutenberg block
	dupr_rating_register_block();
}
add_action( 'init', 'dupr_rating_init' );

// Initialize admin functionality
function dupr_rating_admin_init() {
	if ( is_admin() ) {
		new DUPR_Admin_Settings();
	}
}
add_action( 'init', 'dupr_rating_admin_init' );

// Initialize AJAX handler
function dupr_rating_ajax_init() {
	try {
		new DUPR_Ajax_Handler();
	} catch ( Exception $e ) {
		error_log( 'DUPR: AJAX handler error: ' . $e->getMessage() );
	}
}
add_action( 'wp_loaded', 'dupr_rating_ajax_init' );

// Add settings link to plugin list
function dupr_rating_add_settings_link( $links ) {
	$settings_link = '<a href="' . admin_url( 'options-general.php?page=dupr-rating-settings' ) . '">' . __( 'Settings', 'dupr-rating' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'dupr_rating_add_settings_link' );

// Register the Gutenberg block
function dupr_rating_register_block() {
	// Get asset file for dependencies and version
	$asset_file = include( DUPR_RATING_PLUGIN_DIR . 'build/index.asset.php' );

	// Register block script
	wp_register_script(
		'dupr-rating-block',
		DUPR_RATING_PLUGIN_URL . 'build/index.js',
		$asset_file['dependencies'],
		$asset_file['version']
	);

	// Register block style
	wp_register_style(
		'dupr-rating-block-style',
		DUPR_RATING_PLUGIN_URL . 'build/style-index.css',
		array(),
		$asset_file['version']
	);

	// Register block editor style
	wp_register_style(
		'dupr-rating-block-editor',
		DUPR_RATING_PLUGIN_URL . 'build/style-index.css',
		array( 'wp-edit-blocks', 'dashicons' ),
		$asset_file['version']
	);

	// Register the block
	register_block_type( 'dupr-rating/player-rating', array(
		'editor_script' => 'dupr-rating-block',
		'editor_style'  => 'dupr-rating-block-editor',
		'style'         => 'dupr-rating-block-style',
		'render_callback' => 'dupr_rating_render_block',
		'attributes'    => array(
			'duprId' => array(
				'type' => 'string',
				'default' => '',
			),
			'showProfilePic' => array(
				'type' => 'boolean',
				'default' => true,
			),
		),
	) );

	// Localize script for AJAX
	wp_localize_script(
		'dupr-rating-block',
		'duprRatingAjax',
		array(
			'nonce' => wp_create_nonce( 'dupr_get_player_data' ),
		)
	);
}

// Render callback for the block
function dupr_rating_render_block( $attributes ) {
	$dupr_id = isset( $attributes['duprId'] ) ? sanitize_text_field( $attributes['duprId'] ) : '';
	$show_profile_pic = isset( $attributes['showProfilePic'] ) ? (bool) $attributes['showProfilePic'] : true;
	
	// Basic validation
	if ( empty( $dupr_id ) ) {
		return '<div class="dupr-rating-block dupr-rating-error">Please enter a valid DUPR ID.</div>';
	}
	
	// Validate 6-character alphanumeric format
	if ( ! preg_match( '/^[A-Z0-9]{6}$/', $dupr_id ) ) {
		return '<div class="dupr-rating-block dupr-rating-error">Invalid DUPR ID format. Please enter a 6-character alphanumeric code.</div>';
	}
	
	// Get player data from DUPR API
	$api = new DUPR_API();
	$player_data = $api->get_player_data( $dupr_id );
	
	// Handle API errors
	if ( is_wp_error( $player_data ) ) {
		$error_message = $player_data->get_error_message();
		
		// If it's an authentication error, show a different message
		if ( 'no_auth' === $player_data->get_error_code() ) {
			$error_message = 'DUPR API not configured. Please contact the site administrator.';
		}
		
		return '<div class="dupr-rating-block dupr-rating-error">' . esc_html( $error_message ) . '</div>';
	}
	
	// Build the output
	$output = '<div class="dupr-rating-block">';
	$output .= '<div class="dupr-rating-header">';
	$output .= '<h3 class="dupr-rating-title">DUPR Rating</h3>';
	$output .= '<span class="dupr-rating-id">ID: ' . esc_html( $dupr_id ) . '</span>';
	$output .= '</div>';
	
	// Add player name if available
	if ( ! empty( $player_data['name'] ) ) {
		// Create title attribute for last updated info
		$title_attribute = ! empty( $player_data['last_updated'] ) 
			? ' title="Last updated: ' . esc_attr( $player_data['last_updated'] ) . '"' 
			: '';
		
		$output .= '<div class="dupr-rating-player-name"' . $title_attribute . '>';
		
		// Add profile picture if enabled and available
		if ( $show_profile_pic ) {
			if ( ! empty( $player_data['profile_image'] ) ) {
				$output .= '<img src="' . esc_url( $player_data['profile_image'] ) . '" alt="' . esc_attr( $player_data['name'] ) . '" class="dupr-rating-profile-pic" />';
			} else {
				$output .= '<span class="dashicons dashicons-admin-users dupr-rating-profile-pic-fallback"></span>';
			}
		}
		
		$output .= esc_html( $player_data['name'] );
		$output .= '</div>';
	}
	
	$output .= '<div class="dupr-rating-content">';
	$output .= '<div class="dupr-rating-item">';
	$output .= '<span class="dupr-rating-label">Doubles Rating:</span>';
	$output .= '<span class="dupr-rating-value">' . esc_html( $player_data['doubles_rating'] ) . '</span>';
	$output .= '</div>';
	$output .= '<div class="dupr-rating-item">';
	$output .= '<span class="dupr-rating-label">Singles Rating:</span>';
	$output .= '<span class="dupr-rating-value">' . esc_html( $player_data['singles_rating'] ) . '</span>';
	$output .= '</div>';
	$output .= '</div>';
	
	$output .= '</div>';
	
	return $output;
}
