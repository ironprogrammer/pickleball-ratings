<?php
/**
 * Render template for the round robin block.
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

$players = isset( $attributes['players'] ) ? (int) $attributes['players'] : 8;
$courts  = isset( $attributes['courts'] ) ? (int) $attributes['courts'] : 2;

// Sanitize inputs.
$players = max( 4, min( 32, $players ) );
$courts  = max( 1, min( 8, $courts ) );
?>

<div <?php echo get_block_wrapper_attributes( array( 'class' => 'pbr-block pbr-block--round-robin' ) ); ?>>
	<div class="round-robin-container">
		<div class="input-section">
			<div class="input-group">
				<div class="form-group">
					<label for="pbr-players"><?php esc_html_e( 'Players', 'pickleball-ratings' ); ?></label>
					<input type="number" id="pbr-players" class="pbr-input" min="4" max="32" value="<?php echo esc_attr( $players ); ?>">
				</div>
				<div class="form-group">
					<label for="pbr-courts"><?php esc_html_e( 'Courts', 'pickleball-ratings' ); ?></label>
					<input type="number" id="pbr-courts" class="pbr-input" min="1" max="8" value="<?php echo esc_attr( $courts ); ?>">
				</div>
				<button class="pbr-generate-btn" type="button"><?php esc_html_e( 'Generate', 'pickleball-ratings' ); ?></button>
				<button class="pbr-cancel-btn" type="button" style="display: none;"><?php esc_html_e( 'Cancel', 'pickleball-ratings' ); ?></button>
			</div>
		</div>

		<button class="pbr-new-matchups-btn" type="button" style="display: none;"><?php esc_html_e( 'New Matchups', 'pickleball-ratings' ); ?></button>

		<div id="pbr-schedule-output" class="schedule-output"></div>
		<div id="pbr-stats-output" class="stats-output"></div>
	</div>
</div>