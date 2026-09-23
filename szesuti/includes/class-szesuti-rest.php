<?php
/**
 * REST végpontok: hozzájárulás naplózása és geo-lekérdezés.
 *
 * Mindkét végpont nyilvános (a látogató nincs bejelentkezve), ezért
 * szigorú validáció és egyszerű sebességkorlátozás véd ellenük.
 *
 * @package SZESUTI
 */

defined( 'ABSPATH' ) || exit;

class SZESUTI_Rest {

	const NAMESPACE_V1 = 'szesuti/v1';

	/** @var SZESUTI_Settings */
	private $settings;

	/** @var SZESUTI_Log */
	private $log;

	/** @var SZESUTI_Geo */
	private $geo;

	public function __construct( SZESUTI_Settings $settings, SZESUTI_Log $log, SZESUTI_Geo $geo ) {
		$this->settings = $settings;
		$this->log      = $log;
		$this->geo      = $geo;

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/** Útvonalak regisztrálása. */
	public function register_routes() {
		if ( ! $this->settings->get( 'advanced.rest_enabled', 1 ) ) {
			return;
		}

		register_rest_route(
			self::NAMESPACE_V1,
			'/consent',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_consent' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'consent_id'     => array( 'type' => 'string' ),
					'action'         => array( 'type' => 'string' ),
					'categories'     => array( 'type' => 'array' ),
					'policy_version' => array( 'type' => 'string' ),
					'lang'           => array( 'type' => 'string' ),
					'url'            => array( 'type' => 'string' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/geo',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_geo' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Hozzájárulás naplózása.
	 *
	 * @param WP_REST_Request $request Kérés.
	 * @return WP_REST_Response
	 */
	public function handle_consent( WP_REST_Request $request ) {
		if ( ! $this->settings->get( 'log.enabled', 0 ) ) {
			return new WP_REST_Response( array( 'logged' => false ), 200 );
		}

		$ip = $this->client_ip();

		if ( $this->is_rate_limited( $ip ) ) {
			return new WP_REST_Response( array( 'logged' => false ), 429 );
		}

		$categories = $request->get_param( 'categories' );
		if ( ! is_array( $categories ) ) {
			$categories = array();
		}

		$entry = array(
			'consent_id'     => (string) $request->get_param( 'consent_id' ),
			'action'         => (string) $request->get_param( 'action' ),
			'categories'     => $categories,
			'policy_version' => (string) $request->get_param( 'policy_version' ),
			'lang'           => (string) $request->get_param( 'lang' ),
			'url'            => (string) $request->get_param( 'url' ),
			'country'        => $this->geo->country(),
			'ip'             => $ip,
			'user_agent'     => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
		);

		$id = $this->log->record( $entry );

		return new WP_REST_Response( array( 'logged' => (bool) $id ), 200 );
	}

	/**
	 * Geo-döntés a böngésző számára (gyorsítótárazatlan).
	 *
	 * @return WP_REST_Response
	 */
	public function handle_geo() {
		$country = $this->geo->country();

		$response = new WP_REST_Response(
			array(
				'country' => $country,
				'show'    => $this->geo->should_show( $country ),
			),
			200
		);

		// Ezt a választ soha nem szabad gyorsítótárazni.
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );

		return $response;
	}

	/**
	 * A látogató IP-címe (proxy fejlécek figyelembevételével).
	 *
	 * @return string
	 */
	private function client_ip() {
		$candidates = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

		foreach ( $candidates as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) {
				continue;
			}

			$value = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );

			// Az X-Forwarded-For több címet is tartalmazhat.
			if ( false !== strpos( $value, ',' ) ) {
				$parts = explode( ',', $value );
				$value = trim( $parts[0] );
			}

			if ( filter_var( $value, FILTER_VALIDATE_IP ) ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * Egyszerű sebességkorlátozás: óránként legfeljebb 30 bejegyzés IP-nként.
	 *
	 * @param string $ip IP-cím.
	 * @return bool
	 */
	private function is_rate_limited( $ip ) {
		if ( '' === $ip ) {
			return false;
		}

		$key   = 'szesuti_rl_' . substr( $this->log->hash_ip( $ip ), 0, 24 );
		$count = (int) get_transient( $key );

		if ( $count >= 30 ) {
			return true;
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );

		return false;
	}
}
