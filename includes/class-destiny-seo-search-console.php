<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Destiny_SEO_Search_Console {

	private const OPTION_SETTINGS   = 'destiny_seo_google_settings';
	private const OPTION_CONNECTION = 'destiny_seo_google_connection';
	private const OPTION_PROPERTY   = 'destiny_seo_google_property';

	public static function init(): void {
		add_action( 'admin_post_destiny_seo_save_google_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_destiny_seo_connect_google', array( __CLASS__, 'connect' ) );
		add_action( 'admin_post_destiny_seo_google_callback', array( __CLASS__, 'callback' ) );
		add_action( 'admin_post_destiny_seo_disconnect_google', array( __CLASS__, 'disconnect' ) );
		add_action( 'admin_post_destiny_seo_save_property', array( __CLASS__, 'save_property' ) );
	}

	public static function get_redirect_uri(): string {
		return admin_url( 'admin-post.php?action=destiny_seo_google_callback' );
	}

	public static function get_settings(): array {
		$settings = get_option( self::OPTION_SETTINGS, array() );

		return wp_parse_args(
			is_array( $settings ) ? $settings : array(),
			array(
				'client_id'     => '',
				'client_secret' => '',
			)
		);
	}

	public static function has_credentials(): bool {
		$settings = self::get_settings();

		return '' !== trim( (string) $settings['client_id'] )
			&& '' !== trim( (string) $settings['client_secret'] );
	}

	public static function is_connected(): bool {
		$connection = get_option( self::OPTION_CONNECTION, array() );

		return is_array( $connection )
			&& ! empty( $connection['access_token'] )
			&& ! empty( $connection['refresh_token'] );
	}

	public static function is_ready(): bool {
		return self::is_connected() && '' !== self::get_selected_property();
	}

	public static function get_selected_property(): string {
		return (string) get_option( self::OPTION_PROPERTY, '' );
	}

	public static function save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to configure Google Search Console.', 'destiny-seo' ) );
		}

		check_admin_referer( 'destiny_seo_save_google_settings' );

		$current = self::get_settings();

		$client_id = isset( $_POST['client_id'] )
			? sanitize_text_field( wp_unslash( $_POST['client_id'] ) )
			: '';

		$client_secret = isset( $_POST['client_secret'] )
			? sanitize_text_field( wp_unslash( $_POST['client_secret'] ) )
			: '';

		if ( '' === $client_secret && ! empty( $current['client_secret'] ) ) {
			$client_secret = (string) $current['client_secret'];
		}

		update_option(
			self::OPTION_SETTINGS,
			array(
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
			),
			false
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'  => 'destiny-seo',
					'tab'   => 'search-console',
					'saved' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function connect(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to connect Google Search Console.', 'destiny-seo' ) );
		}

		check_admin_referer( 'destiny_seo_connect_google' );

		if ( ! self::has_credentials() ) {
			self::redirect_error( __( 'Save the Google Client ID and Client Secret first.', 'destiny-seo' ) );
		}

		$settings = self::get_settings();
		$state    = wp_create_nonce( 'destiny_seo_google_oauth_state' );

		set_transient(
			'destiny_seo_google_oauth_state_' . get_current_user_id(),
			$state,
			10 * MINUTE_IN_SECONDS
		);

		$url = add_query_arg(
			array(
				'client_id'              => $settings['client_id'],
				'redirect_uri'           => self::get_redirect_uri(),
				'response_type'          => 'code',
				'scope'                  => 'https://www.googleapis.com/auth/webmasters.readonly',
				'access_type'            => 'offline',
				'include_granted_scopes' => 'true',
				'prompt'                 => 'consent',
				'state'                  => $state,
			),
			'https://accounts.google.com/o/oauth2/v2/auth'
		);

		wp_redirect( esc_url_raw( $url ) );
		exit;
	}

	public static function callback(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to connect Google Search Console.', 'destiny-seo' ) );
		}

		$state    = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$expected = get_transient( 'destiny_seo_google_oauth_state_' . get_current_user_id() );

		delete_transient( 'destiny_seo_google_oauth_state_' . get_current_user_id() );

		if ( ! is_string( $expected ) || '' === $expected || ! hash_equals( $expected, $state ) ) {
			wp_die( esc_html__( 'Invalid OAuth state.', 'destiny-seo' ) );
		}

		if ( isset( $_GET['error'] ) ) {
			self::redirect_error(
				sanitize_text_field( wp_unslash( $_GET['error_description'] ?? $_GET['error'] ) )
			);
		}

		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';

		if ( '' === $code ) {
			self::redirect_error( __( 'Google did not return an authorisation code.', 'destiny-seo' ) );
		}

		$settings = self::get_settings();

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 20,
				'body'    => array(
					'code'          => $code,
					'client_id'     => $settings['client_id'],
					'client_secret' => $settings['client_secret'],
					'redirect_uri'  => self::get_redirect_uri(),
					'grant_type'    => 'authorization_code',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			self::redirect_error( $response->get_error_message() );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) || empty( $data['access_token'] ) ) {
			$message = is_array( $data ) && ! empty( $data['error_description'] )
				? (string) $data['error_description']
				: __( 'Google did not return a valid access token.', 'destiny-seo' );

			self::redirect_error( $message );
		}

		$existing = get_option( self::OPTION_CONNECTION, array() );

		update_option(
			self::OPTION_CONNECTION,
			array(
				'access_token'  => sanitize_text_field( (string) $data['access_token'] ),
				'refresh_token' => sanitize_text_field(
					(string) ( $data['refresh_token'] ?? ( is_array( $existing ) ? ( $existing['refresh_token'] ?? '' ) : '' ) )
				),
				'expires_at'    => time() + absint( $data['expires_in'] ?? 3600 ) - 60,
			),
			false
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'destiny-seo',
					'tab'       => 'search-console',
					'connected' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function disconnect(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to disconnect Google Search Console.', 'destiny-seo' ) );
		}

		check_admin_referer( 'destiny_seo_disconnect_google' );

		delete_option( self::OPTION_CONNECTION );
		delete_option( self::OPTION_PROPERTY );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'destiny-seo',
					'tab'  => 'search-console',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function save_property(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to configure Google Search Console.', 'destiny-seo' ) );
		}

		check_admin_referer( 'destiny_seo_save_property' );

		if ( ! self::is_connected() ) {
			self::redirect_error( __( 'Connect Google Search Console first.', 'destiny-seo' ) );
		}

		$property = isset( $_POST['property'] )
			? sanitize_text_field( wp_unslash( $_POST['property'] ) )
			: '';

		update_option( self::OPTION_PROPERTY, $property, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'           => 'destiny-seo',
					'tab'            => 'search-console',
					'property_saved' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private static function get_access_token() {
		if ( ! self::is_connected() ) {
			return new WP_Error(
				'destiny_seo_not_connected',
				__( 'Google Search Console is not connected.', 'destiny-seo' )
			);
		}

		$connection = get_option( self::OPTION_CONNECTION, array() );

		if ( ! is_array( $connection ) ) {
			return new WP_Error( 'destiny_seo_invalid_connection', __( 'Google connection data is invalid.', 'destiny-seo' ) );
		}

		if ( ! empty( $connection['expires_at'] ) && time() < absint( $connection['expires_at'] ) ) {
			return (string) $connection['access_token'];
		}

		if ( empty( $connection['refresh_token'] ) ) {
			return new WP_Error(
				'destiny_seo_refresh_missing',
				__( 'Google access expired. Reconnect Search Console.', 'destiny-seo' )
			);
		}

		$settings = self::get_settings();

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 20,
				'body'    => array(
					'client_id'     => $settings['client_id'],
					'client_secret' => $settings['client_secret'],
					'refresh_token' => $connection['refresh_token'],
					'grant_type'    => 'refresh_token',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) || empty( $data['access_token'] ) ) {
			return new WP_Error(
				'destiny_seo_refresh_failed',
				__( 'Could not refresh Google access. Reconnect Search Console.', 'destiny-seo' )
			);
		}

		$connection['access_token'] = sanitize_text_field( (string) $data['access_token'] );
		$connection['expires_at']   = time() + absint( $data['expires_in'] ?? 3600 ) - 60;

		update_option( self::OPTION_CONNECTION, $connection, false );

		return (string) $connection['access_token'];
	}

	public static function get_properties() {
		$token = self::get_access_token();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$response = wp_remote_get(
			'https://www.googleapis.com/webmasters/v3/sites',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status < 200 || $status >= 300 ) {
			$message = is_array( $data ) && isset( $data['error']['message'] )
				? (string) $data['error']['message']
				: __( 'Could not load Search Console properties.', 'destiny-seo' );

			return new WP_Error( 'destiny_seo_properties_error', sanitize_text_field( $message ) );
		}

		return isset( $data['siteEntry'] ) && is_array( $data['siteEntry'] )
			? $data['siteEntry']
			: array();
	}

	public static function get_page_performance( string $page_url, int $days = 90 ) {
		if ( ! self::is_connected() ) {
			return new WP_Error(
				'destiny_seo_search_console_required',
				__( 'Google Search Console is not connected.', 'destiny-seo' )
			);
		}

		$property = self::get_selected_property();

		if ( '' === $property ) {
			return new WP_Error(
				'destiny_seo_property_required',
				__( 'Select a Google Search Console property first.', 'destiny-seo' )
			);
		}

		$token = self::get_access_token();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$days       = max( 1, min( 180, $days ) );
		$end_date   = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
		$start_date = gmdate( 'Y-m-d', strtotime( '-' . $days . ' days' ) );

		$request_body = array(
			'startDate'  => $start_date,
			'endDate'    => $end_date,
			'dimensions' => array( 'query' ),
			'rowLimit'   => 100,
			'dimensionFilterGroups' => array(
				array(
					'filters' => array(
						array(
							'dimension'  => 'page',
							'operator'   => 'equals',
							'expression' => $page_url,
						),
					),
				),
			),
		);

		$response = wp_remote_post(
			sprintf(
				'https://www.googleapis.com/webmasters/v3/sites/%s/searchAnalytics/query',
				rawurlencode( $property )
			),
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body' => wp_json_encode( $request_body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status < 200 || $status >= 300 ) {
			$message = is_array( $data ) && isset( $data['error']['message'] )
				? (string) $data['error']['message']
				: __( 'Search Console request failed.', 'destiny-seo' );

			return new WP_Error( 'destiny_seo_gsc_error', sanitize_text_field( $message ) );
		}

		$rows = array();

		foreach ( (array) ( $data['rows'] ?? array() ) as $row ) {
			$rows[] = array(
				'query'       => sanitize_text_field( (string) ( $row['keys'][0] ?? '' ) ),
				'clicks'      => (float) ( $row['clicks'] ?? 0 ),
				'impressions' => (float) ( $row['impressions'] ?? 0 ),
				'ctr'         => (float) ( $row['ctr'] ?? 0 ),
				'position'    => (float) ( $row['position'] ?? 0 ),
			);
		}

		$totals = array(
			'clicks'      => 0.0,
			'impressions' => 0.0,
			'ctr'         => 0.0,
			'position'    => 0.0,
		);

		foreach ( $rows as $row ) {
			$totals['clicks'] += $row['clicks'];
			$totals['impressions'] += $row['impressions'];
		}

		if ( $totals['impressions'] > 0 ) {
			$totals['ctr'] = $totals['clicks'] / $totals['impressions'];
		}

		if ( ! empty( $rows ) ) {
			$positions = array_column( $rows, 'position' );
			$totals['position'] = array_sum( $positions ) / count( $positions );
		}

		return array(
			'property'   => $property,
			'page'       => esc_url_raw( $page_url ),
			'start_date' => $start_date,
			'end_date'   => $end_date,
			'totals'     => $totals,
			'rows'       => $rows,
		);
	}

	private static function redirect_error( string $message ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'              => 'destiny-seo',
					'tab'               => 'search-console',
					'destiny_seo_error' => rawurlencode( $message ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
