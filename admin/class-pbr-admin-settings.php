<?php
/**
 * Admin Settings Class.
 *
 * Handles the admin settings page for DUPR API configuration.
 *
 * @package Pickleball_Ratings
 * @since 0.2.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Settings Class.
 */
class PBR_Admin_Settings {

	/**
	 * API instance.
	 *
	 * @var PBR_DUPR_API
	 */
	private $api;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Ensure the API class is available.
		if ( ! class_exists( 'PBR_DUPR_API' ) ) {
			require_once PICKLEBALL_RATINGS_PLUGIN_DIR . 'includes/class-pbr-dupr-api.php';
		}

		$this->api = new PBR_DUPR_API();
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'init_settings' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'Pickleball Ratings Settings', 'pickleball-ratings' ),
			__( 'Pickleball Ratings', 'pickleball-ratings' ),
			'manage_options',
			'pickleball-ratings-settings',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Initialize settings.
	 */
	public function init_settings() {
		register_setting(
			'pickleball_ratings_settings',
			'pickleball_ratings_cache_ttl',
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( $this, 'sanitize_cache_ttl' ),
				'default'           => 86400,
			)
		);

		add_settings_section(
			'pickleball_ratings_cache_section',
			__( 'Cache Configuration', 'pickleball-ratings' ),
			array( $this, 'cache_section_callback' ),
			'pickleball_ratings_settings'
		);

		add_settings_field(
			'pickleball_ratings_cache_ttl',
			__( 'Cache Duration (hours)', 'pickleball-ratings' ),
			array( $this, 'cache_ttl_field_callback' ),
			'pickleball_ratings_settings',
			'pickleball_ratings_cache_section'
		);
	}

	/**
	 * Cache section callback.
	 */
	public function cache_section_callback() {
		echo '<p>' . esc_html__( 'Configure caching settings for DUPR player data.', 'pickleball-ratings' ) . '</p>';
	}

	/**
	 * Cache TTL field callback.
	 */
	public function cache_ttl_field_callback() {
		$ttl   = get_option( 'pickleball_ratings_cache_ttl', 86400 );
		$hours = intval( $ttl / 3600 );
		
		echo '<div style="display: flex; align-items: center; gap: 10px;">';
		echo '<input type="number" id="pickleball_ratings_cache_ttl" name="pickleball_ratings_cache_ttl" value="' . esc_attr( $hours ) . '" min="1" max="168" class="small-text" />';
		
		// Only show clear cache button if user is authenticated
		if ( $this->api && $this->api->is_authenticated() ) {
			echo '<button type="button" id="clear-cache-btn" class="button button-secondary" onclick="return confirmClearCache();">' . esc_html__( 'Clear Cache', 'pickleball-ratings' ) . '</button>';
		}
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'How long to cache player data (1-168 hours).', 'pickleball-ratings' ) . '</p>';
		
		// Add JavaScript for clear cache functionality
		if ( $this->api && $this->api->is_authenticated() ) :
		?>
		<script>
		function confirmClearCache() {
			if (confirm('<?php echo esc_js( __( 'Are you sure you want to clear all cached data?', 'pickleball-ratings' ) ); ?>')) {
				// Create and submit a hidden form
				var form = document.createElement('form');
				form.method = 'post';
				form.action = '';
				
				var nonceField = document.createElement('input');
				nonceField.type = 'hidden';
				nonceField.name = 'pbr_cache_nonce';
				nonceField.value = '<?php echo esc_js( wp_create_nonce( 'pbr_clear_cache' ) ); ?>';
				
				var clearField = document.createElement('input');
				clearField.type = 'hidden';
				clearField.name = 'clear_cache';
				clearField.value = '1';
				
				form.appendChild(nonceField);
				form.appendChild(clearField);
				document.body.appendChild(form);
				form.submit();
			}
			return false;
		}
		</script>
		<?php
		endif;
	}

	/**
	 * Sanitize cache TTL (convert hours to seconds).
	 *
	 * @param int|string $value TTL in hours from settings field.
	 * @return int TTL in seconds, clamped to 1..168 hours.
	 */
	public function sanitize_cache_ttl( $value ) {
		$hours = absint( $value );

		// Ensure minimum and maximum values.
		if ( $hours < 1 ) {
			$hours = 1;
		} elseif ( $hours > 168 ) {
			$hours = 168;
		}

		// Convert hours to seconds.
		return $hours * 3600;
	}

	/**
	 * Settings page.
	 */
	public function settings_page() {
		try {
			// Handle form submissions.
			if ( isset( $_POST['submit'] ) ) {
				$this->handle_form_submission();
			}

			// Handle cache clearing.
			if (
				isset( $_POST['clear_cache'] )
				&& check_admin_referer( 'pbr_clear_cache', 'pbr_cache_nonce' )
			) {
				$this->api->clear_cache();
				add_settings_error(
					'pickleball_ratings_settings',
					'cache_cleared',
					__( 'Cache cleared successfully.', 'pickleball-ratings' ),
					'success'
				);
			}

			// Handle authentication.
			if (
				isset( $_POST['pbr_connect'] )
				&& check_admin_referer( 'pickleball_ratings_authenticate', 'pbr_auth_nonce' )
			) {
				$email    = isset( $_POST['pickleball_ratings_dupr_auth_email'] )
					? sanitize_email( wp_unslash( $_POST['pickleball_ratings_dupr_auth_email'] ) )
					: '';
				$password = isset( $_POST['pickleball_ratings_dupr_auth_password'] )
					? wp_unslash( $_POST['pickleball_ratings_dupr_auth_password'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					: '';

				$this->handle_authentication( $email, $password );
			}

			// Handle disconnect.
			if (
				isset( $_POST['pbr_disconnect'] )
				&& check_admin_referer( 'pickleball_ratings_disconnect', 'pbr_disconnect_nonce' )
			) {
				$this->handle_disconnect();
			}

			?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Pickleball Ratings Settings', 'pickleball-ratings' ); ?></h1>
			
			<!-- Authentication Section -->
			<h2><?php esc_html_e( 'DUPR API Authentication', 'pickleball-ratings' ); ?></h2>
			<p><?php esc_html_e( 'Connect to your DUPR account to enable API access.', 'pickleball-ratings' ); ?></p>
			
				<?php
				$auth_status = $this->api->get_auth_status();

				if ( ! $auth_status['authenticated'] ) :
					?>
					<!-- Authentication Form (only show when not connected) -->
					<form method="post" action="">
						<?php wp_nonce_field( 'pickleball_ratings_authenticate', 'pbr_auth_nonce' ); ?>
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="pickleball_ratings_dupr_auth_email"><?php esc_html_e( 'Email Address', 'pickleball-ratings' ); ?></label>
								</th>
								<td>
									<input type="email" id="pickleball_ratings_dupr_auth_email" name="pickleball_ratings_dupr_auth_email" 
											value="<?php echo esc_attr( $auth_status['user_info'] ? $auth_status['user_info']['email'] : '' ); ?>" 
											class="regular-text" required />
									<p class="description"><?php esc_html_e( 'Enter your DUPR account email address.', 'pickleball-ratings' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="pickleball_ratings_dupr_auth_password"><?php esc_html_e( 'Password', 'pickleball-ratings' ); ?></label>
								</th>
								<td>
									<input type="password" id="pickleball_ratings_dupr_auth_password" name="pickleball_ratings_dupr_auth_password" 
											class="regular-text" required />
									<p class="description"><?php esc_html_e( 'Enter your DUPR account password.', 'pickleball-ratings' ); ?></p>
								</td>
							</tr>
						</table>
						
						<p class="submit">
							<input type="submit" name="pbr_connect" class="button button-primary" 
									value="<?php esc_attr_e( 'Connect to DUPR', 'pickleball-ratings' ); ?>" />
						</p>
					</form>
				<?php else : ?>
					<!-- Connected Status Display -->
					<div class="pbr-connection-status" style="margin-bottom: 20px;">
						<h4 style="margin: 0 0 8px 0; color: #135e96;">
							<span class="dashicons dashicons-yes-alt" style="font-size: 16px; margin-right: 5px; color: #00a32a;"></span>
							<?php esc_html_e( 'Connected to DUPR', 'pickleball-ratings' ); ?>
						</h4>
						<div style="margin-left: 21px; font-size: 13px; line-height: 1.6;">
							<div>
								<strong><?php esc_html_e( 'User:', 'pickleball-ratings' ); ?></strong> <?php echo esc_html( $auth_status['user_info']['user_name'] ); ?>
							</div>
							<?php if ( ! empty( $auth_status['user_info']['dupr_id'] ) ) : ?>
							<div>
								<strong><?php esc_html_e( 'DUPR ID:', 'pickleball-ratings' ); ?></strong> <?php echo esc_html( $auth_status['user_info']['dupr_id'] ); ?>
							</div>
							<?php endif; ?>
							<?php if ( ! empty( $auth_status['user_info']['email'] ) ) : ?>
							<div>
								<strong><?php esc_html_e( 'Email:', 'pickleball-ratings' ); ?></strong> <?php echo esc_html( $auth_status['user_info']['email'] ); ?>
							</div>
							<?php endif; ?>
						</div>
					</div>

					<!-- Test Success Notice (hidden by default, shown via JS) -->
					<div id="test-success-notice" class="notice notice-success is-dismissible" style="display: none;">
						<p><strong><?php esc_html_e( 'Connection test successful!', 'pickleball-ratings' ); ?></strong> <?php esc_html_e( 'Your DUPR API connection is working properly.', 'pickleball-ratings' ); ?></p>
					</div>
					
					<!-- Action Buttons -->
					<div class="pbr-action-buttons" style="margin: 20px 0; display: flex; gap: 10px; align-items: center;">
						<button type="button" class="button" id="test-connection"><?php esc_html_e( 'Test Connection', 'pickleball-ratings' ); ?></button>
						<form method="post" style="margin: 0; display: inline;">
							<?php wp_nonce_field( 'pickleball_ratings_disconnect', 'pbr_disconnect_nonce' ); ?>
							<input type="submit" name="pbr_disconnect" class="button button-secondary" 
									value="<?php esc_attr_e( 'Disconnect from DUPR', 'pickleball-ratings' ); ?>" 
									onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to disconnect from DUPR? This will remove your authentication and you will need to reconnect to use the plugin.', 'pickleball-ratings' ); ?>')" />
						</form>
						<span id="test-result" style="margin-left: 10px;"></span>
					</div>
				<?php endif; ?>

			<hr>

			<!-- Settings Section -->
			<!--<h2><?php esc_html_e( 'Plugin Settings', 'pickleball-ratings' ); ?></h2>-->
			<form method="post" action="options.php">
				<?php
				settings_fields( 'pickleball_ratings_settings' );
				do_settings_sections( 'pickleball_ratings_settings' );
				submit_button();
				?>
			</form>


		</div>

		<script>
		jQuery(document).ready(function($) {
			// Handle test connection
			$('#test-connection').on('click', function() {
				var button = $(this);
				var resultSpan = $('#test-result');
				var successNotice = $('#test-success-notice');
				var testDetails = $('#test-details');
				
				console.log('DUPR: Test connection button clicked');
				
				button.prop('disabled', true).text('<?php echo esc_js( __( 'Testing...', 'pickleball-ratings' ) ); ?>');
				resultSpan.html('');
				successNotice.hide();
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'pickleball_ratings_test_dupr_connection',
						nonce: '<?php echo esc_js( wp_create_nonce( 'pickleball_ratings_test_dupr_connection' ) ); ?>'
					},
					success: function(response) {
						console.log('DUPR: AJAX success response:', response);
						if (response.success) {
							// Show dismissible success notice (like WordPress settings pages)
							successNotice.show();
						} else {
							resultSpan.html('<span style="color: red;">✗ ' + response.data + '</span>');
						}
					},
					error: function(xhr, status, error) {
						console.log('DUPR: AJAX error:', xhr, status, error);
						var errorMessage = '<?php echo esc_js( __( 'Connection test failed', 'pickleball-ratings' ) ); ?>';
						if (xhr.responseJSON && xhr.responseJSON.data) {
							errorMessage = xhr.responseJSON.data;
						}
						resultSpan.html('<span style="color: red;">✗ ' + errorMessage + '</span>');
					},
					complete: function() {
						console.log('DUPR: AJAX complete');
						button.prop('disabled', false).text('<?php echo esc_js( __( 'Test Connection', 'pickleball-ratings' ) ); ?>');
					}
				});
			});

			// Handle dismissible notice close button
			$(document).on('click', '#test-success-notice .notice-dismiss', function() {
				$('#test-success-notice').fadeOut();
			});
		});
		</script>
			<?php
		} catch ( Exception $e ) {
			echo '<div class="notice notice-error"><p>Error loading settings: ' . esc_html( $e->getMessage() ) . '</p></div>';
		}
	}

	/**
	 * Handle form submission.
	 */
	private function handle_form_submission() {
		// This method is called when the main settings form is submitted.
		// Cache clearing is now handled in the main settings_page method.
	}

	/**
	 * Handle authentication when settings are saved.
	 *
	 * @param string $email    DUPR account email address.
	 * @param string $password DUPR account password.
	 * @return void
	 */
	private function handle_authentication( $email, $password ) {
		// Attempt to authenticate with DUPR API.
		$auth_data = $this->api->authenticate( $email, $password );

		if ( is_wp_error( $auth_data ) ) {
			add_settings_error(
				'pickleball_ratings_settings',
				'auth_error',
				$auth_data->get_error_message(),
				'error'
			);
		} else {
			add_settings_error(
				'pickleball_ratings_settings',
				'auth_success',
				sprintf(
					/* translators: 1: user name, 2: DUPR ID */
					__( 'Authentication successful! Connected as %1$s (DUPR ID: %2$s)', 'pickleball-ratings' ),
					esc_html( $auth_data['user_name'] ),
					esc_html( $auth_data['dupr_id'] )
				),
				'success'
			);
		}
	}



	/**
	 * Handle disconnect from DUPR.
	 */
	private function handle_disconnect() {
		$this->api->disconnect();

		add_settings_error(
			'pickleball_ratings_settings',
			'disconnect_success',
			__( 'Successfully disconnected from DUPR API. All authentication data has been removed.', 'pickleball-ratings' ),
			'success'
		);
	}

	/**
	 * Admin notices.
	 */
	public function admin_notices() {
		settings_errors( 'pickleball_ratings_settings' );
	}
}
