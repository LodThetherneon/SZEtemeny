<?php
/**
 * Hozzájárulás-napló: a GDPR 7. cikk szerinti bizonyítási kötelezettséghez.
 *
 * Személyes adatot szándékosan nem tárolunk: az IP-t saltinggal képzett SHA-256 hash-ként
 * mentjük, ami önmagában nem visszafejthető, de ugyanahhoz a látogatóhoz
 * tartozó bejegyzések összekapcsolására alkalmas.
 *
 * @package SZESUTI
 */

defined( 'ABSPATH' ) || exit;

class SZESUTI_Log {

	/** @var SZESUTI_Settings */
	private $settings;

	public function __construct( SZESUTI_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * A napló táblájának neve.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'szesuti_consent_log';
	}

	/** A tábla létrehozása / frissítése. */
	public static function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			consent_id VARCHAR(40) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			action VARCHAR(20) NOT NULL DEFAULT '',
			categories VARCHAR(255) NOT NULL DEFAULT '',
			policy_version VARCHAR(20) NOT NULL DEFAULT '',
			lang VARCHAR(10) NOT NULL DEFAULT '',
			country VARCHAR(2) NOT NULL DEFAULT '',
			ip_hash VARCHAR(64) NOT NULL DEFAULT '',
			user_agent VARCHAR(255) NOT NULL DEFAULT '',
			url VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY (id),
			KEY consent_id (consent_id),
			KEY created_at (created_at),
			KEY action (action)
		) {$collate};";

		dbDelta( $sql );
	}

	/** Létezik-e a tábla? */
	public static function table_exists() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	/** Engedélyezett-e a naplózás? */
	public function is_enabled() {
		return (bool) $this->settings->get( 'log.enabled', 0 );
	}

	/**
	 * Egy hozzájárulás naplózása.
	 *
	 * @param array $entry consent_id, action, categories, policy_version, lang, country, url, user_agent, ip.
	 * @return int|false Beszúrt sor azonosítója vagy false.
	 */
	public function record( array $entry ) {
		global $wpdb;

		if ( ! $this->is_enabled() ) {
			return false;
		}

		$allowed_actions = array( 'accept_all', 'reject_all', 'custom', 'update', 'withdraw' );
		$action          = isset( $entry['action'] ) ? sanitize_key( $entry['action'] ) : 'custom';
		if ( ! in_array( $action, $allowed_actions, true ) ) {
			$action = 'custom';
		}

		$categories = isset( $entry['categories'] ) ? (array) $entry['categories'] : array();
		$categories = array_slice( array_filter( array_map( 'sanitize_key', $categories ) ), 0, 20 );

		$row = array(
			'consent_id'     => isset( $entry['consent_id'] ) ? substr( sanitize_text_field( $entry['consent_id'] ), 0, 40 ) : '',
			'created_at'     => current_time( 'mysql', true ),
			'action'         => $action,
			'categories'     => substr( implode( ',', $categories ), 0, 255 ),
			'policy_version' => isset( $entry['policy_version'] ) ? substr( sanitize_text_field( $entry['policy_version'] ), 0, 20 ) : '',
			'lang'           => isset( $entry['lang'] ) ? substr( sanitize_text_field( $entry['lang'] ), 0, 10 ) : '',
			'country'        => '',
			'ip_hash'        => '',
			'user_agent'     => '',
			'url'            => '',
		);

		if ( $this->settings->get( 'log.store_country', 0 ) && ! empty( $entry['country'] ) ) {
			$row['country'] = substr( strtoupper( preg_replace( '/[^A-Za-z]/', '', $entry['country'] ) ), 0, 2 );
		}

		if ( ! empty( $entry['ip'] ) ) {
			$row['ip_hash'] = $this->hash_ip( $entry['ip'] );
		}

		if ( $this->settings->get( 'log.store_ua', 0 ) && ! empty( $entry['user_agent'] ) ) {
			$row['user_agent'] = substr( sanitize_text_field( $entry['user_agent'] ), 0, 255 );
		}

		if ( $this->settings->get( 'log.store_url', 0 ) && ! empty( $entry['url'] ) ) {
			$row['url'] = substr( esc_url_raw( $entry['url'] ), 0, 255 );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert( self::table(), $row );

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * IP-cím hashelése saltinggal (nem visszafejthető).
	 *
	 * @param string $ip IP-cím.
	 * @return string
	 */
	public function hash_ip( $ip ) {
		$salt = (string) $this->settings->get( 'log.salt', '' );
		if ( '' === $salt ) {
			$salt = wp_salt( 'auth' );
		}
		return hash( 'sha256', $salt . '|' . trim( (string) $ip ) );
	}

	/**
	 * Naplóbejegyzések lekérdezése.
	 *
	 * @param array $args per_page, page, action, search, from, to, orderby, order.
	 * @return array {items, total}
	 */
	public function query( array $args = array() ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return array(
				'items' => array(),
				'total' => 0,
			);
		}

		$args = wp_parse_args(
			$args,
			array(
				'per_page' => 25,
				'page'     => 1,
				'action'   => '',
				'search'   => '',
				'from'     => '',
				'to'       => '',
				'orderby'  => 'created_at',
				'order'    => 'DESC',
			)
		);

		$per_page = max( 1, min( 500, (int) $args['per_page'] ) );
		$page     = max( 1, (int) $args['page'] );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$params = array();

		if ( '' !== $args['action'] ) {
			$where[]  = 'action = %s';
			$params[] = sanitize_key( $args['action'] );
		}
		if ( '' !== $args['search'] ) {
			$where[]  = '(consent_id LIKE %s OR ip_hash LIKE %s OR url LIKE %s)';
			$like     = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		if ( '' !== $args['from'] ) {
			$where[]  = 'created_at >= %s';
			$params[] = gmdate( 'Y-m-d 00:00:00', strtotime( $args['from'] ) );
		}
		if ( '' !== $args['to'] ) {
			$where[]  = 'created_at <= %s';
			$params[] = gmdate( 'Y-m-d 23:59:59', strtotime( $args['to'] ) );
		}

		$allowed_orderby = array( 'id', 'created_at', 'action', 'country' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$table      = self::table();
		$where_sql  = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		// phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
		$total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );

		$list_sql       = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$list_params    = array_merge( $params, array( $per_page, $offset ) );
		// phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
		$items = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ), ARRAY_A );

		return array(
			'items' => is_array( $items ) ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Összesítő statisztika a napló fejlécéhez.
	 *
	 * @param int $days Hány napra visszamenőleg.
	 * @return array
	 */
	public function stats( $days = 30 ) {
		global $wpdb;

		$empty = array(
			'total'      => 0,
			'accept_all' => 0,
			'reject_all' => 0,
			'custom'     => 0,
		);

		if ( ! self::table_exists() ) {
			return $empty;
		}

		$table = self::table();
		$since = gmdate( 'Y-m-d H:i:s', time() - ( max( 1, (int) $days ) * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT action, COUNT(*) AS c FROM {$table} WHERE created_at >= %s GROUP BY action", $since ),
			ARRAY_A
		);

		$out = $empty;
		foreach ( (array) $rows as $row ) {
			$action = isset( $row['action'] ) ? $row['action'] : '';
			$count  = isset( $row['c'] ) ? (int) $row['c'] : 0;
			$out['total'] += $count;
			if ( isset( $out[ $action ] ) ) {
				$out[ $action ] += $count;
			}
		}

		return $out;
	}

	/**
	 * CSV export (közvetlenül a kimenetre írva).
	 *
	 * @param array $args Szűrési feltételek.
	 */
	public function export_csv( array $args = array() ) {
		$args['per_page'] = 5000;
		$page             = 1;

		$filename = 'szesuti-hozzajarulasok-' . gmdate( 'Y-m-d' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' );

		// UTF-8 BOM, hogy az Excel helyesen nyissa meg az ékezeteket.
		fwrite( $out, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		fputcsv(
			$out,
			array( 'id', 'consent_id', 'created_at_utc', 'action', 'categories', 'policy_version', 'lang', 'country', 'ip_hash', 'user_agent', 'url' ),
			';'
		);

		do {
			$args['page'] = $page;
			$result       = $this->query( $args );

			foreach ( $result['items'] as $row ) {
				fputcsv(
					$out,
					array(
						$row['id'],
						$row['consent_id'],
						$row['created_at'],
						$row['action'],
						$row['categories'],
						$row['policy_version'],
						$row['lang'],
						$row['country'],
						$row['ip_hash'],
						$row['user_agent'],
						$row['url'],
					),
					';'
				);
			}

			$page++;
			$done = empty( $result['items'] ) || ( $page - 1 ) * $args['per_page'] >= $result['total'];
		} while ( ! $done );

		fclose( $out );
	}

	/** Megőrzési időn túli bejegyzések törlése (napi cron). */
	public function cleanup() {
		global $wpdb;

		$days = (int) $this->settings->get( 'log.retention_days', 365 );
		if ( $days <= 0 || ! self::table_exists() ) {
			return;
		}

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$table  = self::table();

		// phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) );
	}

	/**
	 * Az összes bejegyzés száma.
	 *
	 * @return int
	 */
	public function count_all() {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return 0;
		}

		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/** A teljes napló törlése. */
	public function truncate() {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return;
		}

		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "TRUNCATE TABLE {$table}" );
	}
}
