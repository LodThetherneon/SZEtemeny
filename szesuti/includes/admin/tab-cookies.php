<?php
/**
 * Admin – Sütilista fül.
 *
 * Ez a fül két külön űrlapot tartalmaz: egyet a szkennerhez, egyet a listához.
 *
 * @var array           $s       Beállítások.
 * @var SZESUTI_Scanner $scanner Szkenner.
 * @var SZESUTI_Admin   $this    Admin osztály.
 *
 * @package SZESUTI
 */

defined( 'ABSPATH' ) || exit;

$szesuti_cats = $this->category_options();
$szesuti_scan = $scanner->last_scan();

SZESUTI_F::section( __( 'Sütiszkenner', 'szesuti' ) );
?>

<div class="szesuti-crawler" data-szesuti-crawler>
	<div class="szesuti-crawler__intro">
		<div class="szesuti-crawler__intro-text">
			<p class="szesuti-section-desc"><?php esc_html_e( 'A szkenner a böngésződben végigjárja az oldalt: megnyitja a kezdőlapot, az összes oldalt és egy mintát a bejegyzésekből, követi a menük és a tartalom belső linkjeit, legörget minden oldal aljáig, és feljegyzi, milyen sütik, böngészős tárolók és külső szolgáltatások jelennek meg – úgy, mintha a látogató mindent elfogadott volna. A szerver ezen felül a válaszfejlécekből a JavaScriptből nem látható (HttpOnly) sütiket is kiolvassa.', 'szesuti' ); ?></p>

			<div class="szesuti-crawler__controls">
				<label class="szesuti-crawler__limit">
					<span><?php esc_html_e( 'Legfeljebb', 'szesuti' ); ?></span>
					<select data-crawl-limit>
						<option value="50"><?php esc_html_e( '50 oldal (kb. 1 perc)', 'szesuti' ); ?></option>
						<option value="150" selected><?php esc_html_e( '150 oldal (kb. 2–3 perc, ajánlott)', 'szesuti' ); ?></option>
						<option value="300"><?php esc_html_e( '300 oldal (kb. 5 perc)', 'szesuti' ); ?></option>
						<option value="600"><?php esc_html_e( '600 oldal (kb. 10 perc)', 'szesuti' ); ?></option>
					</select>
				</label>
				<button type="button" class="button button-primary" data-crawl-start><?php esc_html_e( 'Teljes szkennelés indítása', 'szesuti' ); ?></button>
				<button type="button" class="button" data-crawl-stop hidden><?php esc_html_e( 'Leállítás és mentés', 'szesuti' ); ?></button>
			</div>

			<p class="description szesuti-crawler__note">
				<?php esc_html_e( 'A szkennelés alatt ne zárd be ezt a lapot. A mérőkódok közben a te böngésződben futnak le (a statisztikában megjelenhet néhány látogatásod); az általuk létrehozott sütiket a szkenner a végén törli a böngésződből. A hasonló oldalakból (pl. hírek) útvonalanként legfeljebb 12-t nyit meg, mert ugyanaz a sablon ugyanazokat a kódokat tölti be.', 'szesuti' ); ?>
			</p>
		</div>

		<img class="szesuti-crawler__art" src="<?php echo esc_url( SZESUTI_URL . 'assets/img/suti.png' ); ?>" width="300" height="200" alt="" decoding="async" />
	</div>

	<div class="szesuti-crawler__panel" data-crawl-panel hidden>
		<div class="szesuti-crawler__bar"><span data-crawl-bar></span></div>
		<p class="szesuti-crawler__status">
			<strong data-crawl-count></strong>
			<span class="szesuti-crawler__sep" aria-hidden="true">·</span>
			<span data-crawl-time>0:00</span>
			<span class="szesuti-crawler__sep" aria-hidden="true">·</span>
			<span data-crawl-stats></span>
		</p>
		<div class="szesuti-crawler__frames" data-crawl-frames></div>
		<p class="szesuti-crawler__msg" data-crawl-msg aria-live="polite"></p>
	</div>

	<noscript><p><?php esc_html_e( 'A teljes szkennelés JavaScriptet igényel. Használd a lenti gyors, szerveroldali szkennelést.', 'szesuti' ); ?></p></noscript>
</div>

<details class="szesuti-quickscan">
	<summary><?php esc_html_e( 'Gyors, szerveroldali szkennelés megadott URL-ekkel', 'szesuti' ); ?></summary>

<form method="post" action="" class="szesuti-scan-form">
	<?php wp_nonce_field( 'szesuti_scan', 'szesuti_nonce' ); ?>
	<input type="hidden" name="szesuti_action" value="scan" />

	<table class="form-table szesuti-table-form" role="presentation"><tbody>
		<tr class="szesuti-row">
			<th scope="row">
				<label for="szesuti-scan-urls"><?php esc_html_e( 'Vizsgálandó URL-ek', 'szesuti' ); ?></label>
			</th>
			<td>
				<textarea id="szesuti-scan-urls" name="szesuti_scan_urls" rows="4" class="large-text code"><?php echo esc_textarea( implode( "\n", $scanner->default_urls() ) ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Soronként egy URL, legfeljebb 8. Ez a mód csak a szerver válaszát nézi: a JavaScriptből beállított sütiket nem látja, azokra a felismert szolgáltatásokból következtet. Akkor hasznos, ha a teljes szkennelés nem futtatható (pl. a tárhely tiltja az oldal keretben való megnyitását).', 'szesuti' ); ?></p>
			</td>
		</tr>
	</tbody></table>

	<p><button type="submit" class="button button-secondary"><?php esc_html_e( 'Gyors szkennelés indítása', 'szesuti' ); ?></button></p>
</form>
</details>

<?php if ( $szesuti_scan ) : ?>
	<div class="szesuti-scan-result" id="szesuti-scan-result">
		<p class="szesuti-scan-result__meta">
			<?php
			$szesuti_is_browser = isset( $szesuti_scan['mode'] ) && 'browser' === $szesuti_scan['mode'];
			printf(
				/* translators: 1: időpont, 2: oldalak száma */
				esc_html__( 'Utolsó szkennelés: %1$s – %2$d oldal vizsgálva', 'szesuti' ),
				esc_html( wp_date( 'Y. m. d. H:i', (int) $szesuti_scan['time'] ) ),
				count( $szesuti_scan['urls'] )
			);
			if ( $szesuti_is_browser ) {
				$szesuti_secs = isset( $szesuti_scan['duration'] ) ? (int) $szesuti_scan['duration'] : 0;
				echo ' ' . esc_html(
					sprintf(
						/* translators: %s: időtartam perc:másodperc alakban */
						__( '(teljes szkennelés, %s alatt)', 'szesuti' ),
						sprintf( '%d:%02d', floor( $szesuti_secs / 60 ), $szesuti_secs % 60 )
					)
				);
				if ( ! empty( $szesuti_scan['stopped'] ) ) {
					echo ' – ' . esc_html__( 'leállítva, részleges eredmény', 'szesuti' );
				}
			} else {
				echo ' ' . esc_html__( '(gyors, szerveroldali szkennelés)', 'szesuti' );
			}
			echo '.';
			?>
		</p>

		<?php if ( $szesuti_is_browser && isset( $szesuti_scan['scan_mode'] ) && ! $szesuti_scan['scan_mode'] ) : ?>
			<?php SZESUTI_F::notice( esc_html__( 'A szkennelő mód az oldalak többségén nem kapcsolt be, ezért a blokkolt kódok ott nem futottak le, és a találati lista hiányos lehet. Ellenőrizd, hogy a webhely címe (Beállítások → Általános) megegyezik-e azzal, amelyen az adminba bejelentkeztél.', 'szesuti' ), 'warning' ); ?>
		<?php endif; ?>

		<?php if ( ! empty( $szesuti_scan['urls'] ) ) : ?>
			<details class="szesuti-scan-pages">
				<summary><?php esc_html_e( 'Bejárt oldalak listája', 'szesuti' ); ?></summary>
				<ol>
					<?php foreach ( $szesuti_scan['urls'] as $szesuti_url ) : ?>
						<li><a href="<?php echo esc_url( $szesuti_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $szesuti_url ); ?></a></li>
					<?php endforeach; ?>
				</ol>
			</details>
		<?php endif; ?>

		<?php if ( ! empty( $szesuti_scan['errors'] ) ) : ?>
			<?php
			SZESUTI_F::notice(
				esc_html__( 'Néhány URL-t nem sikerült lekérni:', 'szesuti' ) . '<br>' . esc_html( implode( ' · ', $szesuti_scan['errors'] ) ),
				'warning'
			);
			?>
		<?php endif; ?>

		<?php if ( ! empty( $szesuti_scan['services'] ) ) : ?>
			<h3 class="szesuti-subsection"><?php esc_html_e( 'Felismert szolgáltatások', 'szesuti' ); ?></h3>
			<ul class="szesuti-scan-services">
				<?php foreach ( $szesuti_scan['services'] as $szesuti_service ) : ?>
					<li>
						<strong><?php echo esc_html( $szesuti_service['label'] ); ?></strong>
						<code><?php echo esc_html( $szesuti_service['matched'] ); ?></code>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( ! empty( $szesuti_scan['hosts'] ) ) : ?>
			<h3 class="szesuti-subsection"><?php esc_html_e( 'Egyéb külső kiszolgálók', 'szesuti' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Ezekről a domainekről töltött be valamit az oldal, de egyik ismert szolgáltatáshoz sem tartoznak. Szigorú módban hozzájárulásig blokkolva vannak. Ha valamelyik az oldal működéséhez kell és biztosan nem követ, a Szkriptblokkolás fülön vedd fel a kivételek közé.', 'szesuti' ); ?>
			</p>
			<ul class="szesuti-seen-hosts">
				<?php foreach ( $szesuti_scan['hosts'] as $szesuti_host ) : ?>
					<li><code><?php echo esc_html( $szesuti_host ); ?></code></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( ! empty( $szesuti_scan['cookies'] ) ) : ?>
			<h3 class="szesuti-subsection"><?php esc_html_e( 'Talált sütik', 'szesuti' ); ?></h3>

			<form method="post" action="">
				<?php wp_nonce_field( 'szesuti_scan', 'szesuti_nonce' ); ?>
				<input type="hidden" name="szesuti_action" value="add_scanned" />

				<table class="widefat striped szesuti-scan-table">
					<thead>
						<tr>
							<th class="check-column"></th>
							<th><?php esc_html_e( 'Süti', 'szesuti' ); ?></th>
							<th><?php esc_html_e( 'Szolgáltató', 'szesuti' ); ?></th>
							<th><?php esc_html_e( 'Javasolt kategória', 'szesuti' ); ?></th>
							<th><?php esc_html_e( 'Forrás', 'szesuti' ); ?></th>
							<th><?php esc_html_e( 'Állapot', 'szesuti' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $szesuti_scan['cookies'] as $szesuti_found ) : ?>
							<tr>
								<td class="check-column">
									<?php if ( ! empty( $szesuti_found['is_new'] ) ) : ?>
										<?php // Az ismeretlen böngészős tárolók gyakran belső működéshez kellenek – alapból nincsenek kijelölve. ?>
										<input type="checkbox" name="szesuti_scanned[]" value="<?php echo esc_attr( $szesuti_found['name'] ); ?>" <?php checked( ! empty( $szesuti_found['known'] ) || 'html' !== ( isset( $szesuti_found['type'] ) ? $szesuti_found['type'] : 'http' ) ); ?> />
									<?php endif; ?>
								</td>
								<td>
									<code><?php echo esc_html( $szesuti_found['name'] ); ?></code>
									<?php if ( isset( $szesuti_found['type'] ) && 'html' === $szesuti_found['type'] ) : ?>
										<span class="szesuti-pill szesuti-pill--muted"><?php esc_html_e( 'tároló', 'szesuti' ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $szesuti_found['provider'] ); ?></td>
								<td><?php echo esc_html( $szesuti_found['category'] ); ?></td>
								<td><?php echo esc_html( isset( $szesuti_found['source'] ) ? $szesuti_found['source'] : '' ); ?></td>
								<td>
									<?php if ( empty( $szesuti_found['is_new'] ) ) : ?>
										<span class="szesuti-pill szesuti-pill--muted"><?php esc_html_e( 'már a listán', 'szesuti' ); ?></span>
									<?php elseif ( ! empty( $szesuti_found['known'] ) ) : ?>
										<span class="szesuti-pill szesuti-pill--ok"><?php esc_html_e( 'ismert, leírással', 'szesuti' ); ?></span>
									<?php else : ?>
										<span class="szesuti-pill szesuti-pill--warn"><?php esc_html_e( 'ismeretlen – írj leírást', 'szesuti' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p><button type="submit" class="button button-secondary"><?php esc_html_e( 'Kijelöltek hozzáadása a listához', 'szesuti' ); ?></button></p>
			</form>
		<?php endif; ?>
	</div>
<?php endif; ?>

<?php
SZESUTI_F::section(
	__( 'Sütilista', 'szesuti' ),
	__( 'Ez a lista jelenik meg a beállítások ablakban kategóriánként, és ezt írja ki a <code>[szesuti_cookie_table]</code> shortcode is. A csillag (*) helyettesítő karakterként használható a névben, pl. <code>_ga_*</code>.', 'szesuti' )
	. ' ' . sprintf(
		/* translators: %s: link a Háttéradatbázis oldalra */
		__( 'Új süti felvételekor elég a nevét beírni: ha a %s ismeri, a többi adata magától kitöltődik. A leírással mentett sütik pedig automatikusan bekerülnek oda.', 'szesuti' ),
		'<a href="' . esc_url( admin_url( 'admin.php?page=szesuti-library' ) ) . '">' . esc_html__( 'Háttéradatbázis', 'szesuti' ) . '</a>'
	)
);
?>

<form method="post" action="">
	<?php wp_nonce_field( 'szesuti_save', 'szesuti_nonce' ); ?>
	<input type="hidden" name="szesuti_action" value="save" />
	<input type="hidden" name="szesuti_tab" value="cookies" />

	<div class="szesuti-repeater" data-szesuti-repeater="cookies">

		<div class="szesuti-repeater__rows szesuti-cookie-rows">
			<?php foreach ( (array) $s['cookies'] as $szesuti_index => $szesuti_cookie ) : ?>
				<div class="szesuti-repeater__row szesuti-cookie-row">

					<div class="szesuti-cookie-row__grid">
						<label>
							<span><?php esc_html_e( 'Név', 'szesuti' ); ?></span>
							<input type="text" name="szesuti[cookies][<?php echo (int) $szesuti_index; ?>][name]" value="<?php echo esc_attr( $szesuti_cookie['name'] ); ?>" class="code" />
						</label>

						<label>
							<span><?php esc_html_e( 'Szolgáltató', 'szesuti' ); ?></span>
							<input type="text" name="szesuti[cookies][<?php echo (int) $szesuti_index; ?>][provider]" value="<?php echo esc_attr( $szesuti_cookie['provider'] ); ?>" />
						</label>

						<label>
							<span><?php esc_html_e( 'Kategória', 'szesuti' ); ?></span>
							<select name="szesuti[cookies][<?php echo (int) $szesuti_index; ?>][category]">
								<?php foreach ( $szesuti_cats as $szesuti_slug => $szesuti_label ) : ?>
									<option value="<?php echo esc_attr( $szesuti_slug ); ?>" <?php selected( $szesuti_cookie['category'], $szesuti_slug ); ?>><?php echo esc_html( $szesuti_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>

						<label>
							<span><?php esc_html_e( 'Élettartam', 'szesuti' ); ?></span>
							<input type="text" name="szesuti[cookies][<?php echo (int) $szesuti_index; ?>][duration]" value="<?php echo esc_attr( $szesuti_cookie['duration'] ); ?>" placeholder="<?php esc_attr_e( '2 év', 'szesuti' ); ?>" />
						</label>

						<label>
							<span><?php esc_html_e( 'Típus', 'szesuti' ); ?></span>
							<select name="szesuti[cookies][<?php echo (int) $szesuti_index; ?>][type]">
								<option value="http" <?php selected( $szesuti_cookie['type'], 'http' ); ?>>HTTP</option>
								<option value="html" <?php selected( $szesuti_cookie['type'], 'html' ); ?>>HTML storage</option>
								<option value="pixel" <?php selected( $szesuti_cookie['type'], 'pixel' ); ?>>Pixel</option>
							</select>
						</label>

						<span class="szesuti-cookie-row__actions">
							<button type="button" class="button-link szesuti-repeater__remove"><?php esc_html_e( 'Törlés', 'szesuti' ); ?></button>
						</span>
					</div>

					<div class="szesuti-cookie-row__desc">
						<label>
							<span><?php esc_html_e( 'Leírás (magyar)', 'szesuti' ); ?></span>
							<textarea name="szesuti[cookies][<?php echo (int) $szesuti_index; ?>][desc_hu]" rows="2"><?php echo esc_textarea( $szesuti_cookie['desc_hu'] ); ?></textarea>
						</label>
						<label>
							<span><?php esc_html_e( 'Leírás (angol)', 'szesuti' ); ?></span>
							<textarea name="szesuti[cookies][<?php echo (int) $szesuti_index; ?>][desc_en]" rows="2"><?php echo esc_textarea( $szesuti_cookie['desc_en'] ); ?></textarea>
						</label>
					</div>

				</div>
			<?php endforeach; ?>
		</div>

		<template class="szesuti-repeater__template">
			<div class="szesuti-repeater__row szesuti-cookie-row">
				<div class="szesuti-cookie-row__grid">
					<label>
						<span><?php esc_html_e( 'Név', 'szesuti' ); ?></span>
						<input type="text" name="szesuti[cookies][__INDEX__][name]" value="" class="code" />
					</label>
					<label>
						<span><?php esc_html_e( 'Szolgáltató', 'szesuti' ); ?></span>
						<input type="text" name="szesuti[cookies][__INDEX__][provider]" value="" />
					</label>
					<label>
						<span><?php esc_html_e( 'Kategória', 'szesuti' ); ?></span>
						<select name="szesuti[cookies][__INDEX__][category]">
							<?php foreach ( $szesuti_cats as $szesuti_slug => $szesuti_label ) : ?>
								<option value="<?php echo esc_attr( $szesuti_slug ); ?>"><?php echo esc_html( $szesuti_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label>
						<span><?php esc_html_e( 'Élettartam', 'szesuti' ); ?></span>
						<input type="text" name="szesuti[cookies][__INDEX__][duration]" value="" />
					</label>
					<label>
						<span><?php esc_html_e( 'Típus', 'szesuti' ); ?></span>
						<select name="szesuti[cookies][__INDEX__][type]">
							<option value="http">HTTP</option>
							<option value="html">HTML storage</option>
							<option value="pixel">Pixel</option>
						</select>
					</label>
					<span class="szesuti-cookie-row__actions">
						<button type="button" class="button-link szesuti-repeater__remove"><?php esc_html_e( 'Törlés', 'szesuti' ); ?></button>
					</span>
				</div>
				<div class="szesuti-cookie-row__desc">
					<label>
						<span><?php esc_html_e( 'Leírás (magyar)', 'szesuti' ); ?></span>
						<textarea name="szesuti[cookies][__INDEX__][desc_hu]" rows="2"></textarea>
					</label>
					<label>
						<span><?php esc_html_e( 'Leírás (angol)', 'szesuti' ); ?></span>
						<textarea name="szesuti[cookies][__INDEX__][desc_en]" rows="2"></textarea>
					</label>
				</div>
			</div>
		</template>

		<p><button type="button" class="button szesuti-repeater__add"><?php esc_html_e( '+ Új süti', 'szesuti' ); ?></button></p>

	</div>

	<p class="szesuti-submit">
		<?php submit_button( __( 'Sütilista mentése', 'szesuti' ), 'primary large', 'submit', false ); ?>
	</p>
</form>
