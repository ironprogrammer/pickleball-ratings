<?php
/**
 * DUPR API Integration Class.
 *
 * Handles authentication, data fetching, and caching for the DUPR API.
 *
 * @package Pickleball_Ratings
 * @since 0.2.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DUPR API Integration Class.
 */
class PBR_DUPR_API {

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private $api_base_url = 'https://api.dupr.gg';

	/**
	 * Authentication token.
	 *
	 * @var string
	 */
	private $auth_token = '';

	/**
	 * Refresh token.
	 *
	 * @var string
	 */
	private $refresh_token = '';

	/**
	 * Cache TTL in seconds (default 24 hours).
	 *
	 * @var int
	 */
	private $cache_ttl = 86400;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->auth_token    = get_option( 'pickleball_ratings_dupr_auth_token', '' );
		$this->refresh_token = get_option( 'pickleball_ratings_dupr_auth_refresh_token', '' );
		$this->cache_ttl     = get_option( 'pickleball_ratings_cache_ttl', 86400 );
	}

	/**
	 * Get player data by DUPR ID.
	 *
	 * @param string $dupr_id The DUPR player ID.
	 * @return array|WP_Error Player data or error.
	 */
	public function get_player_data( $dupr_id ) {
		// Sanitize the DUPR ID.
		$dupr_id = sanitize_text_field( $dupr_id );

		if ( ! $this->is_valid_dupr_id( $dupr_id ) ) {
			return new WP_Error( 'invalid_dupr_id', 'Invalid DUPR ID format' );
		}

		// Check cache first.
		$cached_data = $this->get_cached_player_data( $dupr_id );
		if ( false !== $cached_data ) {
			return $cached_data;
		}

		// Check if we have authentication.
		if ( empty( $this->auth_token ) ) {
			return new WP_Error( 'no_auth', 'DUPR API authentication required. Please configure in plugin settings.' );
		}

		// Fetch data from API using the correct flow.
		$player_data = $this->fetch_player_data( $dupr_id );

		if ( is_wp_error( $player_data ) ) {
			return $player_data;
		}

		// Cache the data.
		$this->cache_player_data( $dupr_id, $player_data );

		return $player_data;
	}

	/**
	 * Fetch player data from DUPR API.
	 *
	 * @param string $dupr_id The DUPR player ID.
	 * @return array|WP_Error Player data or error.
	 */
	private function fetch_player_data( $dupr_id ) {
		// Step 1: Search by DUPR ID to get the internal user ID.
		$search_url = $this->api_base_url . '/player/search/byDuprId';

		$search_response = wp_remote_post(
			$search_url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->auth_token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'duprId' => $dupr_id,
					)
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $search_response ) ) {
			return new WP_Error( 'api_error', 'Failed to search for player: ' . $search_response->get_error_message() );
		}

		$search_status = wp_remote_retrieve_response_code( $search_response );
		$search_body   = wp_remote_retrieve_body( $search_response );

		if ( 200 !== $search_status ) {
			// Check if token is expired (401 status).
			if ( 401 === $search_status && ! empty( $this->refresh_token ) ) {
				if ( function_exists( 'pbr_log' ) ) {
					pbr_log( 'API: token expired during search; attempting refresh' );
				}
				$refresh_result = $this->refresh_access_token();

				if ( ! is_wp_error( $refresh_result ) ) {
					// Retry the search with new token.
					$search_response = wp_remote_post(
						$search_url,
						array(
							'headers' => array(
								'Authorization' => 'Bearer ' . $this->auth_token,
								'Content-Type'  => 'application/json',
							),
							'body'    => wp_json_encode(
								array(
									'duprId' => $dupr_id,
								)
							),
							'timeout' => 30,
						)
					);

					if ( ! is_wp_error( $search_response ) ) {
						$search_status = wp_remote_retrieve_response_code( $search_response );
						$search_body   = wp_remote_retrieve_body( $search_response );

						if ( 200 !== $search_status ) {
							return new WP_Error( 'api_error', 'Player search failed after token refresh (HTTP ' . $search_status . ')' );
						}
					} else {
						return new WP_Error( 'api_error', 'Failed to retry search after token refresh: ' . $search_response->get_error_message() );
					}
				} else {
					return new WP_Error( 'auth_error', 'Token expired and refresh failed: ' . $refresh_result->get_error_message() );
				}
			} else {
				return new WP_Error( 'api_error', 'Player search failed (HTTP ' . $search_status . ')' );
			}
		}

		$search_data = json_decode( $search_body, true );
		if ( ! $search_data || ! isset( $search_data['results'] ) || empty( $search_data['results'] ) ) {
			return new WP_Error( 'player_not_found', 'Player not found or invalid DUPR ID' );
		}

		// Get the internal user ID from the search results.
		$user_id = $search_data['results'][0]['userId'] ?? null;
		if ( ! $user_id ) {
			return new WP_Error( 'player_not_found', 'Could not retrieve player ID' );
		}

		// Step 2: Fetch player data using the internal user ID.
		$player_url = $this->api_base_url . '/player/v3/' . $user_id;

		$response = wp_remote_get(
			$player_url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->auth_token,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'api_error', 'Failed to fetch player data: ' . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( 200 !== $status_code ) {
			// Check if token is expired (401 status).
			if ( 401 === $status_code && ! empty( $this->refresh_token ) ) {
				if ( function_exists( 'pbr_log' ) ) {
					pbr_log( 'API: token expired during player fetch; attempting refresh' );
				}
				$refresh_result = $this->refresh_access_token();

				if ( ! is_wp_error( $refresh_result ) ) {
					// Retry the request with new token.
					$response = wp_remote_get(
						$player_url,
						array(
							'headers' => array(
								'Authorization' => 'Bearer ' . $this->auth_token,
								'Content-Type'  => 'application/json',
							),
							'timeout' => 30,
						)
					);

					if ( ! is_wp_error( $response ) ) {
						$status_code = wp_remote_retrieve_response_code( $response );
						$body        = wp_remote_retrieve_body( $response );

						if ( 200 !== $status_code ) {
							$error_message = 'DUPR API error after token refresh (HTTP ' . $status_code . ')';
							$error_data    = json_decode( $body, true );
							if ( $error_data && isset( $error_data['message'] ) ) {
								$error_message .= ': ' . $error_data['message'];
							}
							return new WP_Error( 'api_error', $error_message );
						}
					} else {
						return new WP_Error( 'api_error', 'Failed to retry player fetch after token refresh: ' . $response->get_error_message() );
					}
				} else {
					return new WP_Error( 'auth_error', 'Token expired and refresh failed: ' . $refresh_result->get_error_message() );
				}
			} else {
				$error_message = 'DUPR API error (HTTP ' . $status_code . ')';

				// Try to parse error response.
				$error_data = json_decode( $body, true );
				if ( $error_data && isset( $error_data['message'] ) ) {
					$error_message .= ': ' . $error_data['message'];
				}

				return new WP_Error( 'api_error', $error_message );
			}
		}

		$data = json_decode( $body, true );
		if ( ! $data || ! isset( $data['result'] ) ) {
			return new WP_Error( 'parse_error', 'Failed to parse DUPR API response' );
		}

		// Parse and structure the player data.
		return $this->parse_player_data( $data['result'] );
	}

	/**
	 * Parse and structure player data from API response.
	 *
	 * @param array $api_data Raw API response data.
	 * @return array Structured player data.
	 */
	private function parse_player_data( $api_data ) {
		$player_data = array(
			'dupr_id'        => '',
			'name'           => '',
			'profile_image'  => '',
			'doubles_rating' => 'NR',
			'singles_rating' => 'NR',
			'last_updated'   => current_time( 'mysql' ),
		);

		// Extract basic player info.
		if ( isset( $api_data['duprId'] ) ) {
			$player_data['dupr_id'] = sanitize_text_field( $api_data['duprId'] );
		}

		if ( isset( $api_data['fullName'] ) ) {
			$player_data['name'] = sanitize_text_field( $api_data['fullName'] );
		} elseif ( isset( $api_data['firstName'] ) && isset( $api_data['lastName'] ) ) {
			$player_data['name'] = sanitize_text_field( $api_data['firstName'] . ' ' . $api_data['lastName'] );
		}

		if ( isset( $api_data['imageUrl'] ) ) {
			$player_data['profile_image'] = esc_url_raw( $api_data['imageUrl'] );
		}

		// Extract ratings from ratings object.
		if ( isset( $api_data['ratings'] ) ) {
			$ratings = $api_data['ratings'];

			// Doubles rating.
			if ( isset( $ratings['doubles'] ) && 'NR' !== $ratings['doubles'] ) {
				$player_data['doubles_rating'] = (string) $ratings['doubles'];
			}

			// Singles rating.
			if ( isset( $ratings['singles'] ) && 'NR' !== $ratings['singles'] ) {
				$player_data['singles_rating'] = (string) $ratings['singles'];
			}
		}

		return $player_data;
	}

	/**
	 * Get cached player data.
	 *
	 * @param string $dupr_id The DUPR player ID.
	 * @return array|false Cached data or false if not found/expired.
	 */
	private function get_cached_player_data( $dupr_id ) {
		$cache_key = 'pbr_dupr_player_' . $this->get_cache_salt() . '_' . $dupr_id;
		$cached    = get_transient( $cache_key );

		if ( false === $cached ) {
			return false;
		}

		// Check if cache is expired.
		$cache_time = get_option( '_transient_timeout_' . $cache_key, 0 );
		if ( $cache_time < time() ) {
			delete_transient( $cache_key );
			return false;
		}

		return $cached;
	}

	/**
	 * Cache player data.
	 *
	 * @param string $dupr_id The DUPR player ID.
	 * @param array  $data    Player data to cache.
	 */
	private function cache_player_data( $dupr_id, $data ) {
		$cache_key = 'pbr_dupr_player_' . $this->get_cache_salt() . '_' . $dupr_id;
		set_transient( $cache_key, $data, $this->cache_ttl );
	}

	/**
	 * Validate DUPR ID format.
	 *
	 * @param string $dupr_id The DUPR ID to validate.
	 * @return bool True if valid, false otherwise.
	 */
	private function is_valid_dupr_id( $dupr_id ) {
		return preg_match( '/^[A-Z0-9]{6}$/', $dupr_id );
	}

	/**
	 * Set authentication token.
	 *
	 * @param string $token Authentication token.
	 */
	public function set_auth_token( $token ) {
		$this->auth_token = sanitize_text_field( $token );
		update_option( 'pickleball_ratings_dupr_auth_token', $this->auth_token );
	}

	/**
	 * Get authentication token.
	 *
	 * @return string Authentication token.
	 */
	public function get_auth_token() {
		return $this->auth_token;
	}

	/**
	 * Set cache TTL.
	 *
	 * @param int $ttl Cache TTL in seconds.
	 */
	public function set_cache_ttl( $ttl ) {
		$this->cache_ttl = absint( $ttl );
		update_option( 'pickleball_ratings_cache_ttl', $this->cache_ttl );
	}

	/**
	 * Get cache TTL.
	 *
	 * @return int Cache TTL in seconds.
	 */
	public function get_cache_ttl() {
		return $this->cache_ttl;
	}

	/**
	 * Clear all cached data.
	 */
	public function clear_cache() {
		// Invalidate all cached entries by bumping the salt; old keys will expire naturally.
		$this->bump_cache_salt();
	}

	/**
	 * Get the current cache salt used to namespace transients.
	 *
	 * @return string Cache salt value.
	 */
	private function get_cache_salt() {
		$salt = get_option( 'pickleball_ratings_cache_salt', '' );
		if ( empty( $salt ) ) {
			$salt = $this->generate_new_salt();
			update_option( 'pickleball_ratings_cache_salt', $salt, false );
		}
		return $salt;
	}

	/**
	 * Bump the cache salt to invalidate existing transients.
	 *
	 * @return void
	 */
	private function bump_cache_salt() {
		update_option( 'pickleball_ratings_cache_salt', $this->generate_new_salt(), false );
	}

	/**
	 * Generate a new cache salt string.
	 *
	 * @return string New cache salt.
	 */
	private function generate_new_salt() {
		return 'v' . wp_generate_password( 8, false, false );
	}

	/**
	 * Refresh access token using refresh token.
	 *
	 * @return bool|WP_Error True if successful, WP_Error on failure.
	 */
	private function refresh_access_token() {
		if ( empty( $this->refresh_token ) ) {
			return new WP_Error( 'no_refresh_token', 'No refresh token available' );
		}

		$url = $this->api_base_url . '/auth/v3/refresh';

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'refreshToken' => $this->refresh_token,
					)
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'refresh_error', 'Failed to refresh token: ' . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( 200 !== $status_code ) {
			return new WP_Error( 'refresh_error', 'Token refresh failed with status ' . $status_code );
		}

		$data = json_decode( $body, true );
		if ( ! $data || ! isset( $data['result']['accessToken'] ) ) {
			return new WP_Error( 'refresh_error', 'Invalid refresh response' );
		}

		// Update tokens.
		$this->auth_token    = $data['result']['accessToken'];
		$this->refresh_token = $data['result']['refreshToken'];

		// Save to database.
		update_option( 'pickleball_ratings_dupr_auth_token', $this->auth_token );
		update_option( 'pickleball_ratings_dupr_auth_refresh_token', $this->refresh_token );

		return true;
	}

	/**
	 * Authenticate with DUPR API using email and password.
	 *
	 * @param string $email    DUPR account email address.
	 * @param string $password DUPR account password.
	 * @return array|WP_Error Auth data array on success, WP_Error on failure.
	 */
	public function authenticate( $email, $password ) {
		if ( function_exists( 'pbr_log' ) ) {
			pbr_log( 'Auth: attempt started' );
		}

		if ( function_exists( 'pbr_log' ) ) {
			pbr_log(
				'Auth: received credentials',
				array(
					'email_present'    => ! empty( $email ),
					'password_present' => ! empty( $password ),
				)
			);
		}

		if ( empty( $email ) || empty( $password ) ) {
			if ( function_exists( 'pbr_log' ) ) {
				pbr_log( 'Auth: missing email or password' );
			}
			return new WP_Error( 'missing_credentials', 'Email and password are required.' );
		}

		$api_url = $this->api_base_url . '/auth/v3/login';

		if ( function_exists( 'pbr_log' ) ) {
			pbr_log( 'Auth: making request', array( 'endpoint' => $api_url ) );
		}

		$request_body = array(
			'email'    => $email,
			'password' => $password,
		);

		$response = wp_remote_post(
			$api_url,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $request_body ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			if ( function_exists( 'pbr_log' ) ) {
				pbr_log( 'Auth: request error', array( 'error' => $response->get_error_message() ) );
			}
			return new WP_Error( 'api_error', 'Failed to connect to DUPR API: ' . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( function_exists( 'pbr_log' ) ) {
			pbr_log( 'Auth: response received', array( 'status' => $status_code ) );
		}

		if ( 200 !== $status_code ) {
			if ( function_exists( 'pbr_log' ) ) {
				pbr_log( 'Auth: authentication failed with status', array( 'status' => $status_code ) );
			}
			return new WP_Error( 'auth_failed', 'Authentication failed. Please check your email and password.' );
		}

		$data = json_decode( $body, true );

		if ( ! $data || ! isset( $data['result']['accessToken'] ) ) {
			if ( function_exists( 'pbr_log' ) ) {
				pbr_log( 'Auth: invalid response shape' );
			}
			return new WP_Error( 'invalid_response', 'Invalid authentication response from DUPR API.' );
		}

		// Extract user information.
		$user      = $data['result']['user'] ?? array();
		$user_name = $user['fullName'] ?? ( isset( $user['firstName'], $user['lastName'] ) ? $user['firstName'] . ' ' . $user['lastName'] : '' );
		$dupr_id   = $user['referralCode'] ?? '';

		$auth_data = array(
			'token'         => $data['result']['accessToken'],
			'refresh_token' => $data['result']['refreshToken'],
			'user_name'     => $user_name,
			'dupr_id'       => $dupr_id,
			'email'         => $email,
		);

		// Save authentication data.
		$this->save_auth_data( $auth_data );

		if ( function_exists( 'pbr_log' ) ) {
			pbr_log( 'Auth: authentication successful' );
		}

		return $auth_data;
	}

	/**
	 * Disconnect from DUPR API.
	 *
	 * @return bool True on success.
	 */
	public function disconnect() {
		if ( function_exists( 'pbr_log' ) ) {
			pbr_log( 'Auth: disconnect requested' );
		}

		// Clear all authentication data.
		$this->clear_auth_data();

		// Clear cached data.
		$this->clear_cache();

		if ( function_exists( 'pbr_log' ) ) {
			pbr_log( 'Auth: disconnected from DUPR API' );
		}

		return true;
	}

	/**
	 * Check if user is authenticated.
	 *
	 * @return bool True if authenticated, false otherwise.
	 */
	public function is_authenticated() {
		return ! empty( $this->auth_token );
	}

	/**
	 * Get authenticated user information.
	 *
	 * @return array|false User info array or false if not authenticated.
	 */
	public function get_user_info() {
		if ( ! $this->is_authenticated() ) {
			return false;
		}

		return array(
			'user_name' => get_option( 'pickleball_ratings_dupr_auth_user_name', '' ),
			'dupr_id'   => get_option( 'pickleball_ratings_dupr_auth_id', '' ),
			'email'     => get_option( 'pickleball_ratings_dupr_auth_email', '' ),
		);
	}

	/**
	 * Save authentication data to WordPress options.
	 *
	 * @param array $auth_data Authentication data array.
	 */
	private function save_auth_data( $auth_data ) {
		$this->auth_token    = $auth_data['token'];
		$this->refresh_token = $auth_data['refresh_token'];

		update_option( 'pickleball_ratings_dupr_auth_token', $auth_data['token'] );
		update_option( 'pickleball_ratings_dupr_auth_refresh_token', $auth_data['refresh_token'] );
		update_option( 'pickleball_ratings_dupr_auth_user_name', $auth_data['user_name'] );
		update_option( 'pickleball_ratings_dupr_auth_id', $auth_data['dupr_id'] );
		
		if ( isset( $auth_data['email'] ) ) {
			update_option( 'pickleball_ratings_dupr_auth_email', $auth_data['email'] );
		}
	}

	/**
	 * Clear all authentication data from WordPress options.
	 */
	private function clear_auth_data() {
		$this->auth_token    = '';
		$this->refresh_token = '';

		delete_option( 'pickleball_ratings_dupr_auth_token' );
		delete_option( 'pickleball_ratings_dupr_auth_refresh_token' );
		delete_option( 'pickleball_ratings_dupr_auth_user_name' );
		delete_option( 'pickleball_ratings_dupr_auth_id' );
		delete_option( 'pickleball_ratings_dupr_auth_email' );
	}

	/**
	 * Test API connection.
	 *
	 * @return array|WP_Error Test result or error.
	 */
	public function test_connection() {
		if ( empty( $this->auth_token ) ) {
			return new WP_Error( 'no_auth', 'No authentication token configured' );
		}

		// Use the validate endpoint to test the connection.
		$url = $this->api_base_url . '/auth/v3/validate?code=DUPR100';

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->auth_token,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'api_error', 'Failed to connect to DUPR API: ' . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( 200 !== $status_code ) {
			// Check if token is expired (401 status).
			if ( 401 === $status_code && ! empty( $this->refresh_token ) ) {
				if ( function_exists( 'pbr_log' ) ) {
					pbr_log( 'API: token expired during test; attempting refresh' );
				}
				$refresh_result = $this->refresh_access_token();

				if ( ! is_wp_error( $refresh_result ) ) {
					// Retry the test with new token.
					$response = wp_remote_get(
						$url,
						array(
							'headers' => array(
								'Authorization' => 'Bearer ' . $this->auth_token,
								'Content-Type'  => 'application/json',
							),
							'timeout' => 30,
						)
					);

					if ( ! is_wp_error( $response ) ) {
						$status_code = wp_remote_retrieve_response_code( $response );
						$body        = wp_remote_retrieve_body( $response );
						if ( 200 !== $status_code ) {
							return new WP_Error( 'api_error', 'Token validation failed after refresh (HTTP ' . $status_code . ')' );
						}
					} else {
						return new WP_Error( 'api_error', 'Failed to retry validation after token refresh: ' . $response->get_error_message() );
					}
				} else {
					return new WP_Error( 'auth_error', 'Token expired and refresh failed: ' . $refresh_result->get_error_message() );
				}
			} else {
				return new WP_Error( 'api_error', 'Token validation failed (HTTP ' . $status_code . ')' );
			}
		}

		$data = json_decode( $body, true );
		if ( ! $data || ! isset( $data['status'] ) ) {
			return new WP_Error( 'parse_error', 'Failed to parse validation response' );
		}

		if ( 'SUCCESS' !== $data['status'] ) {
			return new WP_Error( 'validation_error', 'Token validation failed: ' . ( $data['message'] ?? 'Unknown error' ) );
		}

		// Get the authenticated user's data for display.
		$user_name = get_option( 'pickleball_ratings_dupr_auth_user_name', '' );
		$dupr_id   = get_option( 'pickleball_ratings_dupr_auth_id', '' );

		return array(
			'success' => true,
			'message' => 'API connection successful - token is valid',
			'data'    => array(
				'name'    => $user_name,
				'dupr_id' => $dupr_id,
			),
		);
	}
}
