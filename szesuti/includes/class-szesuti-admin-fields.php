<?php
/**
 * Admin mezők kirajzolása.
 *
 * Minden mező neve `szesuti[szekció][kulcs]` formájú, hogy mentéskor egyben
 * megkapjuk a szekciót.
 *
 * @package SZESUTI
 */

defined( 'ABSPATH' ) || exit;

class SZESUTI_Admin_Fields {

	/**
	 * Mezőnév összeállítása útvonalból: array( 'general', 'enabled' ).
	 *
	 * @param array $path Útvonal.
	 * @return string
	 */
	public static function name( array $path ) {
		$name = 'szesuti';
		foreach ( $path as $part ) {
			$name .= '[' . $part . ']';
		}
		return $name;
	}

	/**
	 * Egyedi azonosító az útvonalból.
	 *
	 * @param array $path Útvonal.
	 * @return string
	 */
	public static function id( array $path ) {
		return 'szesuti-' . implode( '-', array_map( 'sanitize_html_class', $path ) );
	}

	/**
	 * Sor nyitása (címke + leírás + mező).
	 *
	 * @param string $label Címke.
	 * @param string $for   Mező azonosító.
	 * @param string $desc  Leírás.
	 * @param bool   $important Kiemelt (fontos) sor.
	 */
	public static function row_start( $label, $for = '', $desc = '', $important = false ) {
		echo '<tr class="szesuti-row' . ( $important ? ' szesuti-row--important' : '' ) . '">';
		echo '<th scope="row">';
		if ( '' !== $for ) {
			echo '<label for="' . esc_attr( $for ) . '">' . esc_html( $label ) . '</label>';
		} else {
			echo esc_html( $label );
		}
		if ( $important ) {
			echo ' <span class="szesuti-important-pill">' . esc_html__( 'Fontos', 'szesuti' ) . '</span>';
		}
		if ( '' !== $desc ) {
			echo '<p class="szesuti-th-desc">' . wp_kses_post( $desc ) . '</p>';
		}
		echo '</th><td>';
	}

	/** Sor zárása. */
	public static function row_end() {
		echo '</td></tr>';
	}

	/**
	 * Szöveges mező.
	 *
	 * @param array  $path  Útvonal.
	 * @param mixed  $value Érték.
	 * @param array  $args  label, desc, placeholder, type, class.
	 */
	public static function text( array $path, $value, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'label'       => '',
				'desc'        => '',
				'placeholder' => '',
				'type'        => 'text',
				'class'       => 'regular-text',
				'min'         => null,
				'max'         => null,
				'step'        => null,
				'suffix'      => '',
			)
		);

		$id = self::id( $path );
		self::row_start( $args['label'], $id );

		printf(
			'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" placeholder="%5$s" class="%6$s"%7$s%8$s%9$s />',
			esc_attr( $args['type'] ),
			esc_attr( $id ),
			esc_attr( self::name( $path ) ),
			esc_attr( (string) $value ),
			esc_attr( $args['placeholder'] ),
			esc_attr( $args['class'] ),
			null !== $args['min'] ? ' min="' . esc_attr( $args['min'] ) . '"' : '',
			null !== $args['max'] ? ' max="' . esc_attr( $args['max'] ) . '"' : '',
			null !== $args['step'] ? ' step="' . esc_attr( $args['step'] ) . '"' : ''
		);

		if ( '' !== $args['suffix'] ) {
			echo ' <span class="szesuti-suffix">' . esc_html( $args['suffix'] ) . '</span>';
		}

		self::desc( $args['desc'] );
		self::row_end();
	}

	/**
	 * Többsoros mező.
	 *
	 * @param array $path  Útvonal.
	 * @param mixed $value Érték.
	 * @param array $args  label, desc, rows, placeholder, code.
	 */
	public static function textarea( array $path, $value, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'label'       => '',
				'desc'        => '',
				'rows'        => 4,
				'placeholder' => '',
				'code'        => false,
			)
		);

		$id = self::id( $path );
		self::row_start( $args['label'], $id );

		printf(
			'<textarea id="%1$s" name="%2$s" rows="%3$d" placeholder="%4$s" class="large-text%5$s">%6$s</textarea>',
			esc_attr( $id ),
			esc_attr( self::name( $path ) ),
			(int) $args['rows'],
			esc_attr( $args['placeholder'] ),
			$args['code'] ? ' code szesuti-code' : '',
			esc_textarea( (string) $value )
		);

		self::desc( $args['desc'] );
		self::row_end();
	}

	/**
	 * Jelölőnégyzet (kapcsoló).
	 *
	 * @param array $path  Útvonal.
	 * @param mixed $value Érték.
	 * @param array $args  label, desc, text, important (kiemelt, bekapcsolva piros kapcsoló).
	 */
	public static function checkbox( array $path, $value, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'label'     => '',
				'desc'      => '',
				'text'      => __( 'Bekapcsolva', 'szesuti' ),
				'important' => false,
			)
		);

		$id = self::id( $path );
		self::row_start( $args['label'], $id, '', (bool) $args['important'] );

		echo '<label class="szesuti-toggle' . ( $args['important'] ? ' szesuti-toggle--important' : '' ) . '">';
		printf(
			'<input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s />',
			esc_attr( $id ),
			esc_attr( self::name( $path ) ),
			checked( ! empty( $value ), true, false )
		);
		echo '<span class="szesuti-toggle__track"><span class="szesuti-toggle__thumb"></span></span>';
		echo '<span class="szesuti-toggle__text">' . esc_html( $args['text'] ) . '</span>';
		if ( $args['important'] ) {
			// Állapotjelző: színvakok számára is egyértelmű, hogy be vagy ki van kapcsolva.
			echo '<span class="szesuti-toggle__state szesuti-toggle__state--on">' . esc_html__( 'BE', 'szesuti' ) . '</span>';
			echo '<span class="szesuti-toggle__state szesuti-toggle__state--off">' . esc_html__( 'KI', 'szesuti' ) . '</span>';
		}
		echo '</label>';

		self::desc( $args['desc'] );
		self::row_end();
	}

	/**
	 * Legördülő lista.
	 *
	 * @param array $path    Útvonal.
	 * @param mixed $value   Érték.
	 * @param array $options Lehetőségek (érték => címke).
	 * @param array $args    label, desc.
	 */
	public static function select( array $path, $value, array $options, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'label' => '',
				'desc'  => '',
			)
		);

		$id = self::id( $path );
		self::row_start( $args['label'], $id );

		printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( self::name( $path ) ) );
		foreach ( $options as $key => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $key ),
				selected( (string) $value, (string) $key, false ),
				esc_html( $label )
			);
		}
		echo '</select>';

		self::desc( $args['desc'] );
		self::row_end();
	}

	/**
	 * Színválasztó.
	 *
	 * @param array $path  Útvonal.
	 * @param mixed $value Érték.
	 * @param array $args  label, desc.
	 */
	public static function color( array $path, $value, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'label'    => '',
				'desc'     => '',
				'optional' => false, // Üresen hagyható: ilyenkor automatikus.
				'fallback' => '#ffffff', // A választó ezt mutatja, ha az érték üres.
			)
		);

		$id    = self::id( $path );
		$name  = self::name( $path );
		$value = (string) $value;

		echo '<div class="szesuti-color' . ( $args['optional'] ? ' szesuti-color--optional' : '' ) . '">';
		printf( '<label class="szesuti-color__label" for="%1$s">%2$s</label>', esc_attr( $id ), esc_html( $args['label'] ) );
		echo '<span class="szesuti-color__controls">';
		printf(
			'<input type="color" id="%1$s" value="%2$s" data-szesuti-color-for="%3$s" />',
			esc_attr( $id ),
			esc_attr( '' !== $value ? $value : $args['fallback'] ),
			esc_attr( $id . '-text' )
		);
		printf(
			'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="szesuti-color__text" maxlength="7"%4$s />',
			esc_attr( $id . '-text' ),
			esc_attr( $name ),
			esc_attr( $value ),
			$args['optional'] ? ' placeholder="' . esc_attr__( 'automatikus', 'szesuti' ) . '"' : ''
		);
		if ( $args['optional'] ) {
			printf(
				'<button type="button" class="button-link szesuti-color__clear" data-szesuti-color-clear="%1$s" title="%2$s" aria-label="%2$s">&times;</button>',
				esc_attr( $id . '-text' ),
				esc_attr__( 'Vissza automatikusra', 'szesuti' )
			);
		}
		echo '</span></div>';
	}

	/**
	 * Csúszka számértékhez.
	 *
	 * @param array $path  Útvonal.
	 * @param mixed $value Érték.
	 * @param array $args  label, desc, min, max, step, unit.
	 */
	public static function range( array $path, $value, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'label' => '',
				'desc'  => '',
				'min'   => 0,
				'max'   => 100,
				'step'  => 1,
				'unit'  => 'px',
			)
		);

		$id = self::id( $path );
		self::row_start( $args['label'], $id );

		printf(
			'<span class="szesuti-range"><input type="range" id="%1$s" name="%2$s" value="%3$s" min="%4$s" max="%5$s" step="%6$s" /><output for="%1$s">%3$s%7$s</output></span>',
			esc_attr( $id ),
			esc_attr( self::name( $path ) ),
			esc_attr( (string) $value ),
			esc_attr( $args['min'] ),
			esc_attr( $args['max'] ),
			esc_attr( $args['step'] ),
			esc_html( $args['unit'] )
		);

		self::desc( $args['desc'] );
		self::row_end();
	}

	/**
	 * Ikonválasztó: kattintható csempék a beépített ikonokkal, a pixeles sütivel és
	 * saját képpel (médiatárból vagy a gépről feltöltve).
	 *
	 * @param array  $path      Az ikon kulcsának útvonala (pl. appearance.icon).
	 * @param string $value     Kiválasztott ikon.
	 * @param array  $url_path  A saját kép URL-jének útvonala.
	 * @param string $url_value A saját kép URL-je.
	 * @param array  $args      label, desc, with_none.
	 */
	public static function icon_picker( array $path, $value, array $url_path, $url_value, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'label'     => '',
				'desc'      => '',
				'with_none' => true,
			)
		);

		$id      = self::id( $path );
		$choices = SZESUTI_Icons::choices( $args['with_none'] );

		self::row_start( $args['label'] );

		echo '<div class="szesuti-iconpick" data-szesuti-iconpick>';
		echo '<div class="szesuti-iconpick__grid" role="radiogroup" aria-label="' . esc_attr( $args['label'] ) . '">';

		foreach ( $choices as $key => $label ) {
			if ( 'custom' === $key ) {
				$tile = '' !== (string) $url_value
					? '<img src="' . esc_url( $url_value ) . '" alt="" data-szesuti-iconpick-img />'
					: '<span class="szesuti-iconpick__plus" aria-hidden="true">+</span>';
			} elseif ( 'none' === $key ) {
				$tile = '<span class="szesuti-iconpick__none" aria-hidden="true">∅</span>';
			} else {
				$tile = SZESUTI_Icons::html( $key );
			}

			printf(
				'<label class="szesuti-iconpick__opt"><input type="radio" name="%1$s" value="%2$s"%3$s%4$s /><span class="szesuti-iconpick__tile szesuti-iconpick__tile--%2$s">%5$s</span><span class="szesuti-iconpick__label">%6$s</span></label>',
				esc_attr( self::name( $path ) ),
				esc_attr( $key ),
				checked( (string) $value, (string) $key, false ),
				'custom' === $key ? ' data-szesuti-iconpick-custom' : '',
				$tile, // phpcs:ignore WordPress.Security.EscapeOutput -- fent escapelve / SZESUTI_Icons::html().
				esc_html( $label )
			);
		}

		echo '</div>';

		printf(
			'<input type="hidden" id="%1$s" name="%2$s" value="%3$s" data-szesuti-iconpick-url />',
			esc_attr( $id . '-url' ),
			esc_attr( self::name( $url_path ) ),
			esc_attr( (string) $url_value )
		);

		echo '<div class="szesuti-iconpick__custom">';
		printf(
			'<button type="button" class="button" data-szesuti-iconpick-choose>%s</button>',
			esc_html__( 'Kép választása vagy feltöltése…', 'szesuti' )
		);
		printf(
			' <button type="button" class="button-link szesuti-iconpick__clear" data-szesuti-iconpick-clear%2$s>%1$s</button>',
			esc_html__( 'Saját kép eltávolítása', 'szesuti' ),
			'' === (string) $url_value ? ' hidden' : ''
		);
		echo '</div>';

		echo '</div>';

		self::desc( $args['desc'] );
		self::row_end();
	}

	/**
	 * Leírás kiírása a mező alá.
	 *
	 * @param string $desc Leírás (korlátozott HTML megengedett).
	 */
	public static function desc( $desc ) {
		if ( '' === $desc ) {
			return;
		}
		echo '<p class="description">' . wp_kses_post( $desc ) . '</p>';
	}

	/**
	 * Figyelmeztető doboz.
	 *
	 * @param string $message Üzenet.
	 * @param string $type    info | warning | error | success.
	 */
	public static function notice( $message, $type = 'info' ) {
		printf(
			'<div class="szesuti-note szesuti-note--%1$s">%2$s</div>',
			esc_attr( $type ),
			wp_kses_post( $message )
		);
	}

	/**
	 * Szekciócím.
	 *
	 * @param string $title Cím.
	 * @param string $desc  Leírás.
	 */
	public static function section( $title, $desc = '' ) {
		echo '<h2 class="szesuti-section">' . esc_html( $title ) . '</h2>';
		if ( '' !== $desc ) {
			echo '<p class="szesuti-section-desc">' . wp_kses_post( $desc ) . '</p>';
		}
	}

	/** Táblázat nyitása. */
	public static function table_start() {
		echo '<table class="form-table szesuti-table-form" role="presentation"><tbody>';
	}

	/** Táblázat zárása. */
	public static function table_end() {
		echo '</tbody></table>';
	}
}

class_alias( 'SZESUTI_Admin_Fields', 'SZESUTI_F' );
