<?php
/**
 * Országfelismerés és geo-célzás.
 *
 * Fontos: ha az oldalon teljes oldalas gyorsítótár (cache) fut, a szerveroldali
 * döntés is gyorsítótárazódik. Ezért az alapértelmezett mód a „rest”, ahol a
 * banner mindig kikerül a HTML-be, és a megjelenítésről a böngésző dönt egy
 * gyorsítótárazatlan REST hívás alapján.
 *
 * @package SZESUTI
 */

defined( 'ABSPATH' ) || exit;

class SZESUTI_Geo {

	/** @var SZESUTI_Settings */
	private $settings;

	/** @var string|null */
	private $country = null;

	public function __construct( SZESUTI_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * EU/EGT + azonos elbánású országok kódjai.
	 *
	 * @return string[]
	 */
	public static function eu_countries() {
		return array(
			// EU tagállamok.
			'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
			'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
			'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
			// EGT + Svájc + Egyesült Királyság (UK GDPR).
			'IS', 'LI', 'NO', 'CH', 'GB',
		);
	}

	/**
	 * A látogató országkódja a bejövő fejlécek alapján.
	 *
	 * @return string Kétbetűs kód vagy üres string, ha ismeretlen.
	 */
	public function country() {
		if ( null !== $this->country ) {
			return $this->country;
		}

		$headers = array(
			'HTTP_CF_IPCOUNTRY',              // Cloudflare.
			'HTTP_CLOUDFRONT_VIEWER_COUNTRY', // AWS CloudFront.
			'HTTP_X_COUNTRY_CODE',
			'HTTP_X_GEOIP_COUNTRY',
			'HTTP_GEOIP_COUNTRY_CODE',
			'GEOIP_COUNTRY_CODE',             // MaxMind Apache modul.
			'HTTP_X_APPENGINE_COUNTRY',
		);

		$code = '';
		foreach ( $headers as $header ) {
			if ( empty( $_SERVER[ $header ] ) ) {
				continue;
			}
			$candidate = strtoupper( preg_replace( '/[^A-Za-z]/', '', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) ) );
			if ( 2 === strlen( $candidate ) && 'XX' !== $candidate && 'T1' !== $candidate ) {
				$code = $candidate;
				break;
			}
		}

		/**
		 * Országkód felülírása (pl. saját GeoIP megoldáshoz).
		 *
		 * @param string $code Kétbetűs kód vagy üres.
		 */
		$this->country = (string) apply_filters( 'szesuti_visitor_country', $code );

		return $this->country;
	}

	/** Be van kapcsolva a geo-célzás? */
	public function is_enabled() {
		return (bool) $this->settings->get( 'geo.enabled', 0 );
	}

	/**
	 * A célzott országok listája a beállítás szerint.
	 *
	 * @return string[]
	 */
	public function target_countries() {
		if ( 'custom' === $this->settings->get( 'geo.mode', 'eu' ) ) {
			$list = (array) $this->settings->get( 'geo.countries', array() );
			return ! empty( $list ) ? $list : self::eu_countries();
		}
		return self::eu_countries();
	}

	/**
	 * Meg kell-e jeleníteni a bannert ennek a látogatónak?
	 *
	 * @param string $country Opcionális országkód (ha már ismert).
	 * @return bool
	 */
	public function should_show( $country = null ) {
		if ( ! $this->is_enabled() ) {
			return true;
		}

		$country = ( null === $country ) ? $this->country() : strtoupper( (string) $country );

		if ( '' === $country ) {
			return 'hide' !== $this->settings->get( 'geo.unknown', 'show' );
		}

		return in_array( $country, $this->target_countries(), true );
	}

	/**
	 * Tudunk-e egyáltalán országot olvasni a fejlécekből?
	 * Az adminban ez alapján figyelmeztetünk.
	 *
	 * @return bool
	 */
	public function has_country_source() {
		return '' !== $this->country();
	}
}
