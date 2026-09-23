<?php
/**
 * Admin – Általános fül.
 *
 * @var array        $s    Beállítások.
 * @var SZESUTI_Admin $this Admin osztály.
 *
 * @package SZESUTI
 */

defined( 'ABSPATH' ) || exit;

$g   = $s['general'];
$geo = $s['geo'];

SZESUTI_F::section(
	__( 'Alapbeállítások', 'szesuti' ),
	__( 'A sütikezelő működésének alapjai. Ha kikapcsolod, sem a banner, sem a szkriptblokkolás nem fut.', 'szesuti' )
);

SZESUTI_F::table_start();

SZESUTI_F::checkbox(
	array( 'general', 'enabled' ),
	$g['enabled'],
	array(
		'important' => true,
		'label' => __( 'Sütikezelő', 'szesuti' ),
		'text'  => __( 'Bekapcsolva az egész oldalon', 'szesuti' ),
		'desc'  => __( 'Ez a főkapcsoló. Kikapcsolt állapotban semmilyen szkriptblokkolás nem történik.', 'szesuti' ),
	)
);

SZESUTI_F::text(
	array( 'general', 'policy_version' ),
	$g['policy_version'],
	array(
		'label' => __( 'Szabályzat verziója', 'szesuti' ),
		'class' => 'small-text',
		'desc'  => __( 'Ha megváltoztatod (pl. 1 → 2), <strong>minden látogatótól újra bekéri a hozzájárulást</strong>. Akkor használd, ha új sütit vagy új célt vezettél be.', 'szesuti' ),
	)
);

SZESUTI_F::text(
	array( 'general', 'consent_expiry_days' ),
	$g['consent_expiry_days'],
	array(
		'label'  => __( 'Hozzájárulás érvényessége', 'szesuti' ),
		'type'   => 'number',
		'class'  => 'small-text',
		'min'    => 1,
		'max'    => 730,
		'suffix' => __( 'nap', 'szesuti' ),
		'desc'   => __( 'Ennyi idő után a látogatótól újra megkérdezzük. A hazai és uniós gyakorlat 6–12 hónapot tart elfogadhatónak.', 'szesuti' ),
	)
);

SZESUTI_F::text(
	array( 'general', 'cookie_name' ),
	$g['cookie_name'],
	array(
		'label' => __( 'Hozzájárulási süti neve', 'szesuti' ),
		'desc'  => __( 'Csak akkor változtasd meg, ha ütközik valamivel. A módosítás után minden korábbi hozzájárulás elveszik.', 'szesuti' ),
	)
);

SZESUTI_F::text(
	array( 'general', 'cookie_domain' ),
	$g['cookie_domain'],
	array(
		'label'       => __( 'Süti domainje', 'szesuti' ),
		'placeholder' => '.pelda.hu',
		'desc'        => __( 'Csak akkor töltsd ki, ha aldomainek között is közös legyen a döntés (pl. <code>.pelda.hu</code> a shop.pelda.hu és a www.pelda.hu között).', 'szesuti' ),
	)
);

SZESUTI_F::table_end();

/* ------------------------------------------------------------------ */

SZESUTI_F::section( __( 'Viselkedés', 'szesuti' ) );
SZESUTI_F::table_start();

SZESUTI_F::text(
	array( 'general', 'auto_open_delay' ),
	$g['auto_open_delay'],
	array(
		'label'  => __( 'Megjelenés késleltetése', 'szesuti' ),
		'type'   => 'number',
		'class'  => 'small-text',
		'min'    => 0,
		'max'    => 60000,
		'suffix' => __( 'ezredmásodperc', 'szesuti' ),
		'desc'   => __( '0 = azonnal. Nagyobb érték javíthat a Core Web Vitals mutatókon, de a látogató addig hozzájárulás nélkül böngészik.', 'szesuti' ),
	)
);

SZESUTI_F::checkbox(
	array( 'general', 'reload_on_reject' ),
	$g['reload_on_reject'],
	array(
		'label' => __( 'Újratöltés visszavonáskor', 'szesuti' ),
		'text'  => __( 'Töltse újra az oldalt, ha a látogató visszavon egy korábbi engedélyt', 'szesuti' ),
		'desc'  => __( '<strong>Ajánlott bekapcsolva hagyni.</strong> A már lefutott mérőkódokat utólag nem lehet leállítani, csak újratöltéssel lehet ténylegesen eltávolítani őket.', 'szesuti' ),
	)
);

SZESUTI_F::checkbox(
	array( 'general', 'reload_on_accept' ),
	$g['reload_on_accept'],
	array(
		'label' => __( 'Újratöltés elfogadáskor', 'szesuti' ),
		'text'  => __( 'Töltse újra az oldalt elfogadás után', 'szesuti' ),
		'desc'  => __( 'Általában <strong>nem kell</strong>: a blokkolt szkripteket a plugin újratöltés nélkül engedi el. Csak akkor kapcsold be, ha valamelyik külső kód nem bírja a menet közbeni betöltést.', 'szesuti' ),
	)
);

SZESUTI_F::checkbox(
	array( 'general', 'show_on_admin' ),
	$g['show_on_admin'],
	array(
		'label' => __( 'Megjelenés bejelentkezett adminoknak', 'szesuti' ),
		'text'  => __( 'Az adminok is lássák a bannert', 'szesuti' ),
		'desc'  => __( 'Teszteléshez hasznos. Ha kikapcsolod, a szkriptek adminként is blokkolva maradnak, csak a banner nem jelenik meg.', 'szesuti' ),
	)
);

SZESUTI_F::checkbox(
	array( 'general', 'respect_gpc' ),
	$g['respect_gpc'],
	array(
		'label' => __( 'Global Privacy Control (GPC)', 'szesuti' ),
		'text'  => __( 'A böngésző GPC jelzésének automatikus elfogadása', 'szesuti' ),
		'desc'  => __( 'Ha a látogató böngészője adatvédelmi jelzést küld, a banner megjelenítése nélkül automatikusan elutasításnak vesszük. Több joghatóságban elvárás.', 'szesuti' ),
	)
);

SZESUTI_F::checkbox(
	array( 'general', 'respect_dnt' ),
	$g['respect_dnt'],
	array(
		'label' => __( 'Do Not Track (DNT)', 'szesuti' ),
		'text'  => __( 'A DNT fejléc automatikus elfogadása', 'szesuti' ),
		'desc'  => __( 'A DNT szabvány elavult és sok böngésző alapból küldi, ezért ez érdemben csökkentheti a mérési adataidat. Alapból kikapcsolva.', 'szesuti' ),
	)
);

SZESUTI_F::checkbox(
	array( 'general', 'hide_from_bots' ),
	$g['hide_from_bots'],
	array(
		'label' => __( 'Elrejtés keresőrobotok elől', 'szesuti' ),
		'text'  => __( 'Ne jelenjen meg ismert robotoknak (Googlebot, Lighthouse…)', 'szesuti' ),
		'desc'  => __( '<strong>Vigyázz:</strong> ha teljes oldalas gyorsítótár (WP Rocket, LiteSpeed, Super Cache…) fut az oldalon, a robotnak készült, banner nélküli változat kerülhet a cache-be, és valódi látogatók is azt kapnák. Gyorsítótárazott oldalon hagyd kikapcsolva.', 'szesuti' ),
	)
);

SZESUTI_F::textarea(
	array( 'general', 'exclude_urls' ),
	$g['exclude_urls'],
	array(
		'label'       => __( 'Kizárt URL-részletek', 'szesuti' ),
		'rows'        => 4,
		'placeholder' => "/kosar\n/penztar\n/wp-login",
		'desc'        => __( 'Soronként egy részlet. Ha a kért URL tartalmazza, azon az oldalon sem a banner, sem a blokkolás nem fut. Például fizetési visszairányító oldalakhoz hasznos.', 'szesuti' ),
	)
);

SZESUTI_F::table_end();

/* ------------------------------------------------------------------ */

SZESUTI_F::section(
	__( 'Lebegő „Süti beállítások” gomb', 'szesuti' ),
	__( 'A döntés után is elérhetőnek kell lennie a beállítások módosításának – ez jogszabályi elvárás. Ha kikapcsolod ezt a gombot, tedd ki helyette a <code>[szesuti_settings_button]</code> shortcode-ot vagy a láblécbe egy linket.', 'szesuti' )
);
SZESUTI_F::table_start();

SZESUTI_F::checkbox(
	array( 'general', 'reopen_button' ),
	$g['reopen_button'],
	array(
		'important' => true,
		'label' => __( 'Lebegő gomb', 'szesuti' ),
		'text'  => __( 'Megjelenjen a döntés után', 'szesuti' ),
	)
);

SZESUTI_F::icon_picker(
	array( 'general', 'reopen_icon' ),
	$g['reopen_icon'],
	array( 'general', 'reopen_icon_url' ),
	$g['reopen_icon_url'],
	array(
		'label'     => __( 'Ikon', 'szesuti' ),
		'with_none' => false,
		'desc'      => __( 'A „Saját kép”-nél a médiatárból választhatsz, vagy a gépedről tölthetsz fel képet.', 'szesuti' ),
	)
);

SZESUTI_F::select(
	array( 'general', 'reopen_position' ),
	$g['reopen_position'],
	array(
		'bottom-left'  => __( 'Bal alul', 'szesuti' ),
		'bottom-right' => __( 'Jobb alul', 'szesuti' ),
		'top-left'     => __( 'Bal felül', 'szesuti' ),
		'top-right'    => __( 'Jobb felül', 'szesuti' ),
	),
	array( 'label' => __( 'Pozíció', 'szesuti' ) )
);

SZESUTI_F::text(
	array( 'general', 'reopen_offset_x' ),
	$g['reopen_offset_x'],
	array(
		'label'  => __( 'Vízszintes távolság', 'szesuti' ),
		'type'   => 'number',
		'class'  => 'small-text',
		'min'    => 0,
		'max'    => 400,
		'suffix' => 'px',
	)
);

SZESUTI_F::text(
	array( 'general', 'reopen_offset_y' ),
	$g['reopen_offset_y'],
	array(
		'label'  => __( 'Függőleges távolság', 'szesuti' ),
		'type'   => 'number',
		'class'  => 'small-text',
		'min'    => 0,
		'max'    => 400,
		'suffix' => 'px',
	)
);

SZESUTI_F::checkbox(
	array( 'general', 'reopen_hide_mobile' ),
	$g['reopen_hide_mobile'],
	array(
		'label' => __( 'Elrejtés mobilon', 'szesuti' ),
		'text'  => __( 'Kis képernyőn ne jelenjen meg a lebegő gomb', 'szesuti' ),
		'desc'  => __( 'Hasznos, ha ütközik egy chat-buborékkal vagy a „Vissza a tetejére” gombbal.', 'szesuti' ),
	)
);

SZESUTI_F::table_end();

/* ------------------------------------------------------------------ */

SZESUTI_F::section( __( 'Linkek a banneren', 'szesuti' ) );
SZESUTI_F::table_start();

if ( '' === $g['privacy_url'] ) {
	$szesuti_privacy_page = (int) get_option( 'wp_page_for_privacy_policy' );
	if ( $szesuti_privacy_page > 0 ) {
		SZESUTI_F::row_start( '' );
		SZESUTI_F::notice(
			sprintf(
				/* translators: %s: az adatvédelmi oldal URL-je */
				__( 'A WordPress adatvédelmi oldala be van állítva: <code>%s</code> – érdemes ezt bemásolni alább.', 'szesuti' ),
				esc_html( get_permalink( $szesuti_privacy_page ) )
			),
			'info'
		);
		SZESUTI_F::row_end();
	}
}

SZESUTI_F::text(
	array( 'general', 'privacy_url' ),
	$g['privacy_url'],
	array(
		'label' => __( 'Adatkezelési tájékoztató', 'szesuti' ),
		'type'  => 'url',
		'class' => 'large-text',
		'desc'  => __( 'Erősen ajánlott kitölteni – hozzájárulás kérésekor kötelező tájékoztatást adni.', 'szesuti' ),
	)
);

SZESUTI_F::text(
	array( 'general', 'cookie_policy_url' ),
	$g['cookie_policy_url'],
	array(
		'label' => __( 'Süti tájékoztató', 'szesuti' ),
		'type'  => 'url',
		'class' => 'large-text',
		'desc'  => __( 'Erre az oldalra teheted ki a <code>[szesuti_cookie_table]</code> shortcode-ot, ami automatikusan listázza a sütiket.', 'szesuti' ),
	)
);

SZESUTI_F::text(
	array( 'general', 'imprint_url' ),
	$g['imprint_url'],
	array(
		'label' => __( 'Impresszum', 'szesuti' ),
		'type'  => 'url',
		'class' => 'large-text',
	)
);

SZESUTI_F::table_end();

/* ------------------------------------------------------------------ */

SZESUTI_F::section(
	__( 'Geo-célzás', 'szesuti' ),
	__( 'Csak a megadott országokból érkező látogatóknak jelenjen meg a banner. A régión kívülieknél minden kategória engedélyezettnek számít.', 'szesuti' )
);

$szesuti_country = szesuti()->geo->country();
if ( '' !== $szesuti_country ) {
	SZESUTI_F::notice(
		sprintf(
			/* translators: %s: felismert országkód */
			__( 'Országfelismerés <strong>működik</strong>. A szerver most ezt látja rólad: <code>%s</code>', 'szesuti' ),
			esc_html( $szesuti_country )
		),
		'success'
	);
} else {
	SZESUTI_F::notice(
		__( 'A szerver jelenleg <strong>nem küld országkódot</strong>. A geo-célzás csak akkor működik, ha a tárhely vagy a CDN beállítja valamelyik fejlécet: <code>CF-IPCountry</code> (Cloudflare), <code>CloudFront-Viewer-Country</code>, <code>GEOIP_COUNTRY_CODE</code> vagy <code>X-Country-Code</code>. Enélkül a lenti „ismeretlen ország” beállítás dönt.', 'szesuti' ),
		'warning'
	);
}

SZESUTI_F::table_start();

SZESUTI_F::checkbox(
	array( 'geo', 'enabled' ),
	$geo['enabled'],
	array(
		'important' => true,
		'label' => __( 'Geo-célzás', 'szesuti' ),
		'text'  => __( 'Bekapcsolva', 'szesuti' ),
	)
);

SZESUTI_F::select(
	array( 'geo', 'mode' ),
	$geo['mode'],
	array(
		'eu'     => __( 'EU / EGT + Svájc + Egyesült Királyság', 'szesuti' ),
		'custom' => __( 'Saját országlista', 'szesuti' ),
	),
	array( 'label' => __( 'Célzott terület', 'szesuti' ) )
);

SZESUTI_F::text(
	array( 'geo', 'countries' ),
	implode( ', ', (array) $geo['countries'] ),
	array(
		'label'       => __( 'Saját országlista', 'szesuti' ),
		'class'       => 'large-text',
		'placeholder' => 'HU, AT, DE, SK, RO',
		'desc'        => __( 'Kétbetűs országkódok vesszővel elválasztva. Csak a „Saját országlista” módban számít.', 'szesuti' ),
	)
);

SZESUTI_F::select(
	array( 'geo', 'unknown' ),
	$geo['unknown'],
	array(
		'show' => __( 'Jelenjen meg a banner (biztonságos)', 'szesuti' ),
		'hide' => __( 'Ne jelenjen meg', 'szesuti' ),
	),
	array(
		'label' => __( 'Ismeretlen ország esetén', 'szesuti' ),
		'desc'  => __( 'Ha nem sikerül megállapítani az országot. A „jelenjen meg” a jogilag biztonságos választás.', 'szesuti' ),
	)
);

SZESUTI_F::select(
	array( 'geo', 'method' ),
	$geo['method'],
	array(
		'rest'   => __( 'Böngészőből, gyorsítótár-biztos (ajánlott)', 'szesuti' ),
		'server' => __( 'Szerveroldalon, gyorsabb', 'szesuti' ),
	),
	array(
		'label' => __( 'Döntés módja', 'szesuti' ),
		'desc'  => __( 'Ha teljes oldalas gyorsítótár fut az oldalon, <strong>kötelezően a böngészős módot</strong> használd – különben az első látogató országa szerinti döntés ragad be a cache-be mindenkinek.', 'szesuti' ),
	)
);

SZESUTI_F::table_end();
