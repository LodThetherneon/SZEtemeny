<?php
/**
 * Admin – Háttéradatbázis oldal.
 *
 * @package SZESUTI
 */

defined( 'ABSPATH' ) || exit;

class SZESUTI_Admin_Library {

	const PAGE = 'szesuti-library';

	/** @var SZESUTI_Settings */
	private $settings;

	/** @var SZESUTI_Library */
	private $library;

	/** @var SZESUTI_I18n */
	private $i18n;

	/** @var SZESUTI_Admin */
	private $admin;

	public function __construct( SZESUTI_Settings $settings, SZESUTI_Library $library, SZESUTI_I18n $i18n, SZESUTI_Admin $admin ) {
		$this->settings = $settings;
		$this->library  = $library;
		$this->i18n     = $i18n;
		$this->admin    = $admin;

		add_action( 'admin_init', array( $this, 'handle_actions' ) );
	}

	/* ---------------------------------------------------------------------
	 * Műveletek
	 * ------------------------------------------------------------------ */

	/** POST műveletek. */
	public function handle_actions() {
		if ( empty( $_POST['szesuti_lib_action'] ) || wp_doing_ajax() || ! current_user_can( SZESUTI_Admin::CAPABILITY ) ) {
			return;
		}
		check_admin_referer( 'szesuti_library', 'szesuti_lib_nonce' );

		$action = sanitize_key( wp_unslash( $_POST['szesuti_lib_action'] ) );

		switch ( $action ) {
			case 'save':
				$raw      = isset( $_POST['lib'] ) && is_array( $_POST['lib'] ) ? wp_unslash( $_POST['lib'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- a SZESUTI_Library::normalize() tisztít.
				$original = isset( $_POST['lib_original'] ) ? sanitize_text_field( wp_unslash( $_POST['lib_original'] ) ) : '';
				$result   = $this->library->put( (array) $raw, $original );
				$this->redirect( 'invalid' === $result ? array( 'szesuti_msg' => 'lib_invalid' ) : array( 'szesuti_msg' => 'lib_saved' ) );
				break;

			case 'bulk':
				$selected = isset( $_POST['lib_selected'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['lib_selected'] ) ) : array();
				if ( empty( $selected ) ) {
					$this->redirect( array( 'szesuti_msg' => 'nothing_added' ) );
				}
				if ( isset( $_POST['lib_delete'] ) ) {
					$count = $this->library->delete( $selected );
					$this->redirect( array( 'szesuti_msg' => 'lib_deleted', 'count' => $count ) );
				}
				$count = $this->add_to_cookie_list( $selected );
				$this->redirect( array( 'szesuti_msg' => 'lib_listed', 'count' => $count ) );
				break;

			case 'export':
				$json     = $this->library->export_json( ! empty( $_POST['with_builtin'] ), (array) $this->settings->get( 'cookies', array() ) );
				$filename = 'szetemeny-hatteradatbazis-' . sanitize_file_name( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) . '-' . gmdate( 'Y-m-d' ) . '.json';
				// Más bővítmény/téma kósza kimenete (szóköz, BOM) ne kerüljön a fájl elejére.
				while ( ob_get_level() > 0 ) {
					ob_end_clean();
				}
				nocache_headers();
				header( 'Content-Type: application/json; charset=utf-8' );
				header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
				header( 'Content-Length: ' . strlen( $json ) );
				echo $json; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON letöltés.
				exit;

			case 'import':
				$json = '';
				if ( ! empty( $_FILES['lib_file']['tmp_name'] ) ) {
					$file = $_FILES['lib_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
					if ( ! empty( $file['error'] ) || ! is_uploaded_file( $file['tmp_name'] ) || (int) $file['size'] > 5 * MB_IN_BYTES ) {
						set_transient( 'szesuti_import_error', __( 'A fájl feltöltése nem sikerült, vagy túl nagy (legfeljebb 5 MB).', 'szesuti' ), 60 );
						$this->redirect( array( 'szesuti_msg' => 'import_error' ) );
					}
					$json = (string) file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				} elseif ( ! empty( $_POST['lib_json'] ) ) {
					$json = (string) wp_unslash( $_POST['lib_json'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- JSON-ként dolgozzuk fel.
				}
				if ( '' === trim( $json ) ) {
					$this->redirect( array( 'szesuti_msg' => 'import_empty' ) );
				}
				$result = $this->library->import_json( $json, ! empty( $_POST['lib_replace'] ) );
				if ( is_wp_error( $result ) ) {
					set_transient( 'szesuti_import_error', $result->get_error_message(), 60 );
					$this->redirect( array( 'szesuti_msg' => 'import_error' ) );
				}
				$this->redirect(
					array(
						'szesuti_msg' => 'lib_imported',
						'a'           => $result['added'],
						'f'           => $result['filled'],
						'k'           => $result['skipped'],
						'b'           => $result['builtin'],
						'i'           => $result['invalid'],
					)
				);
				break;

			case 'learn':
				$count = $this->library->learn( (array) $this->settings->get( 'cookies', array() ) );
				$this->redirect( array( 'szesuti_msg' => 'lib_learned', 'count' => $count ) );
				break;
		}
	}

	/**
	 * A kijelölt bejegyzések felvétele az oldal sütilistájába (a meglévőket kihagyja).
	 *
	 * @param string[] $names Nevek.
	 * @return int Felvett darabszám.
	 */
	private function add_to_cookie_list( array $names ) {
		$all      = SZESUTI_Library::all();
		$stored   = $this->settings->stored();
		$cookies  = isset( $stored['cookies'] ) ? (array) $stored['cookies'] : array();
		$existing = array();
		foreach ( $cookies as $cookie ) {
			$existing[ SZESUTI_Library::key( $cookie['name'] ) ] = true;
		}

		$added = 0;
		foreach ( $names as $name ) {
			$key = SZESUTI_Library::key( $name );
			if ( ! isset( $all[ $key ] ) || isset( $existing[ $key ] ) ) {
				continue;
			}
			$entry     = $all[ $key ];
			$cookies[] = array(
				'name'     => $entry['match'],
				'provider' => $entry['provider'],
				'category' => $entry['category'],
				'duration' => $entry['duration'],
				'type'     => $entry['type'],
				'desc_hu'  => $entry['desc_hu'],
				'desc_en'  => $entry['desc_en'],
			);
			$existing[ $key ] = true;
			$added++;
		}

		if ( $added ) {
			$stored['cookies'] = $cookies;
			$this->settings->save( $this->settings->sanitize( $stored ) );
		}

		return $added;
	}

	/**
	 * Visszairányítás az oldalra.
	 *
	 * @param array $args Query paraméterek.
	 */
	private function redirect( array $args ) {
		$args['page'] = self::PAGE;
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Megjelenítés
	 * ------------------------------------------------------------------ */

	/**
	 * A kategóriák választéka: az oldal kategóriái + a négy alap, és ami az adatbázisban előfordul.
	 *
	 * @param array $all Bejegyzések.
	 * @return array slug => címke
	 */
	private function categories( array $all ) {
		$slugs = array( 'necessary', 'functional', 'analytics', 'marketing' );
		foreach ( $this->settings->category_slugs() as $slug ) {
			$slugs[] = $slug;
		}
		foreach ( $all as $entry ) {
			$slugs[] = $entry['category'];
		}
		$out  = array();
		$lang = (string) $this->settings->get( 'default_language', 'hu' );
		foreach ( array_unique( array_filter( $slugs ) ) as $slug ) {
			// Az oldal alapnyelvén, ahogy a Sütilista fül is mutatja (nem az admin nyelvén).
			$meta         = $this->i18n->category( $slug, $lang );
			$out[ $slug ] = ! empty( $meta['name'] ) ? $meta['name'] : $slug;
		}
		return $out;
	}

	/** Az oldal kirajzolása. */
	public function render() {
		$all      = SZESUTI_Library::all();
		$custom   = SZESUTI_Library::entries();
		$cats     = $this->categories( $all );
		$here     = SZESUTI_Library::this_site();
		$in_list  = array();
		foreach ( (array) $this->settings->get( 'cookies', array() ) as $cookie ) {
			$in_list[ SZESUTI_Library::key( $cookie['name'] ) ] = true;
		}
		$types = array(
			'http'  => 'HTTP',
			'html'  => __( 'Tároló', 'szesuti' ),
			'pixel' => 'Pixel',
		);

		echo '<div class="wrap szesuti-wrap szesuti-wrap--wide">';
		echo '<hr class="wp-header-end">';
		$this->admin->render_messages();
		$this->admin->render_header();
		echo '<div class="szesuti-panel szesuti-panel--solo">';

		SZESUTI_F::section(
			__( 'Háttéradatbázis', 'szesuti' ),
			__( 'Itt gyűlik össze minden sütifajta, amit a SZetemény ismer, minden adatával: a beépített lista, az ezen az oldalon <strong>leírással</strong> felvett sütik (a Sütilista mentésekor automatikusan bekerülnek), és a más oldalakról importált adatbázisok. A szkenner és a Sütilista innen ismeri fel a sütiket – ha egy másik oldalon már leírtál egy sütit, exportáld onnan, importáld ide, és itt is felismerésre kerül. Egy név csak egyszer szerepelhet.', 'szesuti' )
		);
		?>

		<p class="szesuti-lib-stats">
			<span class="szesuti-pill"><?php echo esc_html( sprintf( /* translators: %d: darabszám */ __( 'Összesen: %d', 'szesuti' ), count( $all ) ) ); ?></span>
			<span class="szesuti-pill szesuti-pill--ok"><?php echo esc_html( sprintf( /* translators: %d: darabszám */ __( 'Saját és importált: %d', 'szesuti' ), count( $custom ) ) ); ?></span>
			<span class="szesuti-pill szesuti-pill--muted"><?php echo esc_html( sprintf( /* translators: %d: darabszám */ __( 'Beépített: %d', 'szesuti' ), count( SZESUTI_Library::builtin() ) ) ); ?></span>
		</p>

		<?php /* ---------- Szerkesztő ---------- */ ?>
		<div class="szesuti-lib-editor" data-lib-editor hidden>
			<h3 class="szesuti-subsection" data-lib-editor-title
				data-title-new="<?php esc_attr_e( 'Új süti felvétele', 'szesuti' ); ?>"
				data-title-edit="<?php esc_attr_e( 'Süti szerkesztése', 'szesuti' ); ?>"><?php esc_html_e( 'Új süti felvétele', 'szesuti' ); ?></h3>
			<form method="post" action="">
				<?php wp_nonce_field( 'szesuti_library', 'szesuti_lib_nonce' ); ?>
				<input type="hidden" name="szesuti_lib_action" value="save" />
				<input type="hidden" name="lib_original" value="" data-lib-field="original" />
				<div class="szesuti-lib-editor__grid">
					<label>
						<span><?php esc_html_e( 'Név vagy minta', 'szesuti' ); ?></span>
						<input type="text" name="lib[match]" class="code" required maxlength="100" pattern="[A-Za-z0-9_\-\.\*\$!#%&+^|~:@]+" data-lib-field="match" placeholder="_pelda_*" />
					</label>
					<label>
						<span><?php esc_html_e( 'Szolgáltató', 'szesuti' ); ?></span>
						<input type="text" name="lib[provider]" maxlength="120" data-lib-field="provider" />
					</label>
					<label>
						<span><?php esc_html_e( 'Kategória', 'szesuti' ); ?></span>
						<select name="lib[category]" data-lib-field="category">
							<?php foreach ( $cats as $slug => $label ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label>
						<span><?php esc_html_e( 'Élettartam', 'szesuti' ); ?></span>
						<input type="text" name="lib[duration]" maxlength="80" data-lib-field="duration" placeholder="<?php esc_attr_e( '1 év', 'szesuti' ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'Típus', 'szesuti' ); ?></span>
						<select name="lib[type]" data-lib-field="type">
							<option value="http">HTTP</option>
							<option value="html"><?php esc_html_e( 'HTML tároló', 'szesuti' ); ?></option>
							<option value="pixel">Pixel</option>
						</select>
					</label>
				</div>
				<div class="szesuti-lib-editor__desc">
					<label>
						<span><?php esc_html_e( 'Leírás (magyar)', 'szesuti' ); ?></span>
						<textarea name="lib[desc_hu]" rows="2" data-lib-field="desc_hu"></textarea>
					</label>
					<label>
						<span><?php esc_html_e( 'Leírás (angol)', 'szesuti' ); ?></span>
						<textarea name="lib[desc_en]" rows="2" data-lib-field="desc_en"></textarea>
					</label>
				</div>
				<p class="description"><?php esc_html_e( 'A csillag (*) bármilyen karaktersort helyettesít, pl. _ga_* minden GA4 adatfolyam sütijére illeszkedik. Beépített süti szerkesztésekor egy saját, erősebb változat jön létre.', 'szesuti' ); ?></p>
				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Mentés az adatbázisba', 'szesuti' ); ?></button>
					<button type="button" class="button" data-lib-cancel><?php esc_html_e( 'Mégse', 'szesuti' ); ?></button>
				</p>
			</form>
		</div>

		<?php /* ---------- Lista ---------- */ ?>
		<form method="post" action="" class="szesuti-lib-list">
			<?php wp_nonce_field( 'szesuti_library', 'szesuti_lib_nonce' ); ?>
			<input type="hidden" name="szesuti_lib_action" value="bulk" />

			<div class="szesuti-lib-toolbar">
				<input type="search" class="regular-text" placeholder="<?php esc_attr_e( 'Keresés névben, szolgáltatóban, leírásban…', 'szesuti' ); ?>" data-lib-search />
				<select data-lib-filter-source>
					<option value=""><?php esc_html_e( 'Minden forrás', 'szesuti' ); ?></option>
					<option value="custom"><?php esc_html_e( 'Saját és importált', 'szesuti' ); ?></option>
					<option value="builtin"><?php esc_html_e( 'Beépített', 'szesuti' ); ?></option>
				</select>
				<select data-lib-filter-category>
					<option value=""><?php esc_html_e( 'Minden kategória', 'szesuti' ); ?></option>
					<?php foreach ( $cats as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<span class="szesuti-lib-count" data-lib-count></span>
				<button type="button" class="button button-primary szesuti-lib-new" data-lib-new><?php esc_html_e( '+ Új süti', 'szesuti' ); ?></button>
			</div>

			<table class="widefat striped szesuti-lib-table">
				<thead>
					<tr>
						<td class="check-column"><input type="checkbox" data-lib-all aria-label="<?php esc_attr_e( 'Összes kijelölése', 'szesuti' ); ?>" /></td>
						<th><?php esc_html_e( 'Név / minta', 'szesuti' ); ?></th>
						<th><?php esc_html_e( 'Szolgáltató', 'szesuti' ); ?></th>
						<th><?php esc_html_e( 'Kategória', 'szesuti' ); ?></th>
						<th><?php esc_html_e( 'Élettartam', 'szesuti' ); ?></th>
						<th><?php esc_html_e( 'Leírás', 'szesuti' ); ?></th>
						<th><?php esc_html_e( 'Forrás', 'szesuti' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $all as $key => $entry ) : ?>
						<?php
						$is_builtin = ! empty( $entry['builtin'] );
						$overrides  = ! $is_builtin && isset( SZESUTI_Library::builtin()[ $key ] );
						$search     = strtolower( $entry['match'] . ' ' . $entry['provider'] . ' ' . $entry['desc_hu'] . ' ' . $entry['desc_en'] . ' ' . $entry['source'] );
						$data       = array(
							'match'    => $entry['match'],
							'provider' => $entry['provider'],
							'category' => $entry['category'],
							'duration' => $entry['duration'],
							'type'     => $entry['type'],
							'desc_hu'  => $entry['desc_hu'],
							'desc_en'  => $entry['desc_en'],
							'original' => $is_builtin ? '' : $entry['match'],
						);
						?>
						<tr data-lib-row data-source="<?php echo $is_builtin ? 'builtin' : 'custom'; ?>" data-category="<?php echo esc_attr( $entry['category'] ); ?>" data-search="<?php echo esc_attr( $search ); ?>">
							<th scope="row" class="check-column">
								<input type="checkbox" name="lib_selected[]" value="<?php echo esc_attr( $entry['match'] ); ?>" />
							</th>
							<td>
								<code><?php echo esc_html( $entry['match'] ); ?></code>
								<?php if ( 'http' !== $entry['type'] ) : ?>
									<span class="szesuti-pill szesuti-pill--muted"><?php echo esc_html( $types[ $entry['type'] ] ); ?></span>
								<?php endif; ?>
								<?php if ( isset( $in_list[ $key ] ) ) : ?>
									<span class="szesuti-pill szesuti-pill--ok" title="<?php esc_attr_e( 'Szerepel az oldal sütilistájában', 'szesuti' ); ?>"><?php esc_html_e( 'listán', 'szesuti' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $entry['provider'] ); ?></td>
							<td><?php echo esc_html( isset( $cats[ $entry['category'] ] ) ? $cats[ $entry['category'] ] : $entry['category'] ); ?></td>
							<td><?php echo esc_html( $entry['duration'] ); ?></td>
							<td class="szesuti-lib-desc">
								<?php echo esc_html( $entry['desc_hu'] ); ?>
								<?php if ( '' !== $entry['desc_en'] ) : ?>
									<span class="szesuti-lib-desc__en"><?php echo esc_html( $entry['desc_en'] ); ?></span>
								<?php endif; ?>
							</td>
							<td class="szesuti-lib-source">
								<?php if ( $is_builtin ) : ?>
									<span class="szesuti-pill szesuti-pill--muted"><?php esc_html_e( 'beépített', 'szesuti' ); ?></span>
								<?php else : ?>
									<?php echo esc_html( $entry['source'] === $here ? __( 'ez az oldal', 'szesuti' ) : $entry['source'] ); ?>
									<?php if ( $overrides ) : ?>
										<span class="szesuti-pill szesuti-pill--warn"><?php esc_html_e( 'felülírja a beépítettet', 'szesuti' ); ?></span>
									<?php endif; ?>
									<?php if ( ! empty( $entry['updated'] ) ) : ?>
										<small><?php echo esc_html( wp_date( 'Y. m. d.', (int) $entry['updated'] ) ); ?></small>
									<?php endif; ?>
								<?php endif; ?>
							</td>
							<td class="szesuti-lib-actions">
								<button type="button" class="button-link" data-lib-edit="<?php echo esc_attr( wp_json_encode( $data ) ); ?>">
									<?php $is_builtin ? esc_html_e( 'Felülírás', 'szesuti' ) : esc_html_e( 'Szerkesztés', 'szesuti' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p class="szesuti-lib-empty" data-lib-empty hidden><?php esc_html_e( 'Nincs a szűrésnek megfelelő süti.', 'szesuti' ); ?></p>

			<p class="szesuti-lib-bulk">
				<button type="submit" class="button button-secondary" name="lib_add"><?php esc_html_e( 'Kijelöltek felvétele az oldal sütilistájába', 'szesuti' ); ?></button>
				<button type="submit" class="button-link szesuti-lib-bulk__delete" name="lib_delete" value="1" data-szesuti-confirm="lib-delete"><?php esc_html_e( 'Kijelöltek törlése (csak saját és importált)', 'szesuti' ); ?></button>
			</p>
		</form>

		<?php
		SZESUTI_F::section(
			__( 'Export és import', 'szesuti' ),
			__( 'Az adatbázis egy JSON fájlban átvihető egy másik oldalra. Importáláskor a már meglévő nevek nem kerülnek be kétszer: alapból csak a hiányzó adataik (pl. üres angol leírás) egészülnek ki. A SZetemény beállítás-exportja is importálható ide – ilyenkor annak sütilistája kerül be.', 'szesuti' )
		);
		?>

		<div class="szesuti-lib-io">
			<form method="post" action="" class="szesuti-lib-io__box">
				<?php wp_nonce_field( 'szesuti_library', 'szesuti_lib_nonce' ); ?>
				<input type="hidden" name="szesuti_lib_action" value="export" />
				<h4><?php esc_html_e( 'Exportálás', 'szesuti' ); ?></h4>
				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: darabszám */
							__( 'A fájlba kerül: a saját és importált bejegyzések, valamint az oldal sütilistája – összesen %d süti.', 'szesuti' ),
							count( SZESUTI_Library::export_entries( false, (array) $this->settings->get( 'cookies', array() ) ) )
						)
					);
					?>
				</p>
				<label class="szesuti-lib-check">
					<input type="checkbox" name="with_builtin" value="1" />
					<?php echo esc_html( sprintf( /* translators: %d: darabszám */ __( 'A beépített bejegyzésekkel együtt (+%d, a másik oldalon úgyis megvannak)', 'szesuti' ), count( SZESUTI_Library::builtin() ) ) ); ?>
				</label>
				<p><button type="submit" class="button button-secondary"><?php esc_html_e( 'JSON letöltése', 'szesuti' ); ?></button></p>
			</form>

			<form method="post" action="" enctype="multipart/form-data" class="szesuti-lib-io__box">
				<?php wp_nonce_field( 'szesuti_library', 'szesuti_lib_nonce' ); ?>
				<input type="hidden" name="szesuti_lib_action" value="import" />
				<h4><?php esc_html_e( 'Importálás', 'szesuti' ); ?></h4>
				<p><input type="file" name="lib_file" accept=".json,application/json" /></p>
				<details>
					<summary><?php esc_html_e( 'vagy JSON beillesztése', 'szesuti' ); ?></summary>
					<textarea name="lib_json" rows="5" class="large-text code"></textarea>
				</details>
				<label class="szesuti-lib-check">
					<input type="checkbox" name="lib_replace" value="1" />
					<?php esc_html_e( 'A meglévő bejegyzések adatainak felülírása az importált adatokkal', 'szesuti' ); ?>
				</label>
				<p><button type="submit" class="button button-secondary"><?php esc_html_e( 'Importálás', 'szesuti' ); ?></button></p>
			</form>

			<form method="post" action="" class="szesuti-lib-io__box">
				<?php wp_nonce_field( 'szesuti_library', 'szesuti_lib_nonce' ); ?>
				<input type="hidden" name="szesuti_lib_action" value="learn" />
				<h4><?php esc_html_e( 'Átvétel a sütilistából', 'szesuti' ); ?></h4>
				<p class="description"><?php esc_html_e( 'Ez automatikusan történik minden mentéskor. Kézzel akkor hasznos, ha a sütilista még a háttéradatbázis előtti verzióval készült.', 'szesuti' ); ?></p>
				<p><button type="submit" class="button button-secondary"><?php esc_html_e( 'Leírással ellátott sütik átvétele', 'szesuti' ); ?></button></p>
			</form>
		</div>

		<?php
		echo '</div></div>';
	}
}
