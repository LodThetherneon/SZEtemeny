<?php
/**
 * Színkontraszt-őr.
 *
 * Bármilyen színeket állít is be valaki, a banner szövege és minden gombja
 * olvasható és felismerhető kell maradjon. Ez az osztály a WCAG kontrasztarány
 * alapján ellenőrzi a színpárokat, és ahol olvashatatlan lenne az eredmény,
 * automatikusan olvasható színt választ helyette.
 *
 * @package SZESUTI
 */

defined( 'ABSPATH' ) || exit;

class SZESUTI_Colors {

	/** Szövegre elvárt minimum (WCAG AA, normál szöveg). */
	const TEXT_MIN = 4.5;

	/** Nagy/félkövér szövegre, ikonokra, kapcsolókra elvárt minimum. */
	const UI_MIN = 3.0;

	/**
	 * Hex szín → [r, g, b] (0–255). Érvénytelen értéknél null.
	 *
	 * @param string $hex Szín.
	 * @return int[]|null
	 */
	public static function rgb( $hex ) {
		$hex = ltrim( trim( (string) $hex ), '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 8 === strlen( $hex ) ) {
			$hex = substr( $hex, 0, 6 ); // Az átlátszóságot figyelmen kívül hagyjuk.
		}
		if ( ! preg_match( '/^[0-9a-f]{6}$/i', $hex ) ) {
			return null;
		}
		return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
	}

	/**
	 * [r, g, b] → #rrggbb.
	 *
	 * @param int[] $rgb Szín.
	 * @return string
	 */
	public static function hex( array $rgb ) {
		return sprintf( '#%02x%02x%02x', max( 0, min( 255, (int) round( $rgb[0] ) ) ), max( 0, min( 255, (int) round( $rgb[1] ) ) ), max( 0, min( 255, (int) round( $rgb[2] ) ) ) );
	}

	/**
	 * Relatív fénysűrűség (WCAG 2.x).
	 *
	 * @param int[] $rgb Szín.
	 * @return float
	 */
	public static function luminance( array $rgb ) {
		$c = array();
		foreach ( $rgb as $v ) {
			$v   = $v / 255;
			$c[] = ( $v <= 0.03928 ) ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
	}

	/**
	 * Kontrasztarány két szín között (1–21). Érvénytelen színnél 21 (nem avatkozunk be).
	 *
	 * @param string $a Szín.
	 * @param string $b Szín.
	 * @return float
	 */
	public static function contrast( $a, $b ) {
		$ra = self::rgb( $a );
		$rb = self::rgb( $b );
		if ( null === $ra || null === $rb ) {
			return 21.0;
		}
		$la = self::luminance( $ra );
		$lb = self::luminance( $rb );
		return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
	}

	/**
	 * Két szín keverése (arány: az $a súlya 0–1).
	 *
	 * @param string $a     Szín.
	 * @param string $b     Szín.
	 * @param float  $ratio Az $a aránya.
	 * @return string
	 */
	public static function mix( $a, $b, $ratio ) {
		$ra = self::rgb( $a );
		$rb = self::rgb( $b );
		if ( null === $ra || null === $rb ) {
			return (string) $a;
		}
		return self::hex(
			array(
				$ra[0] * $ratio + $rb[0] * ( 1 - $ratio ),
				$ra[1] * $ratio + $rb[1] * ( 1 - $ratio ),
				$ra[2] * $ratio + $rb[2] * ( 1 - $ratio ),
			)
		);
	}

	/**
	 * A háttéren jobban olvasható szín a sötét és a világos közül.
	 *
	 * @param string $bg Háttér.
	 * @return string
	 */
	public static function readable_on( $bg ) {
		return self::contrast( '#111827', $bg ) >= self::contrast( '#ffffff', $bg ) ? '#111827' : '#ffffff';
	}

	/**
	 * Az $fg marad, ha elég kontrasztos a $bg-n; különben a $fallback, és ha az
	 * sem elég, a sötét/világos közül a jobbik.
	 *
	 * @param string $fg       Előtérszín.
	 * @param string $bg       Háttér.
	 * @param float  $min      Elvárt arány.
	 * @param string $fallback Első tartalék.
	 * @return string
	 */
	private static function ensure( $fg, $bg, $min, $fallback = '' ) {
		if ( self::contrast( $fg, $bg ) >= $min ) {
			return $fg;
		}
		if ( '' !== $fallback && self::contrast( $fallback, $bg ) >= $min ) {
			return $fallback;
		}
		return self::readable_on( $bg );
	}

	/**
	 * Biztonságos színkészlet: a beállított színek, ahol kell, javítva, plusz
	 * néhány származtatott változó (gombkeretek, előtérben használt kiemelőszín).
	 *
	 * @param array $colors Színek (bg, text, muted, border, primary, primary_text, secondary, secondary_text, link, toggle_on, overlay).
	 * @return array{colors: array, fixes: string[]}
	 */
	public static function safe_palette( array $colors ) {
		$c     = $colors;
		$fixes = array();
		$bg    = isset( $c['bg'] ) ? (string) $c['bg'] : '#ffffff';

		$fix = function ( $key, $value, $label ) use ( &$c, &$fixes ) {
			if ( isset( $c[ $key ] ) && strtolower( (string) $c[ $key ] ) !== strtolower( $value ) ) {
				$fixes[] = $label;
			}
			$c[ $key ] = $value;
		};

		$text = isset( $c['text'] ) ? (string) $c['text'] : '#1f2430';
		$fix( 'text', self::ensure( $text, $bg, self::TEXT_MIN ), __( 'Szöveg a háttéren', 'szesuti' ) );

		// A banner fő üzenete a „halvány” színnel jelenik meg, így annak is olvashatónak kell lennie.
		$muted = isset( $c['muted'] ) ? (string) $c['muted'] : $c['text'];
		$fix( 'muted', self::ensure( $muted, $bg, self::TEXT_MIN, $c['text'] ), __( 'Halvány szöveg a háttéren', 'szesuti' ) );

		$link = isset( $c['link'] ) ? (string) $c['link'] : $c['text'];
		$fix( 'link', self::ensure( $link, $bg, self::TEXT_MIN, $c['text'] ), __( 'Linkek a háttéren', 'szesuti' ) );

		$primary = isset( $c['primary'] ) ? (string) $c['primary'] : '#2d6cdf';
		$ptext   = isset( $c['primary_text'] ) ? (string) $c['primary_text'] : '#ffffff';
		$fix( 'primary_text', self::ensure( $ptext, $primary, self::TEXT_MIN ), __( 'Elsődleges gomb felirata', 'szesuti' ) );

		$secondary = isset( $c['secondary'] ) ? (string) $c['secondary'] : '#eef1f7';
		$stext     = isset( $c['secondary_text'] ) ? (string) $c['secondary_text'] : $c['text'];
		$fix( 'secondary_text', self::ensure( $stext, $secondary, self::TEXT_MIN, $c['text'] ), __( 'Másodlagos gomb felirata', 'szesuti' ) );

		$toggle = isset( $c['toggle_on'] ) ? (string) $c['toggle_on'] : $primary;
		$fix( 'toggle_on', self::ensure( $toggle, $bg, self::UI_MIN, $primary ), __( 'Bekapcsolt kapcsoló', 'szesuti' ) );

		// A kiemelőszín előtérként (körvonalas gomb felirata, ikon, fókuszkeret) is megjelenik a háttéren.
		$c['primary_fg'] = self::ensure( $primary, $bg, self::UI_MIN, $c['text'] );

		// Ha egy gomb kitöltése szinte egybeolvad a háttérrel, kap egy jól látható keretet,
		// hogy gombként felismerhető maradjon.
		$edge                 = self::mix( $c['text'], $bg, 0.35 );
		$c['primary_border']  = self::contrast( $primary, $bg ) < 1.3 ? $edge : $primary;
		$c['secondary_border'] = self::contrast( $secondary, $bg ) < 1.06 ? $edge : $secondary;

		// Harmadik (Beállítások) gomb: az üres szín automatikus – átlátszó háttér, halvány
		// szöveg, a keret színe. A felirat itt is olvasható kell legyen.
		$tbg   = ! empty( $c['tertiary'] ) ? (string) $c['tertiary'] : $bg;
		$ttext = ! empty( $c['tertiary_text'] ) ? (string) $c['tertiary_text'] : $c['muted'];
		$fixed = self::ensure( $ttext, $tbg, self::TEXT_MIN, $c['text'] );
		if ( strtolower( $fixed ) !== strtolower( $ttext ) ) {
			$fixes[] = __( 'Beállítások gomb felirata', 'szesuti' );
		}
		if ( ! empty( $c['tertiary_text'] ) || ! empty( $c['tertiary'] ) || strtolower( $fixed ) !== strtolower( $ttext ) ) {
			$c['tertiary_text'] = $fixed;
		}

		$border = ! empty( $c['tertiary_border'] ) ? (string) $c['tertiary_border'] : ( isset( $c['border'] ) ? (string) $c['border'] : '#e4e7ee' );
		// Ha sem a kerete, sem a kitöltése nem válik el a háttértől, kap egy látható keretet.
		$c['ghost_border'] = ( self::contrast( $border, $bg ) < 1.15 && self::contrast( $tbg, $bg ) < 1.3 ) ? $edge : $border;

		return array(
			'colors' => $c,
			'fixes'  => array_values( array_unique( $fixes ) ),
		);
	}
}
