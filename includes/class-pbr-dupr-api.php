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
	 * Authenticated user's name.
	 *
	 * @var string
	 */
	private $user_name = '';

	/**
	 * Authenticated user's email.
	 *
	 * @var string
	 */
	private $user_email = '';

	/**
	 * Authenticated user's DUPR ID.
	 *
	 * @var string
	 */
	private $user_dupr_id = '';

	/**
	 * Cache TTL in seconds (default 24 hours).
	 *
	 * @var int
	 */
	private $cache_ttl = 86400;

	/**
	 * Option name used to persist the most recent token refresh attempt.
	 *
	 * @var string
	 */
	const LAST_REFRESH_OPTION = 'pickleball_ratings_dupr_last_refresh_attempt';

	/**
	 * How long before the session expires to start warning administrators.
	 *
	 * DUPR refresh tokens last 90 days and cannot be extended, so recovering
	 * requires a manual re-login. A week is enough notice to act on.
	 *
	 * @var int
	 */
	const EXPIRY_WARNING_WINDOW = 7 * DAY_IN_SECONDS;

	/**
	 * Message shown whenever the DUPR session can no longer be renewed.
	 *
	 * @var string
	 */
	const SESSION_EXPIRED_MESSAGE = 'Your DUPR session has expired. Reconnect to DUPR in the plugin settings to resume updates.';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->auth_token    = get_option( 'pickleball_ratings_dupr_auth_token', '' );
		$this->refresh_token = get_option( 'pickleball_ratings_dupr_auth_refresh_token', '' );
		$this->user_name     = get_option( 'pickleball_ratings_dupr_auth_user_name', '' );
		$this->user_email    = get_option( 'pickleball_ratings_dupr_auth_email', '' );
		$this->user_dupr_id  = get_option( 'pickleball_ratings_dupr_auth_id', '' );
		$this->cache_ttl     = get_option( 'pickleball_ratings_cache_ttl', 86400 );
	}

	/**
	 * Get player data by DUPR ID.
	 *
	 * Implements stale-while-revalidate pattern: returns cached data if fresh,
	 * attempts to refresh stale data, and falls back to stale data if API fails.
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
			// Calculate cache age using last_updated timestamp. A missing or
			// unparseable timestamp is treated as indefinitely stale rather
			// than unusable, so the entry can still serve as a fallback.
			$last_updated_timestamp = empty( $cached_data['last_updated'] )
				? false
				: strtotime( $cached_data['last_updated'] );
			$is_fresh               = false !== $last_updated_timestamp
				&& ( time() - $last_updated_timestamp ) < $this->cache_ttl;

			if ( $is_fresh ) {
				// Cache is fresh, return it.
				pbr_log( 'Cache: fresh hit for DUPR ID ' . $dupr_id );
				return $cached_data;
			}

			// Cache is stale, try to refresh from API.
			pbr_log( 'Cache: stale data for DUPR ID ' . $dupr_id . ', attempting refresh' );

			// Check for a usable session before attempting refresh. An expired
			// session cannot be renewed, so calling the API would only burn two
			// requests per stale entry to arrive at the same stale fallback.
			if ( ! empty( $this->auth_token ) && ! $this->is_session_expired() ) {
				$player_data = $this->fetch_player_data( $dupr_id );

				if ( ! is_wp_error( $player_data ) ) {
					// Successfully refreshed, cache and return new data.
					$this->cache_player_data( $dupr_id, $player_data );
					pbr_log( 'Cache: successfully refreshed stale data for DUPR ID ' . $dupr_id );
					return $player_data;
				}

				// API fetch failed, fall back to stale data.
				pbr_log(
					'Cache: API refresh failed for DUPR ID ' . $dupr_id . ', using stale cache as fallback',
					array( 'error' => $player_data->get_error_message() )
				);
			}

			// Return stale data as fallback.
			return $cached_data;
		}

		// No cached data, must fetch from API.
		pbr_log( 'Cache: miss for DUPR ID ' . $dupr_id );

		// Check if we have authentication.
		if ( empty( $this->auth_token ) ) {
			return new WP_Error( 'no_auth', 'DUPR API authentication required. Please configure in plugin settings.' );
		}

		if ( $this->is_session_expired() ) {
			return new WP_Error( 'session_expired', self::SESSION_EXPIRED_MESSAGE );
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
				pbr_log( 'API: token expired during search; attempting refresh' );
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
					return new WP_Error( 'session_expired', self::SESSION_EXPIRED_MESSAGE );
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
				pbr_log( 'API: token expired during player fetch; attempting refresh' );
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
					return new WP_Error( 'session_expired', self::SESSION_EXPIRED_MESSAGE );
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
			'dupr_id'             => '',
			'name'                => '',
			'profile_image'       => '',
			'doubles_rating'      => 'NR',
			'singles_rating'      => 'NR',
			'doubles_reliability' => null,
			'singles_reliability' => null,
			'last_updated'        => current_time( 'mysql' ),
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

			// Doubles reliability score.
			if ( isset( $ratings['doublesReliabilityScore'] ) ) {
				$player_data['doubles_reliability'] = (int) $ratings['doublesReliabilityScore'];
			}

			// Singles reliability score.
			if ( isset( $ratings['singlesReliabilityScore'] ) ) {
				$player_data['singles_reliability'] = (int) $ratings['singlesReliabilityScore'];
			}
		}

		return $player_data;
	}

	/**
	 * Get cached player data.
	 *
	 * @param string $dupr_id The DUPR player ID.
	 * @return array|false Cached data or false if not found.
	 */
	private function get_cached_player_data( $dupr_id ) {
		$cache_key = 'pbr_dupr_player_' . $dupr_id;
		$cached    = get_option( $cache_key, false );

		// Defensive check: the entry must carry usable player data. A missing
		// last_updated timestamp does not disqualify it - freshness is decided
		// by the caller, and an undateable entry is simply always stale.
		if ( ! self::is_usable_cache_entry( $cached ) ) {
			if ( false !== $cached ) {
				pbr_log( 'Cache: invalid cached data structure for DUPR ID ' . $dupr_id );
			}
			return false;
		}

		return $cached;
	}

	/**
	 * Determine whether a cached value carries usable player data.
	 *
	 * @param mixed $cached Candidate cache value.
	 * @return bool True when the value can be rendered.
	 */
	private static function is_usable_cache_entry( $cached ) {
		if ( ! is_array( $cached ) ) {
			return false;
		}

		foreach ( array( 'name', 'doubles_rating', 'singles_rating' ) as $field ) {
			if ( ! empty( $cached[ $field ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Cache player data.
	 *
	 * @param string $dupr_id The DUPR player ID.
	 * @param array  $data    Player data to cache.
	 */
	private function cache_player_data( $dupr_id, $data ) {
		$cache_key = 'pbr_dupr_player_' . $dupr_id;
		update_option( $cache_key, $data, false ); // autoload=false for performance.
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
	 *
	 * Note: This method is deprecated and will be removed in a future version.
	 * Cached data now uses stable keys and will refresh automatically when stale.
	 */
	public function clear_cache() {
		// Method retained for backward compatibility but no longer performs any action.
		// Cache clearing functionality will be removed entirely in Phase 3.
	}


	/**
	 * Refresh access token using refresh token.
	 *
	 * @return bool|WP_Error True if successful, WP_Error on failure.
	 */
	private function refresh_access_token() {
		$url = $this->api_base_url . '/auth/v3/refresh';

		$attempt = array(
			'endpoint'          => $url,
			'method'            => 'GET',
			'request_headers'   => array( 'x-refresh-token' ),
			'token_fingerprint' => self::fingerprint( $this->refresh_token ),
		);

		if ( empty( $this->refresh_token ) ) {
			$attempt['outcome'] = 'no_refresh_token';
			$this->record_refresh_attempt( $attempt );
			return new WP_Error( 'no_refresh_token', 'No refresh token available' );
		}

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'x-refresh-token' => $this->refresh_token,
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			$attempt['outcome'] = 'transport_error';
			$attempt['error']   = $response->get_error_message();
			$this->record_refresh_attempt( $attempt );
			return new WP_Error( 'refresh_error', 'Failed to refresh token: ' . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		$attempt['status']        = $status_code;
		$attempt['response_body'] = self::redact( $body );

		if ( 200 !== $status_code ) {
			$attempt['outcome'] = 'http_error';
			$this->record_refresh_attempt( $attempt );
			pbr_log(
				'API: token refresh failed',
				array(
					'status' => $status_code,
					'body'   => $attempt['response_body'],
				)
			);
			return new WP_Error( 'refresh_error', 'Token refresh failed with status ' . $status_code );
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || ! isset( $data['result'], $data['status'] ) || 'SUCCESS' !== $data['status'] ) {
			$attempt['outcome']       = 'invalid_response';
			$attempt['response_keys'] = is_array( $data ) ? array_keys( $data ) : null;
			$this->record_refresh_attempt( $attempt );
			pbr_log( 'API: invalid refresh response', array( 'data' => $attempt['response_body'] ) );
			return new WP_Error( 'refresh_error', 'Invalid refresh response' );
		}

		// The result field is expected to be the new access token as a string.
		// Guard against other shapes so an unexpected response cannot overwrite
		// the stored token with an array or object.
		if ( ! is_string( $data['result'] ) || '' === $data['result'] ) {
			$attempt['outcome']     = 'unexpected_result_shape';
			$attempt['result_type'] = gettype( $data['result'] );
			if ( is_array( $data['result'] ) ) {
				$attempt['result_keys'] = array_keys( $data['result'] );
			}
			$this->record_refresh_attempt( $attempt );
			pbr_log( 'API: unexpected refresh result shape', array( 'type' => $attempt['result_type'] ) );
			return new WP_Error( 'refresh_error', 'Refresh response did not contain an access token string' );
		}

		$attempt['outcome'] = 'success';
		// Record whether DUPR returned a new refresh token, which would mean
		// refresh tokens rotate and the stored one is now stale.
		$attempt['response_contains_refresh_token'] = false !== stripos( $body, 'refreshToken' );
		$this->record_refresh_attempt( $attempt );

		// Update access token with the new token from the result field.
		$this->auth_token = $data['result'];

		// Save new access token to database.
		// Note: refresh token remains the same and is reused until it expires.
		update_option( 'pickleball_ratings_dupr_auth_token', $this->auth_token );

		pbr_log( 'API: token refresh successful' );

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
		pbr_log( 'Auth: attempt started' );

		pbr_log(
			'Auth: received credentials',
			array(
				'email_present'    => ! empty( $email ),
				'password_present' => ! empty( $password ),
			)
		);

		if ( empty( $email ) || empty( $password ) ) {
			pbr_log( 'Auth: missing email or password' );
			return new WP_Error( 'missing_credentials', 'Email and password are required.' );
		}

		$api_url = $this->api_base_url . '/auth/v3/login';

		pbr_log( 'Auth: making request', array( 'endpoint' => $api_url ) );

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
			pbr_log( 'Auth: request error', array( 'error' => $response->get_error_message() ) );
			return new WP_Error( 'api_error', 'Failed to connect to DUPR API: ' . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		pbr_log( 'Auth: response received', array( 'status' => $status_code ) );

		if ( 200 !== $status_code ) {
			pbr_log( 'Auth: authentication failed with status', array( 'status' => $status_code ) );
			return new WP_Error( 'auth_failed', 'Authentication failed. Please check your email and password.' );
		}

		$data = json_decode( $body, true );

		if ( ! $data || ! isset( $data['result']['accessToken'] ) ) {
			pbr_log( 'Auth: invalid response shape' );
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

		pbr_log( 'Auth: authentication successful' );

		return $auth_data;
	}

	/**
	 * Disconnect from DUPR API.
	 *
	 * Does not clear cached data on disconnect.
	 *
	 * @return bool True on success.
	 */
	public function disconnect() {
		pbr_log( 'Auth: disconnect requested' );

		// Clear all authentication data.
		$this->clear_auth_data();

		pbr_log( 'Auth: disconnected from DUPR API' );

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
			'user_name' => $this->user_name,
			'dupr_id'   => $this->user_dupr_id,
			'email'     => $this->user_email,
		);
	}

	/**
	 * Get complete authentication status and user information.
	 *
	 * @return array Authentication status with user info.
	 */
	public function get_auth_status() {
		$is_authenticated = $this->is_authenticated();
		$user_info        = $is_authenticated ? $this->get_user_info() : false;

		return array_merge(
			array(
				'authenticated' => $is_authenticated,
				'user_info'     => $user_info,
				'has_token'     => ! empty( $this->auth_token ),
				'has_refresh'   => ! empty( $this->refresh_token ),
			),
			$this->get_session_status()
		);
	}

	/**
	 * Read the expiry timestamp out of a JWT.
	 *
	 * @param mixed $token Token to inspect.
	 * @return int|null Unix timestamp, or null when the token carries no readable expiry.
	 */
	private static function get_token_expiry( $token ) {
		if ( ! is_string( $token ) || '' === $token ) {
			return null;
		}

		$segments = explode( '.', $token );
		if ( 3 !== count( $segments ) ) {
			return null;
		}

		$claims = self::decode_jwt_segment( $segments[1] );

		return isset( $claims['exp'] ) ? (int) $claims['exp'] : null;
	}

	/**
	 * Describe the lifetime of the current DUPR session.
	 *
	 * The session lasts as long as the refresh token: once that expires the
	 * access token cannot be renewed and only a fresh login will recover it.
	 * Expiry is read from the token locally, so this costs no API request.
	 *
	 * A token with no readable expiry is never reported as expired, so an
	 * opaque or unexpected token format cannot lock a working site out.
	 *
	 * @return array Session status.
	 */
	public function get_session_status() {
		$expires_at = self::get_token_expiry( $this->refresh_token );

		if ( null === $expires_at ) {
			return array(
				'session_expires_at'    => null,
				'session_expired'       => false,
				'session_expiring_soon' => false,
				'session_expiry_known'  => false,
			);
		}

		$remaining = $expires_at - time();

		return array(
			'session_expires_at'    => gmdate( 'c', $expires_at ),
			'session_expired'       => $remaining <= 0,
			'session_expiring_soon' => $remaining > 0 && $remaining <= self::EXPIRY_WARNING_WINDOW,
			'session_expiry_known'  => true,
			'session_expires_in'    => $remaining > 0 ? human_time_diff( time(), $expires_at ) : null,
		);
	}

	/**
	 * Whether the DUPR session has expired and requires a fresh login.
	 *
	 * @return bool True when the refresh token is known to have expired.
	 */
	public function is_session_expired() {
		$status = $this->get_session_status();

		return $status['session_expired'];
	}

	/**
	 * Get authentication user name.
	 *
	 * @return string User name or empty string if not authenticated.
	 */
	public function get_auth_user_name() {
		return $this->user_name;
	}

	/**
	 * Get authentication user email.
	 *
	 * @return string User email or empty string if not authenticated.
	 */
	public function get_auth_user_email() {
		return $this->user_email;
	}

	/**
	 * Get authentication DUPR ID.
	 *
	 * @return string DUPR ID or empty string if not authenticated.
	 */
	public function get_auth_dupr_id() {
		return $this->user_dupr_id;
	}

	/**
	 * Save authentication data to WordPress options.
	 *
	 * @param array $auth_data Authentication data array.
	 */
	private function save_auth_data( $auth_data ) {
		$this->auth_token    = $auth_data['token'];
		$this->refresh_token = $auth_data['refresh_token'];
		$this->user_name     = $auth_data['user_name'];
		$this->user_dupr_id  = $auth_data['dupr_id'];
		$this->user_email    = $auth_data['email'] ?? '';

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
		$this->user_name     = '';
		$this->user_email    = '';
		$this->user_dupr_id  = '';

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
				pbr_log( 'API: token expired during test; attempting refresh' );
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
					return new WP_Error( 'session_expired', self::SESSION_EXPIRED_MESSAGE );
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
		$user_info = $this->get_user_info();

		return array(
			'success' => true,
			'message' => 'API connection successful - token is valid',
			'data'    => array(
				'name'    => $user_info['user_name'] ?? '',
				'dupr_id' => $user_info['dupr_id'] ?? '',
			),
		);
	}

	/**
	 * Persist details of the most recent token refresh attempt.
	 *
	 * Stored so that refresh failures occurring outside the admin screen (for
	 * example during a front-end cache revalidation) remain visible in the
	 * diagnostics panel.
	 *
	 * @param array $attempt Attempt details.
	 */
	private function record_refresh_attempt( $attempt ) {
		$attempt['time'] = gmdate( 'c' );
		update_option( self::LAST_REFRESH_OPTION, $attempt, false );
	}

	/**
	 * Produce a short, non-reversible fingerprint of a secret.
	 *
	 * Allows comparing whether a token changed between attempts without
	 * exposing its value.
	 *
	 * @param mixed $secret The secret to fingerprint.
	 * @return string Fingerprint, or empty string when there is no secret.
	 */
	private static function fingerprint( $secret ) {
		if ( ! is_string( $secret ) || '' === $secret ) {
			return '';
		}
		return substr( hash( 'sha256', $secret ), 0, 12 );
	}

	/**
	 * Remove JWT-shaped strings from arbitrary text and truncate it.
	 *
	 * @param string $text  Text to redact.
	 * @param int    $limit Maximum length to retain.
	 * @return string Redacted text.
	 */
	private static function redact( $text, $limit = 1000 ) {
		$text = (string) $text;
		$text = preg_replace(
			'/eyJ[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+/',
			'[REDACTED_JWT]',
			$text
		);

		if ( strlen( $text ) > $limit ) {
			$text = substr( $text, 0, $limit ) . '… [truncated]';
		}

		return $text;
	}

	/**
	 * Decode a single base64url-encoded JWT segment.
	 *
	 * @param string $segment The segment to decode.
	 * @return array|null Decoded claims, or null if the segment is not valid JSON.
	 */
	private static function decode_jwt_segment( $segment ) {
		$padded  = strtr( $segment, '-_', '+/' );
		$padded .= str_repeat( '=', ( 4 - strlen( $padded ) % 4 ) % 4 );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$decoded = base64_decode( $padded, true );
		if ( false === $decoded ) {
			return null;
		}

		$data = json_decode( $decoded, true );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * Describe a stored token for diagnostic purposes.
	 *
	 * JWT header and payload claims are included because they are not secret -
	 * they are base64-encoded, not encrypted, and cannot be used to
	 * authenticate. The signed token string itself is only included when
	 * explicitly requested.
	 *
	 * @param mixed $token       The stored token value.
	 * @param bool  $include_raw Whether to include the raw token string.
	 * @return array Token description.
	 */
	private function describe_token( $token, $include_raw = false ) {
		$info = array(
			'present' => ! empty( $token ),
			'type'    => gettype( $token ),
		);

		if ( ! is_string( $token ) || '' === $token ) {
			// Surface the actual stored value when it is not a usable string,
			// since that itself is the defect worth seeing.
			$info['stored_value'] = is_scalar( $token ) ? (string) $token : wp_json_encode( $token );
			return $info;
		}

		$info['length']      = strlen( $token );
		$info['fingerprint'] = self::fingerprint( $token );

		$segments         = explode( '.', $token );
		$info['segments'] = count( $segments );
		$info['is_jwt']   = 3 === count( $segments );

		if ( $info['is_jwt'] ) {
			$info['jwt_header'] = self::decode_jwt_segment( $segments[0] );
			$claims             = self::decode_jwt_segment( $segments[1] );
			$info['jwt_claims'] = $claims;

			if ( isset( $claims['iat'] ) ) {
				$info['issued_at'] = gmdate( 'c', (int) $claims['iat'] );
			}

			if ( isset( $claims['exp'] ) ) {
				$expires              = (int) $claims['exp'];
				$now                  = time();
				$info['expires_at']   = gmdate( 'c', $expires );
				$info['expired']      = $expires <= $now;
				$info['expiry_human'] = $expires > $now
					? 'expires in ' . human_time_diff( $now, $expires )
					: 'expired ' . human_time_diff( $expires, $now ) . ' ago';
			} else {
				$info['expires_at'] = null;
				$info['note']       = 'JWT contains no exp claim';
			}
		}

		if ( $include_raw ) {
			$info['raw'] = $token;
		}

		return $info;
	}

	/**
	 * Build a diagnostic report about the current DUPR authentication state.
	 *
	 * Intended for display to administrators on the plugin settings screen.
	 * Token values are excluded unless raw tokens are explicitly requested.
	 *
	 * @param bool $include_raw_tokens Whether to include raw token strings.
	 * @return array Diagnostic report.
	 */
	public function get_diagnostics( $include_raw_tokens = false ) {
		$last_refresh = get_option( self::LAST_REFRESH_OPTION, null );

		return array(
			'generated_at'         => gmdate( 'c' ),
			'raw_tokens_included'  => (bool) $include_raw_tokens,
			'environment'          => array(
				'plugin_version' => PICKLEBALL_RATINGS_VERSION,
				'wp_version'     => get_bloginfo( 'version' ),
				'php_version'    => PHP_VERSION,
				'api_base_url'   => $this->api_base_url,
				'cache_ttl'      => $this->cache_ttl,
			),
			'auth_state'           => array(
				'reports_authenticated' => $this->is_authenticated(),
				'has_user_name'         => ! empty( $this->user_name ),
				'has_user_email'        => ! empty( $this->user_email ),
				'has_user_dupr_id'      => ! empty( $this->user_dupr_id ),
			),
			'access_token'         => $this->describe_token( $this->auth_token, $include_raw_tokens ),
			'refresh_token'        => $this->describe_token( $this->refresh_token, $include_raw_tokens ),
			'last_refresh_attempt' => $last_refresh,
			'cache'                => $this->get_cache_diagnostics(),
		);
	}

	/**
	 * Summarise a single cached player entry for the diagnostics report.
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $value       Stored value.
	 * @return array Summary.
	 */
	private function describe_cache_entry( $option_name, $value ) {
		$entry = array(
			'option_name' => $option_name,
			'dupr_id'     => ( is_array( $value ) && ! empty( $value['dupr_id'] ) ) ? $value['dupr_id'] : '',
			'value_type'  => gettype( $value ),
			'usable'      => self::is_usable_cache_entry( $value ),
		);

		if ( ! is_array( $value ) ) {
			return $entry;
		}

		$entry['has_name']     = ! empty( $value['name'] );
		$entry['last_updated'] = $value['last_updated'] ?? null;

		if ( ! empty( $value['last_updated'] ) ) {
			$timestamp = strtotime( $value['last_updated'] );
			if ( false !== $timestamp ) {
				$entry['age_human'] = human_time_diff( $timestamp, time() ) . ' old';
				$entry['is_fresh']  = ( time() - $timestamp ) < $this->cache_ttl;
			}
		}

		return $entry;
	}

	/**
	 * Report on cached player data held in the database.
	 *
	 * Cached entries are what block rendering falls back to when the DUPR API is
	 * unreachable, so their presence and age is the key thing to inspect.
	 *
	 * @return array Cache diagnostics.
	 */
	public function get_cache_diagnostics() {
		global $wpdb;

		$like = $wpdb->esc_like( 'pbr_dupr_player_' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$names = $wpdb->get_col(
			$wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like )
		);

		$entries = array();
		$usable  = 0;

		foreach ( (array) $names as $name ) {
			$entry = $this->describe_cache_entry( $name, get_option( $name, false ) );
			if ( $entry['usable'] ) {
				++$usable;
			}
			$entries[] = $entry;
		}

		return array(
			'cache_ttl'      => $this->cache_ttl,
			'entry_count'    => count( $entries ),
			'usable_entries' => $usable,
			'entries'        => $entries,
		);
	}

	/**
	 * Probe the DUPR refresh endpoint with several request shapes.
	 *
	 * The plugin's refresh call has not been confirmed against the live API, so
	 * this reports how DUPR responds to each candidate shape. It is read-only:
	 * no token returned by any variant is stored.
	 *
	 * @return array|WP_Error Probe results, or WP_Error if there is nothing to probe with.
	 */
	public function probe_refresh_endpoint() {
		if ( empty( $this->refresh_token ) ) {
			return new WP_Error( 'no_refresh_token', 'No refresh token is stored, so the refresh endpoint cannot be probed.' );
		}

		$url = $this->api_base_url . '/auth/v3/refresh';

		$variants = array(
			'get_refresh_header'             => array(
				'description' => 'GET with x-refresh-token header (current plugin behaviour)',
				'method'      => 'GET',
				'headers'     => array( 'x-refresh-token' => $this->refresh_token ),
			),
			'get_refresh_header_with_bearer' => array(
				'description' => 'GET with x-refresh-token header plus Authorization bearer',
				'method'      => 'GET',
				'headers'     => array(
					'x-refresh-token' => $this->refresh_token,
					'Authorization'   => 'Bearer ' . $this->auth_token,
				),
			),
			'post_refresh_header'            => array(
				'description' => 'POST with x-refresh-token header, no body',
				'method'      => 'POST',
				'headers'     => array( 'x-refresh-token' => $this->refresh_token ),
			),
			'post_json_body'                 => array(
				'description' => 'POST with JSON body {"refreshToken": "..."}',
				'method'      => 'POST',
				'headers'     => array( 'Content-Type' => 'application/json' ),
				'body'        => wp_json_encode( array( 'refreshToken' => $this->refresh_token ) ),
				'body_shape'  => 'json: {"refreshToken": "<token>"}',
			),
			'post_form_body'                 => array(
				'description' => 'POST with form-encoded refreshToken',
				'method'      => 'POST',
				'headers'     => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'        => 'refreshToken=' . rawurlencode( $this->refresh_token ),
				'body_shape'  => 'form: refreshToken=<token>',
			),
			'get_bearer_refresh_token'       => array(
				'description' => 'GET with the refresh token as the Authorization bearer',
				'method'      => 'GET',
				'headers'     => array( 'Authorization' => 'Bearer ' . $this->refresh_token ),
			),
		);

		$results = array();

		foreach ( $variants as $name => $variant ) {
			$args = array(
				'method'  => $variant['method'],
				'headers' => $variant['headers'],
				'timeout' => 30,
			);

			if ( isset( $variant['body'] ) ) {
				$args['body'] = $variant['body'];
			}

			$response = wp_remote_request( $url, $args );

			$result = array(
				'description'     => $variant['description'],
				'method'          => $variant['method'],
				'request_headers' => array_keys( $variant['headers'] ),
				'body_shape'      => $variant['body_shape'] ?? null,
			);

			if ( is_wp_error( $response ) ) {
				$result['transport_error'] = $response->get_error_message();
				$results[ $name ]          = $result;
				continue;
			}

			$status = wp_remote_retrieve_response_code( $response );
			$body   = wp_remote_retrieve_body( $response );
			$parsed = json_decode( $body, true );

			$result['status']           = $status;
			$result['response_body']    = self::redact( $body, 600 );
			$result['looks_successful'] = 200 === $status
				&& is_array( $parsed )
				&& isset( $parsed['status'], $parsed['result'] )
				&& 'SUCCESS' === $parsed['status'];
			$result['result_type']      = isset( $parsed['result'] ) ? gettype( $parsed['result'] ) : null;
			$result['result_keys']      = ( isset( $parsed['result'] ) && is_array( $parsed['result'] ) )
				? array_keys( $parsed['result'] )
				: null;

			$results[ $name ] = $result;
		}

		return array(
			'probed_at'         => gmdate( 'c' ),
			'endpoint'          => $url,
			'token_fingerprint' => self::fingerprint( $this->refresh_token ),
			'note'              => 'Read-only probe. No token returned by any variant was stored.',
			'variants'          => $results,
		);
	}
}
