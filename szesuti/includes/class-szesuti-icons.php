<?php
/**
 * Ikonok: a beépített rajzolt ikonok, a beépített képes ikon és a saját kép kezelése.
 *
 * Egy helyen él, mert a banner, a lebegő gomb és az admin ikonválasztója is használja.
 *
 * @package SZESUTI
 */

defined( 'ABSPATH' ) || exit;

class SZESUTI_Icons {

	/**
	 * A választható ikonok: kulcs => címke.
	 *
	 * @param bool $with_none Legyen-e „nincs ikon” lehetőség.
	 * @return array
	 */
	public static function choices( $with_none = true ) {
		$choices = array(
			'cookie' => __( 'Süti', 'szesuti' ),
			'shield' => __( 'Pajzs', 'szesuti' ),
			'lock'   => __( 'Lakat', 'szesuti' ),
			'pixel'  => __( 'Pixeles süti', 'szesuti' ),
			'custom' => __( 'Saját kép', 'szesuti' ),
		);

		if ( $with_none ) {
			$choices['none'] = __( 'Nincs ikon', 'szesuti' );
		}

		return $choices;
	}

	/**
	 * Rajzolt (SVG) ikon, vagy üres string, ha a kulcs nem SVG-s ikon.
	 *
	 * @param string $name Ikon kulcsa.
	 * @return string
	 */
	public static function svg( $name ) {
		$open = '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">';

		switch ( $name ) {
			case 'cookie':
				return $open . '<path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5Z"/><circle cx="9" cy="10" r="1" fill="currentColor" stroke="none"/><circle cx="14.5" cy="14.5" r="1" fill="currentColor" stroke="none"/><circle cx="9.5" cy="15.5" r="1" fill="currentColor" stroke="none"/></svg>';
			case 'shield':
				return $open . '<path d="M12 2 4 5v6c0 5 3.4 9.4 8 11 4.6-1.6 8-6 8-11V5Z"/><path d="m9 12 2 2 4-4"/></svg>';
			case 'lock':
				return $open . '<rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';
		}

		return '';
	}

	/**
	 * A beépített pixeles süti kép URL-je.
	 *
	 * @return string
	 */
	public static function pixel_url() {
		return SZESUTI_URL . 'assets/img/suti-pixel.png';
	}

	/**
	 * Egy ikon kirajzolásra kész HTML-je.
	 *
	 * @param string $name       Ikon kulcsa.
	 * @param string $custom_url Saját kép URL-je (csak „custom” esetén).
	 * @param string $alt        Alternatív szöveg képnél (díszítő ikonnál üres).
	 * @return string Üres string, ha nincs mit kirajzolni.
	 */
	public static function html( $name, $custom_url = '', $alt = '' ) {
		if ( 'pixel' === $name ) {
			return sprintf(
				'<img class="szesuti-icon-img szesuti-icon-img--pixel" src="%1$s" alt="%2$s" width="256" height="256" decoding="async" />',
				esc_url( self::pixel_url() ),
				esc_attr( $alt )
			);
		}

		if ( 'custom' === $name ) {
			if ( '' === (string) $custom_url ) {
				return self::svg( 'cookie' );
			}
			return sprintf(
				'<img class="szesuti-icon-img" src="%1$s" alt="%2$s" decoding="async" />',
				esc_url( $custom_url ),
				esc_attr( $alt )
			);
		}

		return self::svg( $name );
	}

	/**
	 * Kép-e (és nem rajzolt ikon) az adott választás.
	 *
	 * @param string $name       Ikon kulcsa.
	 * @param string $custom_url Saját kép URL-je.
	 * @return bool
	 */
	public static function is_image( $name, $custom_url = '' ) {
		return 'pixel' === $name || ( 'custom' === $name && '' !== (string) $custom_url );
	}
}
