<?php
/**
 * Admin Settings Class
 *
 * Handles the admin settings page for DUPR API configuration.
 *
 * @package Dupr_Rating
 * @since 0.2.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Settings Class
 */
class DUPR_Admin_Settings {

	/**
	 * API instance
	 *
	 * @var DUPR_API
	 */
	private $api;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Ensure the API class is available
		if ( ! class_exists( 'DUPR_API' ) ) {
			require_once DUPR_RATING_PLUGIN_DIR . 'includes/class-dupr-api.php';
		}
		
		$this->api = new DUPR_API();
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'init_settings' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'DUPR Rating Settings', 'dupr-rating' ),
			__( 'DUPR Rating', 'dupr-rating' ),
			'manage_options',
			'dupr-rating-settings',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Initialize settings
	 */
	public function init_settings() {
		register_setting(
			'dupr_rating_settings',
			'dupr_rating_cache_ttl',
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( $this, 'sanitize_cache_ttl' ),
				'default'           => 86400,
			)
		);

		add_settings_section(
			'dupr_rating_cache_section',
			__( 'Cache Configuration', 'dupr-rating' ),
			array( $this, 'cache_section_callback' ),
			'dupr_rating_settings'
		);

		add_settings_field(
			'dupr_rating_cache_ttl',
			__( 'Cache Duration (hours)', 'dupr-rating' ),
			array( $this, 'cache_ttl_field_callback' ),
			'dupr_rating_settings',
			'dupr_rating_cache_section'
		);
	}

	/**
	 * Cache section callback
	 */
	public function cache_section_callback() {
		echo '<p>' . __( 'Configure caching settings for DUPR player data.', 'dupr-rating' ) . '</p>';
	}

	/**
	 * Cache TTL field callback
	 */
	public function cache_ttl_field_callback() {
		$ttl = get_option( 'dupr_rating_cache_ttl', 86400 );
		$hours = intval( $ttl / 3600 );
		echo '<input type="number" id="dupr_rating_cache_ttl" name="dupr_rating_cache_ttl" value="' . esc_attr( $hours ) . '" min="1" max="168" class="small-text" />';
		echo '<p class="description">' . __( 'How long to cache player data (1-168 hours).', 'dupr-rating' ) . '</p>';
	}

	/**
	 * Sanitize password (store encrypted)
	 */
	public function sanitize_password( $value ) {
		// Don't store password in plain text - we'll use it for login then discard
		return '';
	}

	/**
	 * Sanitize cache TTL (convert hours to seconds)
	 */
	public function sanitize_cache_ttl( $value ) {
		$hours = absint( $value );
		
		// Ensure minimum and maximum values
		if ( $hours < 1 ) {
			$hours = 1;
		} elseif ( $hours > 168 ) {
			$hours = 168;
		}
		
		// Convert hours to seconds
		return $hours * 3600;
	}

	/**
	 * Settings page
	 */
	public function settings_page() {
		try {
			// Handle form submissions
			if ( isset( $_POST['submit'] ) ) {
				$this->handle_form_submission();
			}

					// Handle authentication
		if ( isset( $_POST['dupr_connect'] ) && wp_verify_nonce( $_POST['dupr_auth_nonce'], 'dupr_authenticate' ) ) {
			$this->handle_authentication();
		}

		// Handle disconnect
		if ( isset( $_POST['dupr_disconnect'] ) && wp_verify_nonce( $_POST['dupr_disconnect_nonce'], 'dupr_disconnect' ) ) {
			$this->handle_disconnect();
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'DUPR Rating Settings', 'dupr-rating' ); ?></h1>
			
			<!-- Authentication Section -->
			<h2><?php esc_html_e( 'DUPR API Authentication', 'dupr-rating' ); ?></h2>
			<p><?php esc_html_e( 'Connect to your DUPR account to enable API access.', 'dupr-rating' ); ?></p>
			
				<?php
				$token = get_option( 'dupr_rating_auth_token', '' );
				$user_name = get_option( 'dupr_rating_auth_user_name', '' );
				$dupr_id = get_option( 'dupr_rating_auth_dupr_id', '' );
				
				error_log( 'DUPR: Displaying settings - token: ' . ( ! empty( $token ) ? 'present' : 'empty' ) . ', user_name: ' . $user_name . ', dupr_id: ' . $dupr_id );
				
				if ( empty( $token ) || empty( $user_name ) ) : ?>
					<!-- Authentication Form (only show when not connected) -->
					<form method="post" action="">
						<?php wp_nonce_field( 'dupr_authenticate', 'dupr_auth_nonce' ); ?>
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="dupr_rating_auth_email"><?php esc_html_e( 'Email Address', 'dupr-rating' ); ?></label>
								</th>
								<td>
									<input type="email" id="dupr_rating_auth_email" name="dupr_rating_auth_email" 
										   value="<?php echo esc_attr( get_option( 'dupr_rating_auth_email', '' ) ); ?>" 
										   class="regular-text" required />
									<p class="description"><?php esc_html_e( 'Enter your DUPR account email address.', 'dupr-rating' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="dupr_rating_auth_password"><?php esc_html_e( 'Password', 'dupr-rating' ); ?></label>
								</th>
								<td>
									<input type="password" id="dupr_rating_auth_password" name="dupr_rating_auth_password" 
										   class="regular-text" required />
									<p class="description"><?php esc_html_e( 'Enter your DUPR account password.', 'dupr-rating' ); ?></p>
								</td>
							</tr>
						</table>
						
						<p class="submit">
							<input type="submit" name="dupr_connect" class="button button-primary" 
								   value="<?php esc_attr_e( 'Connect to DUPR', 'dupr-rating' ); ?>" />
						</p>
					</form>
				<?php else : ?>
					<!-- Connected Status Display -->
					<div class="notice notice-success inline">
						<p><strong><?php esc_html_e( 'Connected as:', 'dupr-rating' ); ?></strong> <?php echo esc_html( $user_name ); ?><?php if ( ! empty( $dupr_id ) ) : ?> (DUPR ID: <?php echo esc_html( $dupr_id ); ?>)<?php endif; ?></p>
					</div>
					
					<p>
						<button type="button" class="button" id="test-connection"><?php esc_html_e( 'Test Connection', 'dupr-rating' ); ?></button>
						<span id="test-result"></span>
					</p>
					
					<!-- Disconnect Form -->
					<form method="post" style="margin-top: 10px;">
						<?php wp_nonce_field( 'dupr_disconnect', 'dupr_disconnect_nonce' ); ?>
						<input type="submit" name="dupr_disconnect" class="button button-secondary" 
							   value="<?php esc_attr_e( 'Disconnect from DUPR', 'dupr-rating' ); ?>" 
							   onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to disconnect from DUPR? This will remove your authentication and you will need to reconnect to use the plugin.', 'dupr-rating' ); ?>')" />
					</form>
				<?php endif; ?>

			<hr>

			<!-- Settings Section -->
			<!--<h2><?php esc_html_e( 'Plugin Settings', 'dupr-rating' ); ?></h2>-->
			<form method="post" action="options.php">
				<?php
				settings_fields( 'dupr_rating_settings' );
				do_settings_sections( 'dupr_rating_settings' );
				submit_button();
				?>
			</form>

			<?php if ( get_option( 'dupr_rating_auth_token' ) ) : ?>
				<hr>

				<h2><?php esc_html_e( 'Cache Management', 'dupr-rating' ); ?></h2>
				<p><?php esc_html_e( 'Clear cached player data to force fresh data from the DUPR API.', 'dupr-rating' ); ?></p>
				<form method="post">
					<?php wp_nonce_field( 'dupr_clear_cache', 'dupr_cache_nonce' ); ?>
					<input type="submit" 
						   name="clear_cache" 
						   class="button button-secondary" 
						   value="<?php esc_attr_e( 'Clear Cache', 'dupr-rating' ); ?>" 
						   onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to clear all cached data?', 'dupr-rating' ); ?>')" />
				</form>

				<hr>

				<h2><?php esc_html_e( 'Example DUPR IDs', 'dupr-rating' ); ?></h2>
				<p><?php esc_html_e( 'Use these IDs to test the plugin:', 'dupr-rating' ); ?></p>
				<ul>
					<li><strong>JW Johnson:</strong> <code>8WZ4ML</code> (high reliability, lots of history)</li>
					<li><strong>Test Player:</strong> <code>PW24RQ</code> (low reliability, minimal history)</li>
				</ul>
			<?php endif; ?>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('#test-connection').on('click', function() {
				var button = $(this);
				var resultSpan = $('#test-result');
				
				console.log('DUPR: Test connection button clicked');
				
				button.prop('disabled', true).text('<?php echo esc_js( __( 'Testing...', 'dupr-rating' ) ); ?>');
				resultSpan.html('');
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'dupr_test_connection',
						nonce: '<?php echo wp_create_nonce( 'dupr_test_connection' ); ?>'
					},
					success: function(response) {
						console.log('DUPR: AJAX success response:', response);
						if (response.success) {
							resultSpan.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
							if (response.data.data) {
								resultSpan.append(' <small>' + response.data.data.name + ' (DUPR ID: ' + response.data.data.dupr_id + ')</small>');
							}
						} else {
							resultSpan.html('<span style="color: red;">✗ ' + response.data + '</span>');
						}
					},
					error: function(xhr, status, error) {
						console.log('DUPR: AJAX error:', xhr, status, error);
						var errorMessage = '<?php echo esc_js( __( 'Connection test failed', 'dupr-rating' ) ); ?>';
						if (xhr.responseJSON && xhr.responseJSON.data) {
							errorMessage = xhr.responseJSON.data;
						}
						resultSpan.html('<span style="color: red;">✗ ' + errorMessage + '</span>');
					},
					complete: function() {
						console.log('DUPR: AJAX complete');
						button.prop('disabled', false).text('<?php echo esc_js( __( 'Test Connection', 'dupr-rating' ) ); ?>');
					}
				});
			});
		});
		</script>
		<?php
		} catch ( Exception $e ) {
			echo '<div class="notice notice-error"><p>Error loading settings: ' . esc_html( $e->getMessage() ) . '</p></div>';
		}
	}

	/**
	 * Handle form submission
	 */
	private function handle_form_submission() {
		// Handle cache clearing
		if ( isset( $_POST['clear_cache'] ) && wp_verify_nonce( $_POST['dupr_cache_nonce'], 'dupr_clear_cache' ) ) {
			$this->api->clear_cache();
			add_settings_error(
				'dupr_rating_settings',
				'cache_cleared',
				__( 'Cache cleared successfully.', 'dupr-rating' ),
				'success'
			);
		}
	}

	/**
	 * Handle authentication when settings are saved
	 */
	private function handle_authentication() {
		error_log( 'DUPR: Authentication attempt started' );
		
		$email = sanitize_email( $_POST['dupr_rating_auth_email'] ?? '' );
		$password = $_POST['dupr_rating_auth_password'] ?? '';

		error_log( 'DUPR: Email: ' . $email . ', Password provided: ' . ( ! empty( $password ) ? 'yes' : 'no' ) );

		if ( empty( $email ) || empty( $password ) ) {
			error_log( 'DUPR: Email or password empty' );
			add_settings_error(
				'dupr_rating_settings',
				'auth_error',
				__( 'Email and password are required.', 'dupr-rating' ),
				'error'
			);
			return;
		}

		// Save email for future reference
		update_option( 'dupr_rating_auth_email', $email );

		// Attempt to authenticate with DUPR API
		error_log( 'DUPR: Attempting to authenticate with DUPR API' );
		$auth_data = $this->authenticate_with_dupr( $email, $password );

		if ( $auth_data && isset( $auth_data['token'] ) ) {
			error_log( 'DUPR: Authentication successful, token length: ' . strlen( $auth_data['token'] ) );
			error_log( 'DUPR: Saving dupr_id to database: ' . $auth_data['dupr_id'] );
			update_option( 'dupr_rating_auth_token', $auth_data['token'] );
			update_option( 'dupr_rating_auth_refresh_token', $auth_data['refresh_token'] );
			update_option( 'dupr_rating_auth_user_name', $auth_data['user_name'] );
			update_option( 'dupr_rating_auth_dupr_id', $auth_data['dupr_id'] );
			
			add_settings_error(
				'dupr_rating_settings',
				'auth_success',
				sprintf(
					__( 'Authentication successful! Connected as %s (DUPR ID: %s)', 'dupr-rating' ),
					esc_html( $auth_data['user_name'] ),
					esc_html( $auth_data['dupr_id'] )
				),
				'success'
			);
		} else {
			error_log( 'DUPR: Authentication failed - auth_data: ' . print_r( $auth_data, true ) );
			add_settings_error(
				'dupr_rating_settings',
				'auth_error',
				__( 'Authentication failed. Please check your email and password.', 'dupr-rating' ),
				'error'
			);
		}
	}

	/**
	 * Authenticate with DUPR API
	 */
	private function authenticate_with_dupr( $email, $password ) {
		$api_url = 'https://api.dupr.gg/auth/v3/login';
		
		error_log( 'DUPR: Making request to: ' . $api_url );
		
		$request_body = array(
			'email' => $email,
			'password' => $password,
		);
		
		error_log( 'DUPR: Request body: ' . json_encode( $request_body ) );
		
		$response = wp_remote_post( $api_url, array(
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body' => json_encode( $request_body ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			error_log( 'DUPR: Authentication error: ' . $response->get_error_message() );
			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		error_log( 'DUPR: Response status: ' . $status_code );
		error_log( 'DUPR: Response body: ' . $body );

		if ( $status_code !== 200 ) {
			error_log( 'DUPR: Authentication failed with status ' . $status_code . ': ' . $body );
			return false;
		}

		$data = json_decode( $body, true );

		if ( ! $data || ! isset( $data['result']['accessToken'] ) ) {
			error_log( 'DUPR: Invalid authentication response: ' . $body );
			return false;
		}

		// Extract user information
		$user = $data['result']['user'] ?? array();
		$user_name = $user['fullName'] ?? ( isset( $user['firstName'], $user['lastName'] ) ? $user['firstName'] . ' ' . $user['lastName'] : '' );
		$dupr_id = $user['referralCode'] ?? '';

		error_log( 'DUPR: User data keys: ' . implode( ', ', array_keys( $user ) ) );
		error_log( 'DUPR: referralCode value: ' . ( $user['referralCode'] ?? 'NOT SET' ) );
		error_log( 'DUPR: Extracted user info - name: ' . $user_name . ', DUPR ID: ' . $dupr_id );
		error_log( 'DUPR: Full user object: ' . json_encode( $user ) );

		return array(
			'token' => $data['result']['accessToken'],
			'refresh_token' => $data['result']['refreshToken'],
			'user_name' => $user_name,
			'dupr_id' => $dupr_id,
		);
	}

	/**
	 * Handle disconnect from DUPR
	 */
	private function handle_disconnect() {
		error_log( 'DUPR: Disconnect request received' );
		
		// Clear all authentication data
		delete_option( 'dupr_rating_auth_token' );
		delete_option( 'dupr_rating_auth_refresh_token' );
		delete_option( 'dupr_rating_auth_user_name' );
		delete_option( 'dupr_rating_auth_dupr_id' );
		
		// Clear any cached data
		if ( $this->api ) {
			$this->api->clear_cache();
		}
		
		error_log( 'DUPR: Disconnected from DUPR API' );
		
		add_settings_error(
			'dupr_rating_settings',
			'disconnect_success',
			__( 'Successfully disconnected from DUPR API. All authentication data has been removed.', 'dupr-rating' ),
			'success'
		);
	}

	/**
	 * Admin notices
	 */
	public function admin_notices() {
		settings_errors( 'dupr_rating_settings' );
	}
} 