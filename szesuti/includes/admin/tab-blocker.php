<?php
/**
 * Admin – Szkriptblokkolás fül.
 *
 * @var array         $s    Beállítások.
 * @var SZESUTI_Admin $this Admin osztály.
 *
 * @package SZESUTI
 */

defined( 'ABSPATH' ) || exit;

$b                 = $s['blocker'];
$szesuti_overrides = (array) $b['rules'];
$szesuti_cats      = $this->category_options();

SZESUTI_F::section(
	__( 'Automatikus blokkolás', 'szesuti' ),
	__( 'A plugin átnézi az oldal kimenetét, és a felismert külső szkripteket <code>type="text/plain"</code> típusra írja át, a <code>src</code> értékét pedig elrejti. Így a böngésző el sem kezdi letölteni őket, amíg a látogató hozzá nem járul.', 'szesuti' )
);

SZESUTI_F::table_start();

SZESUTI_F::checkbox(
	array( 'blocker', 'enabled' ),
	$b['enabled'],
	array(
		'important' => true,
		'label' => __( 'Szkriptblokkolás', 'szesuti' ),
		'text'  => __( 'Bekapcsolva', 'szesuti' ),
		'desc'  => __( 'Enélkül a banner csak egy díszlet: a mérőkódok a döntéstől függetlenül lefutnának.', 'szesuti' ),
	)
);

SZESUTI_F::checkbox(
	array( 'blocker', 'auto' ),
	$b['auto'],
	array(
		'important' => true,
		'label' => __( 'Beépített felismerés', 'szesuti' ),
		'text'  => __( 'Az alábbi ismert szolgáltatások automatikus blokkolása', 'szesuti' ),
		'desc'  => __( 'Ha kikapcsolod, csak a saját szabályaid és a kézzel megjelölt elemek blokkolódnak.', 'szesuti' ),
	)
);

SZESUTI_F::checkbox(
	array( 'blocker', 'strict' ),
	$b['strict'],
	array(
		'important' => true,
		'label'     => __( 'Szigorú mód', 'szesuti' ),
		'text'      => __( 'Minden ismeretlen külső forrás blokkolása', 'szesuti' ),
		'desc'      => __( 'Nem csak a felismert szolgáltatásokat tartja vissza, hanem <strong>minden más domainről</strong> érkező szkriptet, beágyazást, stíluslapot és követőpixelt is, amíg a látogató hozzá nem járul. A saját domain (és aldomainjei), valamint az alábbi biztonságos könyvtár-CDN-ek kivételek. Ajánlott bekapcsolva hagyni.', 'szesuti' ),
	)
);

SZESUTI_F::select(
	array( 'blocker', 'unknown_category' ),
	$b['unknown_category'],
	array_diff_key( $szesuti_cats, array( 'necessary' => true ) ),
	array(
		'label' => __( 'Ismeretlen források kategóriája', 'szesuti' ),
		'desc'  => __( 'Szigorú módban ennek a kategóriának az elfogadása oldja fel az ismeretlen külső forrásokat. A legóvatosabb a „Marketing”.', 'szesuti' ),
	)
);

SZESUTI_F::checkbox(
	array( 'blocker', 'guard' ),
	$b['guard'],
	array(
		'important' => true,
		'label'     => __( 'Futásidejű védelem', 'szesuti' ),
		'text'      => __( 'A később (JavaScriptből) beszúrt elemek blokkolása is', 'szesuti' ),
		'desc'      => __( 'Sok mérőkód az oldal betöltése után, JavaScriptből tölt be további szkripteket, iframe-eket és pixeleket, vagy közvetlenül küld adatot (fetch, sendBeacon). A védelem ezeket is elkapja. Kikapcsolni csak hibakereséshez érdemes.', 'szesuti' ),
	)
);

SZESUTI_F::checkbox(
	array( 'blocker', 'safe_hosts' ),
	$b['safe_hosts'],
	array(
		'label' => __( 'Biztonságos CDN-ek', 'szesuti' ),
		'text'  => __( 'A beépített, nem követő könyvtár-CDN-ek átengedése', 'szesuti' ),
		'desc'  => sprintf(
			/* translators: %s: hosztnevek listája */
			__( 'Ezek a kiszolgálók csak programkönyvtárakat, betűtípusokat és fizetési/robotszűrő felületeket szolgálnak ki, sütit nem állítanak be: %s', 'szesuti' ),
			'<code class="szesuti-hostlist">' . esc_html( implode( ', ', SZESUTI_Cookie_DB::safe_hosts() ) ) . '</code>'
		),
	)
);

SZESUTI_F::checkbox(
	array( 'blocker', 'placeholder_enabled' ),
	$b['placeholder_enabled'],
	array(
		'label' => __( 'Helyőrző blokkolt beágyazásoknál', 'szesuti' ),
		'text'  => __( 'Magyarázó doboz a letiltott videó/térkép helyén', 'szesuti' ),
		'desc'  => __( 'A helyőrzőn egy gombbal a látogató azonnal engedélyezheti az adott kategóriát. Enélkül a beágyazás helye üresen marad.', 'szesuti' ),
	)
);

SZESUTI_F::textarea(
	array( 'blocker', 'skip_patterns' ),
	$b['skip_patterns'],
	array(
		'label'       => __( 'Mindig engedélyezett (kivételek)', 'szesuti' ),
		'rows'        => 4,
		'placeholder' => "recaptcha\ncdn.sajat-szolgaltato.hu",
		'desc'        => __( 'Soronként egy részlet (domain vagy fájlnév). Ha egy forrás címe vagy egy beágyazott kód tartalma ezt tartalmazza, soha nem blokkoljuk – szigorú módban sem. Csak olyat vegyél fel, ami biztosan nem állít be sütit és nem követ.', 'szesuti' ),
	)
);

SZESUTI_F::table_end();

/* ------------------------------------------------------------------ */

$szesuti_seen = get_option( 'szesuti_seen_hosts', array() );
if ( $b['strict'] && is_array( $szesuti_seen ) && ! empty( $szesuti_seen ) ) :
	?>
	<h3 class="szesuti-subsection"><?php esc_html_e( 'A szigorú mód által legutóbb visszatartott ismeretlen források', 'szesuti' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'Ezeket a domaineket a plugin egyik szolgáltatáslistában sem találta, ezért hozzájárulásig visszatartja őket. Ha valamelyik az oldal működéséhez kell és biztosan nem követ (pl. saját CDN), vedd fel a fenti kivételek közé.', 'szesuti' ); ?>
	</p>
	<ul class="szesuti-seen-hosts">
		<?php foreach ( $szesuti_seen as $szesuti_host => $szesuti_time ) : ?>
			<li>
				<code><?php echo esc_html( $szesuti_host ); ?></code>
				<span><?php echo esc_html( sprintf( /* translators: %s: eltelt idő */ __( '%s ezelőtt', 'szesuti' ), human_time_diff( (int) $szesuti_time ) ) ); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
endif;

/* ------------------------------------------------------------------ */

if ( $s['gcm']['enabled'] && $s['gcm']['dont_block_google'] ) {
	SZESUTI_F::notice(
		__( 'A <strong>Consent Mode be van kapcsolva</strong>, és a „Google mérőkódok kihagyása” is aktív. Ezért a Google Analytics, a Google Ads és a Google Tag Manager szabályai most <strong>nincsenek érvényben</strong> – ezeket a Consent Mode jelzések vezérlik, nem a blokkolás. Ez a helyes beállítás, ha GTM-et használsz.', 'szesuti' ),
		'info'
	);
}

SZESUTI_F::section(
	__( 'Felismert szolgáltatások', 'szesuti' ),
	__( 'Kategóriánként állítható, melyik szolgáltatás melyik hozzájáruláshoz kötődjön. Ha egy szolgáltatást a „feltétlenül szükséges” kategóriába sorolsz, az gyakorlatilag soha nem blokkolódik.', 'szesuti' )
);

$szesuti_services = SZESUTI_Cookie_DB::services();
$szesuti_groups   = SZESUTI_Cookie_DB::service_groups();
$szesuti_grouped  = array();

foreach ( $szesuti_services as $szesuti_id => $szesuti_service ) {
	$szesuti_group                      = isset( $szesuti_service['group'] ) ? $szesuti_service['group'] : 'other';
	$szesuti_grouped[ $szesuti_group ][ $szesuti_id ] = $szesuti_service;
}

foreach ( $szesuti_groups as $szesuti_group_key => $szesuti_group_label ) :
	if ( empty( $szesuti_grouped[ $szesuti_group_key ] ) ) {
		continue;
	}
	?>
	<h3 class="szesuti-subsection"><?php echo esc_html( $szesuti_group_label ); ?></h3>

	<div class="szesuti-services">
		<?php foreach ( $szesuti_grouped[ $szesuti_group_key ] as $szesuti_id => $szesuti_service ) : ?>
			<?php
			$szesuti_default_on = empty( $szesuti_service['default_off'] );
			$szesuti_enabled    = isset( $szesuti_overrides[ $szesuti_id ]['enabled'] )
				? (bool) $szesuti_overrides[ $szesuti_id ]['enabled']
				: $szesuti_default_on;
			$szesuti_category   = ! empty( $szesuti_overrides[ $szesuti_id ]['category'] )
				? $szesuti_overrides[ $szesuti_id ]['category']
				: $szesuti_service['category'];
			?>
			<div class="szesuti-service">

				<div class="szesuti-service__main">
					<input type="hidden" name="szesuti[blocker][rules][<?php echo esc_attr( $szesuti_id ); ?>][enabled]" value="0" />
					<label class="szesuti-toggle szesuti-toggle--compact">
						<input
							type="checkbox"
							name="szesuti[blocker][rules][<?php echo esc_attr( $szesuti_id ); ?>][enabled]"
							value="1"
							<?php checked( $szesuti_enabled ); ?>
						/>
						<span class="szesuti-toggle__track"><span class="szesuti-toggle__thumb"></span></span>
					</label>

					<div class="szesuti-service__label">
						<strong><?php echo esc_html( $szesuti_service['label'] ); ?></strong>
						<code class="szesuti-service__patterns"><?php echo esc_html( implode( '  ·  ', array_slice( $szesuti_service['patterns'], 0, 3 ) ) ); ?></code>
					</div>

					<select name="szesuti[blocker][rules][<?php echo esc_attr( $szesuti_id ); ?>][category]">
						<?php foreach ( $szesuti_cats as $szesuti_slug => $szesuti_label ) : ?>
							<option value="<?php echo esc_attr( $szesuti_slug ); ?>" <?php selected( $szesuti_category, $szesuti_slug ); ?>>
								<?php echo esc_html( $szesuti_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<?php if ( ! empty( $szesuti_service['note'] ) ) : ?>
					<p class="szesuti-service__note"><?php echo esc_html( $szesuti_service['note'] ); ?></p>
				<?php endif; ?>

			</div>
		<?php endforeach; ?>
	</div>
	<?php
endforeach;

/* ------------------------------------------------------------------ */

SZESUTI_F::section(
	__( 'Saját szabályok', 'szesuti' ),
	__( 'Ha valamit a beépített lista nem ismer fel, itt vehetsz fel saját mintát. A minta egy egyszerű szövegrészlet, amit a szkript forrásában (src) vagy a beágyazott kódban keresünk – nem reguláris kifejezés.', 'szesuti' )
);
?>

<div class="szesuti-repeater" data-szesuti-repeater="custom-rules">

	<div class="szesuti-repeater__head szesuti-rule-grid">
		<span><?php esc_html_e( 'Megnevezés', 'szesuti' ); ?></span>
		<span><?php esc_html_e( 'Minta', 'szesuti' ); ?></span>
		<span><?php esc_html_e( 'Kategória', 'szesuti' ); ?></span>
		<span><?php esc_html_e( 'Elemtípus', 'szesuti' ); ?></span>
		<span><?php esc_html_e( 'Aktív', 'szesuti' ); ?></span>
		<span></span>
	</div>

	<div class="szesuti-repeater__rows">
		<?php foreach ( (array) $b['custom'] as $szesuti_index => $szesuti_rule ) : ?>
			<div class="szesuti-repeater__row szesuti-rule-grid">
				<span>
					<input type="text" name="szesuti[blocker][custom][<?php echo (int) $szesuti_index; ?>][label]" value="<?php echo esc_attr( $szesuti_rule['label'] ); ?>" placeholder="<?php esc_attr_e( 'pl. Saját CRM', 'szesuti' ); ?>" />
				</span>
				<span>
					<input type="text" name="szesuti[blocker][custom][<?php echo (int) $szesuti_index; ?>][pattern]" value="<?php echo esc_attr( $szesuti_rule['pattern'] ); ?>" class="code" placeholder="crm.pelda.hu/track.js" />
				</span>
				<span>
					<select name="szesuti[blocker][custom][<?php echo (int) $szesuti_index; ?>][category]">
						<?php foreach ( $szesuti_cats as $szesuti_slug => $szesuti_label ) : ?>
							<option value="<?php echo esc_attr( $szesuti_slug ); ?>" <?php selected( $szesuti_rule['category'], $szesuti_slug ); ?>><?php echo esc_html( $szesuti_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</span>
				<span>
					<select name="szesuti[blocker][custom][<?php echo (int) $szesuti_index; ?>][type]">
						<option value="both" <?php selected( $szesuti_rule['type'], 'both' ); ?>><?php esc_html_e( 'Mindkettő', 'szesuti' ); ?></option>
						<option value="script" <?php selected( $szesuti_rule['type'], 'script' ); ?>><?php esc_html_e( 'Szkript', 'szesuti' ); ?></option>
						<option value="iframe" <?php selected( $szesuti_rule['type'], 'iframe' ); ?>><?php esc_html_e( 'Beágyazás (iframe)', 'szesuti' ); ?></option>
					</select>
				</span>
				<span>
					<input type="hidden" name="szesuti[blocker][custom][<?php echo (int) $szesuti_index; ?>][enabled]" value="0" />
					<label class="szesuti-toggle szesuti-toggle--compact">
						<input type="checkbox" name="szesuti[blocker][custom][<?php echo (int) $szesuti_index; ?>][enabled]" value="1" <?php checked( ! empty( $szesuti_rule['enabled'] ) ); ?> />
						<span class="szesuti-toggle__track"><span class="szesuti-toggle__thumb"></span></span>
					</label>
				</span>
				<span>
					<button type="button" class="button-link szesuti-repeater__remove"><?php esc_html_e( 'Törlés', 'szesuti' ); ?></button>
				</span>
			</div>
		<?php endforeach; ?>
	</div>

	<template class="szesuti-repeater__template">
		<div class="szesuti-repeater__row szesuti-rule-grid">
			<span><input type="text" name="szesuti[blocker][custom][__INDEX__][label]" value="" placeholder="<?php esc_attr_e( 'pl. Saját CRM', 'szesuti' ); ?>" /></span>
			<span><input type="text" name="szesuti[blocker][custom][__INDEX__][pattern]" value="" class="code" placeholder="crm.pelda.hu/track.js" /></span>
			<span>
				<select name="szesuti[blocker][custom][__INDEX__][category]">
					<?php foreach ( $szesuti_cats as $szesuti_slug => $szesuti_label ) : ?>
						<option value="<?php echo esc_attr( $szesuti_slug ); ?>"><?php echo esc_html( $szesuti_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</span>
			<span>
				<select name="szesuti[blocker][custom][__INDEX__][type]">
					<option value="both"><?php esc_html_e( 'Mindkettő', 'szesuti' ); ?></option>
					<option value="script"><?php esc_html_e( 'Szkript', 'szesuti' ); ?></option>
					<option value="iframe"><?php esc_html_e( 'Beágyazás (iframe)', 'szesuti' ); ?></option>
				</select>
			</span>
			<span>
				<input type="hidden" name="szesuti[blocker][custom][__INDEX__][enabled]" value="0" />
				<label class="szesuti-toggle szesuti-toggle--compact">
					<input type="checkbox" name="szesuti[blocker][custom][__INDEX__][enabled]" value="1" checked />
					<span class="szesuti-toggle__track"><span class="szesuti-toggle__thumb"></span></span>
				</label>
			</span>
			<span><button type="button" class="button-link szesuti-repeater__remove"><?php esc_html_e( 'Törlés', 'szesuti' ); ?></button></span>
		</div>
	</template>

	<p><button type="button" class="button szesuti-repeater__add"><?php esc_html_e( '+ Új szabály', 'szesuti' ); ?></button></p>

</div>

<?php
SZESUTI_F::section(
	__( 'Kézi megjelölés', 'szesuti' ),
	__( 'Ha egy szkriptet te magad helyezel el a témában vagy egy oldalépítőben, közvetlenül is megjelölheted. Az így megjelölt elemeket a plugin akkor is kezeli, ha az automatikus felismerés ki van kapcsolva.', 'szesuti' )
);
?>

<div class="szesuti-code-examples">
	<div>
		<h4><?php esc_html_e( 'Szkript blokkolása', 'szesuti' ); ?></h4>
		<pre><code>&lt;script type="text/plain" data-szesuti="marketing"
        data-szesuti-src="https://pelda.hu/pixel.js"&gt;&lt;/script&gt;</code></pre>
	</div>

	<div>
		<h4><?php esc_html_e( 'Beágyazott kód blokkolása', 'szesuti' ); ?></h4>
		<pre><code>&lt;script type="text/plain" data-szesuti="analytics"&gt;
  console.log('csak hozzájárulás után fut le');
&lt;/script&gt;</code></pre>
	</div>

	<div>
		<h4><?php esc_html_e( 'Kihagyás a blokkolásból', 'szesuti' ); ?></h4>
		<pre><code>&lt;script data-szesuti-ignore="1" src="..."&gt;&lt;/script&gt;</code></pre>
	</div>

	<div>
		<h4><?php esc_html_e( 'Ellenőrzés PHP-ból', 'szesuti' ); ?></h4>
		<pre><code>&lt;?php if ( function_exists( 'szesuti_has_consent' )
     &amp;&amp; szesuti_has_consent( 'marketing' ) ) : ?&gt;
  &lt;!-- marketing tartalom --&gt;
&lt;?php endif; ?&gt;</code></pre>
	</div>

	<div>
		<h4><?php esc_html_e( 'Ellenőrzés JavaScriptből', 'szesuti' ); ?></h4>
		<pre><code>if ( window.SZESUTI &amp;&amp; SZESUTI.hasConsent('analytics') ) { /* ... */ }

document.addEventListener('szesuti:change', function (e) {
  console.log(e.detail.categories, e.detail.action);
});</code></pre>
	</div>
</div>
