<?php
/**
 * A plugin törlésekor fut le.
 *
 * Adatot csak akkor törlünk, ha az „Adatok törlése eltávolításkor” beállítás
 * be volt kapcsolva. Alapból megtartunk mindent, hogy egy véletlen törlés ne
 * semmisítse meg a hozzájárulás-bizonyítékokat.
 *
 * @package SZESUTI
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Egy oldal adatainak törlése.
 */
function szesuti_uninstall_site() {
	global $wpdb;

	$settings = get_option( 'szesuti_settings', array() );

	if ( ! is_array( $settings ) || empty( $settings['advanced']['delete_data_on_uninstall'] ) ) {
		return;
	}

	delete_option( 'szesuti_settings' );
	delete_option( 'szesuti_last_scan' );
	delete_option( 'szesuti_activated_at' );
	delete_option( 'szesuti_db_version' );
	delete_option( 'szesuti_seen_hosts' );
	delete_option( 'szesuti_cookie_library' );

	delete_transient( 'szesuti_just_activated' );
	delete_transient( 'szesuti_import_error' );
	delete_transient( 'szesuti_update_error' );
	delete_transient( 'szesuti_update_version' );
	delete_site_transient( 'szesuti_update_manifest' );

	wp_clear_scheduled_hook( 'szesuti_daily_cleanup' );

	$table = $wpdb->prefix . 'szesuti_consent_log';
	// phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

	// A sebességkorlátozás átmeneti bejegyzései.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_szesuti_rl_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_szesuti_rl_' ) . '%'
		)
	);
}

/**
 * A frissítéskezelő (Plugin Update Checker) nyomai. Ez nem felhasználói adat, csak
 * gyorsítótár és ütemezés, ezért a beállítástól függetlenül mindig törlődik.
 */
function szesuti_uninstall_updater() {
	wp_clear_scheduled_hook( 'puc_cron_check_updates-szesuti' );
}

if ( is_multisite() ) {
	$szesuti_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $szesuti_sites as $szesuti_site_id ) {
		switch_to_blog( $szesuti_site_id );
		szesuti_uninstall_site();
		szesuti_uninstall_updater();
		restore_current_blog();
	}
} else {
	szesuti_uninstall_site();
	szesuti_uninstall_updater();
}

delete_site_option( 'external_updates-szesuti' );
