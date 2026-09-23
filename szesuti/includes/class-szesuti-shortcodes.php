<?php
/**
 * Shortcode-ok a süti tájékoztató oldalhoz.
 *
 * [szesuti_cookie_table]      – kategóriánkénti sütitáblázat
 * [szesuti_settings_button]   – „Süti beállítások módosítása” gomb
 * [szesuti_consent_status]    – az aktuális döntés kiírása
 *
 * @package SZESUTI
 */

defined( 'ABSPATH' ) || exit;

class SZESUTI_Shortcodes {

	/** @var SZESUTI_Settings */
	private $settings;

	/** @var SZESUTI_I18n */
	private $i18n;

	public function __construct( SZESUTI_Settings $settings, SZESUTI_I18n $i18n ) {
		$this->settings = $settings;
		$this->i18n     = $i18n;

		add_shortcode( 'szesuti_cookie_table', array( $this, 'cookie_table' ) );
		add_shortcode( 'szesuti_settings_button', array( $this, 'settings_button' ) );
		add_shortcode( 'szesuti_consent_status', array( $this, 'consent_status' ) );
	}

	/**
	 * Sütitáblázat kategóriánként.
	 *
	 * @param array $atts category, show_desc, title_tag.
	 * @return string
	 */
	public function cookie_table( $atts ) {
		$atts = shortcode_atts(
			array(
				'category'  => '',
				'show_desc' => 'yes',
				'title_tag' => 'h3',
			),
			$atts,
			'szesuti_cookie_table'
		);

		$cookies    = (array) $this->settings->get( 'cookies', array() );
		$categories = $this->settings->active_categories();
		$texts      = $this->i18n->texts();
		$allowed    = $this->settings->allowed_html();

		$title_tag = in_array( $atts['title_tag'], array( 'h2', 'h3', 'h4', 'h5', 'p' ), true ) ? $atts['title_tag'] : 'h3';
		$show_desc = 'no' !== strtolower( $atts['show_desc'] );
		$filter    = sanitize_key( $atts['category'] );

		ob_start();

		echo '<div class="szesuti-cookie-doc szesuti-vars">';

		foreach ( $categories as $cat ) {
			if ( '' !== $filter && $cat['slug'] !== $filter ) {
				continue;
			}

			$rows = array();
			foreach ( $cookies as $cookie ) {
				if ( isset( $cookie['category'] ) && $cookie['category'] === $cat['slug'] ) {
					$rows[] = $cookie;
				}
			}

			$meta = $this->i18n->category( $cat['slug'] );

			printf(
				'<%1$s class="szesuti-cookie-doc__title">%2$s</%1$s>',
				esc_html( $title_tag ),
				esc_html( $meta['name'] )
			);

			if ( $show_desc && '' !== $meta['desc'] ) {
				echo '<div class="szesuti-cookie-doc__desc">' . wp_kses( $meta['desc'], $allowed ) . '</div>';
			}

			if ( empty( $rows ) ) {
				continue;
			}

			echo '<div class="szesuti-table-wrap"><table class="szesuti-table"><thead><tr>';
			echo '<th scope="col">' . esc_html( $texts['cookie_table_name'] ) . '</th>';
			echo '<th scope="col">' . esc_html( $texts['cookie_table_prov'] ) . '</th>';
			echo '<th scope="col">' . esc_html( $texts['cookie_table_dur'] ) . '</th>';
			echo '<th scope="col">' . esc_html( $texts['cookie_table_desc'] ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $rows as $cookie ) {
				echo '<tr>';
				echo '<td><code>' . esc_html( $cookie['name'] ) . '</code></td>';
				echo '<td>' . esc_html( isset( $cookie['provider'] ) ? $cookie['provider'] : '' ) . '</td>';
				echo '<td>' . esc_html( isset( $cookie['duration'] ) ? $cookie['duration'] : '' ) . '</td>';
				echo '<td>' . esc_html( $this->i18n->cookie_description( $cookie ) ) . '</td>';
				echo '</tr>';
			}

			echo '</tbody></table></div>';
		}

		echo '</div>';

		$this->enqueue_doc_styles();

		return (string) ob_get_clean();
	}

	/**
	 * Beállításokat újranyitó gomb.
	 *
	 * @param array $atts text, class.
	 * @return string
	 */
	public function settings_button( $atts ) {
		$atts = shortcode_atts(
			array(
				'text'  => '',
				'class' => '',
				'style' => 'button',
			),
			$atts,
			'szesuti_settings_button'
		);

		$label = '' !== $atts['text'] ? $atts['text'] : $this->i18n->t( 'reopen_label', 'Süti beállítások' );
		$class = 'link' === $atts['style'] ? 'szesuti-doc-link' : 'szesuti-doc-btn';

		if ( '' !== $atts['class'] ) {
			$class .= ' ' . implode( ' ', array_map( 'sanitize_html_class', explode( ' ', $atts['class'] ) ) );
		}

		$this->enqueue_doc_styles();

		$button = sprintf(
			'<button type="button" class="%1$s" data-szesuti-action="open-prefs">%2$s</button>',
			esc_attr( $class ),
			esc_html( $label )
		);

		// A link stílus szövegen belül is használható, ezért azt nem csomagoljuk.
		// A gombot blokkszintű elembe tesszük, hogy a téma tartalomoszlopában maradjon.
		if ( 'link' === $atts['style'] ) {
			return $button;
		}

		return '<div class="szesuti-doc-btn-wrap szesuti-vars">' . $button . '</div>';
	}

	/**
	 * Az aktuális hozzájárulás állapota.
	 *
	 * @return string
	 */
	public function consent_status() {
		$consent = szesuti()->consent;
		$texts   = $this->i18n->texts();

		if ( ! $consent->has_decision() ) {
			$label = isset( $texts['status_none'] ) ? $texts['status_none'] : '';
		} else {
			$accepted = $consent->accepted_categories();
			$all      = $this->settings->category_slugs();
			$optional = array();

			foreach ( $this->settings->active_categories() as $cat ) {
				if ( empty( $cat['locked'] ) ) {
					$optional[] = $cat['slug'];
				}
			}

			$accepted_optional = array_intersect( $optional, $accepted );

			if ( count( $accepted ) >= count( $all ) ) {
				$label = isset( $texts['status_accepted'] ) ? $texts['status_accepted'] : '';
			} elseif ( empty( $accepted_optional ) ) {
				$label = isset( $texts['status_rejected'] ) ? $texts['status_rejected'] : '';
			} else {
				$label = isset( $texts['status_partial'] ) ? $texts['status_partial'] : '';
			}
		}

		return '<span class="szesuti-status">' . esc_html( $label ) . '</span>';
	}

	/**
	 * A shortcode-ok stílusa. Maguk a szabályok a szesuti.css-ben vannak (témabiztosan),
	 * itt csak azt biztosítjuk, hogy a stíluslap és a színváltozók akkor is betöltődjenek,
	 * ha a banner ezen az oldalon nem jelenik meg.
	 */
	private function enqueue_doc_styles() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		if ( ! wp_style_is( 'szesuti', 'enqueued' ) ) {
			wp_enqueue_style( 'szesuti', SZESUTI_URL . 'assets/css/szesuti.css', array(), SZESUTI_VERSION );
			wp_add_inline_style( 'szesuti', szesuti()->frontend->inline_css() );
		}
	}
}
