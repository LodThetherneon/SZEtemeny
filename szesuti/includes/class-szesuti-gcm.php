<?php
/**
 * Google Consent Mode v2.
 *
 * A `consent default` jelzéseket a lehető legkorábban, minden más szkript előtt
 * kiírjuk a <head>-be, hogy a Google mérőkódok már induláskor tudják, mit
 * szabad. A hozzájárulás változásakor a banner JS-e küld `consent update`-et.
 *
 * @package SZESUTI
 */

defined( 'ABSPATH' ) || exit;

class SZESUTI_GCM {

	/** @var SZESUTI_Settings */
	private $settings;

	/** @var SZESUTI_Consent */
	private $consent;

	public function __construct( SZESUTI_Settings $settings, SZESUTI_Consent $consent ) {
		$this->settings = $settings;
		$this->consent  = $consent;

		// Prioritás 0: minden más szkript előtt fusson le.
		add_action( 'wp_head', array( $this, 'print_defaults' ), 0 );
		add_action( 'wp_head', array( $this, 'print_gtm_container' ), 2 );
		add_action( 'wp_body_open', array( $this, 'print_gtm_noscript' ), 1 );
	}

	/** Be van kapcsolva a Consent Mode? */
	public function is_enabled() {
		return (bool) ( $this->settings->get( 'general.enabled', 1 ) && $this->settings->get( 'gcm.enabled', 0 ) );
	}

	/**
	 * A Consent Mode jelzések listája.
	 *
	 * @return string[]
	 */
	public static function signals() {
		return array(
			'ad_storage',
			'ad_user_data',
			'ad_personalization',
			'analytics_storage',
			'functionality_storage',
			'personalization_storage',
			'security_storage',
		);
	}

	/**
	 * Jelzés => kategória leképezés a beállításokból.
	 *
	 * @return array
	 */
	public function map() {
		$map = (array) $this->settings->get( 'gcm.map', array() );
		$out = array();
		foreach ( self::signals() as $signal ) {
			$out[ $signal ] = isset( $map[ $signal ] ) ? (string) $map[ $signal ] : '';
		}
		return $out;
	}

	/**
	 * Az induláskori (default) jelzésállapot.
	 *
	 * @return array signal => 'granted'|'denied'
	 */
	private function default_state() {
		$out = array();
		foreach ( $this->map() as $signal => $category ) {
			// Üres leképezés = nincs hozzájáruláshoz kötve (pl. security_storage).
			$out[ $signal ] = ( '' === $category ) ? 'granted' : 'denied';
		}
		return $out;
	}

	/**
	 * A beállított régiók listája.
	 *
	 * @return string[]
	 */
	private function regions() {
		$raw = trim( (string) $this->settings->get( 'gcm.regions', '' ) );
		if ( '' === $raw ) {
			return array();
		}
		$parts = preg_split( '/[\s,;\r\n]+/', strtoupper( $raw ), -1, PREG_SPLIT_NO_EMPTY );
		$out   = array();
		foreach ( (array) $parts as $part ) {
			$part = preg_replace( '/[^A-Z\-]/', '', $part );
			if ( '' !== $part ) {
				$out[] = $part;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/** A `consent default` blokk kiírása. */
	public function print_defaults() {
		// A sütiszkenner keretében nincs „denied” alapállapot, hogy a Google-kódok is
		// beállíthassák a sütijeiket (a szkenner épp ezeket keresi).
		if ( ! $this->is_enabled() || SZESUTI_Scanner::is_scan_request() ) {
			return;
		}

		$dl      = (string) $this->settings->get( 'gcm.datalayer', 'dataLayer' );
		$wait    = (int) $this->settings->get( 'gcm.wait_for_update', 500 );
		$regions = $this->regions();

		$denied_state = $this->default_state();
		if ( $wait > 0 ) {
			$denied_state['wait_for_update'] = $wait;
		}

		$js  = "window.{$dl} = window.{$dl} || [];\n";
		$js .= "function szesutiGtag(){ window.{$dl}.push(arguments); }\n";
		$js .= "if (typeof window.gtag !== 'function') { window.gtag = szesutiGtag; }\n";

		if ( ! empty( $regions ) ) {
			// Régión kívül minden engedélyezett, a megadott régiókban tiltott az alapállapot.
			$granted_state = array();
			foreach ( array_keys( $this->map() ) as $signal ) {
				$granted_state[ $signal ] = 'granted';
			}
			$js .= 'szesutiGtag(\'consent\', \'default\', ' . wp_json_encode( $granted_state ) . ");\n";

			$denied_state['region'] = $regions;
			$js .= 'szesutiGtag(\'consent\', \'default\', ' . wp_json_encode( $denied_state ) . ");\n";
		} else {
			$js .= 'szesutiGtag(\'consent\', \'default\', ' . wp_json_encode( $denied_state ) . ");\n";
		}

		if ( $this->settings->get( 'gcm.ads_data_redaction', 1 ) ) {
			$js .= "szesutiGtag('set', 'ads_data_redaction', true);\n";
		}
		if ( $this->settings->get( 'gcm.url_passthrough', 1 ) ) {
			$js .= "szesutiGtag('set', 'url_passthrough', true);\n";
		}

		// Ha a látogató már döntött, azonnal küldünk egy update-et, hogy ne kelljen a bannerre várni.
		// FONTOS: a döntést a böngésző olvassa ki a sütiből, nem a szerver írja bele a HTML-be.
		// Oldalgyorsítótárnál a szerver által beírt „elfogadta” állapot a következő
		// látogatóknak is kimenne, akik még semmihez nem járultak hozzá.
		$locked = array();
		foreach ( $this->settings->active_categories() as $cat ) {
			if ( ! empty( $cat['locked'] ) ) {
				$locked[] = $cat['slug'];
			}
		}

		$js .= '(function(){try{'
			. 'var n=' . wp_json_encode( (string) $this->consent->cookie_name() ) . ','
			. 'pv=' . wp_json_encode( (string) $this->settings->get( 'general.policy_version', '1' ) ) . ','
			. 'days=' . (int) $this->settings->get( 'general.consent_expiry_days', 180 ) . ','
			. 'map=' . wp_json_encode( $this->map() ) . ','
			. 'locked=' . wp_json_encode( $locked ) . ','
			. 'raw=null,p=document.cookie?document.cookie.split(";"):[];'
			. 'for(var i=0;i<p.length;i++){var c=p[i].replace(/^\s+/,"");if(c.indexOf(n+"=")===0){raw=c.substring(n.length+1);}}'
			. 'if(!raw){return;}'
			. 'var d=JSON.parse(decodeURIComponent(raw));'
			. 'if(!d||!d.cats||!d.cats.length){return;}'
			. 'if(days>0&&d.t&&(Date.now()/1000-d.t)/86400>days){return;}'
			. 'if(pv&&String(d.pv)!==String(pv)){return;}'
			. 'var cats=d.cats.concat(locked),st={};'
			. 'for(var k in map){if(Object.prototype.hasOwnProperty.call(map,k)){st[k]=(!map[k]||cats.indexOf(map[k])!==-1)?"granted":"denied";}}'
			. 'szesutiGtag("consent","update",st);'
			. ( $this->settings->get( 'gcm.push_events', 1 )
				? "window.{$dl}.push({event:\"szesuti_consent_ready\",szesuti_consent:d.cats.join(\",\"),szesuti_has_choice:true});"
				: '' )
			. "}catch(e){}})();\n";

		echo "<!-- SZetemény: Google Consent Mode v2 -->\n";
		echo '<script data-szesuti-ignore="1">' . $js . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/** GTM konténer beszúrása (opcionális). */
	public function print_gtm_container() {
		if ( ! $this->is_enabled() || ! $this->settings->get( 'gcm.inject_gtm', 0 ) ) {
			return;
		}

		$id = (string) $this->settings->get( 'gcm.gtm_id', '' );
		if ( '' === $id || ! preg_match( '/^GTM-[A-Z0-9]+$/', $id ) ) {
			return;
		}

		$dl = (string) $this->settings->get( 'gcm.datalayer', 'dataLayer' );

		$js  = "(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});";
		$js .= "var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';";
		$js .= "j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);";
		$js .= '})(window,document,\'script\',' . wp_json_encode( $dl ) . ',' . wp_json_encode( $id ) . ');';

		echo "<!-- SZetemény: Google Tag Manager -->\n";
		echo '<script data-szesuti-ignore="1">' . $js . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/** GTM noscript rész a <body> elejére. */
	public function print_gtm_noscript() {
		if ( ! $this->is_enabled() || ! $this->settings->get( 'gcm.inject_gtm', 0 ) ) {
			return;
		}

		// JavaScript nélkül a látogató nem tud hozzájárulni, ezért szigorú módban a
		// GTM noscript változata (ami mérőcímkéket tölthet be) egyáltalán nem kerül ki.
		if ( $this->settings->get( 'blocker.strict', 1 ) ) {
			return;
		}

		$id = (string) $this->settings->get( 'gcm.gtm_id', '' );
		if ( '' === $id || ! preg_match( '/^GTM-[A-Z0-9]+$/', $id ) ) {
			return;
		}

		printf(
			'<noscript><iframe data-szesuti-ignore="1" src="https://www.googletagmanager.com/ns.html?id=%s" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n",
			esc_attr( $id )
		);
	}

	/**
	 * A JS-nek átadott Consent Mode konfiguráció.
	 *
	 * @return array
	 */
	public function js_config() {
		return array(
			'enabled'     => $this->is_enabled() ? 1 : 0,
			'datalayer'   => (string) $this->settings->get( 'gcm.datalayer', 'dataLayer' ),
			'map'         => $this->map(),
			'pushEvents'  => $this->settings->get( 'gcm.push_events', 1 ) ? 1 : 0,
		);
	}
}
