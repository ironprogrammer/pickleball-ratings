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
	
	// Add theme support for gradients
	add_theme_support( 'editor-gradient-presets', array(
		array(
			'name'     => __( 'DUPR Blue Gradient', 'dupr-rating' ),
			'gradient' => 'linear-gradient(45deg, #001762 0%, #0f4299 50%, #187ae8 100%)',
			'slug'     => 'dupr-blue-gradient',
		),
	) );
	
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
		'supports'      => array(
			'color' => array(
				'background' => true,
				'text'       => true,
				'gradients'  => true,
			),
		),
		'attributes'    => array(
			'duprId' => array(
				'type' => 'string',
				'default' => '',
			),
			'showProfilePic' => array(
				'type' => 'boolean',
				'default' => true,
			),
			'showDuprId' => array(
				'type' => 'boolean',
				'default' => false,
			),
			'showPoweredBy' => array(
				'type' => 'boolean',
				'default' => false,
			),
			'useLightLogo' => array(
				'type' => 'boolean',
				'default' => false,
			),
			'backgroundColor' => array(
				'type' => 'string',
				'default' => '',
			),
			'textColor' => array(
				'type' => 'string',
				'default' => '',
			),
			'customBackgroundColor' => array(
				'type' => 'string',
				'default' => '',
			),
			'customTextColor' => array(
				'type' => 'string',
				'default' => '',
			),
			'gradient' => array(
				'type' => 'string',
				'default' => '',
			),
			'customGradient' => array(
				'type' => 'string',
				'default' => '',
			),
			'fontSize' => array(
				'type' => 'string',
				'default' => '',
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
	$show_dupr_id = isset( $attributes['showDuprId'] ) ? (bool) $attributes['showDuprId'] : false;
	$show_powered_by = isset( $attributes['showPoweredBy'] ) ? (bool) $attributes['showPoweredBy'] : false;
	$use_light_logo = isset( $attributes['useLightLogo'] ) ? (bool) $attributes['useLightLogo'] : false;
	$background_color = isset( $attributes['backgroundColor'] ) ? sanitize_text_field( $attributes['backgroundColor'] ) : '';
	$text_color = isset( $attributes['textColor'] ) ? sanitize_text_field( $attributes['textColor'] ) : '';
	$custom_background_color = isset( $attributes['customBackgroundColor'] ) ? sanitize_text_field( $attributes['customBackgroundColor'] ) : '';
	$custom_text_color = isset( $attributes['customTextColor'] ) ? sanitize_text_field( $attributes['customTextColor'] ) : '';
	$gradient = isset( $attributes['gradient'] ) ? sanitize_text_field( $attributes['gradient'] ) : '';
	$custom_gradient = isset( $attributes['customGradient'] ) ? sanitize_text_field( $attributes['customGradient'] ) : '';
	$font_size = isset( $attributes['fontSize'] ) ? sanitize_text_field( $attributes['fontSize'] ) : '';

	
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
	
	// Build color classes and styles using WordPress functions
	$color_classes = array();
	$color_styles = array();
	
	// Handle background color
	if ( ! empty( $background_color ) ) {
		$color_classes[] = 'has-background';
		$color_classes[] = 'has-' . $background_color . '-background-color';
	}
	if ( ! empty( $custom_background_color ) ) {
		$color_styles[] = 'background-color: ' . esc_attr( $custom_background_color );
	}
	
	// Handle gradient
	if ( ! empty( $gradient ) ) {
		$color_classes[] = 'has-background';
		$color_classes[] = 'has-' . $gradient . '-gradient-background';
	}
	if ( ! empty( $custom_gradient ) ) {
		$color_styles[] = 'background: ' . esc_attr( $custom_gradient );
	}
	
	// Handle text color
	if ( ! empty( $text_color ) ) {
		$color_classes[] = 'has-text-color';
		$color_classes[] = 'has-' . $text_color . '-color';
	}
	if ( ! empty( $custom_text_color ) ) {
		$color_styles[] = 'color: ' . esc_attr( $custom_text_color );
	}
	
	$color_class_string = ! empty( $color_classes ) ? ' ' . implode( ' ', $color_classes ) : '';
	$color_style_string = ! empty( $color_styles ) ? ' style="' . implode( '; ', $color_styles ) . '"' : '';
	
	// Build typography classes
	$typography_classes = array();
	if ( ! empty( $font_size ) ) {
		$typography_classes[] = 'has-' . $font_size . '-font-size';
	}

	$typography_class_string = ! empty( $typography_classes ) ? ' ' . implode( ' ', $typography_classes ) : '';
	
	// Build the output
	$output = '<div class="dupr-rating-block' . $color_class_string . $typography_class_string . '"' . $color_style_string . '>';
	
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
		
		// Add DUPR ID after player name if enabled
		if ( $show_dupr_id ) {
			$output .= '<span class="dupr-rating-id">' . esc_html( $dupr_id ) . '</span>';
		}
		
		$output .= '</div>';
	}
	
	$output .= '<div class="dupr-rating-content">';
	$output .= '<div class="dupr-rating-item">';
	$output .= '<span class="dupr-rating-label">';
	$output .= '<span class="dashicons dashicons-admin-users dupr-icon dupr-doubles-back"></span>';
	$output .= '<span class="dashicons dashicons-admin-users dupr-icon dupr-doubles-front"></span>';
	$output .= 'Doubles';
	$output .= '</span>';
	$doubles_title = ( $player_data['doubles_rating'] === 'NR' ) ? ' title="Not Rated"' : '';
	$output .= '<span class="dupr-rating-value"' . $doubles_title . '>' . esc_html( $player_data['doubles_rating'] ) . '</span>';
	$output .= '</div>';
	$output .= '<div class="dupr-rating-item">';
	$output .= '<span class="dupr-rating-label">';
	$output .= '<span class="dashicons dashicons-admin-users dupr-icon"></span>';
	$output .= 'Singles';
	$output .= '</span>';
	$singles_title = ( $player_data['singles_rating'] === 'NR' ) ? ' title="Not Rated"' : '';
	$output .= '<span class="dupr-rating-value"' . $singles_title . '>' . esc_html( $player_data['singles_rating'] ) . '</span>';
	$output .= '</div>';
	$output .= '</div>';
	
	// Add powered by footer if enabled
	if ( $show_powered_by ) {
		$logo_file = $use_light_logo ? 'dupr-logo-white.png' : 'dupr-logo-blue.png';
		$logo_url = DUPR_RATING_PLUGIN_URL . 'images/' . $logo_file;
		
		$output .= '<div class="dupr-rating-footer">';
		$output .= '<span class="dupr-rating-powered-by">';
		$output .= 'Powered by ';
		$output .= '<img src="' . esc_url( $logo_url ) . '" alt="DUPR" class="dupr-rating-logo" />';
		$output .= '</span>';
		$output .= '</div>';
	}
	
	$output .= '</div>';
	
	return $output;
}
