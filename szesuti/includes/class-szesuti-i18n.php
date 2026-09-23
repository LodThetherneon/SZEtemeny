<?php
/**
 * Frontend szövegek nyelvkezelése (WPML / Polylang / WP locale).
 *
 * @package SZESUTI
 */

defined( 'ABSPATH' ) || exit;

class SZESUTI_I18n {

	/** @var SZESUTI_Settings */
	private $settings;

	/** @var string|null */
	private $current = null;

	public function __construct( SZESUTI_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Egy adott nyelv kényszerítése (az admin előnézetéhez). Ismeretlen kódnál az alapnyelv.
	 *
	 * @param string $lang Nyelvkód.
	 */
	public function force_language( $lang ) {
		$available = (array) $this->settings->get( 'languages', array( 'hu' ) );
		$lang      = (string) $lang;

		$this->current = in_array( $lang, $available, true )
			? $lang
			: (string) $this->settings->get( 'default_language', 'hu' );
	}

	/**
	 * Az aktuális nyelv kódja (a beállított nyelvek közül).
	 *
	 * @return string
	 */
	public function current_language() {
		if ( null !== $this->current ) {
			return $this->current;
		}

		$available = (array) $this->settings->get( 'languages', array( 'hu' ) );
		$default   = (string) $this->settings->get( 'default_language', 'hu' );
		$detected  = '';

		if ( function_exists( 'pll_current_language' ) ) {
			$detected = (string) pll_current_language( 'slug' );
		}

		if ( '' === $detected && defined( 'ICL_LANGUAGE_CODE' ) ) {
			$detected = (string) ICL_LANGUAGE_CODE;
		}

		if ( '' === $detected ) {
			$locale   = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
			$detected = strtolower( substr( (string) $locale, 0, 2 ) );
		}

		/**
		 * Felülírható nyelvfelismerés.
		 *
		 * @param string $detected  Felismert nyelvkód.
		 * @param array  $available Elérhető nyelvek.
		 */
		$detected = (string) apply_filters( 'szesuti_current_language', $detected, $available );

		if ( ! in_array( $detected, $available, true ) ) {
			// Próbáljuk a régió nélküli változatot is (pl. en_US -> en).
			$short = strtolower( substr( $detected, 0, 2 ) );
			$detected = in_array( $short, $available, true ) ? $short : $default;
		}

		if ( ! in_array( $detected, $available, true ) ) {
			$detected = ! empty( $available ) ? reset( $available ) : 'hu';
		}

		$this->current = $detected;

		return $this->current;
	}

	/**
	 * Egy nyelv teljes szövegcsomagja (hiányzó kulcsok az alapnyelvből, majd a gyári szövegekből pótolva).
	 *
	 * @param string $lang Nyelvkód, üres esetén az aktuális.
	 * @return array
	 */
	public function texts( $lang = '' ) {
		$lang    = '' !== $lang ? $lang : $this->current_language();
		$all     = (array) $this->settings->get( 'texts', array() );
		$default = (string) $this->settings->get( 'default_language', 'hu' );
		$factory = $this->settings->default_texts();

		$chain = array();
		if ( isset( $all[ $lang ] ) ) {
			$chain[] = $all[ $lang ];
		}
		if ( $lang !== $default && isset( $all[ $default ] ) ) {
			$chain[] = $all[ $default ];
		}
		if ( isset( $factory[ $lang ] ) ) {
			$chain[] = $factory[ $lang ];
		}
		$chain[] = isset( $factory[ $default ] ) ? $factory[ $default ] : $factory['hu'];
		$chain[] = $factory['en'];

		$out = array();
		foreach ( array_reverse( $chain ) as $set ) {
			if ( ! is_array( $set ) ) {
				continue;
			}
			foreach ( $set as $key => $value ) {
				if ( 'cat' === $key && is_array( $value ) ) {
					$out['cat'] = isset( $out['cat'] ) ? array_replace( $out['cat'], $value ) : $value;
					continue;
				}
				if ( '' !== $value && null !== $value ) {
					$out[ $key ] = $value;
				}
			}
		}

		return $out;
	}

	/**
	 * Egyetlen szöveg lekérése.
	 *
	 * @param string $key      Kulcs.
	 * @param string $fallback Alapérték.
	 * @param string $lang     Nyelv.
	 * @return string
	 */
	public function t( $key, $fallback = '', $lang = '' ) {
		$texts = $this->texts( $lang );
		return isset( $texts[ $key ] ) && '' !== $texts[ $key ] ? (string) $texts[ $key ] : $fallback;
	}

	/**
	 * Kategória neve és leírása az adott nyelven.
	 *
	 * @param string $slug Kategória slug.
	 * @param string $lang Nyelv.
	 * @return array {name, desc}
	 */
	public function category( $slug, $lang = '' ) {
		$texts = $this->texts( $lang );

		if ( isset( $texts['cat'][ $slug ] ) ) {
			return array(
				'name' => isset( $texts['cat'][ $slug ]['name'] ) ? $texts['cat'][ $slug ]['name'] : ucfirst( $slug ),
				'desc' => isset( $texts['cat'][ $slug ]['desc'] ) ? $texts['cat'][ $slug ]['desc'] : '',
			);
		}

		return array(
			'name' => ucfirst( str_replace( array( '-', '_' ), ' ', $slug ) ),
			'desc' => '',
		);
	}

	/**
	 * Egy süti leírása az aktuális nyelven.
	 *
	 * @param array  $cookie Süti sor.
	 * @param string $lang   Nyelv.
	 * @return string
	 */
	public function cookie_description( array $cookie, $lang = '' ) {
		$lang = '' !== $lang ? $lang : $this->current_language();
		$key  = 'desc_' . $lang;

		if ( ! empty( $cookie[ $key ] ) ) {
			return (string) $cookie[ $key ];
		}
		if ( ! empty( $cookie['desc_hu'] ) ) {
			return (string) $cookie['desc_hu'];
		}
		if ( ! empty( $cookie['desc_en'] ) ) {
			return (string) $cookie['desc_en'];
		}

		return '';
	}
}
