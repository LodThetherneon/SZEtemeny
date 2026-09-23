<?php
/**
 * Admin – hozzájárulás-napló megjelenítése.
 *
 * @package SZESUTI
 */

defined( 'ABSPATH' ) || exit;

class SZESUTI_Admin_Log {

	/** @var SZESUTI_Settings */
	private $settings;

	/** @var SZESUTI_Log */
	private $log;

	public function __construct( SZESUTI_Settings $settings, SZESUTI_Log $log ) {
		$this->settings = $settings;
		$this->log      = $log;
	}

	/** Az oldal kirajzolása. */
	public function render() {
		// phpcs:disable WordPress.Security.NonceVerification -- csak olvasó szűrők.
		$filters = array(
			'action'   => isset( $_GET['filter_action'] ) ? sanitize_key( wp_unslash( $_GET['filter_action'] ) ) : '',
			'search'   => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'from'     => isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '',
			'to'       => isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '',
			'page'     => isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1,
			'per_page' => 50,
		);
		$message = isset( $_GET['szesuti_msg'] ) ? sanitize_key( wp_unslash( $_GET['szesuti_msg'] ) ) : '';
		// phpcs:enable

		$result = $this->log->query( $filters );
		$total  = $result['total'];
		$pages  = (int) ceil( $total / $filters['per_page'] );

		$actions = array(
			''           => __( 'Minden művelet', 'szesuti' ),
			'accept_all' => __( 'Mindent elfogadott', 'szesuti' ),
			'reject_all' => __( 'Elutasított', 'szesuti' ),
			'custom'     => __( 'Egyedi választás', 'szesuti' ),
			'update'     => __( 'Módosítás', 'szesuti' ),
			'withdraw'   => __( 'Visszavonás', 'szesuti' ),
		);

		echo '<div class="wrap szesuti-wrap">';
		echo '<h1>' . esc_html__( 'Hozzájárulás-napló', 'szesuti' ) . '</h1>';

		if ( 'log_purged' === $message ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'A napló kiürítve.', 'szesuti' ) . '</p></div>';
		}

		if ( ! $this->settings->get( 'log.enabled', 0 ) ) {
			echo '<div class="notice notice-warning"><p>';
			printf(
				/* translators: %s: link a beállításokra */
				esc_html__( 'A naplózás jelenleg ki van kapcsolva, ezért új bejegyzés nem keletkezik. %s', 'szesuti' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=szesuti&tab=log' ) ) . '">' . esc_html__( 'Bekapcsolom', 'szesuti' ) . '</a>'
			);
			echo '</p></div>';
		}

		if ( ! SZESUTI_Log::table_exists() ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'A napló adatbázistáblája hiányzik. Kapcsold ki, majd újra be a plugint.', 'szesuti' ) . '</p></div>';
			echo '</div>';
			return;
		}

		/* --- Szűrők --- */
		?>
		<form method="get" action="" class="szesuti-log-filters">
			<input type="hidden" name="page" value="szesuti-log" />

			<select name="filter_action">
				<?php foreach ( $actions as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filters['action'], $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>

			<label>
				<?php esc_html_e( 'Ettől', 'szesuti' ); ?>
				<input type="date" name="from" value="<?php echo esc_attr( $filters['from'] ); ?>" />
			</label>

			<label>
				<?php esc_html_e( 'Eddig', 'szesuti' ); ?>
				<input type="date" name="to" value="<?php echo esc_attr( $filters['to'] ); ?>" />
			</label>

			<input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Azonosító, lenyomat vagy URL', 'szesuti' ); ?>" />

			<button type="submit" class="button"><?php esc_html_e( 'Szűrés', 'szesuti' ); ?></button>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=szesuti-log' ) ); ?>" class="button-link"><?php esc_html_e( 'Szűrők törlése', 'szesuti' ); ?></a>
		</form>

		<div class="szesuti-log-actions">
			<form method="post" action="">
				<?php wp_nonce_field( 'szesuti_log', 'szesuti_nonce' ); ?>
				<input type="hidden" name="szesuti_action" value="export_log" />
				<input type="hidden" name="filter_action" value="<?php echo esc_attr( $filters['action'] ); ?>" />
				<input type="hidden" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" />
				<input type="hidden" name="from" value="<?php echo esc_attr( $filters['from'] ); ?>" />
				<input type="hidden" name="to" value="<?php echo esc_attr( $filters['to'] ); ?>" />
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'CSV export (a szűrés szerint)', 'szesuti' ); ?></button>
			</form>

			<form method="post" action="">
				<?php wp_nonce_field( 'szesuti_log', 'szesuti_nonce' ); ?>
				<input type="hidden" name="szesuti_action" value="purge_log" />
				<button type="submit" class="button szesuti-btn-danger" data-szesuti-confirm="purge"><?php esc_html_e( 'Teljes napló törlése', 'szesuti' ); ?></button>
			</form>
		</div>

		<p class="szesuti-log-count">
			<?php
			printf(
				/* translators: %s: bejegyzések száma */
				esc_html( _n( '%s bejegyzés', '%s bejegyzés', $total, 'szesuti' ) ),
				esc_html( number_format_i18n( $total ) )
			);
			?>
		</p>

		<table class="widefat striped szesuti-log-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Időpont (UTC)', 'szesuti' ); ?></th>
					<th><?php esc_html_e( 'Művelet', 'szesuti' ); ?></th>
					<th><?php esc_html_e( 'Kategóriák', 'szesuti' ); ?></th>
					<th><?php esc_html_e( 'Verzió', 'szesuti' ); ?></th>
					<th><?php esc_html_e( 'Nyelv', 'szesuti' ); ?></th>
					<th><?php esc_html_e( 'Ország', 'szesuti' ); ?></th>
					<th><?php esc_html_e( 'Hozzájárulás azonosító', 'szesuti' ); ?></th>
					<th><?php esc_html_e( 'IP-lenyomat', 'szesuti' ); ?></th>
					<th><?php esc_html_e( 'Oldal', 'szesuti' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $result['items'] ) ) : ?>
					<tr><td colspan="9"><?php esc_html_e( 'Nincs megjeleníthető bejegyzés.', 'szesuti' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $result['items'] as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['created_at'] ); ?></td>
							<td>
								<span class="szesuti-pill <?php echo esc_attr( $this->action_class( $row['action'] ) ); ?>">
									<?php echo esc_html( isset( $actions[ $row['action'] ] ) ? $actions[ $row['action'] ] : $row['action'] ); ?>
								</span>
							</td>
							<td><code><?php echo esc_html( $row['categories'] ); ?></code></td>
							<td><?php echo esc_html( $row['policy_version'] ); ?></td>
							<td><?php echo esc_html( $row['lang'] ); ?></td>
							<td><?php echo esc_html( $row['country'] ); ?></td>
							<td><code class="szesuti-trunc"><?php echo esc_html( $row['consent_id'] ); ?></code></td>
							<td><code class="szesuti-trunc" title="<?php echo esc_attr( $row['ip_hash'] ); ?>"><?php echo esc_html( substr( $row['ip_hash'], 0, 16 ) ); ?>…</code></td>
							<td class="szesuti-trunc-cell"><?php echo esc_html( $row['url'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php if ( $pages > 1 ) : ?>
			<div class="tablenav"><div class="tablenav-pages">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'current'   => $filters['page'],
							'total'     => $pages,
							'prev_text' => '‹',
							'next_text' => '›',
						)
					)
				);
				?>
			</div></div>
		<?php endif; ?>

		</div>
		<?php
	}

	/**
	 * CSS osztály a művelethez.
	 *
	 * @param string $action Művelet.
	 * @return string
	 */
	private function action_class( $action ) {
		switch ( $action ) {
			case 'accept_all':
				return 'szesuti-pill--ok';
			case 'reject_all':
			case 'withdraw':
				return 'szesuti-pill--warn';
			default:
				return 'szesuti-pill--muted';
		}
	}
}
