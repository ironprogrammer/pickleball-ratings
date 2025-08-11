<?php
/**
 * Admin Settings Class
 *
 * Handles the admin settings page for DUPR API configuration.
 *
 * @package Pickleball_Ratings
 * @since 0.2.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Settings Class
 */
class PBR_Admin_Settings {

	/**
	 * API instance
	 *
     * @var PBR_DUPR_API
	 */
	private $api;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Ensure the API class is available
        if ( ! class_exists( 'PBR_DUPR_API' ) ) {
            require_once PICKLEBALL_RATINGS_PLUGIN_DIR . 'includes/class-dupr-api.php';
		}
		
        $this->api = new PBR_DUPR_API();
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'init_settings' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Add admin menu
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
	 * Initialize settings
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
	 * Cache section callback
	 */
	public function cache_section_callback() {
        echo '<p>' . esc_html__( 'Configure caching settings for DUPR player data.', 'pickleball-ratings' ) . '</p>';
	}

	/**
	 * Cache TTL field callback
	 */
	public function cache_ttl_field_callback() {
        $ttl = get_option( 'pickleball_ratings_cache_ttl', 86400 );
		$hours = intval( $ttl / 3600 );
        echo '<input type="number" id="pickleball_ratings_cache_ttl" name="pickleball_ratings_cache_ttl" value="' . esc_attr( $hours ) . '" min="1" max="168" class="small-text" />';
        echo '<p class="description">' . esc_html__( 'How long to cache player data (1-168 hours).', 'pickleball-ratings' ) . '</p>';
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

			// Handle cache clearing
            if ( isset( $_POST['clear_cache'] ) && wp_verify_nonce( $_POST['pbr_cache_nonce'], 'pbr_clear_cache' ) ) {
				$this->api->clear_cache();
				add_settings_error(
                    'pickleball_ratings_settings',
					'cache_cleared',
                    __( 'Cache cleared successfully.', 'pickleball-ratings' ),
					'success'
				);
			}

			// Handle authentication
            if ( isset( $_POST['pbr_connect'] ) && wp_verify_nonce( $_POST['pbr_auth_nonce'], 'pickleball_ratings_authenticate' ) ) {
				$this->handle_authentication();
			}

			// Handle disconnect
            if ( isset( $_POST['pbr_disconnect'] ) && wp_verify_nonce( $_POST['pbr_disconnect_nonce'], 'pickleball_ratings_disconnect' ) ) {
				$this->handle_disconnect();
			}

		?>
		<div class="wrap">
            <h1><?php esc_html_e( 'Pickleball Ratings Settings', 'pickleball-ratings' ); ?></h1>
			
			<!-- Authentication Section -->
            <h2><?php esc_html_e( 'DUPR API Authentication', 'pickleball-ratings' ); ?></h2>
            <p><?php esc_html_e( 'Connect to your DUPR account to enable API access.', 'pickleball-ratings' ); ?></p>
			
				<?php
                $token = get_option( 'pickleball_ratings_dupr_auth_token', '' );
                $user_name = get_option( 'pickleball_ratings_dupr_auth_user_name', '' );
                $dupr_id = get_option( 'pickleball_ratings_dupr_auth_id', '' );
				
				error_log( 'DUPR: Displaying settings - token: ' . ( ! empty( $token ) ? 'present' : 'empty' ) . ', user_name: ' . $user_name . ', dupr_id: ' . $dupr_id );
				
				if ( empty( $token ) || empty( $user_name ) ) : ?>
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
                                           value="<?php echo esc_attr( get_option( 'pickleball_ratings_dupr_auth_email', '' ) ); ?>" 
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
					<div class="notice notice-success inline">
                        <p><strong><?php esc_html_e( 'Connected as:', 'pickleball-ratings' ); ?></strong> <?php echo esc_html( $user_name ); ?><?php if ( ! empty( $dupr_id ) ) : ?> (DUPR ID: <?php echo esc_html( $dupr_id ); ?>)<?php endif; ?></p>
					</div>
					
					<p>
                        <button type="button" class="button" id="test-connection"><?php esc_html_e( 'Test Connection', 'pickleball-ratings' ); ?></button>
						<span id="test-result"></span>
					</p>
					
					<!-- Disconnect Form -->
					<form method="post" style="margin-top: 10px;">
                        <?php wp_nonce_field( 'pickleball_ratings_disconnect', 'pbr_disconnect_nonce' ); ?>
                        <input type="submit" name="pbr_disconnect" class="button button-secondary" 
                               value="<?php esc_attr_e( 'Disconnect from DUPR', 'pickleball-ratings' ); ?>" 
                               onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to disconnect from DUPR? This will remove your authentication and you will need to reconnect to use the plugin.', 'pickleball-ratings' ); ?>')" />
					</form>
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

            <?php if ( get_option( 'pickleball_ratings_dupr_auth_token' ) ) : ?>
				<hr>

                <h2><?php esc_html_e( 'Cache Management', 'pickleball-ratings' ); ?></h2>
                <p><?php esc_html_e( 'Clear cached player data to force fresh data from the DUPR API.', 'pickleball-ratings' ); ?></p>
				<form method="post">
                    <?php wp_nonce_field( 'pbr_clear_cache', 'pbr_cache_nonce' ); ?>
					<input type="submit" 
						   name="clear_cache" 
						   class="button button-secondary" 
                           value="<?php esc_attr_e( 'Clear Cache', 'pickleball-ratings' ); ?>" 
                           onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to clear all cached data?', 'pickleball-ratings' ); ?>')" />
				</form>

				<hr>

                <h2><?php esc_html_e( 'Example DUPR IDs', 'pickleball-ratings' ); ?></h2>
                <p><?php esc_html_e( 'Use these IDs to test the plugin:', 'pickleball-ratings' ); ?></p>
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
				
                button.prop('disabled', true).text('<?php echo esc_js( __( 'Testing...', 'pickleball-ratings' ) ); ?>');
				resultSpan.html('');
				
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
		// This method is called when the main settings form is submitted
		// Cache clearing is now handled in the main settings_page method
	}

	/**
	 * Handle authentication when settings are saved
	 */
	private function handle_authentication() {
		error_log( 'DUPR: Authentication attempt started' );
		
        $email = sanitize_email( $_POST['pickleball_ratings_dupr_auth_email'] ?? '' );
        $password = $_POST['pickleball_ratings_dupr_auth_password'] ?? '';

		error_log( 'DUPR: Email: ' . $email . ', Password provided: ' . ( ! empty( $password ) ? 'yes' : 'no' ) );

		if ( empty( $email ) || empty( $password ) ) {
			error_log( 'DUPR: Email or password empty' );
            add_settings_error(
                'pickleball_ratings_settings',
				'auth_error',
                __( 'Email and password are required.', 'pickleball-ratings' ),
				'error'
			);
			return;
		}

		// Save email for future reference
        update_option( 'pickleball_ratings_dupr_auth_email', $email );

		// Attempt to authenticate with DUPR API
		error_log( 'DUPR: Attempting to authenticate with DUPR API' );
		$auth_data = $this->authenticate_with_dupr( $email, $password );

		if ( $auth_data && isset( $auth_data['token'] ) ) {
			error_log( 'DUPR: Authentication successful, token length: ' . strlen( $auth_data['token'] ) );
			error_log( 'DUPR: Saving dupr_id to database: ' . $auth_data['dupr_id'] );
            update_option( 'pickleball_ratings_dupr_auth_token', $auth_data['token'] );
            update_option( 'pickleball_ratings_dupr_auth_refresh_token', $auth_data['refresh_token'] );
            update_option( 'pickleball_ratings_dupr_auth_user_name', $auth_data['user_name'] );
            update_option( 'pickleball_ratings_dupr_auth_id', $auth_data['dupr_id'] );
			
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
		} else {
			error_log( 'DUPR: Authentication failed - auth_data: ' . print_r( $auth_data, true ) );
            add_settings_error(
                'pickleball_ratings_settings',
				'auth_error',
                __( 'Authentication failed. Please check your email and password.', 'pickleball-ratings' ),
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
        delete_option( 'pickleball_ratings_dupr_auth_token' );
        delete_option( 'pickleball_ratings_dupr_auth_refresh_token' );
        delete_option( 'pickleball_ratings_dupr_auth_user_name' );
        delete_option( 'pickleball_ratings_dupr_auth_id' );
		
		// Clear any cached data
		if ( $this->api ) {
			$this->api->clear_cache();
		}
		
		error_log( 'DUPR: Disconnected from DUPR API' );
		
        add_settings_error(
            'pickleball_ratings_settings',
			'disconnect_success',
            __( 'Successfully disconnected from DUPR API. All authentication data has been removed.', 'pickleball-ratings' ),
			'success'
		);
	}

	/**
	 * Admin notices
	 */
	public function admin_notices() {
        settings_errors( 'pickleball_ratings_settings' );
	}
} 