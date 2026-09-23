<?php
/**
 * Admin – Szövegek fül.
 *
 * @var array         $s    Beállítások.
 * @var SZESUTI_I18n  $i18n Nyelvkezelő.
 * @var SZESUTI_Admin $this Admin osztály.
 *
 * @package SZESUTI
 */

defined( 'ABSPATH' ) || exit;

$szesuti_langs   = (array) $s['languages'];
$szesuti_default = (string) $s['default_language'];
$szesuti_texts   = (array) $s['texts'];

$szesuti_known_langs = array(
	'hu' => 'Magyar (hu)',
	'en' => 'English (en)',
	'de' => 'Deutsch (de)',
	'sk' => 'Slovenčina (sk)',
	'ro' => 'Română (ro)',
	'hr' => 'Hrvatski (hr)',
	'sr' => 'Srpski (sr)',
	'pl' => 'Polski (pl)',
	'cs' => 'Čeština (cs)',
	'fr' => 'Français (fr)',
	'it' => 'Italiano (it)',
	'es' => 'Español (es)',
	'nl' => 'Nederlands (nl)',
	'uk' => 'Українська (uk)',
);

SZESUTI_F::section(
	__( 'Nyelvek', 'szesuti' ),
	__( 'A banner automatikusan az oldal aktuális nyelvén jelenik meg. Polylang és WPML esetén a plugin azok nyelvét használja, egyébként a WordPress beállított nyelvét.', 'szesuti' )
);

SZESUTI_F::table_start();

SZESUTI_F::text(
	array( 'languages' ),
	implode( ', ', $szesuti_langs ),
	array(
		'label'       => __( 'Használt nyelvkódok', 'szesuti' ),
		'class'       => 'large-text',
		'placeholder' => 'hu, en, de',
		'desc'        => __( 'Vesszővel elválasztva. Minden itt felsorolt nyelvhez külön szerkeszthető szövegcsomag jelenik meg lent. Magyarhoz és angolhoz gyári szövegek tartoznak, a többihez az angol az alap.', 'szesuti' ),
	)
);

$szesuti_default_options = array();
foreach ( $szesuti_langs as $szesuti_lang ) {
	$szesuti_default_options[ $szesuti_lang ] = isset( $szesuti_known_langs[ $szesuti_lang ] )
		? $szesuti_known_langs[ $szesuti_lang ]
		: $szesuti_lang;
}

SZESUTI_F::select(
	array( 'default_language' ),
	$szesuti_default,
	$szesuti_default_options,
	array(
		'label' => __( 'Alapnyelv', 'szesuti' ),
		'desc'  => __( 'Erre esik vissza a banner, ha a látogató nyelvéhez nincs szövegcsomag.', 'szesuti' ),
	)
);

SZESUTI_F::table_end();

SZESUTI_F::notice(
	sprintf(
		/* translators: %s: nyelvkód */
		__( 'A jelenlegi felületen felismert nyelv: <code>%s</code>. A látogatói oldalon ez a látogató nyelve szerint változik.', 'szesuti' ),
		esc_html( $i18n->current_language() )
	),
	'info'
);

/* ------------------------------------------------------------------ */

$szesuti_groups = array(
	'banner' => array(
		'title'  => __( 'Banner', 'szesuti' ),
		'fields' => array(
			'title'   => array( __( 'Cím', 'szesuti' ), 'text' ),
			'message' => array( __( 'Fő szöveg', 'szesuti' ), 'textarea' ),
		),
	),
	'buttons' => array(
		'title'  => __( 'Gombfeliratok', 'szesuti' ),
		'fields' => array(
			'accept_all'      => array( __( 'Összes elfogadása', 'szesuti' ), 'text' ),
			'reject_all'      => array( __( 'Elutasítás', 'szesuti' ), 'text' ),
			'settings'        => array( __( 'Beállítások', 'szesuti' ), 'text' ),
			'accept_selected' => array( __( 'Kiválasztottak mentése', 'szesuti' ), 'text' ),
			'save'            => array( __( 'Mentés', 'szesuti' ), 'text' ),
			'close'           => array( __( 'Bezárás', 'szesuti' ), 'text' ),
			'back'            => array( __( 'Vissza', 'szesuti' ), 'text' ),
		),
	),
	'prefs' => array(
		'title'  => __( 'Beállítások ablak', 'szesuti' ),
		'fields' => array(
			'prefs_title'    => array( __( 'Ablak címe', 'szesuti' ), 'text' ),
			'prefs_intro'    => array( __( 'Bevezető szöveg', 'szesuti' ), 'textarea' ),
			'always_active'  => array( __( '„Mindig aktív” felirat', 'szesuti' ), 'text' ),
			'details'        => array( __( '„Részletek” felirat', 'szesuti' ), 'text' ),
			'reopen_label'   => array( __( 'Lebegő gomb felirata', 'szesuti' ), 'text' ),
		),
	),
	'links' => array(
		'title'  => __( 'Linkek feliratai', 'szesuti' ),
		'fields' => array(
			'privacy_link'       => array( __( 'Adatkezelési tájékoztató', 'szesuti' ), 'text' ),
			'cookie_policy_link' => array( __( 'Süti tájékoztató', 'szesuti' ), 'text' ),
			'imprint_link'       => array( __( 'Impresszum', 'szesuti' ), 'text' ),
		),
	),
	'table' => array(
		'title'  => __( 'Sütitáblázat fejlécei', 'szesuti' ),
		'fields' => array(
			'cookie_table_name' => array( __( 'Süti neve', 'szesuti' ), 'text' ),
			'cookie_table_prov' => array( __( 'Szolgáltató', 'szesuti' ), 'text' ),
			'cookie_table_dur'  => array( __( 'Élettartam', 'szesuti' ), 'text' ),
			'cookie_table_desc' => array( __( 'Leírás', 'szesuti' ), 'text' ),
		),
	),
	'blocked' => array(
		'title'  => __( 'Blokkolt tartalom helyőrzője', 'szesuti' ),
		'fields' => array(
			'blocked_title' => array( __( 'Cím', 'szesuti' ), 'text' ),
			'blocked_text'  => array( __( 'Szöveg', 'szesuti' ), 'text' ),
			'blocked_btn'   => array( __( 'Gomb felirata', 'szesuti' ), 'text' ),
		),
	),
	'status' => array(
		'title'  => __( 'Állapotfeliratok (shortcode-hoz)', 'szesuti' ),
		'fields' => array(
			'status_accepted' => array( __( 'Elfogadva', 'szesuti' ), 'text' ),
			'status_rejected' => array( __( 'Elutasítva', 'szesuti' ), 'text' ),
			'status_partial'  => array( __( 'Részlegesen elfogadva', 'szesuti' ), 'text' ),
			'status_none'     => array( __( 'Nincs döntés', 'szesuti' ), 'text' ),
		),
	),
);

// Gyári szövegek: ezekre esünk vissza, ha egy nyelvhez még nincs mentett érték.
$szesuti_all_factory = ( new SZESUTI_Settings() )->default_texts();

foreach ( $szesuti_langs as $szesuti_lang ) :
	$szesuti_set = isset( $szesuti_texts[ $szesuti_lang ] ) ? $szesuti_texts[ $szesuti_lang ] : array();
	$szesuti_fb  = isset( $szesuti_all_factory[ $szesuti_lang ] ) ? $szesuti_all_factory[ $szesuti_lang ] : $szesuti_all_factory['en'];
	$szesuti_name = isset( $szesuti_known_langs[ $szesuti_lang ] ) ? $szesuti_known_langs[ $szesuti_lang ] : $szesuti_lang;
	?>
	<details class="szesuti-lang" <?php echo ( $szesuti_lang === $szesuti_default ) ? 'open' : ''; ?>>
		<summary>
			<span class="szesuti-lang__code"><?php echo esc_html( strtoupper( $szesuti_lang ) ); ?></span>
			<span class="szesuti-lang__name"><?php echo esc_html( $szesuti_name ); ?></span>
			<?php if ( $szesuti_lang === $szesuti_default ) : ?>
				<span class="szesuti-badge szesuti-badge--on"><?php esc_html_e( 'alapnyelv', 'szesuti' ); ?></span>
			<?php endif; ?>
		</summary>

		<div class="szesuti-lang__body">
			<?php foreach ( $szesuti_groups as $szesuti_group ) : ?>
				<h3 class="szesuti-subsection"><?php echo esc_html( $szesuti_group['title'] ); ?></h3>
				<?php
				SZESUTI_F::table_start();
				foreach ( $szesuti_group['fields'] as $szesuti_key => $szesuti_field ) {
					$szesuti_value = isset( $szesuti_set[ $szesuti_key ] )
						? $szesuti_set[ $szesuti_key ]
						: ( isset( $szesuti_fb[ $szesuti_key ] ) ? $szesuti_fb[ $szesuti_key ] : '' );

					$szesuti_path = array( 'texts', $szesuti_lang, $szesuti_key );

					if ( 'textarea' === $szesuti_field[1] ) {
						SZESUTI_F::textarea(
							$szesuti_path,
							$szesuti_value,
							array(
								'label' => $szesuti_field[0],
								'rows'  => 4,
								'desc'  => ( 'message' === $szesuti_key || 'prefs_intro' === $szesuti_key )
									? __( 'Engedélyezett HTML: <code>&lt;a&gt; &lt;strong&gt; &lt;em&gt; &lt;br&gt; &lt;ul&gt; &lt;li&gt;</code>', 'szesuti' )
									: '',
							)
						);
					} else {
						SZESUTI_F::text(
							$szesuti_path,
							$szesuti_value,
							array(
								'label' => $szesuti_field[0],
								'class' => 'large-text',
								'desc'  => ( 'blocked_text' === $szesuti_key )
									? __( 'A <code>%s</code> helyére a kategória neve kerül.', 'szesuti' )
									: '',
							)
						);
					}
				}
				SZESUTI_F::table_end();
				?>
			<?php endforeach; ?>

			<h3 class="szesuti-subsection"><?php esc_html_e( 'Kategóriák nevei és leírásai', 'szesuti' ); ?></h3>
			<?php
			SZESUTI_F::table_start();
			foreach ( $s['categories'] as $szesuti_cat ) {
				if ( empty( $szesuti_cat['enabled'] ) ) {
					continue;
				}
				$szesuti_slug = $szesuti_cat['slug'];

				$szesuti_cat_name = isset( $szesuti_set['cat'][ $szesuti_slug ]['name'] )
					? $szesuti_set['cat'][ $szesuti_slug ]['name']
					: ( isset( $szesuti_fb['cat'][ $szesuti_slug ]['name'] ) ? $szesuti_fb['cat'][ $szesuti_slug ]['name'] : ucfirst( $szesuti_slug ) );

				$szesuti_cat_desc = isset( $szesuti_set['cat'][ $szesuti_slug ]['desc'] )
					? $szesuti_set['cat'][ $szesuti_slug ]['desc']
					: ( isset( $szesuti_fb['cat'][ $szesuti_slug ]['desc'] ) ? $szesuti_fb['cat'][ $szesuti_slug ]['desc'] : '' );

				SZESUTI_F::text(
					array( 'texts', $szesuti_lang, 'cat', $szesuti_slug, 'name' ),
					$szesuti_cat_name,
					array(
						'label' => sprintf( '%s – %s', $szesuti_slug, __( 'név', 'szesuti' ) ),
						'class' => 'large-text',
					)
				);

				SZESUTI_F::textarea(
					array( 'texts', $szesuti_lang, 'cat', $szesuti_slug, 'desc' ),
					$szesuti_cat_desc,
					array(
						'label' => sprintf( '%s – %s', $szesuti_slug, __( 'leírás', 'szesuti' ) ),
						'rows'  => 3,
					)
				);
			}
			SZESUTI_F::table_end();
			?>
		</div>
	</details>
	<?php
endforeach;
