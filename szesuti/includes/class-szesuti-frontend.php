<?php
/**
 * Frontend: eszközök betöltése, dinamikus CSS, banner kirajzolása.
 *
 * @package SZESUTI
 */

defined( 'ABSPATH' ) || exit;

class SZESUTI_Frontend {

	/** @var SZESUTI_Settings */
	private $settings;

	/** @var SZESUTI_I18n */
	private $i18n;

	/** @var SZESUTI_Consent */
	private $consent;

	/** @var SZESUTI_Geo */
	private $geo;

	/** @var bool Kirajzoltuk-e már a bannert. */
	private $rendered = false;

	public function __construct( SZESUTI_Settings $settings, SZESUTI_I18n $i18n, SZESUTI_Consent $consent, SZESUTI_Geo $geo ) {
		$this->settings = $settings;
		$this->i18n     = $i18n;
		$this->consent  = $consent;
		$this->geo      = $geo;

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_footer', array( $this, 'render' ), 100 );
	}

	/**
	 * Meg kell-e jeleníteni a sütikezelőt ezen az oldalon?
	 *
	 * @return bool
	 */
	public function should_display() {
		if ( ! $this->settings->get( 'general.enabled', 1 ) ) {
			return false;
		}

		if ( is_admin() || is_feed() || is_robots() || is_trackback() || is_preview() || is_embed() ) {
			return false;
		}

		if ( is_customize_preview() ) {
			return false;
		}

		// A sütiszkenner keretében nincs banner (és nem íródik hozzájárulási süti).
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

		// Botok elrejtése (csak teljes oldalas gyorsítótár NÉLKÜL biztonságos).
		if ( $this->settings->get( 'general.hide_from_bots', 0 ) && $this->is_bot() ) {
			return false;
		}

		// Geo: szerveroldali módban itt dől el, REST módban a böngésző dönt.
		if ( $this->geo->is_enabled() && 'server' === $this->settings->get( 'geo.method', 'rest' ) && ! $this->geo->should_show() ) {
			return false;
		}

		/**
		 * A banner megjelenítésének felülbírálása.
		 *
		 * @param bool $display Megjelenjen-e.
		 */
		return (bool) apply_filters( 'szesuti_should_display', true );
	}

	/**
	 * Ismert keresőrobot-e a látogató?
	 *
	 * @return bool
	 */
	private function is_bot() {
		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return false;
		}
		$ua   = strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) );
		$bots = array( 'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider', 'yandexbot', 'facebookexternalhit', 'lighthouse', 'pagespeed', 'gtmetrix', 'pingdom', 'ahrefsbot', 'semrushbot', 'applebot', 'petalbot', 'chrome-lighthouse' );

		foreach ( $bots as $bot ) {
			if ( false !== strpos( $ua, $bot ) ) {
				return true;
			}
		}
		return false;
	}

	/** CSS és JS betöltése. */
	public function enqueue() {
		if ( ! $this->should_display() ) {
			return;
		}

		if ( $this->settings->get( 'advanced.load_css', 1 ) ) {
			wp_enqueue_style( 'szesuti', SZESUTI_URL . 'assets/css/szesuti.css', array(), SZESUTI_VERSION );
			wp_add_inline_style( 'szesuti', $this->inline_css() );
		}

		wp_enqueue_script(
			'szesuti',
			SZESUTI_URL . 'assets/js/szesuti.js',
			array(),
			SZESUTI_VERSION,
			(bool) $this->settings->get( 'advanced.footer_js', 1 )
		);

		wp_add_inline_script( 'szesuti', 'window.SZESUTI_CONFIG = ' . wp_json_encode( $this->js_config() ) . ';', 'before' );
	}

	/**
	 * A JS-nek átadott teljes konfiguráció.
	 *
	 * @return array
	 */
	public function js_config() {
		$categories = array();
		foreach ( $this->settings->active_categories() as $cat ) {
			$meta         = $this->i18n->category( $cat['slug'] );
			$categories[] = array(
				'slug'    => $cat['slug'],
				'locked'  => ! empty( $cat['locked'] ) ? 1 : 0,
				'default' => ! empty( $cat['default'] ) ? 1 : 0,
				'name'    => $meta['name'],
				'desc'    => $meta['desc'],
			);
		}

		$geo_enabled = $this->geo->is_enabled() && 'rest' === $this->settings->get( 'geo.method', 'rest' );

		$config = array(
			'version'        => SZESUTI_VERSION,
			'cookieName'     => $this->consent->cookie_name(),
			'cookieDomain'   => (string) $this->settings->get( 'general.cookie_domain', '' ),
			'expiryDays'     => (int) $this->settings->get( 'general.consent_expiry_days', 180 ),
			'policyVersion'  => (string) $this->settings->get( 'general.policy_version', '1' ),
			'lang'           => $this->i18n->current_language(),
			'categories'     => $categories,
			'layout'         => (string) $this->settings->get( 'appearance.layout', 'box' ),
			'autoOpenDelay'  => (int) $this->settings->get( 'general.auto_open_delay', 0 ),
			'reloadOnAccept' => $this->settings->get( 'general.reload_on_accept', 0 ) ? 1 : 0,
			'reloadOnReject' => $this->settings->get( 'general.reload_on_reject', 0 ) ? 1 : 0,
			'respectDnt'     => $this->settings->get( 'general.respect_dnt', 0 ) ? 1 : 0,
			'respectGpc'     => $this->settings->get( 'general.respect_gpc', 0 ) ? 1 : 0,
			'showOnAdmin'    => $this->settings->get( 'general.show_on_admin', 1 ) ? 1 : 0,
			'isAdmin'        => current_user_can( 'manage_options' ) ? 1 : 0,
			'geo'            => array(
				'enabled' => $geo_enabled ? 1 : 0,
				'unknown' => (string) $this->settings->get( 'geo.unknown', 'show' ),
			),
			'gcm'            => szesuti()->gcm->js_config(),
			'log'            => array(
				'enabled'  => ( $this->settings->get( 'log.enabled', 0 ) && $this->settings->get( 'advanced.rest_enabled', 1 ) ) ? 1 : 0,
			),
			'rest'           => array(
				'consent' => esc_url_raw( rest_url( 'szesuti/v1/consent' ) ),
				'geo'     => esc_url_raw( rest_url( 'szesuti/v1/geo' ) ),
			),
			'debug'          => $this->settings->get( 'advanced.debug', 0 ) ? 1 : 0,
		);

		/**
		 * A frontend konfiguráció módosítása.
		 *
		 * @param array $config Konfiguráció.
		 */
		return apply_filters( 'szesuti_js_config', $config );
	}

	/**
	 * A beállításokból generált CSS változók.
	 *
	 * @return string
	 */
	public function inline_css() {
		$a      = (array) $this->settings->get( 'appearance', array() );
		$theme  = isset( $a['theme'] ) ? $a['theme'] : 'light';

		// Kontrasztőr: az olvashatatlan színpárokat (pl. fehér felirat fehér gombon)
		// automatikusan olvashatóra cseréljük, így minden gomb használható marad.
		$light = SZESUTI_Colors::safe_palette( isset( $a['colors'] ) ? (array) $a['colors'] : array() );
		$light = $light['colors'];
		$dark  = SZESUTI_Colors::safe_palette( isset( $a['colors_dark'] ) ? (array) $a['colors_dark'] : array() );
		$dark  = $dark['colors'];

		$shadows = array(
			'none' => 'none',
			'sm'   => '0 1px 3px rgba(12,18,32,.10)',
			'md'   => '0 6px 20px rgba(12,18,32,.14)',
			'lg'   => '0 18px 50px rgba(12,18,32,.22)',
		);
		$shadow = isset( $shadows[ $a['shadow'] ] ) ? $shadows[ $a['shadow'] ] : $shadows['lg'];

		$vars = function ( $colors ) {
			$out = '';
			foreach ( $colors as $key => $value ) {
				if ( '' === (string) $value ) {
					continue; // Automatikus érték: a CSS tartaléka érvényesül.
				}
				$out .= '--szesuti-' . str_replace( '_', '-', $key ) . ':' . $value . ';';
			}
			return $out;
		};

		// A .szesuti-vars a shortcode-ok (sütitáblázat, gomb) konténere: ugyanazokat
		// a színeket kapja, de szándékosan csak a világos változatot, mert az oldal
		// tartalmának része, nem a banneré.
		$css  = '.szesuti-root,.szesuti-vars{';
		$css .= $vars( $light );
		$css .= '--szesuti-width:' . (int) $a['width'] . 'px;';
		$css .= '--szesuti-radius:' . (int) $a['radius'] . 'px;';
		$css .= '--szesuti-btn-radius:' . (int) $a['btn_radius'] . 'px;';
		$css .= '--szesuti-font-size:' . (int) $a['font_size'] . 'px;';
		$css .= '--szesuti-shadow:' . $shadow . ';';
		$css .= '--szesuti-z:' . (int) $a['z_index'] . ';';
		$css .= '--szesuti-overlay-opacity:' . ( (int) $a['overlay_opacity'] / 100 ) . ';';
		$css .= '--szesuti-reopen-x:' . (int) $this->settings->get( 'general.reopen_offset_x', 20 ) . 'px;';
		$css .= '--szesuti-reopen-y:' . (int) $this->settings->get( 'general.reopen_offset_y', 20 ) . 'px;';

		if ( ! empty( $a['font_family'] ) ) {
			$css .= '--szesuti-font:' . $this->sanitize_font_stack( $a['font_family'] ) . ';';
		}

		$css .= '}';

		// Sötét téma.
		$dark_vars = $vars( $dark );
		if ( 'dark' === $theme ) {
			$css .= '.szesuti-root{' . $dark_vars . '}';
		} elseif ( 'auto' === $theme ) {
			$css .= '@media (prefers-color-scheme: dark){.szesuti-root:not([data-szesuti-theme="light"]){' . $dark_vars . '}}';
			$css .= '.szesuti-root[data-szesuti-theme="dark"]{' . $dark_vars . '}';
		}

		$custom = trim( (string) $this->settings->get( 'appearance.custom_css', '' ) );
		if ( '' !== $custom ) {
			$css .= "\n" . $custom;
		}

		return $css;
	}

	/**
	 * Betűtípus-lista tisztítása CSS-be írás előtt.
	 *
	 * @param string $stack Betűtípus-lista.
	 * @return string
	 */
	private function sanitize_font_stack( $stack ) {
		$stack = preg_replace( '/[^a-zA-Z0-9 ,\'"\-_]/', '', (string) $stack );
		return trim( $stack );
	}

	/** A banner kirajzolása a lábléc előtt. */
	public function render() {
		if ( $this->rendered || ! $this->should_display() ) {
			return;
		}
		$this->rendered = true;

		$this->render_markup();
	}

	/**
	 * Önálló HTML oldal az admin élő előnézetéhez.
	 *
	 * Egy egyszerű, semleges „weboldal” vázat rajzol, rá a bannert a megadott
	 * (akár még nem mentett) beállításokkal. A JS előnézeti módban fut: nem ír
	 * sütit, nem naplóz, nem tölt újra.
	 *
	 * @param string $view banner | prefs | reopen.
	 * @return string
	 */
	public function preview_document( $view = 'banner' ) {
		$config = $this->js_config();

		$config['preview']        = 1;
		$config['previewView']    = in_array( $view, array( 'banner', 'prefs', 'reopen' ), true ) ? $view : 'banner';
		$config['autoOpenDelay']  = 0;
		$config['respectGpc']     = 0;
		$config['respectDnt']     = 0;
		$config['showOnAdmin']    = 1;
		$config['reloadOnAccept'] = 0;
		$config['reloadOnReject'] = 0;
		$config['geo']['enabled'] = 0;
		$config['log']['enabled'] = 0;
		$config['gcm']['enabled'] = 0;

		ob_start();
		$this->render_markup();
		$markup = (string) ob_get_clean();

		$site = get_bloginfo( 'name' );
		$lang = $this->i18n->current_language();

		$html  = '<!DOCTYPE html><html class="szesuti-preview-doc" lang="' . esc_attr( $lang ) . '"><head><meta charset="utf-8">';
		$html .= '<meta name="viewport" content="width=device-width, initial-scale=1">';
		$html .= '<link rel="stylesheet" href="' . esc_url( SZESUTI_URL . 'assets/css/szesuti.css?ver=' . SZESUTI_VERSION ) . '">';
		$html .= '<style>' . $this->inline_css() . '</style>';
		$html .= '<style>'
			. 'html,body{margin:0;min-height:100%;background:#f4f5f8;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;color:#1f2430}'
			. '.pv-top{display:flex;align-items:center;justify-content:space-between;gap:24px;padding:18px 32px;background:#fff;border-bottom:1px solid #e6e8ee}'
			. '.pv-logo{font-weight:700;font-size:17px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}'
			. '.pv-nav{display:flex;gap:18px}.pv-nav i{display:block;width:64px;height:9px;border-radius:5px;background:#e3e6ed}'
			. '.pv-main{max-width:880px;margin:0 auto;padding:40px 32px}'
			. '.pv-h{width:62%;height:26px;border-radius:7px;background:#dde1e9;margin:0 0 22px}'
			. '.pv-l{height:11px;border-radius:6px;background:#e5e8ee;margin:0 0 12px}'
			. '.pv-img{height:180px;border-radius:14px;background:linear-gradient(135deg,#e2e6ef,#eef0f5);margin:26px 0}'
			. '@media(max-width:600px){.pv-nav{display:none}.pv-top{padding:16px 18px}.pv-main{padding:26px 18px}}'
			. '</style></head><body>';
		$html .= '<header class="pv-top"><span class="pv-logo">' . esc_html( $site ) . '</span><nav class="pv-nav"><i></i><i></i><i></i><i></i></nav></header>';
		$html .= '<main class="pv-main"><div class="pv-h"></div>';
		foreach ( array( 96, 88, 93, 70 ) as $w ) {
			$html .= '<div class="pv-l" style="width:' . (int) $w . '%"></div>';
		}
		$html .= '<div class="pv-img"></div>';
		foreach ( array( 90, 94, 60 ) as $w ) {
			$html .= '<div class="pv-l" style="width:' . (int) $w . '%"></div>';
		}
		$html .= '</main>';
		$html .= $markup;
		$html .= '<script>window.SZESUTI_CONFIG = ' . wp_json_encode( $config ) . ';</script>';
		$html .= '<script src="' . esc_url( SZESUTI_URL . 'assets/js/szesuti.js?ver=' . SZESUTI_VERSION ) . '"></script>';
		$html .= '</body></html>';

		return $html;
	}

	/** A banner sablonjának kirajzolása (ellenőrzések nélkül). */
	private function render_markup() {
		$settings   = $this->settings;
		$i18n       = $this->i18n;
		$texts      = $this->i18n->texts();
		$categories = $this->settings->active_categories();
		$appearance = (array) $this->settings->get( 'appearance', array() );
		$general    = (array) $this->settings->get( 'general', array() );
		$cookies    = (array) $this->settings->get( 'cookies', array() );
		$allowed    = $this->settings->allowed_html();

		$template = locate_template( 'szesuti/banner.php' );
		if ( ! $template ) {
			$template = SZESUTI_DIR . 'templates/banner.php';
		}

		/**
		 * A banner sablonjának lecserélése.
		 *
		 * @param string $template Sablon útvonala.
		 */
		$template = apply_filters( 'szesuti_banner_template', $template );

		if ( file_exists( $template ) ) {
			include $template;
		}
	}
}
