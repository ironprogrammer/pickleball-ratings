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
	// Get asset file for dependencies and version.
	$asset_file = include PICKLEBALL_RATINGS_PLUGIN_DIR . 'build/index.asset.php';

	// Register block script.
	wp_register_script(
		'pickleball-ratings-block',
		PICKLEBALL_RATINGS_PLUGIN_URL . 'build/index.js',
		$asset_file['dependencies'],
		$asset_file['version'],
		true
	);

	// Register block style.
	wp_register_style(
		'pickleball-ratings-block-style',
		PICKLEBALL_RATINGS_PLUGIN_URL . 'build/style-index.css',
		array( 'dashicons' ),
		$asset_file['version']
	);

	// Register frontend script.
	wp_register_script(
		'pickleball-ratings-block-frontend',
		PICKLEBALL_RATINGS_PLUGIN_URL . 'build/frontend.js',
		array(),
		$asset_file['version'],
		true // Load in footer.
	);

	// Register the block.
	register_block_type(
		'pickleball-ratings/player-ratings',
		array(
			'editor_script'   => 'pickleball-ratings-block',
			'editor_style'    => 'pickleball-ratings-block-style',
			'style'           => 'pickleball-ratings-block-style',
			'script'          => 'pickleball-ratings-block-frontend',
			'render_callback' => 'pickleball_ratings_render_block',
			'supports'        => array(
				'color' => array(
					'background' => true,
					'text'       => true,
					'gradients'  => true,
				),
			),
			'attributes'      => array(
				'duprId'                => array(
					'type'    => 'string',
					'default' => '',
				),
				'showProfilePic'        => array(
					'type'    => 'boolean',
					'default' => true,
				),

				'showPoweredBy'         => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'useLightLogo'          => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'backgroundColor'       => array(
					'type'    => 'string',
					'default' => '',
				),
				'textColor'             => array(
					'type'    => 'string',
					'default' => '',
				),
				'customBackgroundColor' => array(
					'type'    => 'string',
					'default' => '',
				),
				'customTextColor'       => array(
					'type'    => 'string',
					'default' => '',
				),
				'gradient'              => array(
					'type'    => 'string',
					'default' => '',
				),
				'customGradient'        => array(
					'type'    => 'string',
					'default' => '',
				),
				'fontSize'              => array(
					'type'    => 'string',
					'default' => '',
				),

			),
		)
	);

	// Register the round robin block.
	register_block_type(
		'pickleball-ratings/round-robin',
		array(
			'editor_script'   => 'pickleball-ratings-block',
			'editor_style'    => 'pickleball-ratings-block-style',
			'style'           => 'pickleball-ratings-block-style',
			'script'          => 'pickleball-ratings-block-frontend',
			'render_callback' => 'pickleball_ratings_render_round_robin_block',
			'supports'        => array(
				'align' => true,
			),
			'attributes'      => array(
				'players' => array(
					'type'    => 'number',
					'default' => 8,
				),
				'courts'  => array(
					'type'    => 'number',
					'default' => 2,
				),
			),
		)
	);

	// Localize script for AJAX.
	wp_localize_script(
		'pickleball-ratings-block',
		'duprRatingAjax',
		array(
			'nonce'              => wp_create_nonce( 'pickleball_ratings_get_player_data' ),
			'pluginUrl'          => PICKLEBALL_RATINGS_PLUGIN_URL,
			'enableDuprBranding' => PICKLEBALL_RATINGS_ENABLE_DUPR_BRANDING,
		)
	);
}

/**
 * Render callback for the block.
 *
 * @param array $attributes Block attributes.
 * @return string Rendered HTML output.
 */
function pickleball_ratings_render_block( $attributes ) {
	$dupr_id          = isset( $attributes['duprId'] ) ? sanitize_text_field( $attributes['duprId'] ) : '';
	$show_profile_pic = isset( $attributes['showProfilePic'] ) ? (bool) $attributes['showProfilePic'] : true;
	$show_powered_by  = isset( $attributes['showPoweredBy'] ) ? (bool) $attributes['showPoweredBy'] : false;

	// Load SVG assets once for the entire function.
	$svg_assets = require PICKLEBALL_RATINGS_PLUGIN_DIR . 'build/svg-assets.php';

	// Override powered by setting if DUPR branding feature is disabled.
	if ( ! PICKLEBALL_RATINGS_ENABLE_DUPR_BRANDING ) {
		$show_powered_by = false;
	}
	$use_light_logo          = isset( $attributes['useLightLogo'] ) ? (bool) $attributes['useLightLogo'] : false;
	$background_color        = isset( $attributes['backgroundColor'] ) ? sanitize_text_field( $attributes['backgroundColor'] ) : '';
	$text_color              = isset( $attributes['textColor'] ) ? sanitize_text_field( $attributes['textColor'] ) : '';
	$custom_background_color = isset( $attributes['customBackgroundColor'] ) ? sanitize_text_field( $attributes['customBackgroundColor'] ) : '';
	$custom_text_color       = isset( $attributes['customTextColor'] ) ? sanitize_text_field( $attributes['customTextColor'] ) : '';
	$gradient                = isset( $attributes['gradient'] ) ? sanitize_text_field( $attributes['gradient'] ) : '';
	$custom_gradient         = isset( $attributes['customGradient'] ) ? sanitize_text_field( $attributes['customGradient'] ) : '';
	$font_size               = isset( $attributes['fontSize'] ) ? sanitize_text_field( $attributes['fontSize'] ) : '';

	// Basic validation.
	if ( empty( $dupr_id ) ) {
		return ''; // Hide block on frontend when no DUPR ID provided.
	}

	// Validate 6-character alphanumeric format.
	if ( ! preg_match( '/^[A-Z0-9]{6}$/', $dupr_id ) ) {
		return ''; // Hide block on frontend when DUPR ID format is invalid.
	}

	// Get player data from DUPR API.
	$api         = new PBR_DUPR_API();
	$player_data = $api->get_player_data( $dupr_id );

	// Handle API errors.
	if ( is_wp_error( $player_data ) ) {
		$error_message = $player_data->get_error_message();

		// If it's an authentication error, show a different message.
		if ( 'no_auth' === $player_data->get_error_code() ) {
			$error_message = 'DUPR API not configured. Please contact the site administrator.';
		}

		return ''; // Hide block on frontend when API errors occur.
	}

	// Build color classes and styles using WordPress functions.
	$color_classes = array();
	$color_styles  = array();

	// Handle background color.
	if ( ! empty( $background_color ) ) {
		$color_classes[] = 'has-background';
		$color_classes[] = 'has-' . $background_color . '-background-color';
	}
	if ( ! empty( $custom_background_color ) ) {
		$color_styles[] = 'background-color: ' . esc_attr( $custom_background_color );
	}

	// Handle gradient.
	if ( ! empty( $gradient ) ) {
		$color_classes[] = 'has-background';
		$color_classes[] = 'has-' . $gradient . '-gradient-background';
	}
	if ( ! empty( $custom_gradient ) ) {
		$color_styles[] = 'background: ' . esc_attr( $custom_gradient );
	}

	// Handle text color.
	if ( ! empty( $text_color ) ) {
		$color_classes[] = 'has-text-color';
		$color_classes[] = 'has-' . $text_color . '-color';
	}
	if ( ! empty( $custom_text_color ) ) {
		$color_styles[] = 'color: ' . esc_attr( $custom_text_color );
	}

	$color_class_string = ! empty( $color_classes ) ? ' ' . implode( ' ', $color_classes ) : '';
	$color_style_string = ! empty( $color_styles ) ? ' style="' . implode( '; ', $color_styles ) . '"' : '';

	// Build typography classes.
	$typography_classes = array();
	if ( ! empty( $font_size ) ) {
		$typography_classes[] = 'has-' . $font_size . '-font-size';
	}

	$typography_class_string = ! empty( $typography_classes ) ? ' ' . implode( ' ', $typography_classes ) : '';

	// Build the output.
	$output  = '<div class="pbr-block pickleball-ratings-block' . $color_class_string . $typography_class_string . '"' . $color_style_string . '>';
	$output .= '<div class="block-wrapper">';

	// Add corner copy button for DUPR ID.
	if ( ! empty( $dupr_id ) ) {

		$output .= '<button class="copy-btn" onclick="window.pbrCopyToClipboard(\'' . esc_js( $dupr_id ) . '\', this)" title="Copy DUPR ID: ' . esc_attr( $dupr_id ) . '">';
		$output .= '<span class="copy-icon">' . $svg_assets['copy-to-clipboard'] . '</span>';
		$output .= '<span class="check-icon" style="display: none;">' . $svg_assets['check-circle'] . '</span>';
		$output .= '</button>';
	}

	// Add player name if available.
	if ( ! empty( $player_data['name'] ) ) {
		// Create title attribute for last updated info.
		$title_attribute = ! empty( $player_data['last_updated'] )
			? ' title="Last updated: ' . esc_attr( $player_data['last_updated'] ) . '"'
			: '';

		$output .= '<div class="player-name"' . $title_attribute . '>';

		// Add profile picture if enabled and available.
		if ( $show_profile_pic ) {
			if ( ! empty( $player_data['profile_image'] ) ) {
				$output .= '<img src="' . esc_url( $player_data['profile_image'] ) . '" alt="' . esc_attr( $player_data['name'] ) . '" class="profile-pic" />';
			} else {

				// Use WordPress HTML Tag Processor to modify SVG attributes.
				$user_svg = $svg_assets['user-profile']; // fallback to original
				$processor = new WP_HTML_Tag_Processor( $svg_assets['user-profile'] );
				if ( $processor->next_tag( 'svg' ) ) {
					$processor->set_attribute( 'width', '30' );
					$processor->set_attribute( 'height', '30' );
					$processor->set_attribute( 'style', 'color: #666;' );
					$user_svg = $processor->get_updated_html();
				}

				$output .= '<div class="profile-pic-fallback">' . $user_svg . '</div>';
			}
		}

		$output .= esc_html( $player_data['name'] );
		$output .= '</div>';
	}

	$output       .= '<div class="rating-content">';
	$output       .= '<div class="pbr-item">';
	$output       .= '<span class="pbr-label">';
	$output       .= '<span class="pbr-icon pbr-icon-doubles">' . $svg_assets['pickleball-paddles-crossed'] . '</span>';
	$output       .= 'Doubles';
	$output       .= '</span>';
	$doubles_title = '';
	if ( 'NR' === $player_data['doubles_rating'] ) {
		$doubles_title = ' title="Not Rated"';
	} elseif ( isset( $player_data['doubles_reliability'] ) && $player_data['doubles_reliability'] > 0 ) {
		$doubles_title = ' title="Doubles: ' . esc_attr( $player_data['doubles_rating'] ) . ' (Reliability: ' . esc_attr( $player_data['doubles_reliability'] ) . '%)"';
	} else {
		$doubles_title = ' title="Doubles: ' . esc_attr( $player_data['doubles_rating'] ) . '"';
	}
	$output       .= '<span class="pbr-value"' . $doubles_title . '>' . esc_html( $player_data['doubles_rating'] ) . '</span>';
	$output       .= '</div>';
	$output       .= '<div class="pbr-item">';
	$output       .= '<span class="pbr-label">';
	$output       .= '<span class="pbr-icon pbr-icon-singles">' . $svg_assets['pickleball-paddle'] . '</span>';
	$output       .= 'Singles';
	$output       .= '</span>';
	$singles_title = '';
	if ( 'NR' === $player_data['singles_rating'] ) {
		$singles_title = ' title="Not Rated"';
	} elseif ( isset( $player_data['singles_reliability'] ) && $player_data['singles_reliability'] > 0 ) {
		$singles_title = ' title="Singles: ' . esc_attr( $player_data['singles_rating'] ) . ' (Reliability: ' . esc_attr( $player_data['singles_reliability'] ) . '%)"';
	} else {
		$singles_title = ' title="Singles: ' . esc_attr( $player_data['singles_rating'] ) . '"';
	}
	$output .= '<span class="pbr-value"' . $singles_title . '>' . esc_html( $player_data['singles_rating'] ) . '</span>';
	$output .= '</div>';
	$output .= '</div>';

	// Add powered by footer if enabled.
	if ( $show_powered_by ) {
		$logo_file = $use_light_logo ? 'dupr-logo-white.png' : 'dupr-logo-blue.png';
		$logo_url  = PICKLEBALL_RATINGS_PLUGIN_URL . 'images/' . $logo_file;

		$output .= '<div class="footer">';
		$output .= '<span class="powered-by">';
		$output .= 'Powered by ';
		$output .= '<img src="' . esc_url( $logo_url ) . '" alt="DUPR" class="logo" />';
		$output .= '</span>';
		$output .= '</div>';
	}

	$output .= '</div>';
	$output .= '</div>'; // Close pickleball-ratings-block-wrapper.

	return $output;
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
