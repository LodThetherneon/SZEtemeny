<?php
/**
 * Szkript-, beágyazás- és képpontblokkoló.
 *
 * Két rétegben dolgozik:
 *
 * 1. Szerveroldalon a teljes oldal kimenetét pufferbe olvassuk, és a blokkolandó
 *    elemeket átírjuk: a szkriptek `type="text/plain"` típust kapnak, a `src` /
 *    `href` pedig `data-szesuti-src` / `data-szesuti-href` attribútumba kerül.
 *    Így a böngésző el sem kezdi letölteni őket.
 *
 * 2. Egy „őr” szkript a <head> legelején (print_guard) elfogja azokat az elemeket
 *    és kéréseket, amiket más szkriptek később, futás közben hoznak létre.
 *
 * Döntési sorrend minden külső forrásnál (classify()):
 *   saját fájl / engedélyezett kivétel  -> átmegy
 *   ismert, bekapcsolt szolgáltatás     -> blokkolva, a saját kategóriájával
 *   ismert, tudatosan kikapcsolt szolg. -> átmegy (pl. reCAPTCHA, Google Fonts)
 *   saját domain és aldomainjei         -> átmegy
 *   beépített biztonságos CDN           -> átmegy
 *   minden más külső forrás             -> szigorú módban blokkolva
 *
 * @package SZESUTI
 */

defined( 'ABSPATH' ) || exit;

class SZESUTI_Blocker {

	/**
	 * Másodszintű magyar (és néhány gyakori) domainek, ahol a „saját domain”
	 * három címkéből áll (pl. valami.co.hu).
	 */
	const SECOND_LEVEL = array( 'co', 'com', 'org', 'net', 'gov', 'edu', 'ac', 'info', 'tm', 'priv', 'nui', 'sport', 'media', 'hotel', 'city', 'film', 'games', 'shop', 'bolt', 'forum', 'ingatlan', 'jogasz', 'konyvelo', 'lakas', 'news', 'reklam', 'suli', 'tozsde', 'utazas', 'video', 'agrar', '2000' );

	/** @var SZESUTI_Settings */
	private $settings;

	/** @var array|null Bekapcsolt szabályok. */
	private $rules = null;

	/** @var array|null Ismert, de tudatosan engedélyezett szolgáltatások mintái. */
	private $allowed_known = null;

	/** @var array|null A saját oldal hosztja és domainje. */
	private $site = null;

	/** @var int Hány elemet blokkoltunk ezen az oldalon. */
	private $blocked_count = 0;

	/** @var array Blokkolt szolgáltatás azonosítók. */
	private $blocked_services = array();

	/** @var array Blokkolt ismeretlen hosztok: host => kategória. */
	private $unknown_hosts = array();

	public function __construct( SZESUTI_Settings $settings ) {
		$this->settings = $settings;

		add_action( 'template_redirect', array( $this, 'maybe_start_buffer' ), 1 );
		// Az őrnek minden más szkript ELŐTT kell lefutnia.
		add_action( 'wp_head', array( $this, 'print_guard' ), -1000 );
	}

	/** Elindítja a kimeneti puffert, ha a blokkolásnak van értelme. */
	public function maybe_start_buffer() {
		if ( ! $this->should_run() ) {
			return;
		}

		ob_start( array( $this, 'process' ) );
	}

	/**
	 * Fusson-e a blokkoló ezen a kérésen?
	 *
	 * @return bool
	 */
	private function should_run() {
		if ( ! $this->settings->get( 'general.enabled', 1 ) || ! $this->settings->get( 'blocker.enabled', 1 ) ) {
			return false;
		}

		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			return false;
		}

		if ( is_feed() || is_robots() || is_trackback() || is_preview() || is_embed() ) {
			return false;
		}

		if ( is_customize_preview() ) {
			return false;
		}

		// A sütiszkenner keretében (csak adminnak, érvényes kóddal) minden úgy fut,
		// mintha a látogató mindent elfogadott volna – így derül ki, mi állít be sütit.
		if ( SZESUTI_Scanner::is_scan_request() ) {
			return false;
		}

		// Kizárt URL-ek.
		$excluded = trim( (string) $this->settings->get( 'general.exclude_urls', '' ) );
		if ( '' !== $excluded ) {
			$request = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			foreach ( preg_split( '/\r\n|\r|\n/', $excluded ) as $pattern ) {
				$pattern = trim( $pattern );
				if ( '' !== $pattern && false !== stripos( $request, $pattern ) ) {
					return false;
				}
			}
		}

		// Geo szerveroldali módban: a célterületen kívül nincs banner és nincs
		// feloldó JS sem, ezért blokkolni sem szabad – különben a szkriptek
		// örökre blokkolva maradnának. (REST módban ezt a böngésző intézi.)
		$geo = szesuti()->geo;
		if ( $geo->is_enabled() && 'server' === $this->settings->get( 'geo.method', 'rest' ) && ! $geo->should_show() ) {
			return false;
		}

		/**
		 * A blokkoló futásának végső felülbírálása.
		 *
		 * @param bool $run Fusson-e.
		 */
		return (bool) apply_filters( 'szesuti_run_blocker', true );
	}

	/**
	 * A puffer feldolgozása.
	 *
	 * @param string $html A teljes oldal HTML-je.
	 * @return string
	 */
	public function process( $html ) {
		if ( ! is_string( $html ) || strlen( $html ) < 100 ) {
			return $html;
		}

		// Csak HTML dokumentumot dolgozunk fel.
		if ( false === stripos( $html, '<html' ) && false === stripos( $html, '<body' ) ) {
			return $html;
		}

		$this->get_rules();

		$html = preg_replace_callback( '#<script\b([^>]*)>(.*?)</script\s*>#is', array( $this, 'handle_script' ), $html );
		$html = preg_replace_callback( '#<iframe\b([^>]*)>(.*?)</iframe\s*>#is', array( $this, 'handle_iframe' ), $html );
		$html = preg_replace_callback( '#<link\b([^>]*?)/?>#is', array( $this, 'handle_link' ), $html );
		$html = preg_replace_callback( '#<img\b([^>]*?)/?>#is', array( $this, 'handle_img' ), $html );

		if ( $this->blocked_count > 0 && $this->settings->get( 'advanced.debug', 0 ) ) {
			$note = (int) $this->blocked_count . ' blokkolt elem (' . implode( ', ', array_unique( $this->blocked_services ) ) . ')';
			if ( ! empty( $this->unknown_hosts ) ) {
				$note .= ' – ismeretlen külső források: ' . implode( ', ', array_keys( $this->unknown_hosts ) );
			}
			$html = str_ireplace( '</body>', "\n<!-- SZetemény: " . esc_html( $note ) . " -->\n</body>", $html );
		}

		$this->remember_unknown_hosts();

		return $html;
	}

	/* ---------------------------------------------------------------------
	 * Elemkezelők
	 * ------------------------------------------------------------------ */

	/**
	 * `<script>` elem feldolgozása.
	 *
	 * @param array $m Regex találat.
	 * @return string
	 */
	private function handle_script( $m ) {
		$attrs = $m[1];
		$body  = $m[2];
		$full  = $m[0];

		if ( $this->already_handled( $attrs ) ) {
			return $full;
		}

		// Nem JavaScript típusú script elemeket (pl. JSON-LD, sablon) sosem bántunk.
		$type = $this->get_attr( $attrs, 'type' );
		if ( '' !== $type && ! $this->is_js_type( $type ) ) {
			return $full;
		}

		$src = $this->get_attr( $attrs, 'src' );

		if ( $this->is_own_script( $attrs, $src, $body ) ) {
			return $full;
		}

		if ( '' !== $src ) {
			$rule = $this->classify( $src, 'script' );
		} else {
			// Beágyazott kódnál csak az ismert szolgáltatások mintái döntenek;
			// az általa később beszúrt elemeket az őr kapja el.
			$rule = $this->is_skipped( $body ) ? null : $this->match( $body, 'script' );
		}

		if ( ! $rule ) {
			return $full;
		}

		$new_attrs = $attrs;

		if ( '' !== $type ) {
			$new_attrs = $this->remove_attr( $new_attrs, 'type' );
		}
		if ( '' !== $src ) {
			$new_attrs = $this->rename_attr( $new_attrs, 'src', 'data-szesuti-src' );
		}

		$new_attrs  = rtrim( $new_attrs );
		$new_attrs .= ' type="text/plain"' . $this->marker( $rule, 'script' );

		$this->count( $rule );

		return '<script' . $new_attrs . '>' . $body . '</script>';
	}

	/**
	 * `<iframe>` elem feldolgozása.
	 *
	 * @param array $m Regex találat.
	 * @return string
	 */
	private function handle_iframe( $m ) {
		$attrs = $m[1];
		$body  = $m[2];
		$full  = $m[0];

		if ( $this->already_handled( $attrs ) ) {
			return $full;
		}

		$src = $this->get_attr( $attrs, 'src' );
		if ( '' === $src ) {
			return $full;
		}

		$rule = $this->classify( $src, 'iframe' );
		if ( ! $rule ) {
			return $full;
		}

		$new_attrs  = $this->rename_attr( $attrs, 'src', 'data-szesuti-src' );
		$new_attrs  = rtrim( $new_attrs ) . $this->marker( $rule, 'iframe' );
		$iframe     = '<iframe' . $new_attrs . '>' . $body . '</iframe>';

		$this->count( $rule );

		if ( ! $this->settings->get( 'blocker.placeholder_enabled', 1 ) ) {
			return $iframe;
		}

		return $this->placeholder( $iframe, $rule );
	}

	/**
	 * `<link>` elem feldolgozása (stíluslap, előtöltés).
	 *
	 * A preconnect / dns-prefetch nem küld HTTP-kérést, így sütit sem kaphat – azokat békén hagyjuk.
	 *
	 * @param array $m Regex találat.
	 * @return string
	 */
	private function handle_link( $m ) {
		$attrs = $m[1];
		$full  = $m[0];

		if ( $this->already_handled( $attrs ) ) {
			return $full;
		}

		$rel = strtolower( $this->get_attr( $attrs, 'rel' ) );
		if ( false === strpos( $rel, 'stylesheet' ) && false === strpos( $rel, 'preload' ) && false === strpos( $rel, 'modulepreload' ) ) {
			return $full;
		}

		$href = $this->get_attr( $attrs, 'href' );
		if ( '' === $href ) {
			return $full;
		}

		$rule = $this->classify( $href, 'link' );
		if ( ! $rule ) {
			return $full;
		}

		$new_attrs = $this->rename_attr( $attrs, 'href', 'data-szesuti-href' );
		$new_attrs = rtrim( $new_attrs ) . $this->marker( $rule, 'link' );

		$this->count( $rule );

		return '<link' . $new_attrs . '>';
	}

	/**
	 * `<img>` elem feldolgozása: követőképpontok.
	 *
	 * Az ismert mérőszolgáltatások képeit mindig, szigorú módban pedig minden
	 * külső 1×1-es vagy rejtett képet blokkolunk. A normál külső képeket
	 * (pl. képtároló CDN) nem, mert azok nélkül az oldal tartalma hiányozna.
	 *
	 * @param array $m Regex találat.
	 * @return string
	 */
	private function handle_img( $m ) {
		$attrs = $m[1];
		$full  = $m[0];

		if ( $this->already_handled( $attrs ) ) {
			return $full;
		}

		$src = $this->get_attr( $attrs, 'src' );
		if ( '' === $src || 0 === stripos( $src, 'data:' ) ) {
			return $full;
		}

		$rule = $this->classify( $src, 'img', $this->is_pixel( $attrs ) );
		if ( ! $rule ) {
			return $full;
		}

		$new_attrs = $this->rename_attr( $attrs, 'src', 'data-szesuti-src' );
		if ( '' !== $this->get_attr( $new_attrs, 'srcset' ) ) {
			$new_attrs = $this->rename_attr( $new_attrs, 'srcset', 'data-szesuti-srcset' );
		}
		$new_attrs = rtrim( $new_attrs ) . $this->marker( $rule, 'img' );

		$this->count( $rule );

		return '<img' . $new_attrs . '>';
	}

	/**
	 * Helyőrző doboz a blokkolt beágyazás helyére.
	 *
	 * @param string $inner Az eredeti (blokkolt) elem.
	 * @param array  $rule  A szabály.
	 * @return string
	 */
	private function placeholder( $inner, array $rule ) {
		$i18n     = szesuti()->i18n;
		$cat      = $i18n->category( $rule['category'] );
		$provider = ! empty( $rule['provider'] ) ? $rule['provider'] : $rule['label'];

		$title = $i18n->t( 'blocked_title', 'Ez a tartalom blokkolva van' );
		$text  = sprintf( $i18n->t( 'blocked_text', 'A tartalom megjelenítéséhez engedélyezned kell a(z) „%s” kategóriájú sütiket.' ), $cat['name'] );
		$btn   = $i18n->t( 'blocked_btn', 'Tartalom engedélyezése' );

		$html  = '<div class="szesuti-placeholder" data-szesuti-ph="' . esc_attr( $rule['category'] ) . '">';
		$html .= $inner;
		$html .= '<div class="szesuti-placeholder__inner">';
		$html .= '<div class="szesuti-placeholder__icon" aria-hidden="true">';
		$html .= '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';
		$html .= '</div>';
		$html .= '<p class="szesuti-placeholder__title">' . esc_html( $title ) . '</p>';
		$html .= '<p class="szesuti-placeholder__text">' . esc_html( $text ) . '</p>';
		$html .= '<div class="szesuti-placeholder__actions">';
		$html .= '<button type="button" class="szesuti-placeholder__btn" data-szesuti-allow="' . esc_attr( $rule['category'] ) . '">' . esc_html( $btn ) . '</button>';
		$html .= '</div>';

		if ( '' !== $provider ) {
			$html .= '<p class="szesuti-placeholder__provider">' . esc_html( $provider ) . '</p>';
		}

		$html .= '</div></div>';

		return $html;
	}

	/* ---------------------------------------------------------------------
	 * Döntés
	 * ------------------------------------------------------------------ */

	/**
	 * Egy külső forrásról dönt: blokkolni kell-e, és ha igen, milyen kategóriában.
	 *
	 * @param string $url   A forrás URL-je.
	 * @param string $kind  script | iframe | link | img.
	 * @param bool   $pixel Képnél: 1×1-es vagy rejtett kép-e.
	 * @return array|null A szabály, vagy null, ha átengedhető.
	 */
	public function classify( $url, $kind, $pixel = false ) {
		$url = html_entity_decode( trim( (string) $url ), ENT_QUOTES, 'UTF-8' );

		if ( '' === $url || preg_match( '#^(data|blob|about|javascript):#i', $url ) ) {
			return null;
		}

		if ( $this->is_own_asset( $url ) || $this->is_skipped( $url ) ) {
			return null;
		}

		$rule = $this->match( $url, $kind );
		if ( $rule ) {
			return $rule;
		}

		// Ismert szolgáltatás, amit tudatosan engedélyeztek (pl. reCAPTCHA, Google Fonts).
		if ( $this->matches_allowed_known( $url ) ) {
			return null;
		}

		$host = $this->host_of( $url );
		if ( '' === $host || $this->is_first_party( $host ) || $this->is_safe_host( $host ) ) {
			return null;
		}

		if ( ! $this->settings->get( 'blocker.strict', 1 ) ) {
			return null;
		}

		// Képeknél csak a követőképpontokat blokkoljuk, a tartalmi képeket nem.
		if ( 'img' === $kind && ! $pixel ) {
			return null;
		}

		return $this->unknown_rule( $host );
	}

	/**
	 * Szabály egy ismeretlen külső hoszthoz.
	 *
	 * @param string $host Hosztnév.
	 * @return array
	 */
	private function unknown_rule( $host ) {
		$category = $this->unknown_category();

		$this->unknown_hosts[ $host ] = $category;

		return array(
			'id'       => 'unknown',
			'label'    => $host,
			'category' => $category,
			'type'     => 'both',
			'patterns' => array(),
			'provider' => $host,
		);
	}

	/**
	 * Az ismeretlen külső források kategóriája.
	 *
	 * Ha a beállított kategória ki van kapcsolva (pl. az oldalon nincs „Marketing”),
	 * a legutolsó aktív, nem kötelező kategóriát használjuk – különben ezeket a
	 * forrásokat a látogató semmilyen módon nem tudná engedélyezni.
	 *
	 * @return string
	 */
	public function unknown_category() {
		$wanted   = (string) $this->settings->get( 'blocker.unknown_category', 'marketing' );
		$optional = array();
		foreach ( $this->settings->active_categories() as $cat ) {
			if ( empty( $cat['locked'] ) ) {
				$optional[] = $cat['slug'];
			}
		}
		if ( in_array( $wanted, $optional, true ) || empty( $optional ) ) {
			return $wanted;
		}
		return in_array( 'marketing', $optional, true ) ? 'marketing' : end( $optional );
	}

	/**
	 * Az érvényes szabályok listája (beépített + egyedi).
	 *
	 * @return array
	 */
	public function get_rules() {
		if ( null !== $this->rules ) {
			return $this->rules;
		}

		$rules         = array();
		$allowed_known = array();
		$overrides     = (array) $this->settings->get( 'blocker.rules', array() );
		$active_slugs  = $this->settings->category_slugs();
		$locked        = $this->locked_categories();

		$gcm_protects_google = $this->settings->get( 'gcm.enabled', 0 ) && $this->settings->get( 'gcm.dont_block_google', 1 );

		foreach ( SZESUTI_Cookie_DB::services() as $id => $service ) {
			$default_enabled = empty( $service['default_off'] );
			$enabled         = isset( $overrides[ $id ]['enabled'] ) ? (bool) $overrides[ $id ]['enabled'] : $default_enabled;
			$category        = ! empty( $overrides[ $id ]['category'] ) ? $overrides[ $id ]['category'] : $service['category'];

			$allow = ! $this->settings->get( 'blocker.auto', 1 )
				|| ! $enabled
				// Consent Mode esetén a Google mérőkódokat szándékosan nem blokkoljuk.
				|| ( $gcm_protects_google && in_array( $id, array( 'google_tag_manager', 'google_analytics', 'google_ads' ), true ) )
				// Zárolt (mindig aktív) kategóriába sorolt szolgáltatást nincs értelme blokkolni.
				|| in_array( $category, $locked, true )
				|| ! in_array( $category, $active_slugs, true );

			if ( $allow ) {
				// Ha a beépített felismerés ki van kapcsolva, ezek szigorú módban
				// ismeretlen forrásként blokkolódnak; egyébként tudatosan engedélyezettek.
				if ( $this->settings->get( 'blocker.auto', 1 ) ) {
					$allowed_known = array_merge( $allowed_known, (array) $service['patterns'] );
				}
				continue;
			}

			$rules[] = array(
				'id'       => $id,
				'label'    => $service['label'],
				'category' => $category,
				'type'     => isset( $service['type'] ) ? $service['type'] : 'both',
				'patterns' => $service['patterns'],
				'provider' => isset( $service['provider'] ) ? $service['provider'] : '',
			);
		}

		foreach ( (array) $this->settings->get( 'blocker.custom', array() ) as $index => $custom ) {
			if ( empty( $custom['enabled'] ) || empty( $custom['pattern'] ) ) {
				continue;
			}
			$category = ! empty( $custom['category'] ) ? $custom['category'] : 'marketing';
			if ( in_array( $category, $locked, true ) || ! in_array( $category, $active_slugs, true ) ) {
				continue;
			}

			$rules[] = array(
				'id'       => 'custom_' . (int) $index,
				'label'    => ! empty( $custom['label'] ) ? $custom['label'] : $custom['pattern'],
				'category' => $category,
				'type'     => isset( $custom['type'] ) ? $custom['type'] : 'both',
				'patterns' => array( $custom['pattern'] ),
				'provider' => ! empty( $custom['label'] ) ? $custom['label'] : '',
			);
		}

		/**
		 * Blokkolási szabályok módosítása futás előtt.
		 *
		 * @param array $rules Szabályok.
		 */
		$this->rules         = apply_filters( 'szesuti_blocker_rules', $rules );
		$this->allowed_known = array_values( array_unique( array_filter( $allowed_known ) ) );

		return $this->rules;
	}

	/**
	 * Megkeresi az első illeszkedő szabályt.
	 *
	 * @param string $haystack Vizsgált szöveg (URL vagy inline kód).
	 * @param string $kind     script | iframe | link | img.
	 * @return array|null
	 */
	private function match( $haystack, $kind ) {
		foreach ( $this->get_rules() as $rule ) {
			if ( ! $this->type_applies( $rule['type'], $kind ) ) {
				continue;
			}
			foreach ( $rule['patterns'] as $pattern ) {
				if ( '' !== $pattern && false !== stripos( $haystack, $pattern ) ) {
					return $rule;
				}
			}
		}
		return null;
	}

	/**
	 * Egy szabálytípus érvényes-e az adott elemfajtára.
	 *
	 * @param string $type script | iframe | both.
	 * @param string $kind script | iframe | link | img.
	 * @return bool
	 */
	private function type_applies( $type, $kind ) {
		if ( 'both' === $type ) {
			return true;
		}
		if ( 'iframe' === $kind ) {
			return 'iframe' === $type;
		}
		// Szkript, stíluslap, kép: a „script” típusú szabályok érvényesek rájuk.
		return 'script' === $type;
	}

	/**
	 * Ismert, de engedélyezett szolgáltatás-e.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private function matches_allowed_known( $url ) {
		$this->get_rules();
		foreach ( (array) $this->allowed_known as $pattern ) {
			if ( '' !== $pattern && false !== stripos( $url, $pattern ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * A felhasználó által megadott kivételek.
	 *
	 * @param string $haystack Vizsgált szöveg.
	 * @return bool
	 */
	private function is_skipped( $haystack ) {
		foreach ( $this->skip_patterns() as $pattern ) {
			if ( false !== stripos( $haystack, $pattern ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * A felhasználói kivételek listája.
	 *
	 * @return string[]
	 */
	private function skip_patterns() {
		$skip = trim( (string) $this->settings->get( 'blocker.skip_patterns', '' ) );
		if ( '' === $skip ) {
			return array();
		}
		return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $skip ) ), 'strlen' ) );
	}

	/**
	 * A zárolt (mindig aktív) kategóriák.
	 *
	 * @return string[]
	 */
	private function locked_categories() {
		$locked = array();
		foreach ( $this->settings->active_categories() as $cat ) {
			if ( ! empty( $cat['locked'] ) ) {
				$locked[] = $cat['slug'];
			}
		}
		return $locked;
	}

	/* ---------------------------------------------------------------------
	 * Hosztok
	 * ------------------------------------------------------------------ */

	/**
	 * Egy URL hosztja kisbetűvel (relatív URL-nél üres).
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private function host_of( $url ) {
		if ( 0 === strpos( $url, '//' ) ) {
			$url = 'https:' . $url;
		}
		if ( ! preg_match( '#^[a-z][a-z0-9+.\-]*://#i', $url ) ) {
			return '';
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );
		return is_string( $host ) ? strtolower( rtrim( $host, '.' ) ) : '';
	}

	/**
	 * A saját oldal hosztja és „saját domainje” (pl. sze-teszt.sze.hu -> sze.hu).
	 *
	 * @return array {host, base}
	 */
	public function site() {
		if ( null !== $this->site ) {
			return $this->site;
		}

		$host = $this->host_of( home_url( '/' ) );
		$base = self::base_domain( $host );

		/**
		 * A saját domain felülírása (pl. ha más domainről is saját fájlok jönnek).
		 *
		 * @param string $base Saját domain.
		 * @param string $host Az oldal hosztja.
		 */
		$base = (string) apply_filters( 'szesuti_first_party_domain', $base, $host );

		$this->site = array(
			'host' => $host,
			'base' => $base,
		);

		return $this->site;
	}

	/**
	 * Egy hoszt „saját domainje”: az utolsó két címke, vagy három, ha a második
	 * szint egy általános magyar második szintű domain (pl. co.hu).
	 *
	 * @param string $host Hosztnév.
	 * @return string
	 */
	public static function base_domain( $host ) {
		$host = strtolower( (string) $host );

		if ( '' === $host || filter_var( $host, FILTER_VALIDATE_IP ) || false === strpos( $host, '.' ) ) {
			return $host;
		}

		$labels = explode( '.', $host );
		$count  = count( $labels );

		if ( $count >= 3 && in_array( $labels[ $count - 2 ], self::SECOND_LEVEL, true ) ) {
			return implode( '.', array_slice( $labels, -3 ) );
		}

		return implode( '.', array_slice( $labels, -2 ) );
	}

	/**
	 * Saját (első féltől származó) hoszt-e: az oldal hosztja vagy a saját domain bármely aldomainje.
	 *
	 * @param string $host Hosztnév.
	 * @return bool
	 */
	private function is_first_party( $host ) {
		$site = $this->site();
		if ( '' === $site['host'] ) {
			return false;
		}
		if ( $host === $site['host'] || $host === $site['base'] ) {
			return true;
		}
		return '' !== $site['base'] && $this->ends_with( $host, '.' . $site['base'] );
	}

	/**
	 * A beépített biztonságos CDN-listán van-e.
	 *
	 * @param string $host Hosztnév.
	 * @return bool
	 */
	private function is_safe_host( $host ) {
		foreach ( $this->safe_hosts() as $safe ) {
			if ( $host === $safe || $this->ends_with( $host, '.' . $safe ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * A használt biztonságos hosztok (üres, ha a beépített lista ki van kapcsolva).
	 *
	 * @return string[]
	 */
	private function safe_hosts() {
		return $this->settings->get( 'blocker.safe_hosts', 1 ) ? SZESUTI_Cookie_DB::safe_hosts() : array();
	}

	private function ends_with( $haystack, $needle ) {
		$len = strlen( $needle );
		return $len > 0 && strlen( $haystack ) >= $len && substr( $haystack, -$len ) === $needle;
	}

	/**
	 * A szigorú mód által blokkolt ismeretlen hosztok megjegyzése az admin számára.
	 * Csak akkor ír az adatbázisba, ha új hoszt jelent meg, vagy egy nap eltelt.
	 */
	private function remember_unknown_hosts() {
		if ( empty( $this->unknown_hosts ) ) {
			return;
		}

		$seen    = get_option( 'szesuti_seen_hosts', array() );
		$seen    = is_array( $seen ) ? $seen : array();
		$now     = time();
		$changed = false;

		foreach ( $this->unknown_hosts as $host => $category ) {
			if ( ! isset( $seen[ $host ] ) || ( $now - (int) $seen[ $host ] ) > DAY_IN_SECONDS ) {
				$seen[ $host ] = $now;
				$changed       = true;
			}
		}

		if ( $changed ) {
			arsort( $seen );
			update_option( 'szesuti_seen_hosts', array_slice( $seen, 0, 60, true ), false );
		}
	}

	/* ---------------------------------------------------------------------
	 * Futásidejű őr
	 * ------------------------------------------------------------------ */

	/**
	 * Az őr beállításai (a JS ugyanazzal a döntési sorrenddel dolgozik, mint a classify()).
	 *
	 * @return array
	 */
	public function guard_config() {
		$rules = array();
		foreach ( $this->get_rules() as $rule ) {
			$rules[] = array(
				'p' => array_values( (array) $rule['patterns'] ),
				'c' => $rule['category'],
				't' => $rule['type'],
			);
		}

		$site = $this->site();

		return array(
			'rules'   => $rules,
			'allow'   => array_values( (array) $this->allowed_known ),
			'skip'    => $this->skip_patterns(),
			'safe'    => array_values( $this->safe_hosts() ),
			'host'    => $site['host'],
			'base'    => $site['base'],
			'strict'  => $this->settings->get( 'blocker.strict', 1 ) ? 1 : 0,
			'unknown' => $this->unknown_category(),
			'locked'  => $this->locked_categories(),
			'cookie'  => (string) $this->settings->get( 'general.cookie_name', 'szesuti_consent' ),
			'pv'      => (string) $this->settings->get( 'general.policy_version', '1' ),
			'days'    => (int) $this->settings->get( 'general.consent_expiry_days', 180 ),
			'own'     => (string) wp_parse_url( SZESUTI_URL, PHP_URL_PATH ),
		);
	}

	/** Az őr kiírása a <head> legelejére. */
	public function print_guard() {
		if ( ! $this->settings->get( 'blocker.guard', 1 ) || ! $this->should_run() ) {
			return;
		}

		$file = SZESUTI_DIR . 'assets/js/szesuti-guard.js';
		if ( ! is_readable( $file ) ) {
			return;
		}

		$code = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		echo "<!-- SZetemény: futásidejű őr -->\n";
		echo '<script data-szesuti-ignore="1">window.SZESUTI_GUARD_CONFIG=' . wp_json_encode( $this->guard_config() ) . ";\n" . $code . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/* ---------------------------------------------------------------------
	 * Segédfüggvények
	 * ------------------------------------------------------------------ */

	/**
	 * A blokkolt elem jelölő attribútumai.
	 *
	 * @param array  $rule Szabály.
	 * @param string $kind Elemfajta.
	 * @return string
	 */
	private function marker( array $rule, $kind ) {
		return ' data-szesuti="' . esc_attr( $rule['category'] ) . '" data-szesuti-svc="' . esc_attr( $rule['id'] ) . '" data-szesuti-el="' . esc_attr( $kind ) . '"';
	}

	/**
	 * Blokkolás számlálása.
	 *
	 * @param array $rule Szabály.
	 */
	private function count( array $rule ) {
		$this->blocked_count++;
		$this->blocked_services[] = 'unknown' === $rule['id'] ? $rule['label'] : $rule['id'];
	}

	/**
	 * 1×1-es vagy rejtett kép-e (tipikus követőképpont).
	 *
	 * @param string $attrs Attribútumok.
	 * @return bool
	 */
	private function is_pixel( $attrs ) {
		$w = $this->get_attr( $attrs, 'width' );
		$h = $this->get_attr( $attrs, 'height' );
		if ( '' !== $w && '' !== $h && (int) $w <= 1 && (int) $h <= 1 ) {
			return true;
		}

		$style = strtolower( str_replace( ' ', '', $this->get_attr( $attrs, 'style' ) ) );
		return false !== strpos( $style, 'display:none' )
			|| false !== strpos( $style, 'visibility:hidden' )
			|| ( false !== strpos( $style, 'width:1px' ) && false !== strpos( $style, 'height:1px' ) )
			|| ( false !== strpos( $style, 'width:0' ) && false !== strpos( $style, 'height:0' ) );
	}

	/**
	 * A plugin saját fájljait sosem blokkoljuk.
	 *
	 * Szándékosan szűk: csak a plugin mappájából betöltött URL számít sajátnak.
	 * Egy egyszerű „szesuti” szórészlet nem elég, különben egy mérőkód, ami
	 * mellesleg a SZESUTI API-t is hívja, kicsúszna a blokkolás alól.
	 *
	 * @param string $url Vizsgált URL.
	 * @return bool
	 */
	private function is_own_asset( $url ) {
		$own_path = (string) wp_parse_url( SZESUTI_URL, PHP_URL_PATH );

		if ( '' !== $own_path && false !== stripos( $url, $own_path ) ) {
			return true;
		}

		return false !== stripos( $url, '/plugins/' . SZESUTI_SLUG . '/' );
	}

	/**
	 * A plugin saját szkriptje-e (külső fájl vagy a WordPress által kiírt inline konfiguráció)?
	 *
	 * @param string $attrs Attribútumok.
	 * @param string $src   Forrás URL (üres, ha inline).
	 * @param string $body  Inline tartalom.
	 * @return bool
	 */
	private function is_own_script( $attrs, $src, $body ) {
		if ( '' !== $src ) {
			return $this->is_own_asset( $src );
		}

		// A wp_add_inline_script() kimenetének azonosítója: id="szesuti-js-before".
		$id = $this->get_attr( $attrs, 'id' );
		if ( '' !== $id && 0 === strpos( $id, SZESUTI_SLUG . '-' ) ) {
			return true;
		}

		return 0 === strpos( ltrim( $body ), 'window.SZESUTI_CONFIG' );
	}

	/**
	 * Már megjelölt (kézzel taggelt vagy kihagyandó) elem?
	 *
	 * @param string $attrs Attribútumok.
	 * @return bool
	 */
	private function already_handled( $attrs ) {
		return false !== stripos( $attrs, 'data-szesuti' ) || false !== stripos( $attrs, 'data-cookieconsent' );
	}

	/**
	 * JavaScript típusú-e a megadott MIME?
	 *
	 * @param string $type Type attribútum.
	 * @return bool
	 */
	private function is_js_type( $type ) {
		$type = strtolower( trim( $type ) );
		return in_array(
			$type,
			array( 'text/javascript', 'application/javascript', 'application/ecmascript', 'text/ecmascript', 'module', 'application/x-javascript' ),
			true
		);
	}

	/**
	 * Attribútum értékének kiolvasása.
	 *
	 * @param string $attrs Attribútumsztring.
	 * @param string $name  Attribútum neve.
	 * @return string
	 */
	private function get_attr( $attrs, $name ) {
		// A név előtt szóköz vagy sorkezdet kell, különben pl. a „data-src” is illeszkedne a „src”-re.
		$pattern = '#(?:^|\s)' . preg_quote( $name, '#' ) . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))#i';
		if ( preg_match( $pattern, $attrs, $m ) ) {
			if ( isset( $m[1] ) && '' !== $m[1] ) {
				return $m[1];
			}
			if ( isset( $m[2] ) && '' !== $m[2] ) {
				return $m[2];
			}
			if ( isset( $m[3] ) ) {
				return $m[3];
			}
		}
		return '';
	}

	/**
	 * Attribútum átnevezése (az érték megmarad).
	 *
	 * @param string $attrs Attribútumsztring.
	 * @param string $from  Régi név.
	 * @param string $to    Új név.
	 * @return string
	 */
	private function rename_attr( $attrs, $from, $to ) {
		$pattern = '#(^|\s)' . preg_quote( $from, '#' ) . '(\s*=\s*)#i';
		return preg_replace( $pattern, '$1' . $to . '$2', $attrs, 1 );
	}

	/**
	 * Attribútum eltávolítása.
	 *
	 * @param string $attrs Attribútumsztring.
	 * @param string $name  Attribútum neve.
	 * @return string
	 */
	private function remove_attr( $attrs, $name ) {
		$pattern = '#\s*(?<![\w-])' . preg_quote( $name, '#' ) . '\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'>]+)#i';
		return preg_replace( $pattern, '', $attrs, 1 );
	}
}
