<?php
/**
 * Egy hibásan csomagolt, korábbi SZESÜTI (a SZetemény régi neve) telepítés maradványainak eltávolítása.
 *
 * Az 1.0.0-s ZIP fordított perjellel tárolta az útvonalakat (szesuti\szesuti.php).
 * Linuxos tárhelyen ebből nem mappák lettek, hanem egyetlen mappában ilyen nevű
 * fájlok. A WordPress ezt a plugint nem tudja sem aktiválni („A bővítmény fájl nem
 * létezik”), sem törölni, mert a fordított perjelet normál perjelre alakítja, és
 * egy nem létező útvonalat keres.
 *
 * Ez az osztály felismeri a maradványt, és az admin kérésére törli.
 *
 * @package SZESUTI
 */

defined( 'ABSPATH' ) || exit;

class SZESUTI_Cleanup {

	const ACTION = 'szesuti_cleanup_broken';

	/**
	 * Az ujjlenyomat: egy fájl, aminek a NEVE (nem útvonala) szesuti\szesuti.php.
	 * Szabályos pluginban ilyen fájlnév nem fordulhat elő.
	 */
	const FINGERPRINT = 'szesuti\\szesuti.php';

	public function __construct() {
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * A maradványok megkeresése.
	 *
	 * @param string|null $plugins_dir Pluginmappa (teszteléshez felülírható).
	 * @param string|null $own_folder  A futó plugin saját mappája, amit sosem érintünk.
	 * @return string[] Abszolút útvonalak.
	 */
	public static function find( $plugins_dir = null, $own_folder = null ) {
		// Windows-on a fordított perjel elválasztó, ilyen fájlnév nem létezhet –
		// és ott a keresés tévesen egy valódi almappára illeszkedhetne.
		if ( '\\' === DIRECTORY_SEPARATOR ) {
			return array();
		}

		$plugins_dir = untrailingslashit( null !== $plugins_dir ? $plugins_dir : WP_PLUGIN_DIR );
		$own_folder  = null !== $own_folder ? $own_folder : dirname( SZESUTI_BASENAME );
		$entries     = @scandir( $plugins_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		if ( ! is_array( $entries ) ) {
			return array();
		}

		$found = array();

		foreach ( $entries as $name ) {
			if ( '.' === $name || '..' === $name || $name === $own_folder ) {
				continue;
			}

			$path = $plugins_dir . '/' . $name;

			if ( is_dir( $path ) && ! is_link( $path ) ) {
				if ( file_exists( $path . '/' . self::FINGERPRINT ) ) {
					$found[] = $path;
				}
			} elseif ( is_file( $path ) && 0 === strpos( $name, 'szesuti\\' ) ) {
				// Ha egy telepítő közvetlenül a pluginmappába bontotta volna ki.
				$found[] = $path;
			}
		}

		return $found;
	}

	/**
	 * Egy maradvány törlése, szigorú biztonsági ellenőrzéssel.
	 *
	 * @param string      $path        Törlendő útvonal (a find() eredményéből).
	 * @param string|null $plugins_dir Pluginmappa.
	 * @return bool
	 */
	public static function remove( $path, $plugins_dir = null ) {
		$plugins_dir = untrailingslashit( null !== $plugins_dir ? $plugins_dir : WP_PLUGIN_DIR );

		$real_root = realpath( $plugins_dir );
		$real_path = realpath( $path );

		// Csak a pluginmappa KÖZVETLEN gyermekét szabad törölni.
		if ( false === $real_root || false === $real_path || dirname( $real_path ) !== $real_root ) {
			return false;
		}

		if ( is_file( $real_path ) ) {
			return @unlink( $real_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		}

		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $real_path, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			$item_path = $item->getPathname();
			// Linkeket csak magukat töröljük, sosem követjük őket.
			if ( $item->isLink() || $item->isFile() ) {
				@unlink( $item_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			} elseif ( $item->isDir() ) {
				@rmdir( $item_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			}
		}

		@rmdir( $real_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions

		return ! file_exists( $real_path );
	}

	/** Figyelmeztetés az adminban, ha van maradvány. */
	public function notice() {
		if ( ! current_user_can( 'delete_plugins' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification -- csak visszajelzés megjelenítése.
		if ( isset( $_GET['szesuti_cleanup'] ) ) {
			$status = sanitize_key( wp_unslash( $_GET['szesuti_cleanup'] ) );
			$count  = isset( $_GET['count'] ) ? (int) $_GET['count'] : 0;

			if ( 'done' === $status ) {
				printf(
					'<div class="notice notice-success is-dismissible"><p><strong>SZetemény:</strong> %s</p></div>',
					esc_html( sprintf( __( 'A hibás korábbi telepítés maradványa törölve (%d elem).', 'szesuti' ), $count ) )
				);
			}
		}
		// phpcs:enable

		$found = self::find();
		if ( empty( $found ) ) {
			return;
		}

		$names = array_map(
			function ( $path ) {
				return 'wp-content/plugins/' . basename( $path );
			},
			$found
		);

		$failed = isset( $_GET['szesuti_cleanup'] ) && 'failed' === $_GET['szesuti_cleanup']; // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<div class="notice notice-warning">
			<p>
				<strong>SZetemény:</strong>
				<?php esc_html_e( 'Egy korábbi, hibásan csomagolt SZESÜTI telepítés maradt a szerveren:', 'szesuti' ); ?>
				<code><?php echo esc_html( implode( ', ', $names ) ); ?></code>
			</p>
			<p>
				<?php esc_html_e( 'Ezt a WordPress nem tudja sem aktiválni („A bővítmény fájl nem létezik”), sem törölni. Nyugodtan eltávolíthatod – a mostani, működő SZetemény-t és a beállításaidat nem érinti.', 'szesuti' ); ?>
			</p>
			<?php if ( $failed ) : ?>
				<p>
					<strong><?php esc_html_e( 'A törlés jogosultsági okból nem sikerült.', 'szesuti' ); ?></strong>
					<?php esc_html_e( 'Töröld a fenti mappát FTP-n vagy a tárhely fájlkezelőjében.', 'szesuti' ); ?>
				</p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0 0 10px">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
				<?php wp_nonce_field( self::ACTION ); ?>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Maradvány törlése', 'szesuti' ); ?></button>
			</form>
		</div>
		<?php
	}

	/** A törlés végrehajtása. */
	public function handle() {
		if ( ! current_user_can( 'delete_plugins' ) ) {
			wp_die( esc_html__( 'Ehhez nincs jogosultságod.', 'szesuti' ), 403 );
		}

		check_admin_referer( self::ACTION );

		// Nem a kérésből dolgozunk: újra megkeressük, és csak azt töröljük, ami most is illeszkedik.
		$found   = self::find();
		$removed = 0;
		$failed  = false;

		foreach ( $found as $path ) {
			if ( self::remove( $path ) ) {
				$removed++;
			} else {
				$failed = true;
			}
		}

		if ( function_exists( 'wp_clean_plugins_cache' ) ) {
			wp_clean_plugins_cache( false );
		}

		$back = wp_get_referer();
		if ( ! $back ) {
			$back = admin_url( 'plugins.php' );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'szesuti_cleanup' => $failed ? 'failed' : 'done',
					'count'           => $removed,
				),
				remove_query_arg( array( 'szesuti_cleanup', 'count' ), $back )
			)
		);
		exit;
	}
}
