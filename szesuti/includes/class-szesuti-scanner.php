<?php
/**
 * Sütiszkenner.
 *
 * Két módban dolgozik:
 *
 *  1. Teljes (böngészős) szkennelés – ez az alapértelmezett. Az admin felület
 *     kis, látható keretekben sorra megnyitja az oldal lapjait, követi a belső
 *     linkeket, legörget az oldalak aljáig, és feljegyzi a megjelenő sütiket,
 *     böngészős tárolókat és külső kiszolgálókat. A keretben megnyitott oldalak
 *     „szkennelő módban” futnak: nincs blokkolás és nincs banner, mintha a
 *     látogató mindent elfogadott volna. Ez csak bejelentkezett adminnak, egy
 *     érvényes biztonsági kóddal kapcsol be.
 *  2. Gyors, szerveroldali szkennelés néhány megadott URL-re: a válasz
 *     `Set-Cookie` fejlécei és a HTML-ben felismert szolgáltatások alapján.
 *
 * A két forrást (böngésző + szerver fejlécek) ugyanaz a feldolgozó egyesíti.
 *
 * @package SZESUTI
 */

defined( 'ABSPATH' ) || exit;

class SZESUTI_Scanner {

	/** A szkennelő mód URL-paramétere. */
	const SCAN_PARAM = 'szesuti_scan';

	/** Legfeljebb ennyi oldalt jár be egy teljes szkennelés. */
	const MAX_PAGES = 600;

	/** @var SZESUTI_Settings */
	private $settings;

	public function __construct( SZESUTI_Settings $settings ) {
		$this->settings = $settings;

		// Szkennelő mód a keretben megnyitott oldalakon.
		add_action( 'template_redirect', array( $this, 'scan_mode_setup' ), 0 );
		add_action( 'wp_head', array( $this, 'scan_mode_marker' ), 1 );
		add_filter( 'show_admin_bar', array( $this, 'scan_mode_admin_bar' ), 100 );
	}

	/* ---------------------------------------------------------------------
	 * Szkennelő mód (a keretben megnyitott látogatói oldalakon)
	 * ------------------------------------------------------------------ */

	/**
	 * Szkennelő módban fut-e a kérés?
	 *
	 * Csak akkor igaz, ha az URL-ben érvényes, a bejelentkezett adminhoz kötött
	 * biztonsági kód van. Így egy látogató semmilyen módon nem tudja kikapcsolni
	 * a blokkolást.
	 *
	 * @return bool
	 */
	public static function is_scan_request() {
		static $result = null;

		if ( null !== $result ) {
			return $result;
		}
		// phpcs:ignore WordPress.Security.NonceVerification -- itt maga a nonce ellenőrzés történik.
		if ( empty( $_GET[ self::SCAN_PARAM ] ) ) {
			return false;
		}
		// A felhasználó csak az init után ismert – addig nem döntünk (és nem is jegyezzük meg).
		if ( ! did_action( 'init' ) || ! function_exists( 'is_user_logged_in' ) ) {
			return false;
		}

		$nonce  = sanitize_text_field( wp_unslash( $_GET[ self::SCAN_PARAM ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$result = is_user_logged_in()
			&& current_user_can( 'manage_options' )
			&& false !== wp_verify_nonce( $nonce, 'szesuti_scan_frame' );

		return $result;
	}

	/** Szkennelő módban az oldal nem kerülhet gyorsítótárba. */
	public function scan_mode_setup() {
		if ( ! self::is_scan_request() ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
		}
		nocache_headers();
	}

	/** Jelzés a szkennernek, hogy a szkennelő mód tényleg bekapcsolt. */
	public function scan_mode_marker() {
		if ( self::is_scan_request() ) {
			echo '<meta name="szesuti-scan" content="1">' . "\n";
		}
	}

	/**
	 * Szkennelő módban nincs admin sáv (az admin saját sütijeit és kódját ne mérje).
	 *
	 * @param bool $show Megjelenjen-e.
	 * @return bool
	 */
	public function scan_mode_admin_bar( $show ) {
		return self::is_scan_request() ? false : $show;
	}

	/* ---------------------------------------------------------------------
	 * Teljes (böngészős) szkennelés – kiszolgáló oldal
	 * ------------------------------------------------------------------ */

	/**
	 * A bejárás kiinduló URL-jei: kezdőlap, minden oldal, minden nyilvános
	 * tartalomtípusból egy minta, és néhány archívum. A többit a szkenner a
	 * linkek követésével találja meg.
	 *
	 * @param int $limit Legfeljebb ennyi URL.
	 * @return string[]
	 */
	public function crawl_seeds( $limit = 150 ) {
		$limit = max( 1, min( self::MAX_PAGES, (int) $limit ) );
		$urls  = array( home_url( '/' ) );

		$front = (int) get_option( 'page_on_front' );
		if ( $front ) {
			$urls[] = get_permalink( $front );
		}
		$privacy = (int) get_option( 'wp_page_for_privacy_policy' );
		if ( $privacy ) {
			$urls[] = get_permalink( $privacy );
		}

		// Minden közzétett oldal (ezeken van a legtöbb egyedi beágyazás).
		$pages = get_posts(
			array(
				'post_type'        => 'page',
				'post_status'      => 'publish',
				'numberposts'      => $limit,
				'orderby'          => 'menu_order',
				'order'            => 'ASC',
				'fields'           => 'ids',
				'suppress_filters' => false,
			)
		);
		foreach ( (array) $pages as $id ) {
			$urls[] = get_permalink( $id );
		}

		// A többi nyilvános tartalomtípusból a legfrissebb néhány (ugyanaz a sablon, ritkán különböznek).
		$types = get_post_types( array( 'public' => true ), 'names' );
		foreach ( (array) $types as $type ) {
			if ( in_array( $type, array( 'page', 'attachment' ), true ) ) {
				continue;
			}
			$items = get_posts(
				array(
					'post_type'        => $type,
					'post_status'      => 'publish',
					'numberposts'      => 10,
					'fields'           => 'ids',
					'suppress_filters' => false,
				)
			);
			foreach ( (array) $items as $id ) {
				$urls[] = get_permalink( $id );
			}
			$archive = get_post_type_archive_link( $type );
			if ( $archive ) {
				$urls[] = $archive;
			}
		}

		// Néhány archívum (kategória, címke, termékkategória).
		$taxonomies = get_taxonomies( array( 'public' => true ), 'names' );
		foreach ( (array) $taxonomies as $taxonomy ) {
			if ( 'post_format' === $taxonomy ) {
				continue;
			}
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'number'     => 2,
					'hide_empty' => true,
				)
			);
			if ( is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$link = get_term_link( $term );
				if ( ! is_wp_error( $link ) ) {
					$urls[] = $link;
				}
			}
		}

		// WooCommerce fő oldalai.
		if ( function_exists( 'wc_get_page_id' ) ) {
			foreach ( array( 'shop', 'cart', 'myaccount' ) as $wc_page ) {
				$id = (int) wc_get_page_id( $wc_page );
				if ( $id > 0 ) {
					$urls[] = get_permalink( $id );
				}
			}
		}

		/**
		 * A teljes szkennelés kiinduló URL-jei.
		 *
		 * @param string[] $urls  URL-ek.
		 * @param int      $limit A kért legnagyobb oldalszám.
		 */
		$urls = (array) apply_filters( 'szesuti_scan_seeds', $urls, $limit );

		$clean = array();
		foreach ( $urls as $url ) {
			$url = esc_url_raw( (string) $url );
			if ( '' !== $url && ! in_array( $url, $clean, true ) ) {
				$clean[] = $url;
			}
		}

		return array_slice( $clean, 0, $limit );
	}

	/**
	 * A böngészős szkenner beállításai (a JS ezekkel dolgozik).
	 *
	 * @param int $limit Legfeljebb ennyi oldal.
	 * @return array
	 */
	public function crawl_config( $limit ) {
		$services = array();
		foreach ( SZESUTI_Cookie_DB::services() as $id => $service ) {
			$services[] = array(
				'id'       => $id,
				'label'    => $service['label'],
				'patterns' => array_values( array_filter( (array) $service['patterns'] ) ),
			);
		}

		$home = wp_parse_url( home_url( '/' ) );

		return array(
			'seeds'      => $this->crawl_seeds( $limit ),
			'limit'      => max( 1, min( self::MAX_PAGES, (int) $limit ) ),
			'frameParam' => self::SCAN_PARAM,
			'frameNonce' => wp_create_nonce( 'szesuti_scan_frame' ),
			'host'       => isset( $home['host'] ) ? strtolower( $home['host'] ) : '',
			'base'       => isset( $home['host'] ) ? SZESUTI_Blocker::base_domain( strtolower( $home['host'] ) ) : '',
			'services'   => $services,
			'safe'       => array_values( SZESUTI_Cookie_DB::safe_hosts() ),
			'ignore'     => array( $this->cookie_name(), 'wordpress_test_cookie' ),
		);
	}

	/**
	 * Szerveroldali fejléc-vizsgálat néhány URL-re (a HttpOnly sütiket a böngésző
	 * JavaScriptből nem látja, ezeket csak a válaszfejlécekből lehet kiolvasni).
	 *
	 * @param string[] $urls URL-ek (legfeljebb 5).
	 * @return array {cookies: string[], services: array, errors: string[]}
	 */
	public function http_probe( array $urls ) {
		$cookies  = array();
		$services = array();
		$errors   = array();

		foreach ( array_slice( $urls, 0, 5 ) as $url ) {
			$url = esc_url_raw( (string) $url );
			if ( '' === $url || ! $this->is_own_url( $url ) ) {
				continue;
			}
			$response = $this->fetch( $url, 8 );
			if ( is_wp_error( $response ) ) {
				// Ha a szerver nem éri el önmagát (tárhely-tiltás, egyszálú szerver), a többivel sem fog.
				$errors[] = sprintf( '%s – %s', $url, $response->get_error_message() );
				break;
			}
			foreach ( $this->extract_cookie_names( $response ) as $name ) {
				$cookies[ $name ] = true;
			}
			foreach ( $this->detect_services( (string) wp_remote_retrieve_body( $response ) ) as $id => $service ) {
				$services[ $id ] = $service['matched'];
			}
		}

		return array(
			'cookies'  => array_keys( $cookies ),
			'services' => $services,
			'errors'   => $errors,
		);
	}

	/**
	 * A böngészős szkennelés eredményének feldolgozása és mentése.
	 *
	 * @param array $data A szkenner által gyűjtött nyers adatok.
	 * @return array Az elmentett eredmény.
	 */
	public function save_browser_result( array $data ) {
		$urls = array();
		foreach ( array_slice( (array) ( isset( $data['urls'] ) ? $data['urls'] : array() ), 0, self::MAX_PAGES ) as $url ) {
			$url = esc_url_raw( (string) $url );
			if ( '' !== $url ) {
				$urls[] = $url;
			}
		}

		// Böngészőben talált sütik és tárolók: név => [típus, első oldal].
		$found = array();
		foreach ( array_slice( (array) ( isset( $data['cookies'] ) ? $data['cookies'] : array() ), 0, 500 ) as $item ) {
			if ( ! is_array( $item ) || empty( $item['name'] ) ) {
				continue;
			}
			$name = $this->clean_name( $item['name'] );
			if ( '' === $name ) {
				continue;
			}
			$type           = ( isset( $item['type'] ) && 'html' === $item['type'] ) ? 'html' : 'http';
			$page           = isset( $item['page'] ) ? $this->page_label( (string) $item['page'] ) : '';
			$found[ $name ] = array(
				'type'   => $type,
				'source' => 'html' === $type
					/* translators: %s: oldal elérési útja */
					? sprintf( __( 'Böngésző tároló – %s', 'szesuti' ), $page )
					/* translators: %s: oldal elérési útja */
					: sprintf( __( 'Böngésző – %s', 'szesuti' ), $page ),
			);
		}

		// Szerveroldali fejlécekből (HttpOnly) talált sütik.
		foreach ( array_slice( (array) ( isset( $data['http'] ) ? $data['http'] : array() ), 0, 200 ) as $name ) {
			$name = $this->clean_name( $name );
			if ( '' !== $name && ! isset( $found[ $name ] ) ) {
				$found[ $name ] = array(
					'type'   => 'http',
					'source' => __( 'HTTP fejléc', 'szesuti' ),
				);
			}
		}

		// Felismert szolgáltatások: azonosító => illeszkedő minta.
		$all_services = SZESUTI_Cookie_DB::services();
		$services     = array();
		foreach ( (array) ( isset( $data['services'] ) ? $data['services'] : array() ) as $id => $matched ) {
			$id = sanitize_key( (string) $id );
			if ( ! isset( $all_services[ $id ] ) ) {
				continue;
			}
			$services[ $id ] = array(
				'id'       => $id,
				'label'    => $all_services[ $id ]['label'],
				'category' => $all_services[ $id ]['category'],
				'group'    => isset( $all_services[ $id ]['group'] ) ? $all_services[ $id ]['group'] : '',
				'matched'  => sanitize_text_field( (string) $matched ),
			);
		}

		// Ismeretlen külső kiszolgálók (ezekre a szigorú mód kivételeinél lehet szükség).
		$hosts = array();
		foreach ( array_slice( (array) ( isset( $data['hosts'] ) ? $data['hosts'] : array() ), 0, 200 ) as $host ) {
			$host = strtolower( preg_replace( '/[^A-Za-z0-9.\-]/', '', (string) $host ) );
			if ( '' !== $host ) {
				$hosts[ $host ] = true;
			}
		}
		$hosts = array_keys( $hosts );
		sort( $hosts );

		$errors = array();
		foreach ( array_slice( (array) ( isset( $data['errors'] ) ? $data['errors'] : array() ), 0, 50 ) as $error ) {
			$errors[] = sanitize_text_field( (string) $error );
		}

		$result = $this->build_result( $urls, $found, $services, $errors );

		$result['mode']      = 'browser';
		$result['hosts']     = $hosts;
		$result['duration']  = isset( $data['duration'] ) ? max( 0, (int) $data['duration'] ) : 0;
		$result['stopped']   = ! empty( $data['stopped'] );
		// A szkennelő mód akkor tekinthető sikeresnek, ha az oldalak legalább felén bekapcsolt
		// (egy-egy WordPress nélküli, egyedi sablonú lapon hiányozhat a jelzés).
		$result['scan_mode'] = ( isset( $data['scanModeFailed'] ) ? (int) $data['scanModeFailed'] : 0 ) * 2 < max( 1, count( $urls ) );

		update_option( 'szesuti_last_scan', $result, false );

		return $result;
	}

	/* ---------------------------------------------------------------------
	 * Gyors, szerveroldali szkennelés
	 * ------------------------------------------------------------------ */

	/**
	 * Alapértelmezetten vizsgált URL-ek a gyors szkennelésnél.
	 *
	 * @return string[]
	 */
	public function default_urls() {
		return array_slice( $this->crawl_seeds( 8 ), 0, 8 );
	}

	/**
	 * Gyors szkennelés futtatása.
	 *
	 * @param array $urls Vizsgálandó URL-ek (üres esetén az alapértelmezettek).
	 * @return array {urls, services, cookies, errors}
	 */
	public function scan( $urls = array() ) {
		$urls = ! empty( $urls ) ? $urls : $this->default_urls();
		$urls = array_slice( array_values( array_unique( array_filter( array_map( 'esc_url_raw', $urls ) ) ) ), 0, 8 );

		$found    = array();
		$services = array();
		$errors   = array();
		$scanned  = array();

		foreach ( $urls as $url ) {
			$response = $this->fetch( $url );

			if ( is_wp_error( $response ) ) {
				$errors[] = sprintf( '%s – %s', $url, $response->get_error_message() );
				continue;
			}

			$scanned[] = $url;

			// 1. HTTP sütik a válaszfejlécekből.
			foreach ( $this->extract_cookie_names( $response ) as $name ) {
				$found[ $name ] = array(
					'type'   => 'http',
					'source' => __( 'HTTP fejléc', 'szesuti' ),
				);
			}

			// 2. Felismert szolgáltatások a HTML-ből.
			foreach ( $this->detect_services( (string) wp_remote_retrieve_body( $response ) ) as $id => $service ) {
				$services[ $id ] = $service;
			}
		}

		$result         = $this->build_result( $scanned, $found, $services, $errors );
		$result['mode'] = 'http';

		update_option( 'szesuti_last_scan', $result, false );

		return $result;
	}

	/**
	 * A legutóbbi szkennelés eredménye.
	 *
	 * @return array|null
	 */
	public function last_scan() {
		$result = get_option( 'szesuti_last_scan', null );
		return is_array( $result ) ? $result : null;
	}

	/* ---------------------------------------------------------------------
	 * Közös feldolgozás
	 * ------------------------------------------------------------------ */

	/**
	 * Az eredmény összeállítása: ismert leírások, a szolgáltatásokhoz tartozó
	 * sütik, és hogy melyik új a mentett listához képest.
	 *
	 * @param string[] $urls     Vizsgált URL-ek.
	 * @param array    $found    Süti neve => [type, source].
	 * @param array    $services Felismert szolgáltatások.
	 * @param string[] $errors   Hibák.
	 * @return array
	 */
	private function build_result( array $urls, array $found, array $services, array $errors ) {
		$cookies = array();

		foreach ( $found as $name => $meta ) {
			$row = $this->describe_cookie( $name, $meta['source'], $meta['type'] );
			// Az ismert, helyettesítő karakteres bejegyzés (pl. _ga_*) egyszer kerüljön a listára.
			$key = strtolower( $row['name'] );
			if ( ! isset( $cookies[ $key ] ) ) {
				$cookies[ $key ] = $row;
			}
		}

		// A felismert szolgáltatásokhoz tartozó ismert sütik (a harmadik féltől
		// származókat – pl. facebook.com – a böngésző sem mutatja meg).
		$map = $this->service_cookie_map();
		foreach ( array_keys( $services ) as $service_id ) {
			if ( empty( $map[ $service_id ] ) ) {
				continue;
			}
			foreach ( $map[ $service_id ] as $pattern ) {
				if ( isset( $cookies[ strtolower( $pattern ) ] ) ) {
					continue;
				}
				$known = SZESUTI_Cookie_DB::match_cookie( str_replace( '*', 'X', $pattern ) );
				if ( ! $known ) {
					continue;
				}
				$known['name']                        = $pattern;
				$known['source']                      = $services[ $service_id ]['label'];
				$known['known']                       = true;
				$cookies[ strtolower( $pattern ) ]    = $known;
			}
		}

		// Melyik nincs még a mentett listában?
		$existing = array();
		foreach ( (array) $this->settings->get( 'cookies', array() ) as $cookie ) {
			$existing[ strtolower( $cookie['name'] ) ] = true;
		}
		foreach ( $cookies as $key => $cookie ) {
			$cookies[ $key ]['is_new'] = ! isset( $existing[ $key ] );
		}

		ksort( $cookies );

		return array(
			'urls'     => array_values( $urls ),
			'services' => $services,
			'cookies'  => array_values( $cookies ),
			'errors'   => array_values( $errors ),
			'time'     => time(),
		);
	}

	/**
	 * Szolgáltatás => ismert süti minták.
	 *
	 * @return array
	 */
	private function service_cookie_map() {
		return array(
			'google_analytics'   => array( '_ga', '_ga_*', '_gid', '_gat*' ),
			'google_tag_manager' => array( '_ga', '_ga_*', '_gid' ),
			'google_ads'         => array( '_gcl_au', 'IDE', 'NID', '1P_JAR' ),
			'facebook_pixel'     => array( '_fbp', '_fbc', 'fr' ),
			'facebook_embed'     => array( '_fbp', 'fr' ),
			'hotjar'             => array( '_hjSession*', '_hj*' ),
			'clarity'            => array( '_clck', '_clsk' ),
			'linkedin'           => array( 'li_gc', 'bcookie', 'lidc', 'li_fat_id' ),
			'linkedin_embed'     => array( 'li_gc', 'bcookie', 'lidc' ),
			'youtube'            => array( 'VISITOR_INFO1_LIVE', 'YSC', 'CONSENT', 'VISITOR_PRIVACY_METADATA' ),
			'barion'             => array( 'barion_*' ),
			'tiktok'             => array( '_ttp' ),
			'vimeo'              => array( 'vuid' ),
			'spotify'            => array( 'sp_t', 'sp_landing' ),
			'recaptcha'          => array( '_GRECAPTCHA' ),
			'twitter_widgets'    => array( 'guest_id*', 'personalization_id' ),
		);
	}

	/**
	 * Egy süti leírása az ismert adatbázisból, vagy semleges alapértelmezés.
	 * Ha az ismert bejegyzés helyettesítő karakteres (pl. _ga_*), azt a mintát
	 * adjuk vissza névként, hogy a lista ne teljen meg azonosító-változatokkal.
	 *
	 * @param string $name   Süti neve.
	 * @param string $source Honnan találtuk.
	 * @param string $type   http | html.
	 * @return array
	 */
	private function describe_cookie( $name, $source, $type = 'http' ) {
		foreach ( SZESUTI_Cookie_DB::known_cookies() as $cookie ) {
			$pattern = '/^' . str_replace( '\*', '.*', preg_quote( $cookie['match'], '/' ) ) . '$/i';
			if ( preg_match( $pattern, $name ) ) {
				$row           = SZESUTI_Cookie_DB::to_row( $cookie );
				$row['name']   = false !== strpos( $cookie['match'], '*' ) ? $cookie['match'] : $name;
				$row['source'] = $source;
				$row['known']  = true;
				return $row;
			}
		}

		return array(
			'name'     => $name,
			'provider' => __( 'A weboldal', 'szesuti' ),
			'category' => 'necessary',
			'duration' => '',
			'type'     => $type,
			'desc_hu'  => '',
			'desc_en'  => '',
			'source'   => $source,
			'known'    => false,
		);
	}

	/**
	 * Egy URL lekérése a szerverről, látogatóként (sütik nélkül).
	 *
	 * @param string $url     URL.
	 * @param int    $timeout Időkorlát (mp).
	 * @return array|WP_Error
	 */
	private function fetch( $url, $timeout = 15 ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => $timeout,
				'redirection' => 3,
				'user-agent'  => 'SZESUTI-Scanner/' . SZESUTI_VERSION . '; ' . home_url(),
				'headers'     => array( 'Accept' => 'text/html' ),
				'cookies'     => array(),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 400 ) {
			return new WP_Error( 'szesuti_scan_http', 'HTTP ' . $code );
		}

		return $response;
	}

	/**
	 * A saját oldalhoz tartozik-e az URL (a szerver csak ezeket kéri le).
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private function is_own_url( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$home = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		return $host && $home && strtolower( $host ) === strtolower( $home );
	}

	/**
	 * A hozzájárulási süti neve (ezt a szkenner nem jelenti).
	 *
	 * @return string
	 */
	private function cookie_name() {
		$name = (string) $this->settings->get( 'general.cookie_name', 'szesuti_consent' );
		return '' !== $name ? $name : 'szesuti_consent';
	}

	/**
	 * Süti- vagy tárolónév tisztítása.
	 *
	 * @param mixed $name Név.
	 * @return string
	 */
	private function clean_name( $name ) {
		$name = trim( wp_strip_all_tags( (string) $name ) );
		if ( '' === $name || strlen( $name ) > 100 || preg_match( '/[\s;,"\\\\]/', $name ) ) {
			return '';
		}
		return $name;
	}

	/**
	 * Egy oldal URL-jéből rövid címke (elérési út) a „Forrás” oszlophoz.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private function page_label( $url ) {
		$path  = (string) wp_parse_url( $url, PHP_URL_PATH );
		$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
		$path  = ( '' !== $path ? $path : '/' ) . ( '' !== $query ? '?' . $query : '' );
		return substr( sanitize_text_field( $path ), 0, 80 );
	}

	/**
	 * Süti nevek kiolvasása a válasz Set-Cookie fejléceiből.
	 *
	 * @param array|WP_Error $response HTTP válasz.
	 * @return string[]
	 */
	private function extract_cookie_names( $response ) {
		$names   = array();
		$cookies = wp_remote_retrieve_cookies( $response );

		if ( is_array( $cookies ) ) {
			foreach ( $cookies as $cookie ) {
				if ( is_object( $cookie ) && isset( $cookie->name ) ) {
					$names[] = $cookie->name;
				} elseif ( is_string( $cookie ) ) {
					$names[] = strtok( $cookie, '=' );
				}
			}
		}

		// Tartalék: nyers fejléc feldolgozás.
		$raw = wp_remote_retrieve_header( $response, 'set-cookie' );
		foreach ( (array) $raw as $line ) {
			if ( ! is_string( $line ) ) {
				continue;
			}
			$pair = explode( '=', $line, 2 );
			if ( ! empty( $pair[0] ) ) {
				$names[] = trim( $pair[0] );
			}
		}

		$clean = array();
		foreach ( $names as $name ) {
			$name = $this->clean_name( $name );
			if ( '' !== $name ) {
				$clean[] = $name;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Ismert szolgáltatások keresése a HTML-ben.
	 *
	 * @param string $html Oldal forrása.
	 * @return array
	 */
	private function detect_services( $html ) {
		$found = array();

		// A plugin saját szkriptjei (futásidejű őr szabálylistája, konfiguráció, Consent Mode)
		// minden szolgáltató mintáját tartalmazzák – ezeket ki kell hagyni, különben a
		// szkenner az oldalon nem létező szolgáltatásokat is „felismerne”.
		$html = (string) preg_replace( '#<script\b[^>]*\bdata-szesuti-ignore\b[^>]*>.*?</script>#is', '', (string) $html );
		$html = (string) preg_replace( '#<script\b[^>]*\bid=["\']szesuti-[^"\']*["\'][^>]*>.*?</script>#is', '', $html );

		foreach ( SZESUTI_Cookie_DB::services() as $id => $service ) {
			foreach ( $service['patterns'] as $pattern ) {
				if ( '' !== $pattern && false !== stripos( $html, $pattern ) ) {
					$found[ $id ] = array(
						'id'       => $id,
						'label'    => $service['label'],
						'category' => $service['category'],
						'group'    => isset( $service['group'] ) ? $service['group'] : '',
						'matched'  => $pattern,
					);
					break;
				}
			}
		}

		return $found;
	}
}
