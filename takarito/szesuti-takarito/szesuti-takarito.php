<?php
/**
 * Plugin Name:       SZetemény takarító
 * Description:       Eltávolítja a hibásan csomagolt SZESÜTI 1.0.0 (a SZetemény régi neve) maradványát, amit a WordPress nem tud törölni. Bekapcsoláskor lefut, utána kikapcsolja magát – ezután nyugodtan töröld.
 * Version:           1.0.0
 * Author:            Műszaki Fejlesztési és Üzemeltetési Igazgatóság
 * Author URI:        https://adatvedelemteszt.sze.hu	
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * License:           GPL-2.0-or-later
 * Text Domain:       szesuti-takarito
 *
 * Miért kell külön bővítmény?
 * Az 1.0.0-s ZIP fordított perjellel tárolta az útvonalakat. Linuxos tárhelyen ebből
 * „szesuti\szesuti.php” NEVŰ fájlok lettek egyetlen mappában. A WordPress minden
 * törlésnél és cserénél a fordított perjelet normál perjelre alakítja, ezért ezeket a
 * fájlokat sosem éri el: a Törlés „sikeres”, de semmi nem történik, a „Csere a
 * feltöltöttre” pedig hibával leáll. Ez a bővítmény közvetlenül a PHP-val töröl.
 *
 * @package SZESUTI_Takarito
 */

defined( 'ABSPATH' ) || exit;

final class SZESUTI_Takarito {

	/** Az ujjlenyomat: egy fájl, aminek a NEVE szesuti\szesuti.php. Szabályos pluginban ilyen nincs. */
	const FINGERPRINT = 'szesuti\\szesuti.php';

	/** Az eredmény átadása az aktiválás és a következő admin oldal között. */
	const RESULT = 'szesuti_takarito_eredmeny';

	/**
	 * Ahol maradvány lehet: a pluginmappa és a WordPress ideiglenes frissítési mappái.
	 *
	 * @return string[]
	 */
	private static function roots() {
		$roots = array( WP_PLUGIN_DIR );

		foreach ( array( '/upgrade', '/upgrade-temp-backup/plugins' ) as $sub ) {
			if ( is_dir( WP_CONTENT_DIR . $sub ) ) {
				$roots[] = WP_CONTENT_DIR . $sub;
			}
		}

		return array_map( 'untrailingslashit', $roots );
	}

	/**
	 * A maradványok megkeresése.
	 *
	 * @return string[] Abszolút útvonalak.
	 */
	public static function find() {
		// Windows-on ilyen fájlnév nem létezhet, és ott a keresés tévesen valódi almappára illeszkedne.
		if ( '\\' === DIRECTORY_SEPARATOR ) {
			return array();
		}

		$own   = basename( __DIR__ );
		$found = array();

		foreach ( self::roots() as $root ) {
			$entries = @scandir( $root ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( ! is_array( $entries ) ) {
				continue;
			}

			foreach ( $entries as $name ) {
				if ( '.' === $name || '..' === $name || $name === $own ) {
					continue;
				}

				$path = $root . '/' . $name;

				if ( is_dir( $path ) && ! is_link( $path ) ) {
					if ( file_exists( $path . '/' . self::FINGERPRINT ) ) {
						$found[] = $path;
					}
				} elseif ( is_file( $path ) && 0 === strpos( $name, 'szesuti\\' ) ) {
					$found[] = $path;
				}
			}
		}

		return $found;
	}

	/**
	 * Egy maradvány törlése. Csak a fenti gyökérmappák KÖZVETLEN gyermeke törölhető.
	 *
	 * @param string $path Útvonal a find() eredményéből.
	 * @return int|false A törölt fájlok száma, vagy false hiba esetén.
	 */
	public static function remove( $path ) {
		$real = realpath( $path );
		if ( false === $real ) {
			return false;
		}

		$allowed = false;
		foreach ( self::roots() as $root ) {
			$real_root = realpath( $root );
			if ( false !== $real_root && dirname( $real ) === $real_root ) {
				$allowed = true;
				break;
			}
		}
		if ( ! $allowed ) {
			return false;
		}

		if ( is_file( $real ) ) {
			return @unlink( $real ) ? 1 : false; // phpcs:ignore
		}

		$count = 0;
		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $real, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			if ( $item->isLink() || $item->isFile() ) {
				if ( @unlink( $item->getPathname() ) ) { // phpcs:ignore
					$count++;
				}
			} elseif ( $item->isDir() ) {
				@rmdir( $item->getPathname() ); // phpcs:ignore
			}
		}

		@rmdir( $real ); // phpcs:ignore

		return file_exists( $real ) ? false : $count;
	}

	/** A takarítás elvégzése; az eredményt eltároljuk a következő oldalbetöltésre. */
	public static function run() {
		$result = array(
			'removed' => array(),
			'failed'  => array(),
		);

		foreach ( self::find() as $path ) {
			$label = str_replace( wp_normalize_path( ABSPATH ), '', wp_normalize_path( dirname( $path ) ) ) . '/' . basename( $path );
			$count = self::remove( $path );

			if ( false === $count ) {
				$result['failed'][] = $label;
			} else {
				$result['removed'][] = $label . ' (' . $count . ' fájl)';
			}
		}

		if ( function_exists( 'wp_clean_plugins_cache' ) ) {
			wp_clean_plugins_cache( false );
		}

		set_transient( self::RESULT, $result, HOUR_IN_SECONDS );

		return $result;
	}

	/** Aktiválás utáni első admin oldalon: eredmény kiírása és önmaga kikapcsolása. */
	public static function admin_init() {
		// Háttérkérésnél (heartbeat, AJAX) nem, hogy az üzenet biztosan egy látható oldalon jelenjen meg.
		if ( wp_doing_ajax() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$result = get_transient( self::RESULT );
		if ( false === $result ) {
			// Ha az aktiválási lépés valamiért nem futott le, most pótoljuk.
			$result = self::run();
		}
		delete_transient( self::RESULT );

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		deactivate_plugins( plugin_basename( __FILE__ ), true );

		add_action(
			'admin_notices',
			function () use ( $result ) {
				self::notice( $result );
			}
		);
	}

	/**
	 * Visszajelzés az adminban.
	 *
	 * @param array $result Eredmény.
	 */
	private static function notice( array $result ) {
		$type = empty( $result['failed'] ) ? 'success' : 'error';

		echo '<div class="notice notice-' . esc_attr( $type ) . '"><p><strong>SZetemény takarító:</strong> ';

		if ( empty( $result['removed'] ) && empty( $result['failed'] ) ) {
			echo esc_html( 'Nem találtam hibás SZESÜTI maradványt – ezen az oldalon már minden rendben.' );
		} elseif ( ! empty( $result['removed'] ) ) {
			echo esc_html( 'Kész. Törölve: ' . implode( ', ', $result['removed'] ) . '.' );
		}

		if ( ! empty( $result['failed'] ) ) {
			echo ' ' . esc_html( 'Jogosultsági okból nem sikerült törölni: ' . implode( ', ', $result['failed'] ) . '. Ezt töröld FTP-n vagy a tárhely fájlkezelőjében.' );
		}

		echo '</p><p>' . esc_html( 'A takarító kikapcsolta magát. Most töröld (a „Törlés” link alatta a listában), majd töltsd fel a SZetemény legfrissebb ZIP-jét (szetemeny-x.y.z.zip).' ) . '</p></div>';
	}
}

register_activation_hook( __FILE__, array( 'SZESUTI_Takarito', 'run' ) );
add_action( 'admin_init', array( 'SZESUTI_Takarito', 'admin_init' ) );
