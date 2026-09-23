<?php
/**
 * Teljes törlés („wipe”): a plugin minden adata törlődik, a beállítások a telepítés
 * utáni állapotba kerülnek.
 *
 * Törlődik: a hozzájárulás-napló, a Háttéradatbázis saját és importált bejegyzései,
 * a szkennelési eredmények és a látott külső kiszolgálók listája, az átmeneti adatok.
 * A beállítások gyári alapértékre állnak (a sütilista a kezdő listára, a salting újra
 * generálódik). Megmarad: a naplótábla szerkezete, az adatbázis-verzió, az aktiválás
 * időpontja és az ütemezett takarítás – ezek a működéshez kellenek, adatot nem hordoznak.
 *
 * @package SZESUTI
 */

defined( 'ABSPATH' ) || exit;

class SZESUTI_Wipe {

	/**
	 * A megerősítéshez beírandó szó. Szándékosan hosszú és nem másolható (lásd a
	 * felületen): tényleg végig kell gépelni, egy véletlen kattintás ne törölhessen.
	 */
	const CONFIRM_WORD = 'BblikqjikvwapBlikuBlikuba;$a';

	/** Törlendő opciók (a beállításokat külön, alapértékre állítjuk). */
	const OPTIONS = array( 'szesuti_last_scan', 'szesuti_seen_hosts' );

	/** Törlendő átmeneti adatok. */
	const TRANSIENTS = array( 'szesuti_just_activated', 'szesuti_import_error', 'szesuti_update_error', 'szesuti_update_version' );

	/**
	 * A megerősítő szó helyes-e. A kis- és nagybetű számít (a szó vegyes írású, hogy
	 * ne legyen kimásolható/kitalálható) – csak a körülötte lévő szóközök nem.
	 *
	 * @param string $typed A beírt szöveg.
	 * @return bool
	 */
	public static function confirmed( $typed ) {
		return self::CONFIRM_WORD === trim( (string) $typed );
	}

	/**
	 * A törlés végrehajtása.
	 *
	 * @param SZESUTI_Settings $settings  Beállítások.
	 * @param SZESUTI_Log      $log       Napló.
	 * @param bool             $reconsent A látogatók korábbi hozzájárulása veszítse érvényét.
	 * @return array{log:int,library:int,policy:string} Mennyi törlődött, és az új szabályzatverzió.
	 */
	public static function run( SZESUTI_Settings $settings, SZESUTI_Log $log, $reconsent ) {
		$stored     = $settings->stored();
		$old_policy = isset( $stored['general']['policy_version'] ) ? (string) $stored['general']['policy_version'] : '1';

		// 1. Hozzájárulás-napló.
		$log_count = $log->count_all();
		$log->truncate();

		// 2. Beállítások: gyári alapérték, kezdő sütilista, új salting.
		$defaults                = $settings->defaults();
		$defaults['log']['salt'] = wp_generate_password( 32, false, false );
		$defaults['cookies']     = SZESUTI_Cookie_DB::starter_list();
		if ( $reconsent ) {
			// A napló törlése után a korábbi döntésekre nincs bizonyíték: a szabályzat
			// verziójának váltásával a látogatók újra döntenek.
			$defaults['general']['policy_version'] = self::next_policy_version( $old_policy );
		}
		$settings->save( $defaults );

		// 3. Háttéradatbázis – a beállítások mentése UTÁN, mert a mentés tanítja.
		SZESUTI_Library::flush();
		$library_count = count( SZESUTI_Library::entries() );
		delete_option( SZESUTI_Library::OPTION );
		SZESUTI_Library::flush();

		// 4. Szkennelési adatok és átmeneti adatok.
		foreach ( self::OPTIONS as $option ) {
			delete_option( $option );
		}
		foreach ( self::TRANSIENTS as $transient ) {
			delete_transient( $transient );
		}
		delete_site_transient( 'szesuti_update_manifest' );
		self::delete_rate_limits();

		return array(
			'log'     => (int) $log_count,
			'library' => (int) $library_count,
			'policy'  => $reconsent ? (string) $defaults['general']['policy_version'] : '',
		);
	}

	/**
	 * A következő szabályzatverzió: a végén álló szám eggyel nő („1” → „2”, „2026-3” → „2026-4”),
	 * szám nélkül „-2” kerül a végére. Üresből (kikapcsolt ellenőrzés) „2” lesz.
	 *
	 * @param string $old Régi verzió.
	 * @return string
	 */
	public static function next_policy_version( $old ) {
		$old = trim( (string) $old );
		if ( '' === $old ) {
			return '2';
		}
		if ( preg_match( '/^(.*?)(\d+)$/', $old, $m ) ) {
			return substr( $m[1] . ( (int) $m[2] + 1 ), 0, 20 );
		}
		return substr( $old, 0, 18 ) . '-2';
	}

	/** A REST sebességkorlátozás átmeneti bejegyzései (ugyanúgy, mint az eltávolításkor). */
	private static function delete_rate_limits() {
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_szesuti_rl_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_szesuti_rl_' ) . '%'
			)
		);
	}
}
