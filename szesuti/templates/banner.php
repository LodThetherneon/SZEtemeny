<?php
/**
 * A sütikezelő banner sablonja.
 *
 * Felülírható a témában: wp-content/themes/<téma>/szesuti/banner.php
 *
 * Elérhető változók:
 *
 * @var SZESUTI_Settings $settings
 * @var SZESUTI_I18n     $i18n
 * @var array            $texts
 * @var array            $categories
 * @var array            $appearance
 * @var array            $general
 * @var array            $cookies
 * @var array            $allowed
 *
 * @package SZESUTI
 */

defined( 'ABSPATH' ) || exit;

// A banner ikonja: rajzolt ikon egy színes „jelvényben”, vagy kép a megadott méretben.
$szesuti_icon_name = isset( $appearance['icon'] ) ? $appearance['icon'] : 'cookie';
$szesuti_icon_url  = isset( $appearance['logo_url'] ) ? $appearance['logo_url'] : '';
$szesuti_icon_html = 'none' === $szesuti_icon_name ? '' : SZESUTI_Icons::html( $szesuti_icon_name, $szesuti_icon_url );
$szesuti_icon_img  = SZESUTI_Icons::is_image( $szesuti_icon_name, $szesuti_icon_url );

// A lebegő gomb ikonja.
$szesuti_reopen_name = isset( $general['reopen_icon'] ) ? $general['reopen_icon'] : 'cookie';
$szesuti_reopen_url  = isset( $general['reopen_icon_url'] ) ? $general['reopen_icon_url'] : '';
$szesuti_reopen_html = SZESUTI_Icons::html( $szesuti_reopen_name, $szesuti_reopen_url );
$szesuti_reopen_img  = SZESUTI_Icons::is_image( $szesuti_reopen_name, $szesuti_reopen_url );

$szesuti_links = array();
if ( ! empty( $general['privacy_url'] ) ) {
	$szesuti_links[] = array(
		'url'   => $general['privacy_url'],
		'label' => isset( $texts['privacy_link'] ) ? $texts['privacy_link'] : __( 'Adatkezelési tájékoztató', 'szesuti' ),
	);
}
if ( ! empty( $general['cookie_policy_url'] ) ) {
	$szesuti_links[] = array(
		'url'   => $general['cookie_policy_url'],
		'label' => isset( $texts['cookie_policy_link'] ) ? $texts['cookie_policy_link'] : __( 'Süti tájékoztató', 'szesuti' ),
	);
}
if ( ! empty( $general['imprint_url'] ) ) {
	$szesuti_links[] = array(
		'url'   => $general['imprint_url'],
		'label' => isset( $texts['imprint_link'] ) ? $texts['imprint_link'] : __( 'Impresszum', 'szesuti' ),
	);
}

/**
 * Gombfelirat: ha üresen hagyták, az alapértelmezett szöveg kerül ki, hogy ne
 * legyen felirat nélküli (használhatatlan) gomb.
 *
 * @param array  $texts    Szövegek.
 * @param string $key      Kulcs.
 * @param string $fallback Tartalék.
 * @return string
 */
$szesuti_label = function ( $texts, $key, $fallback ) {
	return ( isset( $texts[ $key ] ) && "" !== trim( wp_strip_all_tags( (string) $texts[ $key ] ) ) ) ? (string) $texts[ $key ] : $fallback;
};

/**
 * Egy gomb kirajzolása a banneren.
 *
 * @param string $key   accept | reject | settings.
 * @param array  $texts Szövegek.
 * @param array  $appearance Megjelenés.
 */
$szesuti_button = function ( $key, $texts, $appearance ) use ( $szesuti_label ) {
	$map = array(
		'accept'   => array(
			'action' => 'accept-all',
			'label'  => $szesuti_label( $texts, 'accept_all', __( 'Összes elfogadása', 'szesuti' ) ),
			'class'  => 'szesuti-btn--primary',
		),
		'reject'   => array(
			'action' => 'reject-all',
			'label'  => $szesuti_label( $texts, 'reject_all', __( 'Elutasítás', 'szesuti' ) ),
			'class'  => 'szesuti-btn--secondary',
		),
		'settings' => array(
			'action' => 'open-prefs',
			'label'  => $szesuti_label( $texts, 'settings', __( 'Beállítások', 'szesuti' ) ),
			'class'  => 'szesuti-btn--ghost',
		),
	);

	if ( ! isset( $map[ $key ] ) ) {
		return;
	}
	if ( 'reject' === $key && empty( $appearance['show_reject'] ) ) {
		return;
	}
	if ( 'settings' === $key && empty( $appearance['show_settings'] ) ) {
		return;
	}

	printf(
		'<button type="button" class="szesuti-btn %1$s" data-szesuti-action="%2$s">%3$s</button>',
		esc_attr( $map[ $key ]['class'] ),
		esc_attr( $map[ $key ]['action'] ),
		esc_html( $map[ $key ]['label'] )
	);
};

$szesuti_order = array_filter( explode( ',', (string) $appearance['btn_order'] ) );
?>
<div
	class="szesuti-root"
	id="szesuti-root"
	data-szesuti-layout="<?php echo esc_attr( $appearance['layout'] ); ?>"
	data-szesuti-position="<?php echo esc_attr( $appearance['position'] ); ?>"
	data-szesuti-animation="<?php echo esc_attr( $appearance['animation'] ); ?>"
	data-szesuti-btn-style="<?php echo esc_attr( $appearance['btn_style'] ); ?>"
	data-szesuti-btn-layout="<?php echo esc_attr( $appearance['btn_layout'] ); ?>"
	data-szesuti-equal="<?php echo ! empty( $appearance['equal_buttons'] ) ? '1' : '0'; ?>"
	hidden
>

	<?php
	// Modal elrendezésnél a sötétítés mindig kell, különben a beállítás dönt.
	$szesuti_needs_overlay = ! empty( $appearance['overlay'] ) || 'modal' === $appearance['layout'];
	?>
	<?php if ( $szesuti_needs_overlay ) : ?>
		<div class="szesuti-overlay" data-szesuti-overlay hidden></div>
	<?php endif; ?>

	<?php /* ---------- Értesítő sáv / doboz ---------- */ ?>
	<div
		class="szesuti-banner"
		role="dialog"
		aria-labelledby="szesuti-title"
		aria-describedby="szesuti-message"
		aria-live="polite"
		hidden
	>
		<div class="szesuti-banner__inner">

			<?php if ( ! empty( $appearance['show_close_x'] ) ) : ?>
				<button type="button" class="szesuti-close" data-szesuti-action="reject-all" aria-label="<?php echo esc_attr( $szesuti_label( $texts, 'close', __( 'Bezárás', 'szesuti' ) ) ); ?>">
					<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg>
				</button>
			<?php endif; ?>

			<div class="szesuti-banner__content">
				<div class="szesuti-banner__head">
					<?php if ( '' !== $szesuti_icon_html ) : ?>
						<?php if ( $szesuti_icon_img ) : ?>
							<span class="szesuti-icon szesuti-icon--image" aria-hidden="true" style="--szesuti-icon-size:<?php echo (int) $appearance['logo_height']; ?>px"><?php echo $szesuti_icon_html; // phpcs:ignore WordPress.Security.EscapeOutput -- SZESUTI_Icons::html() escapel. ?></span>
						<?php else : ?>
							<span class="szesuti-icon" aria-hidden="true"><?php echo $szesuti_icon_html; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						<?php endif; ?>
					<?php endif; ?>

					<h2 class="szesuti-banner__title" id="szesuti-title"><?php echo esc_html( isset( $texts['title'] ) ? $texts['title'] : '' ); ?></h2>
				</div>

				<div class="szesuti-banner__message" id="szesuti-message">
					<?php echo wp_kses( isset( $texts['message'] ) ? $texts['message'] : '', $allowed ); ?>
				</div>

				<?php if ( ! empty( $szesuti_links ) ) : ?>
					<p class="szesuti-links">
						<?php foreach ( $szesuti_links as $szesuti_link ) : ?>
							<a href="<?php echo esc_url( $szesuti_link['url'] ); ?>" class="szesuti-link" target="_blank" rel="noopener nofollow"><?php echo esc_html( $szesuti_link['label'] ); ?></a>
						<?php endforeach; ?>
					</p>
				<?php endif; ?>
			</div>

			<div class="szesuti-actions">
				<?php
				foreach ( $szesuti_order as $szesuti_key ) {
					$szesuti_button( trim( $szesuti_key ), $texts, $appearance );
				}
				?>
			</div>

			<?php if ( ! empty( $appearance['show_powered_by'] ) ) : ?>
				<p class="szesuti-powered">SZetemény</p>
			<?php endif; ?>

		</div>
	</div>

	<?php /* ---------- Részletes beállítások ---------- */ ?>
	<div class="szesuti-modal" role="dialog" aria-modal="true" aria-labelledby="szesuti-prefs-title" hidden>
		<div class="szesuti-modal__dialog" role="document">

			<div class="szesuti-modal__head">
				<h2 class="szesuti-modal__title" id="szesuti-prefs-title"><?php echo esc_html( isset( $texts['prefs_title'] ) ? $texts['prefs_title'] : '' ); ?></h2>
				<button type="button" class="szesuti-close" data-szesuti-action="close-prefs" aria-label="<?php echo esc_attr( $szesuti_label( $texts, 'close', __( 'Bezárás', 'szesuti' ) ) ); ?>">
					<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg>
				</button>
			</div>

			<div class="szesuti-modal__body">
				<p class="szesuti-modal__intro"><?php echo wp_kses( isset( $texts['prefs_intro'] ) ? $texts['prefs_intro'] : '', $allowed ); ?></p>

				<ul class="szesuti-cats">
					<?php
					foreach ( $categories as $szesuti_cat ) :
						$szesuti_slug  = $szesuti_cat['slug'];
						$szesuti_meta  = $i18n->category( $szesuti_slug );
						$szesuti_lock  = ! empty( $szesuti_cat['locked'] );
						$szesuti_on    = $szesuti_lock || ! empty( $szesuti_cat['default'] );
						$szesuti_panel = 'szesuti-cat-panel-' . sanitize_html_class( $szesuti_slug );

						$szesuti_cat_cookies = array();
						foreach ( $cookies as $szesuti_ck ) {
							if ( isset( $szesuti_ck['category'] ) && $szesuti_ck['category'] === $szesuti_slug ) {
								$szesuti_cat_cookies[] = $szesuti_ck;
							}
						}
						?>
						<li class="szesuti-cat" data-szesuti-cat="<?php echo esc_attr( $szesuti_slug ); ?>">

							<div class="szesuti-cat__head">
								<button
									type="button"
									class="szesuti-cat__expand"
									aria-expanded="false"
									aria-controls="<?php echo esc_attr( $szesuti_panel ); ?>"
									data-szesuti-action="toggle-details"
								>
									<span class="szesuti-cat__chevron" aria-hidden="true">
										<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m9 6 6 6-6 6"/></svg>
									</span>
									<span class="szesuti-cat__name"><?php echo esc_html( $szesuti_meta['name'] ); ?></span>
								</button>

								<?php if ( $szesuti_lock ) : ?>
									<span class="szesuti-cat__always"><?php echo esc_html( isset( $texts['always_active'] ) ? $texts['always_active'] : '' ); ?></span>
								<?php else : ?>
									<button
										type="button"
										class="szesuti-switch"
										role="switch"
										aria-checked="<?php echo $szesuti_on ? 'true' : 'false'; ?>"
										aria-label="<?php echo esc_attr( $szesuti_meta['name'] ); ?>"
										data-szesuti-toggle="<?php echo esc_attr( $szesuti_slug ); ?>"
									>
										<span class="szesuti-switch__track" aria-hidden="true"><span class="szesuti-switch__thumb"></span></span>
									</button>
								<?php endif; ?>
							</div>

							<div class="szesuti-cat__panel" id="<?php echo esc_attr( $szesuti_panel ); ?>" hidden>
								<?php if ( '' !== $szesuti_meta['desc'] ) : ?>
									<div class="szesuti-cat__desc"><?php echo wp_kses( $szesuti_meta['desc'], $allowed ); ?></div>
								<?php endif; ?>

								<?php if ( ! empty( $szesuti_cat_cookies ) ) : ?>
									<div class="szesuti-table-wrap">
										<table class="szesuti-table">
											<thead>
												<tr>
													<th scope="col"><?php echo esc_html( isset( $texts['cookie_table_name'] ) ? $texts['cookie_table_name'] : '' ); ?></th>
													<th scope="col"><?php echo esc_html( isset( $texts['cookie_table_prov'] ) ? $texts['cookie_table_prov'] : '' ); ?></th>
													<th scope="col"><?php echo esc_html( isset( $texts['cookie_table_dur'] ) ? $texts['cookie_table_dur'] : '' ); ?></th>
												</tr>
											</thead>
											<tbody>
												<?php foreach ( $szesuti_cat_cookies as $szesuti_ck ) : ?>
													<tr>
														<td><code><?php echo esc_html( $szesuti_ck['name'] ); ?></code></td>
														<td><?php echo esc_html( isset( $szesuti_ck['provider'] ) ? $szesuti_ck['provider'] : '' ); ?></td>
														<td><?php echo esc_html( isset( $szesuti_ck['duration'] ) ? $szesuti_ck['duration'] : '' ); ?></td>
													</tr>
													<?php $szesuti_desc = $i18n->cookie_description( $szesuti_ck ); ?>
													<?php if ( '' !== $szesuti_desc ) : ?>
														<tr class="szesuti-table__desc">
															<td colspan="3"><?php echo esc_html( $szesuti_desc ); ?></td>
														</tr>
													<?php endif; ?>
												<?php endforeach; ?>
											</tbody>
										</table>
									</div>
								<?php endif; ?>
							</div>

						</li>
					<?php endforeach; ?>
				</ul>

				<?php if ( ! empty( $szesuti_links ) ) : ?>
					<p class="szesuti-links szesuti-links--modal">
						<?php foreach ( $szesuti_links as $szesuti_link ) : ?>
							<a href="<?php echo esc_url( $szesuti_link['url'] ); ?>" class="szesuti-link" target="_blank" rel="noopener nofollow"><?php echo esc_html( $szesuti_link['label'] ); ?></a>
						<?php endforeach; ?>
					</p>
				<?php endif; ?>
			</div>

			<div class="szesuti-modal__foot">
				<div class="szesuti-actions">
					<?php if ( ! empty( $appearance['show_reject'] ) ) : ?>
						<button type="button" class="szesuti-btn szesuti-btn--secondary" data-szesuti-action="reject-all"><?php echo esc_html( $szesuti_label( $texts, 'reject_all', __( 'Elutasítás', 'szesuti' ) ) ); ?></button>
					<?php endif; ?>
					<button type="button" class="szesuti-btn szesuti-btn--secondary" data-szesuti-action="save"><?php echo esc_html( $szesuti_label( $texts, 'accept_selected', __( 'Kiválasztottak mentése', 'szesuti' ) ) ); ?></button>
					<button type="button" class="szesuti-btn szesuti-btn--primary" data-szesuti-action="accept-all"><?php echo esc_html( $szesuti_label( $texts, 'accept_all', __( 'Összes elfogadása', 'szesuti' ) ) ); ?></button>
				</div>
			</div>

		</div>
	</div>

	<?php /* ---------- Újranyitó lebegő gomb ---------- */ ?>
	<?php if ( ! empty( $general['reopen_button'] ) ) : ?>
		<button
			type="button"
			class="szesuti-reopen"
			data-szesuti-action="open-prefs"
			data-szesuti-reopen-position="<?php echo esc_attr( $general['reopen_position'] ); ?>"
			data-szesuti-hide-mobile="<?php echo ! empty( $general['reopen_hide_mobile'] ) ? '1' : '0'; ?>"
			data-szesuti-reopen-image="<?php echo $szesuti_reopen_img ? '1' : '0'; ?>"
			aria-label="<?php echo esc_attr( isset( $texts['reopen_label'] ) ? $texts['reopen_label'] : 'Süti beállítások' ); ?>"
			title="<?php echo esc_attr( isset( $texts['reopen_label'] ) ? $texts['reopen_label'] : 'Süti beállítások' ); ?>"
			hidden
		>
			<?php echo $szesuti_reopen_html; // phpcs:ignore WordPress.Security.EscapeOutput -- SZESUTI_Icons::html() escapel. ?>
		</button>
	<?php endif; ?>

</div>
