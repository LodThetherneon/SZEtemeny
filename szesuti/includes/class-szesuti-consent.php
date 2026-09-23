<?php
/**
 * A látogató hozzájárulásának kiolvasása szerveroldalon.
 *
 * A sütit a böngésző írja (JS), itt csak olvassuk. Ez teszi lehetővé, hogy
 * PHP-ból is le tudd kérdezni: szesuti_has_consent( 'analytics' ).
 *
 * @package SZESUTI
 */

defined( 'ABSPATH' ) || exit;

class SZESUTI_Consent {

	/** @var SZESUTI_Settings */
	private $settings;

	/** @var array|null|false Feloldott süti tartalom. false = még nem olvastuk. */
	private $data = false;

	public function __construct( SZESUTI_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * A hozzájárulási süti neve.
	 *
	 * @return string
	 */
	public function cookie_name() {
		return (string) $this->settings->get( 'general.cookie_name', 'szesuti_consent' );
	}

	/**
	 * A süti tartalma tömbként, vagy null ha nincs / érvénytelen.
	 *
	 * @return array|null
	 */
	public function data() {
		if ( false !== $this->data ) {
			return $this->data;
		}

		$this->data = null;
		$name       = $this->cookie_name();

		if ( empty( $_COOKIE[ $name ] ) ) {
			return null;
		}

		$raw = wp_unslash( $_COOKIE[ $name ] );
		if ( ! is_string( $raw ) || strlen( $raw ) > 4096 ) {
			return null;
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) || empty( $decoded['cats'] ) || ! is_array( $decoded['cats'] ) ) {
			return null;
		}

		// Lejárt-e a hozzájárulás?
		$given = isset( $decoded['t'] ) ? (int) $decoded['t'] : 0;
		$days  = (int) $this->settings->get( 'general.consent_expiry_days', 180 );
		if ( $given > 0 && $days > 0 && ( time() - $given ) > ( $days * DAY_IN_SECONDS ) ) {
			return null;
		}

		// Változott-e a szabályzat verziója az elfogadás óta?
		$policy = (string) $this->settings->get( 'general.policy_version', '1' );
		if ( '' !== $policy && isset( $decoded['pv'] ) && (string) $decoded['pv'] !== $policy ) {
			return null;
		}

		$cats = array();
		foreach ( $decoded['cats'] as $slug ) {
			$slug = sanitize_key( (string) $slug );
			if ( '' !== $slug ) {
				$cats[] = $slug;
			}
		}

		$this->data = array(
			'cats'   => array_values( array_unique( $cats ) ),
			'time'   => $given,
			'id'     => isset( $decoded['id'] ) ? sanitize_text_field( (string) $decoded['id'] ) : '',
			'action' => isset( $decoded['act'] ) ? sanitize_key( (string) $decoded['act'] ) : '',
			'policy' => isset( $decoded['pv'] ) ? sanitize_text_field( (string) $decoded['pv'] ) : '',
		);

		return $this->data;
	}

	/**
	 * Hozott-e már döntést a látogató?
	 *
	 * @return bool
	 */
	public function has_decision() {
		return null !== $this->data();
	}

	/**
	 * Engedélyezett-e egy kategória?
	 *
	 * A „necessary” (és minden zárolt) kategória mindig engedélyezett.
	 *
	 * @param string $category Kategória slug.
	 * @return bool
	 */
	public function has_consent( $category ) {
		$category = sanitize_key( (string) $category );
		if ( '' === $category ) {
			return false;
		}

		foreach ( (array) $this->settings->get( 'categories', array() ) as $cat ) {
			if ( isset( $cat['slug'] ) && $cat['slug'] === $category && ! empty( $cat['locked'] ) ) {
				return true;
			}
		}

		$data = $this->data();
		if ( null === $data ) {
			return false;
		}

		return in_array( $category, $data['cats'], true );
	}

	/**
	 * Az elfogadott kategóriák listája (döntés nélkül az alapértelmezettek).
	 *
	 * @return string[]
	 */
	public function accepted_categories() {
		$data = $this->data();
		if ( null !== $data ) {
			return $data['cats'];
		}
		return $this->default_categories();
	}

	/**
	 * Döntés előtti alapállapot: a zárolt és az alapból bekapcsolt kategóriák.
	 *
	 * @return string[]
	 */
	public function default_categories() {
		$out = array();
		foreach ( $this->settings->active_categories() as $cat ) {
			if ( ! empty( $cat['locked'] ) || ! empty( $cat['default'] ) ) {
				$out[] = $cat['slug'];
			}
		}
		return $out;
	}
}
