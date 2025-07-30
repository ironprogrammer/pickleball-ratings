<?php
/**
 * Plugin Name:     DUPR Rating
 * Plugin URI:      https://github.com/ironprogrammer/dupr-rating
 * Description:     Display DUPR ratings for pickleball players using a customizable block.
 * Author:          Brian Alexander
 * Author URI:      https://brianalexander.com
 * Text Domain:     dupr-rating
 * Domain Path:     /languages
 * Version:         0.1.0
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
define( 'DUPR_RATING_VERSION', '0.1.0' );
define( 'DUPR_RATING_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DUPR_RATING_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Initialize the plugin
function dupr_rating_init() {
	// Load text domain for internationalization
	load_plugin_textdomain( 'dupr-rating', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	
	// Register the Gutenberg block
	dupr_rating_register_block();
}
add_action( 'init', 'dupr_rating_init' );

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
		array( 'wp-edit-blocks' ),
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
		),
	) );
}

// Render callback for the block
function dupr_rating_render_block( $attributes ) {
	$dupr_id = isset( $attributes['duprId'] ) ? sanitize_text_field( $attributes['duprId'] ) : '';
	
	// Basic validation
	if ( empty( $dupr_id ) ) {
		return '<div class="dupr-rating-block dupr-rating-error">Please enter a valid DUPR ID.</div>';
	}
	
	// Validate 6-character alphanumeric format
	if ( ! preg_match( '/^[A-Z0-9]{6}$/', $dupr_id ) ) {
		return '<div class="dupr-rating-block dupr-rating-error">Invalid DUPR ID format. Please enter a 6-character alphanumeric code.</div>';
	}
	
	// For Phase 1, display placeholder data
	$output = '<div class="dupr-rating-block">';
	$output .= '<div class="dupr-rating-header">';
	$output .= '<h3 class="dupr-rating-title">DUPR Rating</h3>';
	$output .= '<span class="dupr-rating-id">ID: ' . esc_html( $dupr_id ) . '</span>';
	$output .= '</div>';
	$output .= '<div class="dupr-rating-content">';
	$output .= '<div class="dupr-rating-item">';
	$output .= '<span class="dupr-rating-label">Doubles Rating:</span>';
	$output .= '<span class="dupr-rating-value">4.25</span>';
	$output .= '</div>';
	$output .= '<div class="dupr-rating-item">';
	$output .= '<span class="dupr-rating-label">Singles Rating:</span>';
	$output .= '<span class="dupr-rating-value">4.50</span>';
	$output .= '</div>';
	$output .= '</div>';
	$output .= '<div class="dupr-rating-note">* Placeholder data for Phase 1</div>';
	$output .= '</div>';
	
	return $output;
}
