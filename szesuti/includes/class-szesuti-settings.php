<?php
/**
 * Beállítások tárolása, alapértékek, mentés, export/import.
 *
 * @package SZESUTI
 */

defined( 'ABSPATH' ) || exit;

class SZESUTI_Settings {

	/** @var array|null Memóriában tartott, feloldott beállítások. */
	private $cache = null;

	/**
	 * A teljes alapértelmezett beállításfa.
	 *
	 * @return array
	 */
	public function defaults() {
		return array(
			'version' => SZESUTI_VERSION,

			'general' => array(
				'enabled'             => 1,
				'consent_expiry_days' => 180,
				'policy_version'      => '1',
				'reload_on_accept'    => 0,
				'reload_on_reject'    => 1,
				'show_on_admin'       => 1,
				'respect_dnt'         => 0,
				'respect_gpc'         => 1,
				'hide_from_bots'      => 0,
				'cookie_name'         => 'szesuti_consent',
				'cookie_domain'       => '',
				'auto_open_delay'     => 0,
				'reopen_button'       => 1,
				'reopen_icon'         => 'cookie',
				'reopen_icon_url'     => '',
				'reopen_position'     => 'bottom-left',
				'reopen_hide_mobile'  => 0,
				'reopen_offset_x'     => 20,
				'reopen_offset_y'     => 20,
				'privacy_url'         => '',
				'cookie_policy_url'   => '',
				'imprint_url'         => '',
				'exclude_urls'        => '',
				'consent_mode_ui'     => 'granular',
			),

			'geo' => array(
				'enabled'   => 0,
				'mode'      => 'eu',
				'countries' => array(),
				'unknown'   => 'show',
				'method'    => 'rest',
			),

			'appearance' => array(
				'layout'          => 'box',
				'position'        => 'bottom-left',
				'theme'           => 'light',
				'width'           => 420,
				'radius'          => 16,
				'font_size'       => 14,
				'font_family'     => '',
				'shadow'          => 'lg',
				'animation'       => 'slide-up',
				'overlay'         => 0,
				'overlay_opacity' => 55,
				'z_index'         => 999990,
				'btn_radius'      => 10,
				'btn_style'       => 'solid',
				'btn_layout'      => 'row',
				'btn_order'       => 'accept,reject,settings',
				'show_reject'     => 1,
				'show_settings'   => 1,
				'show_close_x'    => 0,
				'equal_buttons'   => 1,
				'logo_url'        => '', // A „Saját kép” ikon URL-je.
				'logo_height'     => 40, // Képes ikon mérete (px).
				'icon'            => 'cookie',
				'show_powered_by' => 0,
				'custom_css'      => '',
				'colors'          => array(
					'bg'             => '#ffffff',
					'text'           => '#1f2430',
					'muted'          => '#5b6478',
					'border'         => '#e4e7ee',
					'primary'        => '#2d6cdf',
					'primary_text'   => '#ffffff',
					'secondary'      => '#eef1f7',
					'secondary_text' => '#2b3242',
					'tertiary'        => '', // Beállítások gomb háttere – üres: átlátszó.
					'tertiary_text'   => '', // Beállítások gomb szövege – üres: a halvány szöveg színe.
					'tertiary_border' => '', // Beállítások gomb kerete – üres: a keret színe.
					'link'           => '#2d6cdf',
					'toggle_on'      => '#2d6cdf',
					'overlay'        => '#0b1020',
				),
				'colors_dark'     => array(
					'bg'             => '#161a23',
					'text'           => '#eef1f7',
					'muted'          => '#a4adc0',
					'border'         => '#2a303d',
					'primary'        => '#5b8cf0',
					'primary_text'   => '#0b1020',
					'secondary'      => '#232a36',
					'secondary_text' => '#eef1f7',
					'tertiary'        => '',
					'tertiary_text'   => '',
					'tertiary_border' => '',
					'link'           => '#8fb2f7',
					'toggle_on'      => '#5b8cf0',
					'overlay'        => '#000000',
				),
			),

			'categories' => array(
				array(
					'slug'    => 'necessary',
					'locked'  => 1,
					'default' => 1,
					'enabled' => 1,
				),
				array(
					'slug'    => 'functional',
					'locked'  => 0,
					'default' => 0,
					'enabled' => 1,
				),
				array(
					'slug'    => 'analytics',
					'locked'  => 0,
					'default' => 0,
					'enabled' => 1,
				),
				array(
					'slug'    => 'marketing',
					'locked'  => 0,
					'default' => 0,
					'enabled' => 1,
				),
			),

			'languages'        => array( 'hu', 'en' ),
			'default_language' => 'hu',
			'texts'            => $this->default_texts(),

			'blocker' => array(
				'enabled'             => 1,
				'auto'                => 1,
				'strict'              => 1,           // Minden ismeretlen külső forrás blokkolva.
				'unknown_category'    => 'marketing', // Ennek a kategóriának az elfogadása oldja fel őket.
				'safe_hosts'          => 1,           // A beépített biztonságos CDN-lista használata.
				'guard'               => 1,           // Futásidejű őr a később beszúrt elemekre.
				'placeholder_enabled' => 1,
				'rules'               => array(),
				'custom'              => array(),
				'skip_patterns'       => '',
			),

			'gcm' => array(
				'enabled'            => 0,
				'regions'            => '',
				'wait_for_update'    => 500,
				'url_passthrough'    => 1,
				'ads_data_redaction' => 1,
				'datalayer'          => 'dataLayer',
				'gtm_id'             => '',
				'inject_gtm'         => 0,
				'dont_block_google'  => 1,
				'push_events'        => 1,
				'map'                => array(
					'ad_storage'              => 'marketing',
					'ad_user_data'            => 'marketing',
					'ad_personalization'      => 'marketing',
					'analytics_storage'       => 'analytics',
					'functionality_storage'   => 'functional',
					'personalization_storage' => 'functional',
					'security_storage'        => '',
				),
			),

			'cookies' => array(),

			'log' => array(
				'enabled'        => 1,
				'retention_days' => 365,
				'store_url'      => 1,
				'store_ua'       => 1,
				'store_country'  => 1,
				'salt'           => '',
			),

			'advanced' => array(
				'update_url'               => '',
				'delete_data_on_uninstall' => 0,
				'load_css'                 => 1,
				'footer_js'                => 1,
				'debug'                    => 0,
				'rest_enabled'             => 1,
			),
		);
	}

	/**
	 * Gyári frontend szövegek nyelvenként.
	 *
	 * @return array
	 */
	public function default_texts() {
		return array(
			'hu' => array(
				'title'              => 'Sütiket használunk',
				'message'            => 'Weboldalunk sütiket (cookie-kat) használ a működés biztosításához, a felhasználói élmény javításához, valamint statisztikai és marketing célokra. Az „Összes elfogadása” gombbal minden sütit engedélyezel, az „Elutasítás” gombbal pedig csak a működéshez feltétlenül szükségesek maradnak aktívak.',
				'accept_all'         => 'Összes elfogadása',
				'reject_all'         => 'Elutasítás',
				'settings'           => 'Beállítások',
				'save'               => 'Választás mentése',
				'accept_selected'    => 'Kiválasztottak mentése',
				'close'              => 'Bezárás',
				'back'               => 'Vissza',
				'prefs_title'        => 'Süti beállítások',
				'prefs_intro'        => 'Itt kategóriánként állíthatod be, hogy milyen sütiket engedélyezel. A feltétlenül szükséges sütik nem kapcsolhatók ki, mert nélkülük az oldal nem működik.',
				'always_active'      => 'Mindig aktív',
				'enabled_label'      => 'Engedélyezve',
				'disabled_label'     => 'Letiltva',
				'privacy_link'       => 'Adatkezelési tájékoztató',
				'cookie_policy_link' => 'Süti tájékoztató',
				'imprint_link'       => 'Impresszum',
				'reopen_label'       => 'Süti beállítások',
				'details'            => 'Részletek',
				'cookie_table_name'  => 'Süti neve',
				'cookie_table_prov'  => 'Szolgáltató',
				'cookie_table_dur'   => 'Élettartam',
				'cookie_table_desc'  => 'Leírás',
				'blocked_title'      => 'Ez a tartalom blokkolva van',
				'blocked_text'       => 'A tartalom megjelenítéséhez engedélyezned kell a(z) „%s” kategóriájú sütiket.',
				'blocked_btn'        => 'Tartalom engedélyezése',
				'status_accepted'    => 'Elfogadva',
				'status_rejected'    => 'Elutasítva',
				'status_partial'     => 'Részlegesen elfogadva',
				'status_none'        => 'Még nem döntöttél',
				'cat'                => array(
					'necessary'  => array(
						'name' => 'Feltétlenül szükséges',
						'desc' => 'Ezek a sütik az oldal alapvető működéséhez kellenek: bejelentkezés, biztonsági funkciók, kosár, űrlapok, valamint a süti-beállításaid megjegyzése. Nélkülük a weboldal nem működik megfelelően, ezért nem kapcsolhatók ki.',
					),
					'functional' => array(
						'name' => 'Funkcionális',
						'desc' => 'Kényelmi funkciókat tesznek lehetővé: nyelvválasztás megjegyzése, videó- és térképbeágyazások, élő chat, közösségi tartalmak megjelenítése.',
					),
					'analytics'  => array(
						'name' => 'Statisztikai',
						'desc' => 'Névtelen, összesített adatokat gyűjtenek arról, hogyan használod az oldalt (például mely aloldalak népszerűek). Ezek segítenek fejleszteni a weboldalt.',
					),
					'marketing'  => array(
						'name' => 'Marketing',
						'desc' => 'Ezek a sütik a hirdetések személyre szabására és a hirdetési kampányok hatékonyságának mérésére szolgálnak, akár más weboldalakon is.',
					),
				),
			),
			'en' => array(
				'title'              => 'We use cookies',
				'message'            => 'This website uses cookies to ensure basic functionality, improve your experience, and for statistics and marketing purposes. Click “Accept all” to allow every cookie, or “Reject” to keep only the strictly necessary ones active.',
				'accept_all'         => 'Accept all',
				'reject_all'         => 'Reject',
				'settings'           => 'Settings',
				'save'               => 'Save choice',
				'accept_selected'    => 'Save selection',
				'close'              => 'Close',
				'back'               => 'Back',
				'prefs_title'        => 'Cookie preferences',
				'prefs_intro'        => 'Choose which cookie categories you allow. Strictly necessary cookies cannot be turned off, because the site will not work without them.',
				'always_active'      => 'Always active',
				'enabled_label'      => 'Enabled',
				'disabled_label'     => 'Disabled',
				'privacy_link'       => 'Privacy policy',
				'cookie_policy_link' => 'Cookie policy',
				'imprint_link'       => 'Imprint',
				'reopen_label'       => 'Cookie settings',
				'details'            => 'Details',
				'cookie_table_name'  => 'Cookie name',
				'cookie_table_prov'  => 'Provider',
				'cookie_table_dur'   => 'Duration',
				'cookie_table_desc'  => 'Description',
				'blocked_title'      => 'This content is blocked',
				'blocked_text'       => 'To display this content you need to allow “%s” cookies.',
				'blocked_btn'        => 'Allow content',
				'status_accepted'    => 'Accepted',
				'status_rejected'    => 'Rejected',
				'status_partial'     => 'Partially accepted',
				'status_none'        => 'No choice yet',
				'cat'                => array(
					'necessary'  => array(
						'name' => 'Strictly necessary',
						'desc' => 'Required for the basic operation of the site: login, security, cart, forms and remembering your cookie choice. The site cannot work without them, so they cannot be disabled.',
					),
					'functional' => array(
						'name' => 'Functional',
						'desc' => 'Enable convenience features such as remembering your language, embedded videos and maps, live chat and social content.',
					),
					'analytics'  => array(
						'name' => 'Statistics',
						'desc' => 'Collect anonymous, aggregated data about how you use the site so we can improve it.',
					),
					'marketing'  => array(
						'name' => 'Marketing',
						'desc' => 'Used to personalise ads and measure campaign performance, potentially across other websites too.',
					),
				),
			),
		);
	}

	/**
	 * Teljes beállítástömb (alapértékekkel feltöltve).
	 *
	 * @return array
	 */
	public function all() {
		if ( null !== $this->cache ) {
			return $this->cache;
		}

		/**
		 * Minden beállítás szűrhető – így mu-plugin vagy theme szintről felülírható.
		 *
		 * @param array $merged Beállítások.
		 */
		$this->cache = apply_filters( 'szesuti_settings', $this->stored() );

		return $this->cache;
	}

	/**
	 * A ténylegesen eltárolt beállítások, alapértékekkel kiegészítve, de
	 * a `szesuti_settings` szűrő alkalmazása NÉLKÜL.
	 *
	 * Mentés előtt ezt használjuk kiindulásnak, különben a kóddal felülírt
	 * értékek beleégnének az adatbázisba.
	 *
	 * @return array
	 */
	public function stored() {
		$stored = get_option( SZESUTI_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$merged = $this->merge_deep( $this->defaults(), $stored );

		foreach ( array( 'categories', 'languages', 'cookies' ) as $list ) {
			if ( isset( $stored[ $list ] ) && is_array( $stored[ $list ] ) ) {
				$merged[ $list ] = $stored[ $list ];
			}
		}
		if ( isset( $stored['blocker']['custom'] ) && is_array( $stored['blocker']['custom'] ) ) {
			$merged['blocker']['custom'] = $stored['blocker']['custom'];
		}
		if ( isset( $stored['geo']['countries'] ) && is_array( $stored['geo']['countries'] ) ) {
			$merged['geo']['countries'] = $stored['geo']['countries'];
		}

		return $merged;
	}

	/**
	 * Ideiglenes beállítások használata mentés nélkül (az élő előnézethez).
	 *
	 * @param array $settings Teljes, tisztított beállítástömb.
	 */
	public function override( array $settings ) {
		$this->cache = $settings;
	}

	/**
	 * Egy érték lekérése pont-jelöléssel: get( 'appearance.colors.primary' ).
	 *
	 * @param string $path    Útvonal.
	 * @param mixed  $default Alapértelmezett érték.
	 * @return mixed
	 */
	public function get( $path, $default = null ) {
		$value = $this->all();
		foreach ( explode( '.', $path ) as $key ) {
			if ( is_array( $value ) && array_key_exists( $key, $value ) ) {
				$value = $value[ $key ];
			} else {
				return $default;
			}
		}
		return $value;
	}

	/**
	 * Teljes beállítástömb mentése.
	 *
	 * @param array $settings Beállítások.
	 * @return bool
	 */
	public function save( array $settings ) {
		$settings['version'] = SZESUTI_VERSION;
		$this->cache         = null;
		return update_option( SZESUTI_OPTION, $settings, false );
	}

	/**
	 * Egy szekció mentése – a többi érintetlen marad.
	 *
	 * @param string $section Szekció kulcs.
	 * @param mixed  $data    Adat.
	 * @return bool
	 */
	public function save_section( $section, $data ) {
		$stored = get_option( SZESUTI_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$stored[ $section ] = $data;
		return $this->save( $stored );
	}

	/** Aktiváláskori telepítés – csak ha még nincs mentett beállítás. */
	public function install_defaults() {
		$stored = get_option( SZESUTI_OPTION, null );

		if ( ! is_array( $stored ) ) {
			$defaults                = $this->defaults();
			$defaults['log']['salt'] = wp_generate_password( 32, false, false );
			$defaults['cookies']     = SZESUTI_Cookie_DB::starter_list();
			update_option( SZESUTI_OPTION, $defaults, false );
			return;
		}

		if ( empty( $stored['log']['salt'] ) ) {
			$stored['log']['salt'] = wp_generate_password( 32, false, false );
			update_option( SZESUTI_OPTION, $stored, false );
		}
	}

	/**
	 * Aktív (engedélyezett) kategóriák.
	 *
	 * @return array
	 */
	public function active_categories() {
		$out = array();
		foreach ( (array) $this->get( 'categories', array() ) as $cat ) {
			if ( empty( $cat['slug'] ) || empty( $cat['enabled'] ) ) {
				continue;
			}
			$out[] = $cat;
		}
		return $out;
	}

	/**
	 * Aktív kategória slugok.
	 *
	 * @return string[]
	 */
	public function category_slugs() {
		return wp_list_pluck( $this->active_categories(), 'slug' );
	}

	/**
	 * Mély összefésülés: az $override értékei felülírják az alapot, a hiányzó kulcsok megmaradnak.
	 *
	 * @param array $base     Alap.
	 * @param array $override Felülírás.
	 * @return array
	 */
	private function merge_deep( array $base, array $override ) {
		foreach ( $override as $key => $value ) {
			if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) && $this->is_assoc( $base[ $key ] ) ) {
				$base[ $key ] = $this->merge_deep( $base[ $key ], $value );
			} else {
				$base[ $key ] = $value;
			}
		}
		return $base;
	}

	/**
	 * Asszociatív tömb-e?
	 *
	 * @param array $arr Tömb.
	 * @return bool
	 */
	private function is_assoc( array $arr ) {
		if ( array() === $arr ) {
			return true;
		}
		return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
	}

	/* ---------------------------------------------------------------------
	 * Export / import
	 * ------------------------------------------------------------------ */

	/**
	 * Exportálható JSON string.
	 *
	 * @param bool $include_secrets A naplózási salting exportálása is.
	 * @return string
	 */
	public function export_json( $include_secrets = false ) {
		$data = $this->all();

		unset( $data['version'] );

		if ( ! $include_secrets ) {
			$data['log']['salt'] = '';
		}

		$payload = array(
			'_szesuti'  => true,
			'_version'  => SZESUTI_VERSION,
			'_exported' => gmdate( 'c' ),
			'_site'     => home_url(),
			'settings'  => $data,
		);

		return wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * JSON importálása.
	 *
	 * @param string $json    JSON tartalom.
	 * @param array  $options keep_urls, keep_salt, keep_texts.
	 * @return true|WP_Error
	 */
	public function import_json( $json, $options = array() ) {
		$options = wp_parse_args(
			$options,
			array(
				'keep_urls'  => true,
				'keep_salt'  => true,
				'keep_texts' => false,
			)
		);

		// Jegyzettömbben elmentett fájl elején UTF-8 BOM lehet.
		$decoded = json_decode( preg_replace( '/^\xEF\xBB\xBF/', '', trim( (string) $json ) ), true );
		if ( null === $decoded || ! is_array( $decoded ) ) {
			return new WP_Error( 'szesuti_import_json', __( 'A fájl nem érvényes JSON.', 'szesuti' ) );
		}

		$incoming = ( isset( $decoded['settings'] ) && is_array( $decoded['settings'] ) ) ? $decoded['settings'] : $decoded;

		if ( empty( $incoming['general'] ) && empty( $incoming['appearance'] ) ) {
			return new WP_Error( 'szesuti_import_shape', __( 'A fájl nem SZetemény beállításexport.', 'szesuti' ) );
		}

		// Régebbi verzióból származó exportból hiányoznak az újabb beállítások. Ezek
		// az alapértéket kapják – különben a sanitize() a hiányzó kapcsolót 0-nak
		// venné, és pl. a szigorú blokkolás csendben kikapcsolna az importtal.
		$all_defaults = $this->defaults();
		foreach ( array( 'general', 'geo', 'appearance', 'blocker', 'gcm', 'log', 'advanced' ) as $section ) {
			if ( ! isset( $incoming[ $section ] ) || ! is_array( $incoming[ $section ] ) ) {
				continue;
			}
			foreach ( $all_defaults[ $section ] as $key => $value ) {
				if ( ! array_key_exists( $key, $incoming[ $section ] ) ) {
					$incoming[ $section ][ $key ] = $value;
				}
			}
		}

		$current = $this->all();

		if ( $options['keep_urls'] ) {
			foreach ( array( 'privacy_url', 'cookie_policy_url', 'imprint_url' ) as $key ) {
				$incoming['general'][ $key ] = isset( $current['general'][ $key ] ) ? $current['general'][ $key ] : '';
			}
		}

		if ( $options['keep_salt'] ) {
			$incoming['log']['salt'] = ! empty( $current['log']['salt'] ) ? $current['log']['salt'] : wp_generate_password( 32, false, false );
		}

		if ( $options['keep_texts'] ) {
			$incoming['texts']            = isset( $current['texts'] ) ? $current['texts'] : $this->default_texts();
			$incoming['languages']        = isset( $current['languages'] ) ? $current['languages'] : array( 'hu' );
			$incoming['default_language'] = isset( $current['default_language'] ) ? $current['default_language'] : 'hu';
		}

		$this->save( $this->sanitize( $incoming ) );

		return true;
	}

	/* ---------------------------------------------------------------------
	 * Tisztítás
	 * ------------------------------------------------------------------ */

	/**
	 * Teljes beállítástömb tisztítása mentés előtt.
	 *
	 * @param array $raw Nyers adat.
	 * @return array
	 */
	public function sanitize( array $raw ) {
		$d   = $this->defaults();
		$out = $d;

		/* --- general --- */
		$g              = ( isset( $raw['general'] ) && is_array( $raw['general'] ) ) ? $raw['general'] : array();
		$out['general'] = array(
			'enabled'             => $this->bool( $g, 'enabled' ),
			'consent_expiry_days' => $this->int_range( $g, 'consent_expiry_days', 1, 730, 180 ),
			'policy_version'      => sanitize_text_field( $this->str( $g, 'policy_version', '1' ) ),
			'reload_on_accept'    => $this->bool( $g, 'reload_on_accept' ),
			'reload_on_reject'    => $this->bool( $g, 'reload_on_reject' ),
			'show_on_admin'       => $this->bool( $g, 'show_on_admin' ),
			'respect_dnt'         => $this->bool( $g, 'respect_dnt' ),
			'respect_gpc'         => $this->bool( $g, 'respect_gpc' ),
			'hide_from_bots'      => $this->bool( $g, 'hide_from_bots' ),
			'cookie_name'         => $this->sanitize_cookie_name( $this->str( $g, 'cookie_name', 'szesuti_consent' ) ),
			'cookie_domain'       => sanitize_text_field( $this->str( $g, 'cookie_domain', '' ) ),
			'auto_open_delay'     => $this->int_range( $g, 'auto_open_delay', 0, 60000, 0 ),
			'reopen_button'       => $this->bool( $g, 'reopen_button' ),
			'reopen_icon'         => $this->one_of( $this->str( $g, 'reopen_icon', '' ), array( 'cookie', 'shield', 'lock', 'pixel', 'custom' ), 'cookie' ),
			'reopen_icon_url'     => esc_url_raw( $this->str( $g, 'reopen_icon_url', '' ) ),
			'reopen_position'     => $this->one_of( $this->str( $g, 'reopen_position', '' ), array( 'bottom-left', 'bottom-right', 'top-left', 'top-right' ), 'bottom-left' ),
			'reopen_hide_mobile'  => $this->bool( $g, 'reopen_hide_mobile' ),
			'reopen_offset_x'     => $this->int_range( $g, 'reopen_offset_x', 0, 400, 20 ),
			'reopen_offset_y'     => $this->int_range( $g, 'reopen_offset_y', 0, 400, 20 ),
			'privacy_url'         => esc_url_raw( $this->str( $g, 'privacy_url', '' ) ),
			'cookie_policy_url'   => esc_url_raw( $this->str( $g, 'cookie_policy_url', '' ) ),
			'imprint_url'         => esc_url_raw( $this->str( $g, 'imprint_url', '' ) ),
			'exclude_urls'        => $this->sanitize_multiline( $this->str( $g, 'exclude_urls', '' ) ),
			'consent_mode_ui'     => $this->one_of( $this->str( $g, 'consent_mode_ui', '' ), array( 'granular', 'simple' ), 'granular' ),
		);

		/* --- geo --- */
		$geo       = ( isset( $raw['geo'] ) && is_array( $raw['geo'] ) ) ? $raw['geo'] : array();
		$countries = isset( $geo['countries'] ) ? $geo['countries'] : array();
		if ( is_string( $countries ) ) {
			$countries = preg_split( '/[\s,;]+/', $countries, -1, PREG_SPLIT_NO_EMPTY );
		}
		$countries = array_values(
			array_unique(
				array_filter(
					array_map(
						function ( $c ) {
							$c = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $c ) );
							return 2 === strlen( $c ) ? $c : '';
						},
						(array) $countries
					)
				)
			)
		);

		$out['geo'] = array(
			'enabled'   => $this->bool( $geo, 'enabled' ),
			'mode'      => $this->one_of( $this->str( $geo, 'mode', '' ), array( 'eu', 'custom' ), 'eu' ),
			'countries' => $countries,
			'unknown'   => $this->one_of( $this->str( $geo, 'unknown', '' ), array( 'show', 'hide' ), 'show' ),
			'method'    => $this->one_of( $this->str( $geo, 'method', '' ), array( 'rest', 'server' ), 'rest' ),
		);

		/* --- appearance --- */
		$a                 = ( isset( $raw['appearance'] ) && is_array( $raw['appearance'] ) ) ? $raw['appearance'] : array();
		$out['appearance'] = array(
			'layout'          => $this->one_of( $this->str( $a, 'layout', '' ), array( 'box', 'bar', 'modal' ), 'box' ),
			'position'        => $this->one_of( $this->str( $a, 'position', '' ), array( 'bottom-left', 'bottom-right', 'bottom-center', 'top-left', 'top-right', 'top-center' ), 'bottom-left' ),
			'theme'           => $this->one_of( $this->str( $a, 'theme', '' ), array( 'light', 'dark', 'auto' ), 'light' ),
			'width'           => $this->int_range( $a, 'width', 260, 1200, 420 ),
			'radius'          => $this->int_range( $a, 'radius', 0, 40, 16 ),
			'font_size'       => $this->int_range( $a, 'font_size', 10, 22, 14 ),
			'font_family'     => sanitize_text_field( $this->str( $a, 'font_family', '' ) ),
			'shadow'          => $this->one_of( $this->str( $a, 'shadow', '' ), array( 'none', 'sm', 'md', 'lg' ), 'lg' ),
			'animation'       => $this->one_of( $this->str( $a, 'animation', '' ), array( 'none', 'fade', 'slide-up', 'slide-side', 'zoom' ), 'slide-up' ),
			'overlay'         => $this->bool( $a, 'overlay' ),
			'overlay_opacity' => $this->int_range( $a, 'overlay_opacity', 0, 95, 55 ),
			// Alsó határ: a banner ne kerülhessen véletlenül a téma fejléce vagy menüje alá.
			'z_index'         => $this->int_range( $a, 'z_index', 10000, 2147483000, 999990 ),
			'btn_radius'      => $this->int_range( $a, 'btn_radius', 0, 40, 10 ),
			'btn_style'       => $this->one_of( $this->str( $a, 'btn_style', '' ), array( 'solid', 'outline', 'soft' ), 'solid' ),
			'btn_layout'      => $this->one_of( $this->str( $a, 'btn_layout', '' ), array( 'row', 'column', 'auto' ), 'row' ),
			'btn_order'       => $this->sanitize_btn_order( isset( $a['btn_order'] ) ? $a['btn_order'] : '' ),
			'show_reject'     => $this->bool( $a, 'show_reject' ),
			'show_settings'   => $this->bool( $a, 'show_settings' ),
			'show_close_x'    => $this->bool( $a, 'show_close_x' ),
			'equal_buttons'   => $this->bool( $a, 'equal_buttons' ),
			'logo_url'        => esc_url_raw( $this->str( $a, 'logo_url', '' ) ),
			'logo_height'     => $this->int_range( $a, 'logo_height', 16, 120, 40 ),
			'icon'            => $this->one_of( $this->str( $a, 'icon', '' ), array( 'cookie', 'shield', 'lock', 'pixel', 'custom', 'none' ), 'cookie' ),
			'show_powered_by' => $this->bool( $a, 'show_powered_by' ),
			'custom_css'      => $this->sanitize_css( $this->str( $a, 'custom_css', '' ) ),
			'colors'          => $this->sanitize_colors( isset( $a['colors'] ) ? $a['colors'] : array(), $d['appearance']['colors'] ),
			'colors_dark'     => $this->sanitize_colors( isset( $a['colors_dark'] ) ? $a['colors_dark'] : array(), $d['appearance']['colors_dark'] ),
		);

		/* --- categories --- */
		$cats  = ( isset( $raw['categories'] ) && is_array( $raw['categories'] ) ) ? $raw['categories'] : $d['categories'];
		$clean = array();
		$seen  = array();
		foreach ( $cats as $cat ) {
			if ( ! is_array( $cat ) || empty( $cat['slug'] ) ) {
				continue;
			}
			$slug = sanitize_key( $cat['slug'] );
			if ( '' === $slug || isset( $seen[ $slug ] ) ) {
				continue;
			}
			$seen[ $slug ] = true;
			$locked        = ( 'necessary' === $slug ) ? 1 : $this->bool( $cat, 'locked' );
			$clean[]       = array(
				'slug'    => $slug,
				'locked'  => $locked,
				'default' => $locked ? 1 : $this->bool( $cat, 'default' ),
				'enabled' => ( 'necessary' === $slug ) ? 1 : $this->bool( $cat, 'enabled' ),
			);
		}
		if ( ! isset( $seen['necessary'] ) ) {
			array_unshift(
				$clean,
				array(
					'slug'    => 'necessary',
					'locked'  => 1,
					'default' => 1,
					'enabled' => 1,
				)
			);
		}
		$out['categories'] = $clean;

		/* --- nyelvek és szövegek --- */
		$langs = isset( $raw['languages'] ) ? $raw['languages'] : $d['languages'];
		if ( is_string( $langs ) ) {
			$langs = preg_split( '/[\s,;]+/', $langs, -1, PREG_SPLIT_NO_EMPTY );
		}
		$langs = array_values(
			array_unique(
				array_filter(
					array_map(
						function ( $l ) {
							$l = strtolower( preg_replace( '/[^A-Za-z_-]/', '', (string) $l ) );
							return ( strlen( $l ) >= 2 && strlen( $l ) <= 5 ) ? $l : '';
						},
						(array) $langs
					)
				)
			)
		);
		if ( empty( $langs ) ) {
			$langs = array( 'hu' );
		}

		$default_language        = isset( $raw['default_language'] ) ? (string) $raw['default_language'] : '';
		$out['languages']        = $langs;
		$out['default_language'] = in_array( $default_language, $langs, true ) ? $default_language : $langs[0];
		$out['texts']            = $this->sanitize_texts( isset( $raw['texts'] ) ? $raw['texts'] : array(), $langs, $out['categories'] );

		/* --- blocker --- */
		$b     = ( isset( $raw['blocker'] ) && is_array( $raw['blocker'] ) ) ? $raw['blocker'] : array();
		$rules = array();
		if ( isset( $b['rules'] ) && is_array( $b['rules'] ) ) {
			foreach ( $b['rules'] as $id => $rule ) {
				$id = sanitize_key( $id );
				if ( '' === $id || ! is_array( $rule ) ) {
					continue;
				}
				$rules[ $id ] = array(
					'enabled'  => $this->bool( $rule, 'enabled' ),
					'category' => sanitize_key( $this->str( $rule, 'category', '' ) ),
				);
			}
		}

		$custom = array();
		if ( isset( $b['custom'] ) && is_array( $b['custom'] ) ) {
			foreach ( $b['custom'] as $rule ) {
				if ( ! is_array( $rule ) || '' === trim( (string) ( isset( $rule['pattern'] ) ? $rule['pattern'] : '' ) ) ) {
					continue;
				}
				$custom[] = array(
					'label'    => sanitize_text_field( $this->str( $rule, 'label', '' ) ),
					'pattern'  => sanitize_text_field( $rule['pattern'] ),
					'category' => sanitize_key( $this->str( $rule, 'category', 'marketing' ) ),
					'type'     => $this->one_of( $this->str( $rule, 'type', '' ), array( 'script', 'iframe', 'both' ), 'both' ),
					'enabled'  => $this->bool( $rule, 'enabled' ),
				);
			}
		}

		$unknown_category = sanitize_key( $this->str( $b, 'unknown_category', 'marketing' ) );
		if ( ! in_array( $unknown_category, wp_list_pluck( $out['categories'], 'slug' ), true ) || 'necessary' === $unknown_category ) {
			$unknown_category = 'marketing';
		}

		$out['blocker'] = array(
			'enabled'             => $this->bool( $b, 'enabled' ),
			'auto'                => $this->bool( $b, 'auto' ),
			'strict'              => $this->bool( $b, 'strict' ),
			'unknown_category'    => $unknown_category,
			'safe_hosts'          => $this->bool( $b, 'safe_hosts' ),
			'guard'               => $this->bool( $b, 'guard' ),
			'placeholder_enabled' => $this->bool( $b, 'placeholder_enabled' ),
			'rules'               => $rules,
			'custom'              => $custom,
			'skip_patterns'       => $this->sanitize_multiline( $this->str( $b, 'skip_patterns', '' ) ),
		);

		/* --- gcm --- */
		$c         = ( isset( $raw['gcm'] ) && is_array( $raw['gcm'] ) ) ? $raw['gcm'] : array();
		$map       = array();
		$cat_slugs = wp_list_pluck( $out['categories'], 'slug' );
		foreach ( array_keys( $d['gcm']['map'] ) as $signal ) {
			$value          = isset( $c['map'][ $signal ] ) ? sanitize_key( $c['map'][ $signal ] ) : $d['gcm']['map'][ $signal ];
			$map[ $signal ] = in_array( $value, $cat_slugs, true ) ? $value : '';
		}

		$datalayer = preg_replace( '/[^A-Za-z0-9_]/', '', $this->str( $c, 'datalayer', 'dataLayer' ) );
		if ( '' === $datalayer ) {
			$datalayer = 'dataLayer';
		}

		$out['gcm'] = array(
			'enabled'            => $this->bool( $c, 'enabled' ),
			'regions'            => strtoupper( $this->sanitize_multiline( $this->str( $c, 'regions', '' ) ) ),
			'wait_for_update'    => $this->int_range( $c, 'wait_for_update', 0, 5000, 500 ),
			'url_passthrough'    => $this->bool( $c, 'url_passthrough' ),
			'ads_data_redaction' => $this->bool( $c, 'ads_data_redaction' ),
			'datalayer'          => $datalayer,
			'gtm_id'             => preg_replace( '/[^A-Z0-9\-]/', '', strtoupper( $this->str( $c, 'gtm_id', '' ) ) ),
			'inject_gtm'         => $this->bool( $c, 'inject_gtm' ),
			'dont_block_google'  => $this->bool( $c, 'dont_block_google' ),
			'push_events'        => $this->bool( $c, 'push_events' ),
			'map'                => $map,
		);

		/* --- cookies --- */
		$cookies = array();
		if ( isset( $raw['cookies'] ) && is_array( $raw['cookies'] ) ) {
			foreach ( $raw['cookies'] as $ck ) {
				if ( ! is_array( $ck ) || '' === trim( (string) ( isset( $ck['name'] ) ? $ck['name'] : '' ) ) ) {
					continue;
				}
				$cookies[] = array(
					'name'     => sanitize_text_field( $ck['name'] ),
					'provider' => sanitize_text_field( $this->str( $ck, 'provider', '' ) ),
					'category' => sanitize_key( $this->str( $ck, 'category', 'necessary' ) ),
					'duration' => sanitize_text_field( $this->str( $ck, 'duration', '' ) ),
					'type'     => $this->one_of( $this->str( $ck, 'type', '' ), array( 'http', 'html', 'pixel' ), 'http' ),
					'desc_hu'  => sanitize_textarea_field( $this->str( $ck, 'desc_hu', '' ) ),
					'desc_en'  => sanitize_textarea_field( $this->str( $ck, 'desc_en', '' ) ),
				);
			}
		}
		$out['cookies'] = $cookies;

		/* --- log --- */
		$l          = ( isset( $raw['log'] ) && is_array( $raw['log'] ) ) ? $raw['log'] : array();
		$salt       = sanitize_text_field( $this->str( $l, 'salt', '' ) );
		$out['log'] = array(
			'enabled'        => $this->bool( $l, 'enabled' ),
			'retention_days' => $this->int_range( $l, 'retention_days', 0, 3650, 365 ),
			'store_url'      => $this->bool( $l, 'store_url' ),
			'store_ua'       => $this->bool( $l, 'store_ua' ),
			'store_country'  => $this->bool( $l, 'store_country' ),
			'salt'           => '' !== $salt ? $salt : wp_generate_password( 32, false, false ),
		);

		/* --- advanced --- */
		$adv             = ( isset( $raw['advanced'] ) && is_array( $raw['advanced'] ) ) ? $raw['advanced'] : array();
		$out['advanced'] = array(
			'update_url'               => esc_url_raw( $this->str( $adv, 'update_url', '' ) ),
			'delete_data_on_uninstall' => $this->bool( $adv, 'delete_data_on_uninstall' ),
			'load_css'                 => $this->bool( $adv, 'load_css' ),
			'footer_js'                => $this->bool( $adv, 'footer_js' ),
			'debug'                    => $this->bool( $adv, 'debug' ),
			'rest_enabled'             => $this->bool( $adv, 'rest_enabled' ),
		);

		return $out;
	}

	/* ------------------------------------------------------------------ */

	/**
	 * Logikai érték kinyerése.
	 *
	 * @param array  $arr Tömb.
	 * @param string $key Kulcs.
	 * @return int
	 */
	private function bool( $arr, $key ) {
		if ( ! isset( $arr[ $key ] ) ) {
			return 0;
		}
		$v = $arr[ $key ];
		if ( is_string( $v ) ) {
			$v = trim( $v );
			if ( '' === $v || '0' === $v || 'false' === strtolower( $v ) || 'no' === strtolower( $v ) ) {
				return 0;
			}
			return 1;
		}
		return $v ? 1 : 0;
	}

	private function str( $arr, $key, $default = '' ) {
		if ( ! isset( $arr[ $key ] ) || is_array( $arr[ $key ] ) ) {
			return $default;
		}
		return (string) $arr[ $key ];
	}

	private function int_range( $arr, $key, $min, $max, $default ) {
		if ( ! isset( $arr[ $key ] ) || '' === $arr[ $key ] || is_array( $arr[ $key ] ) ) {
			return $default;
		}
		return max( $min, min( $max, (int) $arr[ $key ] ) );
	}

	private function one_of( $value, array $allowed, $fallback ) {
		$value = is_string( $value ) ? $value : '';
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	private function sanitize_cookie_name( $name ) {
		$name = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $name );
		return '' !== $name ? $name : 'szesuti_consent';
	}

	private function sanitize_btn_order( $value ) {
		$allowed = array( 'accept', 'reject', 'settings' );
		$parts   = is_array( $value ) ? $value : explode( ',', (string) $value );
		$clean   = array();
		foreach ( $parts as $p ) {
			$p = sanitize_key( trim( (string) $p ) );
			if ( in_array( $p, $allowed, true ) && ! in_array( $p, $clean, true ) ) {
				$clean[] = $p;
			}
		}
		foreach ( $allowed as $p ) {
			if ( ! in_array( $p, $clean, true ) ) {
				$clean[] = $p;
			}
		}
		return implode( ',', $clean );
	}

	private function sanitize_multiline( $value ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $value );
		$lines = array_filter( array_map( 'trim', array_map( 'sanitize_text_field', $lines ) ), 'strlen' );
		return implode( "\n", $lines );
	}

	private function sanitize_css( $css ) {
		$css = wp_strip_all_tags( (string) $css );
		return trim( str_replace( array( '<', '>' ), '', $css ) );
	}

	private function sanitize_colors( $colors, array $defaults ) {
		$out = array();
		foreach ( $defaults as $key => $fallback ) {
			$val         = ( is_array( $colors ) && isset( $colors[ $key ] ) ) ? sanitize_hex_color( (string) $colors[ $key ] ) : null;
			$out[ $key ] = $val ? $val : $fallback;
		}
		return $out;
	}

	/**
	 * Szövegek tisztítása nyelvenként.
	 *
	 * @param mixed $texts Nyers szövegek.
	 * @param array $langs Engedélyezett nyelvek.
	 * @param array $cats  Kategóriák.
	 * @return array
	 */
	private function sanitize_texts( $texts, array $langs, array $cats ) {
		$defaults = $this->default_texts();
		$out      = array();

		foreach ( $langs as $lang ) {
			$base  = isset( $defaults[ $lang ] ) ? $defaults[ $lang ] : $defaults['en'];
			$in    = ( is_array( $texts ) && isset( $texts[ $lang ] ) && is_array( $texts[ $lang ] ) ) ? $texts[ $lang ] : array();
			$clean = array();

			foreach ( $base as $key => $default_value ) {
				if ( 'cat' === $key ) {
					continue;
				}
				$value = isset( $in[ $key ] ) && ! is_array( $in[ $key ] ) ? $in[ $key ] : $default_value;

				if ( in_array( $key, array( 'message', 'prefs_intro' ), true ) ) {
					$clean[ $key ] = wp_kses( (string) $value, $this->allowed_html() );
				} else {
					$clean[ $key ] = sanitize_text_field( (string) $value );
				}
			}

			$clean['cat'] = array();
			foreach ( $cats as $cat ) {
				$slug         = $cat['slug'];
				$cat_defaults = isset( $base['cat'][ $slug ] )
					? $base['cat'][ $slug ]
					: array(
						'name' => ucfirst( $slug ),
						'desc' => '',
					);

				$name = isset( $in['cat'][ $slug ]['name'] ) ? $in['cat'][ $slug ]['name'] : $cat_defaults['name'];
				$desc = isset( $in['cat'][ $slug ]['desc'] ) ? $in['cat'][ $slug ]['desc'] : $cat_defaults['desc'];

				$clean['cat'][ $slug ] = array(
					'name' => sanitize_text_field( (string) $name ),
					'desc' => wp_kses( (string) $desc, $this->allowed_html() ),
				);
			}

			$out[ $lang ] = $clean;
		}

		return $out;
	}

	/**
	 * A frontend szövegekben engedélyezett HTML.
	 *
	 * @return array
	 */
	public function allowed_html() {
		return array(
			'a'      => array(
				'href'   => array(),
				'title'  => array(),
				'target' => array(),
				'rel'    => array(),
				'class'  => array(),
			),
			'strong' => array(),
			'b'      => array(),
			'em'     => array(),
			'i'      => array(),
			'br'     => array(),
			'span'   => array( 'class' => array() ),
			'p'      => array( 'class' => array() ),
			'ul'     => array(),
			'ol'     => array(),
			'li'     => array(),
			'small'  => array(),
		);
	}
}
