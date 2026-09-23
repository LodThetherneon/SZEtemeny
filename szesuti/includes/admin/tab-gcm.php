<?php
/**
 * Admin – Google Consent Mode fül.
 *
 * @var array         $s    Beállítások.
 * @var SZESUTI_Admin $this Admin osztály.
 *
 * @package SZESUTI
 */

defined( 'ABSPATH' ) || exit;

$c            = $s['gcm'];
$szesuti_cats = $this->category_options( true );

SZESUTI_F::section(
	__( 'Google Consent Mode v2', 'szesuti' ),
	__( 'A Consent Mode nem blokkolja a Google mérőkódokat, hanem <em>jelzi</em> nekik, mihez járult hozzá a látogató. A Google Ads és a GA4 EGT-területen 2024 márciusa óta ezt várja el a konverziómérés és a remarketing működéséhez.', 'szesuti' )
);

SZESUTI_F::table_start();

SZESUTI_F::checkbox(
	array( 'gcm', 'enabled' ),
	$c['enabled'],
	array(
		'important' => true,
		'label' => __( 'Consent Mode', 'szesuti' ),
		'text'  => __( 'Bekapcsolva', 'szesuti' ),
		'desc'  => __( 'Csak akkor kapcsold be, ha az oldalon tényleg fut Google Analytics, Google Ads vagy Google Tag Manager. Egyébként felesleges kódot tesz a fejlécbe.', 'szesuti' ),
	)
);

SZESUTI_F::checkbox(
	array( 'gcm', 'dont_block_google' ),
	$c['dont_block_google'],
	array(
		'important' => true,
		'label' => __( 'Google mérőkódok kihagyása a blokkolásból', 'szesuti' ),
		'text'  => __( 'A GA / Ads / GTM töltődjön be, a jelzések vezéreljék', 'szesuti' ),
		'desc'  => __( '<strong>Ajánlott bekapcsolva hagyni</strong>, ha Consent Mode-ot használsz. A GTM ilyenkor betöltődik, de hozzájárulás nélkül nem ír sütit és nem küld azonosítható adatot. Ha kikapcsolod, a GTM teljesen blokkolva lesz – ekkor viszont a Consent Mode-nak nincs mit vezérelnie.', 'szesuti' ),
	)
);

SZESUTI_F::text(
	array( 'gcm', 'wait_for_update' ),
	$c['wait_for_update'],
	array(
		'label'  => __( 'Várakozás a döntésre', 'szesuti' ),
		'type'   => 'number',
		'class'  => 'small-text',
		'min'    => 0,
		'max'    => 5000,
		'suffix' => __( 'ezredmásodperc', 'szesuti' ),
		'desc'   => __( 'Ennyit vár a Google mérőkód, mielőtt az alapállapottal dolgozna. 500 ms általában elég ahhoz, hogy a már döntött látogatók adata ne vesszen el.', 'szesuti' ),
	)
);

SZESUTI_F::checkbox(
	array( 'gcm', 'url_passthrough' ),
	$c['url_passthrough'],
	array(
		'label' => __( 'URL passthrough', 'szesuti' ),
		'text'  => __( 'Kattintásazonosítók továbbadása az oldalak között', 'szesuti' ),
		'desc'  => __( 'Hozzájárulás hiányában a hirdetési kattintásazonosítót (gclid) az URL-ben viszi tovább süti helyett, így a konverziómérés modellezhető marad.', 'szesuti' ),
	)
);

SZESUTI_F::checkbox(
	array( 'gcm', 'ads_data_redaction' ),
	$c['ads_data_redaction'],
	array(
		'label' => __( 'Hirdetési adatok visszafogása', 'szesuti' ),
		'text'  => __( 'ads_data_redaction bekapcsolása', 'szesuti' ),
		'desc'  => __( 'Ha a hirdetési hozzájárulás hiányzik, a Google kéréseiből kimaradnak a hirdetési azonosítók. Adatvédelmi szempontból ezt érdemes bekapcsolva hagyni.', 'szesuti' ),
	)
);

SZESUTI_F::checkbox(
	array( 'gcm', 'push_events' ),
	$c['push_events'],
	array(
		'label' => __( 'dataLayer események', 'szesuti' ),
		'text'  => __( 'Küldjön <code>szesuti_consent_update</code> eseményt', 'szesuti' ),
		'desc'  => __( 'Ezekre az eseményekre GTM-ben triggert építhetsz. Az oldalbetöltéskori esemény neve <code>szesuti_consent_ready</code>, a változáskori <code>szesuti_consent_update</code>.', 'szesuti' ),
	)
);

SZESUTI_F::text(
	array( 'gcm', 'datalayer' ),
	$c['datalayer'],
	array(
		'label' => __( 'dataLayer neve', 'szesuti' ),
		'desc'  => __( 'Csak akkor módosítsd, ha a GTM konténered egyedi dataLayer nevet használ.', 'szesuti' ),
	)
);

SZESUTI_F::textarea(
	array( 'gcm', 'regions' ),
	$c['regions'],
	array(
		'label'       => __( 'Korlátozás régiókra', 'szesuti' ),
		'rows'        => 3,
		'placeholder' => "HU\nAT\nDE",
		'desc'        => __( 'Ha üresen hagyod, az alapállapot <strong>mindenhol „elutasítva”</strong> – ez a legbiztonságosabb. Ha kitöltöd, csak a felsorolt régiókban lesz alapból elutasítva, máshol engedélyezve. Országkód (HU) vagy régió (US-CA) adható meg, soronként egy.', 'szesuti' ),
	)
);

SZESUTI_F::table_end();

/* ------------------------------------------------------------------ */

SZESUTI_F::section(
	__( 'Google Tag Manager beillesztése', 'szesuti' ),
	__( 'Csak akkor használd, ha a GTM konténert még nem tette ki más plugin vagy a téma. Kétszeres beillesztés duplázott mérést okoz.', 'szesuti' )
);

SZESUTI_F::table_start();

SZESUTI_F::checkbox(
	array( 'gcm', 'inject_gtm' ),
	$c['inject_gtm'],
	array(
		'important' => true,
		'label' => __( 'GTM konténer beillesztése', 'szesuti' ),
		'text'  => __( 'A plugin tegye ki a GTM kódot', 'szesuti' ),
		'desc'  => __( 'A konténer közvetlenül a Consent Mode jelzések <em>után</em> kerül ki, ami a helyes sorrend.', 'szesuti' ),
	)
);

SZESUTI_F::text(
	array( 'gcm', 'gtm_id' ),
	$c['gtm_id'],
	array(
		'label'       => __( 'GTM azonosító', 'szesuti' ),
		'placeholder' => 'GTM-XXXXXXX',
		'class'       => 'regular-text code',
		'desc'        => __( 'A <code>GTM-</code> előtaggal együtt.', 'szesuti' ),
	)
);

SZESUTI_F::table_end();

/* ------------------------------------------------------------------ */

SZESUTI_F::section(
	__( 'Jelzések hozzárendelése', 'szesuti' ),
	__( 'Melyik hozzájárulási kategória melyik Consent Mode jelzést engedélyezze. Az „mindig engedélyezett” azt jelenti, hogy a jelzés a hozzájárulástól függetlenül <code>granted</code> marad.', 'szesuti' )
);

$szesuti_signal_help = array(
	'ad_storage'              => __( 'Hirdetési célú sütik tárolása.', 'szesuti' ),
	'ad_user_data'            => __( 'Felhasználói adatok küldése a Google-nek hirdetési célból. (Consent Mode v2)', 'szesuti' ),
	'ad_personalization'      => __( 'Személyre szabott hirdetés és remarketing. (Consent Mode v2)', 'szesuti' ),
	'analytics_storage'       => __( 'Statisztikai célú tárolás (GA4).', 'szesuti' ),
	'functionality_storage'   => __( 'Az oldal működését segítő tárolás (pl. nyelvválasztás).', 'szesuti' ),
	'personalization_storage' => __( 'Személyre szabott tartalom, ajánlók.', 'szesuti' ),
	'security_storage'        => __( 'Biztonsági funkciók (csalásmegelőzés, hitelesítés). Ezt szokás mindig engedélyezni.', 'szesuti' ),
);

SZESUTI_F::table_start();

foreach ( SZESUTI_GCM::signals() as $szesuti_signal ) {
	SZESUTI_F::select(
		array( 'gcm', 'map', $szesuti_signal ),
		isset( $c['map'][ $szesuti_signal ] ) ? $c['map'][ $szesuti_signal ] : '',
		$szesuti_cats,
		array(
			'label' => $szesuti_signal,
			'desc'  => isset( $szesuti_signal_help[ $szesuti_signal ] ) ? $szesuti_signal_help[ $szesuti_signal ] : '',
		)
	);
}

SZESUTI_F::table_end();

if ( $c['enabled'] ) {
	SZESUTI_F::notice(
		__( '<strong>Ellenőrzés:</strong> nyisd meg az oldalt böngészőben, és a konzolba írd be: <code>dataLayer</code>. Az első elemek között ott kell lennie egy <code>consent / default</code> hívásnak, hozzájárulás után pedig egy <code>consent / update</code> hívásnak. GTM-ben a Tag Assistant „Consent” fülén is látszik.', 'szesuti' ),
		'info'
	);
}
