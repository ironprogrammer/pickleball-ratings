<?php
/**
 * Render template for the player ratings block.
 *
 * Markup is mirrored in edit.js so the editor preview matches the frontend.
 *
 * @package Pickleball_Ratings
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Render templates run inside the block's render callback, so these variables are function-scoped.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$pbr_ratings = array_filter(
	array(
		'doubles' => array(
			'label'       => __( 'Doubles', 'pickleball-ratings' ),
			'icon'        => 'pickleball-paddles-crossed',
			'rating'      => pickleball_ratings_normalize_rating( $attributes['doublesRating'] ?? '' ),
			'reliability' => pickleball_ratings_normalize_reliability( $attributes['doublesReliability'] ?? null ),
		),
		'singles' => array(
			'label'       => __( 'Singles', 'pickleball-ratings' ),
			'icon'        => 'pickleball-paddle',
			'rating'      => pickleball_ratings_normalize_rating( $attributes['singlesRating'] ?? '' ),
			'reliability' => pickleball_ratings_normalize_reliability( $attributes['singlesReliability'] ?? null ),
		),
	),
	static function ( $item ) {
		return '' !== $item['rating'];
	}
);

if ( empty( $pbr_ratings ) ) {
	return;
}

$pbr_svg     = require PICKLEBALL_RATINGS_PLUGIN_DIR . 'build/svg-assets.php';
$pbr_allowed = pickleball_ratings_allowed_svg_tags();
$pbr_name    = $attributes['name'] ?? '';
$pbr_dupr_id = $attributes['duprId'] ?? '';
$pbr_avatar  = empty( $attributes['mediaId'] )
	? ''
	: wp_get_attachment_image(
		(int) $attributes['mediaId'],
		'thumbnail',
		false,
		array(
			'class' => 'pbr-avatar',
			'alt'   => '',
		)
	);
$pbr_updated = empty( $attributes['updated'] ) ? false : strtotime( $attributes['updated'] );
/* translators: %s: date the ratings were last updated. */
$pbr_updated_label = $pbr_updated ? sprintf( __( 'Updated %s', 'pickleball-ratings' ), wp_date( get_option( 'date_format' ), $pbr_updated ) ) : '';
/* translators: %s: DUPR ID. */
$pbr_copy_label = sprintf( __( 'Copy DUPR ID %s', 'pickleball-ratings' ), $pbr_dupr_id );
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'pbr-block' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by core. ?>>
	<?php if ( pickleball_ratings_is_valid_dupr_id( $pbr_dupr_id ) ) : ?>
		<button
			type="button"
			class="pbr-copy"
			data-dupr-id="<?php echo esc_attr( $pbr_dupr_id ); ?>"
			data-copied-label="<?php esc_attr_e( 'DUPR ID copied', 'pickleball-ratings' ); ?>"
			data-failed-label="<?php esc_attr_e( 'Could not copy the DUPR ID', 'pickleball-ratings' ); ?>"
			aria-label="<?php echo esc_attr( $pbr_copy_label ); ?>"
			title="<?php echo esc_attr( $pbr_copy_label ); ?>"
		>
			<span class="pbr-copy-icon" aria-hidden="true"><?php echo wp_kses( $pbr_svg['copy-to-clipboard'], $pbr_allowed ); ?></span>
			<span class="pbr-check-icon" aria-hidden="true"><?php echo wp_kses( $pbr_svg['check-circle'], $pbr_allowed ); ?></span>
		</button>
		<span class="pbr-copy-status pbr-visually-hidden" aria-live="polite"></span>
	<?php endif; ?>

	<?php if ( $pbr_name || $pbr_avatar ) : ?>
		<div class="pbr-player">
			<?php echo $pbr_avatar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by wp_get_attachment_image(). ?>
			<?php if ( $pbr_name ) : ?>
				<span class="pbr-name"><?php echo esc_html( $pbr_name ); ?></span>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="pbr-ratings"<?php echo $pbr_updated_label ? ' title="' . esc_attr( $pbr_updated_label ) . '"' : ''; ?>>
		<?php foreach ( $pbr_ratings as $pbr_key => $pbr_item ) : ?>
			<div class="pbr-rating pbr-<?php echo esc_attr( $pbr_key ); ?>">
				<span class="pbr-label">
					<span class="pbr-icon" aria-hidden="true"><?php echo wp_kses( $pbr_svg[ $pbr_item['icon'] ], $pbr_allowed ); ?></span>
					<?php echo esc_html( $pbr_item['label'] ); ?>
				</span>
				<span class="pbr-score">
					<?php if ( 'NR' === $pbr_item['rating'] ) : ?>
						<abbr class="pbr-value" title="<?php esc_attr_e( 'Not rated', 'pickleball-ratings' ); ?>">NR</abbr>
					<?php else : ?>
						<span class="pbr-value"><?php echo esc_html( $pbr_item['rating'] ); ?></span>
					<?php endif; ?>
					<?php
					if ( null !== $pbr_item['reliability'] ) :
						/* translators: %d: reliability score from 0 to 100. */
						$pbr_reliability_label = sprintf( __( 'Reliability: %d of 100', 'pickleball-ratings' ), $pbr_item['reliability'] );
						?>
						<span class="pbr-reliability" role="img" aria-label="<?php echo esc_attr( $pbr_reliability_label ); ?>" title="<?php echo esc_attr( $pbr_reliability_label ); ?>" style="--pbr-score: <?php echo (int) $pbr_item['reliability']; ?>">
							<svg viewBox="0 0 36 36" aria-hidden="true" focusable="false">
								<circle class="pbr-ring-track" cx="18" cy="18" r="15.9155"></circle>
								<circle class="pbr-ring-value" cx="18" cy="18" r="15.9155" stroke-dasharray="<?php echo (int) $pbr_item['reliability']; ?> 100"></circle>
							</svg>
							<span class="pbr-reliability-value" aria-hidden="true"><?php echo (int) $pbr_item['reliability']; ?></span>
						</span>
					<?php endif; ?>
				</span>
			</div>
		<?php endforeach; ?>
	</div>

	<?php if ( $pbr_updated ) : ?>
		<p class="pbr-visually-hidden">
			<time datetime="<?php echo esc_attr( gmdate( 'c', $pbr_updated ) ); ?>"><?php echo esc_html( $pbr_updated_label ); ?></time>
		</p>
	<?php endif; ?>
</div>
