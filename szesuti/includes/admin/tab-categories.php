<?php
/**
 * Admin – Kategóriák fül.
 *
 * @var array         $s    Beállítások.
 * @var SZESUTI_I18n  $i18n Nyelvkezelő.
 * @var SZESUTI_Admin $this Admin osztály.
 *
 * @package SZESUTI
 */

defined( 'ABSPATH' ) || exit;

SZESUTI_F::section(
	__( 'Hozzájárulási kategóriák', 'szesuti' ),
	__( 'Ezek a kategóriák jelennek meg a beállítások ablakban, és ezekhez rendeled hozzá a blokkolandó szkripteket. A „feltétlenül szükséges” kategória nem törölhető és nem kapcsolható ki.', 'szesuti' )
);

SZESUTI_F::notice(
	__( 'Új kategória felvétele után ne felejtsd el a <strong>Szövegek</strong> fülön megadni a nevét és leírását minden nyelven, a <strong>Szkriptblokkolás</strong> fülön pedig hozzárendelni a megfelelő szolgáltatásokat.', 'szesuti' ),
	'info'
);

$szesuti_default_lang = (string) $s['default_language'];
?>

<div class="szesuti-repeater" data-szesuti-repeater="categories">

	<div class="szesuti-repeater__head szesuti-cat-grid">
		<span><?php esc_html_e( 'Azonosító (slug)', 'szesuti' ); ?></span>
		<span><?php esc_html_e( 'Név az alapnyelven', 'szesuti' ); ?></span>
		<span><?php esc_html_e( 'Alapból bekapcsolva', 'szesuti' ); ?></span>
		<span><?php esc_html_e( 'Aktív', 'szesuti' ); ?></span>
		<span></span>
	</div>

	<div class="szesuti-repeater__rows">
		<?php foreach ( (array) $s['categories'] as $szesuti_index => $szesuti_cat ) : ?>
			<?php
			$szesuti_slug   = isset( $szesuti_cat['slug'] ) ? $szesuti_cat['slug'] : '';
			$szesuti_locked = ! empty( $szesuti_cat['locked'] );
			$szesuti_meta   = $i18n->category( $szesuti_slug, $szesuti_default_lang );
			?>
			<div class="szesuti-repeater__row szesuti-cat-grid<?php echo $szesuti_locked ? ' is-locked' : ''; ?>">

				<span>
					<input
						type="text"
						name="szesuti[categories][<?php echo (int) $szesuti_index; ?>][slug]"
						value="<?php echo esc_attr( $szesuti_slug ); ?>"
						class="regular-text code"
						<?php echo $szesuti_locked ? 'readonly' : ''; ?>
					/>
					<input type="hidden" name="szesuti[categories][<?php echo (int) $szesuti_index; ?>][locked]" value="<?php echo $szesuti_locked ? '1' : '0'; ?>" />
				</span>

				<span class="szesuti-cat-grid__name">
					<?php echo esc_html( $szesuti_meta['name'] ); ?>
					<?php if ( $szesuti_locked ) : ?>
						<em><?php esc_html_e( '(mindig aktív)', 'szesuti' ); ?></em>
					<?php endif; ?>
				</span>

				<span>
					<?php if ( $szesuti_locked ) : ?>
						<input type="hidden" name="szesuti[categories][<?php echo (int) $szesuti_index; ?>][default]" value="1" />
						<span class="szesuti-dash">—</span>
					<?php else : ?>
						<input type="hidden" name="szesuti[categories][<?php echo (int) $szesuti_index; ?>][default]" value="0" />
						<label class="szesuti-toggle szesuti-toggle--compact">
							<input
								type="checkbox"
								name="szesuti[categories][<?php echo (int) $szesuti_index; ?>][default]"
								value="1"
								<?php checked( ! empty( $szesuti_cat['default'] ) ); ?>
							/>
							<span class="szesuti-toggle__track"><span class="szesuti-toggle__thumb"></span></span>
						</label>
					<?php endif; ?>
				</span>

				<span>
					<?php if ( $szesuti_locked ) : ?>
						<input type="hidden" name="szesuti[categories][<?php echo (int) $szesuti_index; ?>][enabled]" value="1" />
						<span class="szesuti-dash">—</span>
					<?php else : ?>
						<input type="hidden" name="szesuti[categories][<?php echo (int) $szesuti_index; ?>][enabled]" value="0" />
						<label class="szesuti-toggle szesuti-toggle--compact">
							<input
								type="checkbox"
								name="szesuti[categories][<?php echo (int) $szesuti_index; ?>][enabled]"
								value="1"
								<?php checked( ! empty( $szesuti_cat['enabled'] ) ); ?>
							/>
							<span class="szesuti-toggle__track"><span class="szesuti-toggle__thumb"></span></span>
						</label>
					<?php endif; ?>
				</span>

				<span>
					<?php if ( ! $szesuti_locked ) : ?>
						<button type="button" class="button-link szesuti-repeater__remove"><?php esc_html_e( 'Törlés', 'szesuti' ); ?></button>
					<?php endif; ?>
				</span>

			</div>
		<?php endforeach; ?>
	</div>

	<template class="szesuti-repeater__template">
		<div class="szesuti-repeater__row szesuti-cat-grid">
			<span>
				<input type="text" name="szesuti[categories][__INDEX__][slug]" value="" class="regular-text code" placeholder="pl. social" />
				<input type="hidden" name="szesuti[categories][__INDEX__][locked]" value="0" />
			</span>
			<span class="szesuti-cat-grid__name"><em><?php esc_html_e( 'Mentés után szerkeszd a Szövegek fülön', 'szesuti' ); ?></em></span>
			<span>
				<input type="hidden" name="szesuti[categories][__INDEX__][default]" value="0" />
				<label class="szesuti-toggle szesuti-toggle--compact">
					<input type="checkbox" name="szesuti[categories][__INDEX__][default]" value="1" />
					<span class="szesuti-toggle__track"><span class="szesuti-toggle__thumb"></span></span>
				</label>
			</span>
			<span>
				<input type="hidden" name="szesuti[categories][__INDEX__][enabled]" value="0" />
				<label class="szesuti-toggle szesuti-toggle--compact">
					<input type="checkbox" name="szesuti[categories][__INDEX__][enabled]" value="1" checked />
					<span class="szesuti-toggle__track"><span class="szesuti-toggle__thumb"></span></span>
				</label>
			</span>
			<span>
				<button type="button" class="button-link szesuti-repeater__remove"><?php esc_html_e( 'Törlés', 'szesuti' ); ?></button>
			</span>
		</div>
	</template>

	<p>
		<button type="button" class="button szesuti-repeater__add"><?php esc_html_e( '+ Új kategória', 'szesuti' ); ?></button>
	</p>

</div>

<?php
SZESUTI_F::notice(
	__( '<strong>Az „alapból bekapcsolva” óvatosan használandó.</strong> A GDPR szerint a nem szükséges sütiknél előre bejelölt jelölőnégyzet nem minősül érvényes hozzájárulásnak. Statisztikai és marketing kategóriánál hagyd kikapcsolva.', 'szesuti' ),
	'warning'
);
