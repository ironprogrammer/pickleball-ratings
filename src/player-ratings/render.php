<?php
/**
 * Render template for the player ratings block.
 *
 * @package Pickleball_Ratings
 */

/**
 * Variables available to the renderer.
 *
 * @var array $attributes Block attributes.
 * @var string $content Block default content.
 * @var WP_Block $block Block instance.
 */

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
	return; // Hide block on frontend when no DUPR ID provided.
}

// Validate 6-character alphanumeric format.
if ( ! preg_match( '/^[A-Z0-9]{6}$/', $dupr_id ) ) {
	return; // Hide block on frontend when DUPR ID format is invalid.
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

	return; // Hide block on frontend when API errors occur.
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
?>

<div class="pbr-block pickleball-ratings-block<?php echo esc_attr( $color_class_string . $typography_class_string ); ?>"<?php echo esc_attr( $color_style_string ); ?>>
	<div class="block-wrapper">
		<?php if ( ! empty( $dupr_id ) ) : ?>
			<button class="copy-btn" onclick="window.pbrCopyToClipboard('<?php echo esc_js( $dupr_id ); ?>', this)" title="Copy DUPR ID: <?php echo esc_attr( $dupr_id ); ?>">
				<span class="copy-icon"><?php echo wp_kses( $svg_assets['copy-to-clipboard'], pbr_get_allowed_svg_tags() ); ?></span>
				<span class="check-icon" style="display: none;"><?php echo wp_kses( $svg_assets['check-circle'], pbr_get_allowed_svg_tags() ); ?></span>
			</button>
		<?php endif; ?>

		<?php if ( ! empty( $player_data['name'] ) ) : ?>
			<div class="player-name"
			<?php
			if ( ! empty( $player_data['last_updated'] ) ) :
				?>
				title="<?php echo esc_attr( 'Last updated: ' . $player_data['last_updated'] ); ?>"<?php endif; ?>>
				<?php if ( $show_profile_pic ) : ?>
					<?php if ( ! empty( $player_data['profile_image'] ) ) : ?>
						<img src="<?php echo esc_url( $player_data['profile_image'] ); ?>" alt="<?php echo esc_attr( $player_data['name'] ); ?>" class="profile-pic" />
					<?php else : ?>
						<?php
						// Use WordPress HTML Tag Processor to modify SVG attributes.
						$user_svg  = $svg_assets['user-profile'];
						$processor = new WP_HTML_Tag_Processor( $svg_assets['user-profile'] );
						if ( $processor->next_tag( 'svg' ) ) {
							$processor->set_attribute( 'width', '30' );
							$processor->set_attribute( 'height', '30' );
							$processor->set_attribute( 'style', 'color: #666;' );
							$user_svg = $processor->get_updated_html();
						}
						?>
						<div class="profile-pic-fallback"><?php echo wp_kses( $user_svg, pbr_get_allowed_svg_tags() ); ?></div>
					<?php endif; ?>
				<?php endif; ?>
				<?php echo esc_html( $player_data['name'] ); ?>
			</div>
		<?php endif; ?>

		<div class="rating-content">
			<div class="pbr-item">
				<span class="pbr-label">
					<span class="pbr-icon pbr-icon-doubles"><?php echo wp_kses( $svg_assets['pickleball-paddles-crossed'], pbr_get_allowed_svg_tags() ); ?></span>
					Doubles
				</span>
				<span class="pbr-value"
				<?php
				if ( 'NR' === $player_data['doubles_rating'] ) :
					?>
					title="<?php esc_attr_e( 'Not Rated', 'pickleball-ratings' ); ?>"
					<?php
				elseif ( isset( $player_data['doubles_reliability'] ) && $player_data['doubles_reliability'] > 0 ) :
					?>
					title="<?php echo esc_attr( 'Doubles: ' . $player_data['doubles_rating'] . ' (Reliability: ' . $player_data['doubles_reliability'] . '%)' ); ?>"
					<?php
				else :
					?>
					title="<?php echo esc_attr( 'Doubles: ' . $player_data['doubles_rating'] ); ?>"
					<?php
				endif;
				?>
				><?php echo esc_html( $player_data['doubles_rating'] ); ?></span>
			</div>
			<div class="pbr-item">
				<span class="pbr-label">
					<span class="pbr-icon pbr-icon-singles"><?php echo wp_kses( $svg_assets['pickleball-paddle'], pbr_get_allowed_svg_tags() ); ?></span>
					Singles
				</span>
				<span class="pbr-value"
				<?php
				if ( 'NR' === $player_data['singles_rating'] ) :
					?>
					title="<?php esc_attr_e( 'Not Rated', 'pickleball-ratings' ); ?>"
					<?php
				elseif ( isset( $player_data['singles_reliability'] ) && $player_data['singles_reliability'] > 0 ) :
					?>
					title="<?php echo esc_attr( 'Singles: ' . $player_data['singles_rating'] . ' (Reliability: ' . $player_data['singles_reliability'] . '%)' ); ?>"
					<?php
				else :
					?>
					title="<?php echo esc_attr( 'Singles: ' . $player_data['singles_rating'] ); ?>"
					<?php
				endif;
				?>
				><?php echo esc_html( $player_data['singles_rating'] ); ?></span>
			</div>
		</div>

		<?php if ( $show_powered_by ) : ?>
			<?php
			$logo_file = $use_light_logo ? 'dupr-logo-white.png' : 'dupr-logo-blue.png';
			$logo_url  = PICKLEBALL_RATINGS_PLUGIN_URL . 'images/' . $logo_file;
			?>
			<div class="footer">
				<span class="powered-by">
					Powered by 
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="DUPR" class="logo" />
				</span>
			</div>
		<?php endif; ?>
	</div>
</div>