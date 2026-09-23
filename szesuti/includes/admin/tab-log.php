<?php
/**
 * Admin – Naplózás fül.
 *
 * @var array         $s    Beállítások.
 * @var SZESUTI_Log   $log  Naplózó.
 * @var SZESUTI_Admin $this Admin osztály.
 *
 * @package SZESUTI
 */

defined( 'ABSPATH' ) || exit;

$l = $s['log'];

SZESUTI_F::section(
	__( 'Hozzájárulás-napló', 'szesuti' ),
	__( 'A GDPR 7. cikk (1) bekezdése szerint bizonyítanod kell tudni, hogy az érintett hozzájárult. Ez a napló pontosan ezt szolgálja: minden döntésről eltárol egy bejegyzést, amiből utólag kiderül, ki, mikor, mihez és melyik szabályzat-verzió alapján járult hozzá.', 'szesuti' )
);

SZESUTI_F::notice(
	__( 'A napló <strong>nem tárol IP-címet</strong>. Az IP-ből saltinggal képzett SHA-256 lenyomatot készítünk, ami önmagában nem fejthető vissza, de ugyanahhoz a látogatóhoz tartozó bejegyzések összekapcsolására alkalmas. A salting az adatbázisban van – ha kicseréled, a régi lenyomatok már nem egyeztethetők.', 'szesuti' ),
	'info'
);

if ( ! SZESUTI_Log::table_exists() ) {
	SZESUTI_F::notice(
		__( 'A napló <strong>adatbázistáblája hiányzik</strong>. Kapcsold ki, majd újra be a plugint, hogy létrejöjjön.', 'szesuti' ),
		'error'
	);
}

SZESUTI_F::table_start();

SZESUTI_F::checkbox(
	array( 'log', 'enabled' ),
	$l['enabled'],
	array(
		'important' => true,
		'label' => __( 'Naplózás', 'szesuti' ),
		'text'  => __( 'Bekapcsolva', 'szesuti' ),
		'desc'  => __( 'A bejegyzés a látogató böngészőjéből érkezik egy REST hívással, ezért oldalgyorsítótár mellett is pontos.', 'szesuti' ),
	)
);

SZESUTI_F::text(
	array( 'log', 'retention_days' ),
	$l['retention_days'],
	array(
		'label'  => __( 'Megőrzési idő', 'szesuti' ),
		'type'   => 'number',
		'class'  => 'small-text',
		'min'    => 0,
		'max'    => 3650,
		'suffix' => __( 'nap', 'szesuti' ),
		'desc'   => __( 'Ennél régebbi bejegyzéseket egy napi ütemezett feladat automatikusan töröl. A 0 = soha ne töröljön (adatminimalizálási szempontból nem ajánlott).', 'szesuti' ),
	)
);

SZESUTI_F::checkbox(
	array( 'log', 'store_url' ),
	$l['store_url'],
	array(
		'label' => __( 'URL mentése', 'szesuti' ),
		'text'  => __( 'Melyik oldalon született a döntés', 'szesuti' ),
	)
);

SZESUTI_F::checkbox(
	array( 'log', 'store_ua' ),
	$l['store_ua'],
	array(
		'label' => __( 'Böngészőazonosító mentése', 'szesuti' ),
		'text'  => __( 'User-Agent eltárolása', 'szesuti' ),
		'desc'  => __( 'Segít a bizonyításban, de önmagában is azonosításra alkalmas adat lehet. Ha nem érzed indokoltnak, kapcsold ki.', 'szesuti' ),
	)
);

SZESUTI_F::checkbox(
	array( 'log', 'store_country' ),
	$l['store_country'],
	array(
		'label' => __( 'Országkód mentése', 'szesuti' ),
		'text'  => __( 'A felismert országkód eltárolása', 'szesuti' ),
		'desc'  => __( 'Csak akkor működik, ha a tárhely vagy a CDN küld országfejlécet.', 'szesuti' ),
	)
);

SZESUTI_F::text(
	array( 'log', 'salt' ),
	$l['salt'],
	array(
		'label' => __( 'Salting (IP-lenyomathoz)', 'szesuti' ),
		'class' => 'large-text code',
		'desc'  => __( 'Az IP-lenyomat képzéséhez. <strong>Ne oszd meg és ne exportáld.</strong> Ha több oldalon ugyanazt használod, a látogatók oldalak között összekapcsolhatóvá válnak – ezért oldalanként más saltingot érdemes használni.', 'szesuti' ),
	)
);

SZESUTI_F::table_end();

$szesuti_stats = $log->stats( 30 );
?>

<h3 class="szesuti-subsection"><?php esc_html_e( 'Elmúlt 30 nap', 'szesuti' ); ?></h3>

<div class="szesuti-stats">
	<div class="szesuti-stat">
		<span class="szesuti-stat__value"><?php echo esc_html( number_format_i18n( $szesuti_stats['total'] ) ); ?></span>
		<span class="szesuti-stat__label"><?php esc_html_e( 'összes döntés', 'szesuti' ); ?></span>
	</div>
	<div class="szesuti-stat">
		<span class="szesuti-stat__value"><?php echo esc_html( number_format_i18n( $szesuti_stats['accept_all'] ) ); ?></span>
		<span class="szesuti-stat__label"><?php esc_html_e( 'mindent elfogadott', 'szesuti' ); ?></span>
	</div>
	<div class="szesuti-stat">
		<span class="szesuti-stat__value"><?php echo esc_html( number_format_i18n( $szesuti_stats['reject_all'] ) ); ?></span>
		<span class="szesuti-stat__label"><?php esc_html_e( 'elutasított', 'szesuti' ); ?></span>
	</div>
	<div class="szesuti-stat">
		<span class="szesuti-stat__value"><?php echo esc_html( number_format_i18n( $szesuti_stats['custom'] ) ); ?></span>
		<span class="szesuti-stat__label"><?php esc_html_e( 'egyedi választás', 'szesuti' ); ?></span>
	</div>
	<div class="szesuti-stat">
		<span class="szesuti-stat__value">
			<?php
			$szesuti_rate = $szesuti_stats['total'] > 0
				? round( ( $szesuti_stats['accept_all'] / $szesuti_stats['total'] ) * 100 )
				: 0;
			echo esc_html( $szesuti_rate . '%' );
			?>
		</span>
		<span class="szesuti-stat__label"><?php esc_html_e( 'teljes elfogadási arány', 'szesuti' ); ?></span>
	</div>
</div>

<p>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=szesuti-log' ) ); ?>" class="button">
		<?php esc_html_e( 'Napló megnyitása', 'szesuti' ); ?>
	</a>
</p>
