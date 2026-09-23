<?php
/**
 * Saját szerverről működő frissítéskezelő.
 *
 * A „Haladó” fülön megadott URL-en egy JSON manifestet vár. Ha abban nagyobb
 * verziószám szerepel, a WordPress normál frissítési felületén megjelenik a
 * „Frissítés elérhető” értesítés – pont úgy, mint a wordpress.org pluginoknál.
 *
 * Minta manifest:
 * {
 *   "name": "SZetemény",
 *   "slug": "szesuti",
 *   "version": "1.1.0",
 *   "requires": "5.6",
 *   "tested": "6.7",
 *   "requires_php": "7.2",
 *   "author": "<a href=\"https://pelda.hu\">Név</a>",
 *   "homepage": "https://pelda.hu/szesuti",
 *   "download_url": "https://pelda.hu/szesuti/szesuti-1.1.0.zip",
 *   "last_updated": "2026-09-18 10:00:00",
 *   "sections": { "description": "...", "changelog": "<h4>1.1.0</h4><ul><li>...</li></ul>" }
 * }
 *
 * @package SZESUTI
 */

defined( 'ABSPATH' ) || exit;

class SZESUTI_Updater {

	const CACHE_KEY = 'szesuti_update_manifest';

	/** @var SZESUTI_Settings */
	private $settings;

	public function __construct( SZESUTI_Settings $settings ) {
		$this->settings = $settings;

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_folder' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache' ), 10, 0 );
	}

	/**
	 * A beállított manifest URL.
	 *
	 * @return string
	 */
	private function update_url() {
		/**
		 * A frissítési manifest URL-je kóddal is megadható (pl. mu-pluginból).
		 *
		 * @param string $url Manifest URL.
		 */
		return (string) apply_filters( 'szesuti_update_url', (string) $this->settings->get( 'advanced.update_url', '' ) );
	}

	/**
	 * A manifest letöltése (12 órára gyorsítótárazva).
	 *
	 * @param bool $force Gyorsítótár megkerülése.
	 * @return array|WP_Error
	 */
	public function fetch_manifest( $force = false ) {
		$url = $this->update_url();

		if ( '' === $url ) {
			return new WP_Error( 'szesuti_no_update_url', __( 'Nincs megadva frissítési URL.', 'szesuti' ) );
		}

		if ( ! $force ) {
			$cached = get_site_transient( self::CACHE_KEY );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$response = wp_remote_get(
			add_query_arg(
				array(
					'site'    => rawurlencode( home_url() ),
					'version' => SZESUTI_VERSION,
				),
				$url
			),
			array(
				'timeout'    => 12,
				'user-agent' => 'SZESUTI/' . SZESUTI_VERSION . '; ' . home_url(),
			)
		);

		if ( is_wp_error( $response ) ) {
			set_site_transient( self::CACHE_KEY, array(), HOUR_IN_SECONDS );
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			set_site_transient( self::CACHE_KEY, array(), HOUR_IN_SECONDS );
			return new WP_Error( 'szesuti_update_http', sprintf( __( 'A frissítési szerver HTTP %d hibát adott.', 'szesuti' ), $code ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['version'] ) ) {
			set_site_transient( self::CACHE_KEY, array(), HOUR_IN_SECONDS );
			return new WP_Error( 'szesuti_update_json', __( 'A frissítési manifest nem értelmezhető.', 'szesuti' ) );
		}

		set_site_transient( self::CACHE_KEY, $data, 12 * HOUR_IN_SECONDS );

		return $data;
	}

	/** A gyorsítótárazott manifest törlése. */
	public function clear_cache() {
		delete_site_transient( self::CACHE_KEY );
	}

	/**
	 * Frissítés beillesztése a WordPress frissítési listájába.
	 *
	 * @param object $transient Frissítési transient.
	 * @return object
	 */
	public function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		if ( '' === $this->update_url() ) {
			return $transient;
		}

		$manifest = $this->fetch_manifest();
		if ( is_wp_error( $manifest ) || empty( $manifest['version'] ) || empty( $manifest['download_url'] ) ) {
			return $transient;
		}

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}
		if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
			$transient->no_update = array();
		}

		$item = (object) array(
			'id'            => SZESUTI_BASENAME,
			'slug'          => SZESUTI_SLUG,
			'plugin'        => SZESUTI_BASENAME,
			'new_version'   => (string) $manifest['version'],
			'url'           => isset( $manifest['homepage'] ) ? esc_url_raw( $manifest['homepage'] ) : '',
			'package'       => esc_url_raw( $manifest['download_url'] ),
			'icons'         => isset( $manifest['icons'] ) && is_array( $manifest['icons'] ) ? $manifest['icons'] : array(),
			'banners'       => isset( $manifest['banners'] ) && is_array( $manifest['banners'] ) ? $manifest['banners'] : array(),
			'tested'        => isset( $manifest['tested'] ) ? (string) $manifest['tested'] : '',
			'requires_php'  => isset( $manifest['requires_php'] ) ? (string) $manifest['requires_php'] : '',
			'compatibility' => new stdClass(),
		);

		if ( version_compare( SZESUTI_VERSION, (string) $manifest['version'], '<' ) ) {
			$transient->response[ SZESUTI_BASENAME ] = $item;
			unset( $transient->no_update[ SZESUTI_BASENAME ] );
		} else {
			$transient->no_update[ SZESUTI_BASENAME ] = $item;
			unset( $transient->response[ SZESUTI_BASENAME ] );
		}

		return $transient;
	}

	/**
	 * A „Részletek” felugró ablak tartalma.
	 *
	 * @param false|object|array $result Eredeti eredmény.
	 * @param string             $action Kért művelet.
	 * @param object             $args   Argumentumok.
	 * @return false|object|array
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}
		if ( ! isset( $args->slug ) || SZESUTI_SLUG !== $args->slug ) {
			return $result;
		}

		$manifest = $this->fetch_manifest();
		if ( is_wp_error( $manifest ) || empty( $manifest['version'] ) ) {
			return $result;
		}

		$info = (object) array(
			'name'          => isset( $manifest['name'] ) ? $manifest['name'] : 'SZetemény',
			'slug'          => SZESUTI_SLUG,
			'version'       => (string) $manifest['version'],
			'author'        => isset( $manifest['author'] ) ? $manifest['author'] : '',
			'homepage'      => isset( $manifest['homepage'] ) ? $manifest['homepage'] : '',
			'requires'      => isset( $manifest['requires'] ) ? (string) $manifest['requires'] : '',
			'tested'        => isset( $manifest['tested'] ) ? (string) $manifest['tested'] : '',
			'requires_php'  => isset( $manifest['requires_php'] ) ? (string) $manifest['requires_php'] : '',
			'last_updated'  => isset( $manifest['last_updated'] ) ? (string) $manifest['last_updated'] : '',
			'download_link' => isset( $manifest['download_url'] ) ? esc_url_raw( $manifest['download_url'] ) : '',
			'sections'      => isset( $manifest['sections'] ) && is_array( $manifest['sections'] ) ? $manifest['sections'] : array(),
			'banners'       => isset( $manifest['banners'] ) && is_array( $manifest['banners'] ) ? $manifest['banners'] : array(),
		);

		return $info;
	}

	/**
	 * A letöltött ZIP mappájának nevét a plugin mappanevére igazítja.
	 *
	 * Enélkül egy „szesuti-1.1.0” nevű mappából új, párhuzamos plugin lenne.
	 *
	 * @param string $source        Kicsomagolt forrás.
	 * @param string $remote_source Ideiglenes mappa.
	 * @param object $upgrader      Frissítő.
	 * @param array  $hook_extra    Extra adatok.
	 * @return string|WP_Error
	 */
	public function fix_source_folder( $source, $remote_source, $upgrader = null, $hook_extra = array() ) {
		global $wp_filesystem;

		if ( empty( $hook_extra['plugin'] ) || SZESUTI_BASENAME !== $hook_extra['plugin'] ) {
			return $source;
		}

		if ( ! $wp_filesystem ) {
			return $source;
		}

		$desired = trailingslashit( $remote_source ) . SZESUTI_SLUG;

		if ( trailingslashit( $source ) === trailingslashit( $desired ) ) {
			return $source;
		}

		if ( $wp_filesystem->move( $source, $desired, true ) ) {
			return trailingslashit( $desired );
		}

		return new WP_Error( 'szesuti_source_rename', __( 'Nem sikerült a frissítési csomag mappáját átnevezni.', 'szesuti' ) );
	}
}
