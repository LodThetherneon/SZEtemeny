<?php
/**
 * Admin – Eszközök fül (export/import, frissítés, haladó).
 *
 * @var array         $s    Beállítások.
 * @var SZESUTI_Log   $log  Napló.
 * @var SZESUTI_Admin $this Admin osztály.
 *
 * @package SZESUTI
 */

defined( 'ABSPATH' ) || exit;

$adv = $s['advanced'];

SZESUTI_F::section(
	__( 'Beállítások átvitele másik oldalra', 'szesuti' ),
	__( 'Állítsd be egyszer az egyik oldalon, exportáld, majd a többin importáld. A JSON fájl minden beállítást tartalmaz: megjelenést, szövegeket, kategóriákat, blokkolási szabályokat, sütilistát.', 'szesuti' )
);
?>

<div class="szesuti-tools-grid">

	<div class="szesuti-card">
		<h3><?php esc_html_e( 'Exportálás', 'szesuti' ); ?></h3>
		<form method="post" action="">
			<?php wp_nonce_field( 'szesuti_tools', 'szesuti_nonce' ); ?>
			<input type="hidden" name="szesuti_action" value="export" />

			<p>
				<label>
					<input type="checkbox" name="include_secrets" value="1" />
					<?php esc_html_e( 'A naplózási salting is kerüljön bele', 'szesuti' ); ?>
				</label>
			</p>
			<p class="description">
				<?php esc_html_e( 'Alapból a salting nélkül exportálunk, hogy minden oldalnak saját maradjon. Csak akkor jelöld be, ha biztonsági mentésnek szánod ugyanahhoz az oldalhoz.', 'szesuti' ); ?>
			</p>

			<p><button type="submit" class="button button-primary"><?php esc_html_e( 'JSON letöltése', 'szesuti' ); ?></button></p>
		</form>
	</div>

	<div class="szesuti-card">
		<h3><?php esc_html_e( 'Importálás', 'szesuti' ); ?></h3>
		<form method="post" action="" enctype="multipart/form-data">
			<?php wp_nonce_field( 'szesuti_tools', 'szesuti_nonce' ); ?>
			<input type="hidden" name="szesuti_action" value="import" />

			<p><input type="file" name="szesuti_import_file" accept="application/json,.json" /></p>

			<p class="description"><?php esc_html_e( 'Vagy illeszd be a JSON tartalmát:', 'szesuti' ); ?></p>
			<p><textarea name="szesuti_import_text" rows="4" class="large-text code" placeholder='{"_szesuti":true,...}'></textarea></p>

			<p>
				<label>
					<input type="checkbox" name="keep_urls" value="1" checked />
					<?php esc_html_e( 'Az oldal saját linkjei maradjanak meg (adatkezelési tájékoztató stb.)', 'szesuti' ); ?>
				</label>
			</p>
			<p>
				<label>
					<input type="checkbox" name="keep_texts" value="1" />
					<?php esc_html_e( 'A jelenlegi szövegek maradjanak meg', 'szesuti' ); ?>
				</label>
			</p>

			<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Importálás', 'szesuti' ); ?></button></p>
		</form>
	</div>

</div>

<?php
SZESUTI_F::notice(
	sprintf(
		/* translators: %s: PHP kódrészlet */
		__( 'Deployból is felülírhatod a beállításokat – tedd ezt egy mu-pluginba: %s', 'szesuti' ),
		'<pre><code>add_filter( \'szesuti_settings\', function ( $s ) {
    $s[\'general\'][\'privacy_url\'] = home_url( \'/adatkezeles/\' );
    $s[\'gcm\'][\'gtm_id\']          = \'GTM-XXXXXXX\';
    return $s;
} );</code></pre>'
	),
	'info'
);

/* ------------------------------------------------------------------ */

SZESUTI_F::section(
	__( 'Frissítések', 'szesuti' ),
	__( 'Add meg egy JSON manifest URL-jét, és a plugin a WordPress megszokott frissítési felületén jelzi, ha új verzió érhető el. Így nem kell 100 oldalon ZIP-et feltölteni.', 'szesuti' )
);
?>

<form method="post" action="">
	<?php wp_nonce_field( 'szesuti_save', 'szesuti_nonce' ); ?>
	<input type="hidden" name="szesuti_action" value="save" />
	<input type="hidden" name="szesuti_tab" value="tools" />

	<?php
	SZESUTI_F::table_start();

	SZESUTI_F::text(
		array( 'advanced', 'update_url' ),
		$adv['update_url'],
		array(
			'label'       => __( 'Frissítési manifest URL', 'szesuti' ),
			'type'        => 'url',
			'class'       => 'large-text code',
			'placeholder' => 'https://sajatszerver.hu/szesuti/update.json',
			'desc'        => __( 'Hagyd üresen, ha kézzel frissítesz. A várt JSON felépítése lent látható.', 'szesuti' ),
		)
	);

	SZESUTI_F::table_end();

	SZESUTI_F::section( __( 'Haladó', 'szesuti' ) );
	SZESUTI_F::table_start();

	SZESUTI_F::checkbox(
		array( 'advanced', 'load_css' ),
		$adv['load_css'],
		array(
			'label' => __( 'Beépített stílus betöltése', 'szesuti' ),
			'text'  => __( 'A plugin CSS-ének betöltése', 'szesuti' ),
			'desc'  => __( 'Csak akkor kapcsold ki, ha a banner teljes kinézetét saját CSS-ből oldod meg. Enélkül a banner formázatlanul jelenik meg.', 'szesuti' ),
		)
	);

	SZESUTI_F::checkbox(
		array( 'advanced', 'footer_js' ),
		$adv['footer_js'],
		array(
			'label' => __( 'JavaScript a láblécben', 'szesuti' ),
			'text'  => __( 'A banner szkriptje az oldal végén töltődjön be', 'szesuti' ),
			'desc'  => __( 'Jobb az oldalbetöltési mutatóknak. Kapcsold ki, ha a banner késve jelenik meg. A szkriptblokkolás ettől függetlenül a szerveren történik, tehát nem szivárog ki semmi.', 'szesuti' ),
		)
	);

	SZESUTI_F::checkbox(
		array( 'advanced', 'rest_enabled' ),
		$adv['rest_enabled'],
		array(
			'label' => __( 'REST végpontok', 'szesuti' ),
			'text'  => __( 'Bekapcsolva', 'szesuti' ),
			'desc'  => __( 'Ezek kellenek a naplózáshoz és a gyorsítótár-biztos geo-célzáshoz. Kikapcsolva mindkettő leáll.', 'szesuti' ),
		)
	);

	SZESUTI_F::checkbox(
		array( 'advanced', 'debug' ),
		$adv['debug'],
		array(
			'label' => __( 'Hibakeresés', 'szesuti' ),
			'text'  => __( 'Naplózás a böngészőkonzolra és HTML megjegyzés a blokkolt elemekről', 'szesuti' ),
			'desc'  => __( 'Éles üzemben kapcsold ki. Bekapcsolva az oldal forrásában a <code>&lt;/body&gt;</code> előtt látod, hány elemet és milyen szolgáltatásokat blokkoltunk.', 'szesuti' ),
		)
	);

	SZESUTI_F::checkbox(
		array( 'advanced', 'delete_data_on_uninstall' ),
		$adv['delete_data_on_uninstall'],
		array(
			'important' => true,
			'label' => __( 'Adatok törlése eltávolításkor', 'szesuti' ),
			'text'  => __( 'A plugin törlésekor minden beállítás és a napló is törlődjön', 'szesuti' ),
			'desc'  => __( 'Alapból kikapcsolva, hogy egy véletlen törlés ne semmisítse meg a hozzájárulás-bizonyítékokat.', 'szesuti' ),
		)
	);

	SZESUTI_F::table_end();
	?>

	<p class="szesuti-submit">
		<?php submit_button( __( 'Mentés', 'szesuti' ), 'primary large', 'submit', false ); ?>
	</p>
</form>

<?php if ( '' !== $adv['update_url'] ) : ?>
	<form method="post" action="">
		<?php wp_nonce_field( 'szesuti_tools', 'szesuti_nonce' ); ?>
		<input type="hidden" name="szesuti_action" value="check_update" />
		<p>
			<button type="submit" class="button"><?php esc_html_e( 'Frissítés ellenőrzése most', 'szesuti' ); ?></button>
			<?php
			$szesuti_remote = get_transient( 'szesuti_update_version' );
			if ( $szesuti_remote ) {
				delete_transient( 'szesuti_update_version' );
				printf(
					' <span class="szesuti-pill %1$s">%2$s</span>',
					version_compare( SZESUTI_VERSION, $szesuti_remote, '<' ) ? 'szesuti-pill--warn' : 'szesuti-pill--ok',
					esc_html(
						sprintf(
							/* translators: 1: távoli verzió, 2: telepített verzió */
							__( 'Szerveren: %1$s · telepítve: %2$s', 'szesuti' ),
							$szesuti_remote,
							SZESUTI_VERSION
						)
					)
				);
			}
			?>
		</p>
	</form>
<?php endif; ?>

<details class="szesuti-details">
	<summary><?php esc_html_e( 'Példa frissítési manifest (update.json)', 'szesuti' ); ?></summary>
	<pre><code>{
  "name": "SZetemény",
  "slug": "szesuti",
  "version": "1.1.0",
  "requires": "5.6",
  "tested": "6.7",
  "requires_php": "7.2",
  "author": "&lt;a href=\"https://pelda.hu\"&gt;Neved&lt;/a&gt;",
  "homepage": "https://pelda.hu/szesuti",
  "download_url": "https://pelda.hu/szesuti/szesuti-1.1.0.zip",
  "last_updated": "2026-09-20 10:00:00",
  "sections": {
    "description": "Sütikezelő 100 saját oldalra.",
    "changelog": "&lt;h4&gt;1.1.0&lt;/h4&gt;&lt;ul&gt;&lt;li&gt;Új: ...&lt;/li&gt;&lt;/ul&gt;"
  }
}</code></pre>
	<p class="description">
		<?php esc_html_e( 'A ZIP gyökerében egy „szesuti” nevű mappának kell lennie. A plugin a letöltött mappát automatikusan átnevezi, ha a verziószám is benne van a nevében.', 'szesuti' ); ?>
	</p>
</details>

<?php
/* ------------------------------------------------------------------ */

SZESUTI_F::section( __( 'Rendszerállapot', 'szesuti' ) );

$szesuti_checks = array(
	array(
		'label' => __( 'Sütikezelő bekapcsolva', 'szesuti' ),
		'ok'    => (bool) $s['general']['enabled'],
		'hint'  => __( 'Az Általános fülön kapcsolható be.', 'szesuti' ),
	),
	array(
		'label' => __( 'Szkriptblokkolás bekapcsolva', 'szesuti' ),
		'ok'    => (bool) $s['blocker']['enabled'],
		'hint'  => __( 'Enélkül a mérőkódok hozzájárulás nélkül is lefutnak.', 'szesuti' ),
	),
	array(
		'label' => __( 'Adatkezelési tájékoztató megadva', 'szesuti' ),
		'ok'    => '' !== $s['general']['privacy_url'],
		'hint'  => __( 'Hozzájárulás kérésekor kötelező tájékoztatást adni.', 'szesuti' ),
	),
	array(
		'label' => __( '„Elutasítás” gomb látható', 'szesuti' ),
		'ok'    => (bool) $s['appearance']['show_reject'],
		'hint'  => __( 'Az elutasítás legyen ugyanolyan könnyű, mint az elfogadás.', 'szesuti' ),
	),
	array(
		'label' => __( 'Beállítások újranyitható', 'szesuti' ),
		'ok'    => (bool) $s['general']['reopen_button'],
		'hint'  => __( 'Vagy tedd ki a [szesuti_settings_button] shortcode-ot a láblécbe.', 'szesuti' ),
	),
	array(
		'label' => __( 'Napló táblája létezik', 'szesuti' ),
		'ok'    => SZESUTI_Log::table_exists(),
		'hint'  => __( 'Kapcsold ki, majd újra be a plugint a tábla létrehozásához.', 'szesuti' ),
	),
	array(
		'label' => __( 'Napi takarító feladat ütemezve', 'szesuti' ),
		'ok'    => (bool) wp_next_scheduled( 'szesuti_daily_cleanup' ),
		'hint'  => __( 'A megőrzési időn túli naplóbejegyzések törléséhez kell.', 'szesuti' ),
	),
	array(
		'label' => __( 'Nincs bekapcsolva a hibakeresés', 'szesuti' ),
		'ok'    => empty( $adv['debug'] ),
		'hint'  => __( 'Éles üzemben érdemes kikapcsolni.', 'szesuti' ),
	),
);
?>

<ul class="szesuti-checks">
	<?php foreach ( $szesuti_checks as $szesuti_check ) : ?>
		<li class="<?php echo $szesuti_check['ok'] ? 'is-ok' : 'is-warn'; ?>">
			<span class="szesuti-checks__mark" aria-hidden="true"><?php echo $szesuti_check['ok'] ? '✓' : '!'; ?></span>
			<span class="szesuti-checks__label"><?php echo esc_html( $szesuti_check['label'] ); ?></span>
			<?php if ( ! $szesuti_check['ok'] ) : ?>
				<span class="szesuti-checks__hint"><?php echo esc_html( $szesuti_check['hint'] ); ?></span>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
</ul>

<?php
/* ------------------------------------------------------------------ */

SZESUTI_F::section( __( 'Visszaállítás', 'szesuti' ) );
?>

<form method="post" action="" class="szesuti-danger">
	<?php wp_nonce_field( 'szesuti_tools', 'szesuti_nonce' ); ?>
	<input type="hidden" name="szesuti_action" value="reset" />
	<p><?php esc_html_e( 'Minden beállítás visszaáll a gyári alapértékre. A hozzájárulás-napló megmarad.', 'szesuti' ); ?></p>
	<p><button type="submit" class="button szesuti-btn-danger" data-szesuti-confirm="reset"><?php esc_html_e( 'Gyári alapértékek visszaállítása', 'szesuti' ); ?></button></p>
</form>

<?php
/* ------------------------------------------------------------------ */

$szesuti_wipe_log = $log->count_all();
$szesuti_wipe_lib = count( SZESUTI_Library::entries() );

SZESUTI_F::section(
	__( 'Teljes törlés (wipe)', 'szesuti' ),
	__( 'Mindent töröl, és a plugint a friss telepítés utáni állapotba hozza – mintha most kapcsolnád be először.', 'szesuti' )
);
?>

<form method="post" action="" class="szesuti-danger szesuti-wipe" data-szesuti-wipe>
	<?php wp_nonce_field( 'szesuti_wipe', 'szesuti_nonce' ); ?>
	<input type="hidden" name="szesuti_action" value="wipe" />

	<div class="szesuti-wipe__body">
		<div class="szesuti-wipe__text">
			<p><strong><?php esc_html_e( 'Ami törlődik:', 'szesuti' ); ?></strong></p>
			<ul class="szesuti-wipe__list">
				<li>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: bejegyzések száma */
							_n( 'a teljes hozzájárulás-napló (%s bejegyzés)', 'a teljes hozzájárulás-napló (%s bejegyzés)', $szesuti_wipe_log, 'szesuti' ),
							number_format_i18n( $szesuti_wipe_log )
						)
					);
					?>
				</li>
				<li>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: sütik száma */
							_n( 'a háttéradatbázis saját és importált sütijei (%s db) – a beépítettek maradnak', 'a háttéradatbázis saját és importált sütijei (%s db) – a beépítettek maradnak', $szesuti_wipe_lib, 'szesuti' ),
							number_format_i18n( $szesuti_wipe_lib )
						)
					);
					?>
				</li>
				<li><?php esc_html_e( 'minden beállítás, szöveg és megjelenés (gyári alapérték, új salting)', 'szesuti' ); ?></li>
				<li><?php esc_html_e( 'a sütilista (a kezdő listára áll vissza) és a szkennelési eredmények', 'szesuti' ); ?></li>
			</ul>

			<p class="description">
				<?php
				printf(
					/* translators: 1: a napló oldal linkje, 2: link vége */
					esc_html__( 'A napló a hozzájárulások bizonyítéka. Ha még szükséged lehet rá, előbb %1$stöltsd le CSV-ben%2$s; a beállításokat fent, az Exportálással mentheted el.', 'szesuti' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=szesuti-log' ) ) . '">',
					'</a>'
				);
				?>
			</p>

			<p>
				<label>
					<input type="checkbox" name="szesuti_wipe_reconsent" value="1" checked />
					<?php esc_html_e( 'A látogatók döntsenek újra (ajánlott: a napló törlése után a korábbi hozzájárulásokra nincs bizonyíték)', 'szesuti' ); ?>
				</label>
			</p>

			<p class="szesuti-wipe__confirm">
				<label for="szesuti-wipe-confirm">
					<?php
					printf(
						/* translators: %s: a beírandó szó */
						esc_html__( 'Megerősítéshez írd be (kis- és nagybetű is számít): %s', 'szesuti' ),
						'<code class="szesuti-nocopy" data-wipe-nocopy>' . esc_html( SZESUTI_Wipe::CONFIRM_WORD ) . '</code>'
					);
					?>
				</label>
				<input type="text" id="szesuti-wipe-confirm" name="szesuti_wipe_confirm" class="regular-text" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" data-wipe-input="<?php echo esc_attr( SZESUTI_Wipe::CONFIRM_WORD ); ?>" />
				<span class="description"><?php esc_html_e( 'A szó nem másolható ki és nem illeszthető be – végig be kell gépelni.', 'szesuti' ); ?></span>
			</p>
		</div>

		<button type="submit" class="szesuti-wipe__btn" data-szesuti-confirm="wipe" data-wipe-button disabled>
			<img src="<?php echo esc_url( SZESUTI_URL . 'assets/img/wipe.jpg' ); ?>" width="274" height="183" alt="" />
			<span class="szesuti-wipe__label"><?php esc_html_e( 'Mindent törlök (wipe)', 'szesuti' ); ?></span>
		</button>
	</div>
</form>
