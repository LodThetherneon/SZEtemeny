<?php
/**
 * Háttéradatbázis: az összes ismert sütifajta minden adatával.
 *
 * Három forrásból áll össze:
 *  1. a pluginba épített ismert sütik (SZESUTI_Cookie_DB),
 *  2. az ezen az oldalon leírással felvett sütik – a Sütilista mentésekor
 *     automatikusan bekerülnek,
 *  3. más oldalakról importált adatbázisok.
 *
 * A szkenner és a Sütilista is innen ismeri fel a sütiket, így ami egy oldalon
 * egyszer le lett írva, az exporttal-importtal minden más oldalon is felismerhető.
 * Egy név (minta) csak egyszer szerepelhet – kis- és nagybetűtől függetlenül.
 *
 * @package SZESUTI
 */

defined( 'ABSPATH' ) || exit;

class SZESUTI_Library {

	/** A saját és importált bejegyzések opciója (nem töltődik be minden kérésnél). */
	const OPTION = 'szesuti_cookie_library';

	/** Biztonsági felső korlát. */
	const MAX_ENTRIES = 5000;

	/** @var array|null Memóriában tartott bejegyzések. */
	private static $cache = null;

	/** @var array|null A beépített bejegyzések (kérésenként egyszer épül fel). */
	private static $builtin_cache = null;

	/** @var bool Tömeges írás (import) közben csak a végén mentünk. */
	private $batch = false;

	public function __construct() {
		// Minden mentésnél (Sütilista fül, szkennelt sütik felvétele, beállítás-import)
		// megjegyezzük a leírással ellátott sütiket.
		add_action( 'add_option_' . SZESUTI_OPTION, array( $this, 'on_settings_added' ), 10, 2 );
		add_action( 'update_option_' . SZESUTI_OPTION, array( $this, 'on_settings_updated' ), 10, 2 );
	}

	/* ---------------------------------------------------------------------
	 * Olvasás
	 * ------------------------------------------------------------------ */

	/**
	 * A saját és importált bejegyzések (kulcs: kisbetűs név).
	 *
	 * @return array
	 */
	public static function entries() {
		if ( null === self::$cache ) {
			$raw         = get_option( self::OPTION, array() );
			self::$cache = is_array( $raw ) ? $raw : array();
		}
		return self::$cache;
	}

	/**
	 * A beépített bejegyzések egységes formában (kulcs: kisbetűs név).
	 *
	 * @return array
	 */
	public static function builtin() {
		if ( null === self::$builtin_cache ) {
			$out = array();
			foreach ( SZESUTI_Cookie_DB::builtin_cookies() as $cookie ) {
				$entry                = self::normalize( $cookie );
				$entry['source']      = '';
				$entry['builtin']     = true;
				$out[ self::key( $entry['match'] ) ] = $entry;
			}
			self::$builtin_cache = $out;
		}
		return self::$builtin_cache;
	}

	/**
	 * A teljes adatbázis: a saját/importált bejegyzések felülírják az azonos nevű beépítettet.
	 *
	 * @return array
	 */
	public static function all() {
		$all = self::builtin();
		foreach ( self::entries() as $key => $entry ) {
			$entry['builtin'] = false;
			$all[ $key ]      = $entry;
		}
		uasort(
			$all,
			function ( $a, $b ) {
				return strcasecmp( $a['match'], $b['match'] );
			}
		);
		return $all;
	}

	/**
	 * Az ismert sütik listája a felismeréshez (SZESUTI_Cookie_DB::known_cookies() formában).
	 * A saját bejegyzések előre kerülnek, így azok az erősebbek.
	 *
	 * @return array
	 */
	public static function as_known() {
		$out = array();
		foreach ( self::entries() as $entry ) {
			$out[] = array(
				'match'    => $entry['match'],
				'name'     => $entry['match'],
				'provider' => $entry['provider'],
				'category' => $entry['category'],
				'duration' => $entry['duration'],
				'type'     => $entry['type'],
				'desc_hu'  => $entry['desc_hu'],
				'desc_en'  => $entry['desc_en'],
			);
		}
		return $out;
	}

	/**
	 * Egy sütinévhez tartozó bejegyzés (pontos név vagy * minta).
	 *
	 * @param string $name Süti neve.
	 * @return array|null
	 */
	public static function find( $name ) {
		foreach ( array( self::entries(), self::builtin() ) as $set ) {
			if ( isset( $set[ self::key( $name ) ] ) ) {
				return $set[ self::key( $name ) ];
			}
			foreach ( $set as $entry ) {
				if ( false !== strpos( $entry['match'], '*' ) && self::matches( $entry['match'], $name ) ) {
					return $entry;
				}
			}
		}
		return null;
	}

	/* ---------------------------------------------------------------------
	 * Írás
	 * ------------------------------------------------------------------ */

	/**
	 * Egy bejegyzés felvétele vagy frissítése.
	 *
	 * @param array  $raw    Nyers adatok.
	 * @param string $mode   fill: új felvétele, meglévőnél csak az üres mezők kitöltése;
	 *                       replace: meglévő felülírása a nem üres mezőkkel.
	 * @param string $source Honnan származik (oldal hosztneve).
	 * @return string added | filled | updated | skipped | invalid | builtin
	 */
	public function upsert( array $raw, $mode = 'fill', $source = '' ) {
		$entry = self::normalize( $raw );
		if ( '' === $entry['match'] ) {
			return 'invalid';
		}

		$entries = self::entries();
		$key     = self::key( $entry['match'] );
		$now     = time();

		if ( ! isset( $entries[ $key ] ) ) {
			// A beépítettel azonos nevű bejegyzés csak akkor kell, ha kifejezetten felülírjuk.
			$builtin = self::builtin();
			if ( isset( $builtin[ $key ] ) ) {
				if ( 'replace' !== $mode ) {
					return 'builtin';
				}
				// Felülírásnál is csak akkor, ha tényleg eltér (pl. beépítettekkel együtt exportált fájl).
				$same = true;
				foreach ( self::fields() as $field ) {
					if ( isset( $raw[ $field ] ) && '' !== $entry[ $field ] && $entry[ $field ] !== $builtin[ $key ][ $field ] ) {
						$same = false;
						break;
					}
				}
				if ( $same ) {
					return 'builtin';
				}
			}
			if ( count( $entries ) >= self::MAX_ENTRIES ) {
				return 'skipped';
			}
			$entry['source']  = '' !== $source ? $source : self::this_site();
			$entry['added']   = $now;
			$entry['updated'] = $now;
			$entries[ $key ]  = $entry;
			$this->store( $entries );
			return 'added';
		}

		$existing = $entries[ $key ];
		$changed  = false;
		foreach ( self::fields() as $field ) {
			// Csak a ténylegesen megadott mezők számítanak (a tisztító alapértékei nem).
			if ( ! isset( $raw[ $field ] ) || '' === trim( (string) $raw[ $field ] ) || '' === $entry[ $field ] ) {
				continue;
			}
			$empty = '' === (string) $existing[ $field ];
			if ( 'replace' === $mode ? $existing[ $field ] !== $entry[ $field ] : $empty ) {
				$existing[ $field ] = $entry[ $field ];
				$changed            = true;
			}
		}
		// Az alapértelmezett „szükséges” kategóriát egy konkrétabb besorolás felülírhatja.
		if ( 'fill' === $mode && 'necessary' === $existing['category'] && 'necessary' !== $entry['category'] && '' === $existing['desc_hu'] . $existing['desc_en'] ) {
			$existing['category'] = $entry['category'];
			$changed              = true;
		}

		if ( ! $changed ) {
			return 'skipped';
		}

		$existing['updated'] = $now;
		$entries[ $key ]     = $existing;
		$this->store( $entries );

		return 'replace' === $mode ? 'updated' : 'filled';
	}

	/**
	 * Egy bejegyzés pontos mentése (kézi szerkesztés): minden mező a megadott értéket kapja.
	 * Beépített névvel is menthető – ilyenkor az erősebb saját változat lesz érvényes.
	 *
	 * @param array  $raw      Adatok.
	 * @param string $original Szerkesztésnél az eredeti név (átnevezéshez).
	 * @return string added | updated | invalid
	 */
	public function put( array $raw, $original = '' ) {
		$entry = self::normalize( $raw );
		if ( '' === $entry['match'] ) {
			return 'invalid';
		}

		$entries = self::entries();
		$key     = self::key( $entry['match'] );
		$old_key = self::key( $original );

		$previous = isset( $entries[ $key ] ) ? $entries[ $key ] : ( ( '' !== $old_key && isset( $entries[ $old_key ] ) ) ? $entries[ $old_key ] : null );

		// Átnevezés: a régi név törlődik.
		if ( '' !== $old_key && $old_key !== $key ) {
			unset( $entries[ $old_key ] );
		}

		$entry['source']  = $previous && '' !== $previous['source'] ? $previous['source'] : self::this_site();
		$entry['added']   = $previous && $previous['added'] ? $previous['added'] : time();
		$entry['updated'] = time();
		$entries[ $key ]  = $entry;
		$this->store( $entries );

		return $previous ? 'updated' : 'added';
	}

	/**
	 * Bejegyzések törlése (a beépítetteket nem lehet törölni).
	 *
	 * @param string[] $names Nevek.
	 * @return int Törölt darabszám.
	 */
	public function delete( array $names ) {
		$entries = self::entries();
		$deleted = 0;
		foreach ( $names as $name ) {
			$key = self::key( (string) $name );
			if ( isset( $entries[ $key ] ) ) {
				unset( $entries[ $key ] );
				$deleted++;
			}
		}
		if ( $deleted ) {
			$this->store( $entries );
		}
		return $deleted;
	}

	/**
	 * Tanulás egy sütilistából: a leírással ellátott, nem beépített sütik felvétele.
	 *
	 * @param array $cookies Sütilista (a beállítások `cookies` tömbje).
	 * @return int Új vagy kiegészített bejegyzések száma.
	 */
	public function learn( array $cookies ) {
		$count = 0;
		foreach ( $cookies as $cookie ) {
			if ( ! is_array( $cookie ) || empty( $cookie['name'] ) ) {
				continue;
			}
			// Leírás nélkül nincs mit megjegyezni – a név önmagában nem segít egy másik oldalon.
			if ( '' === trim( ( isset( $cookie['desc_hu'] ) ? $cookie['desc_hu'] : '' ) . ( isset( $cookie['desc_en'] ) ? $cookie['desc_en'] : '' ) ) ) {
				continue;
			}
			$result = $this->upsert( array_merge( $cookie, array( 'match' => $cookie['name'] ) ), 'fill' );
			if ( 'added' === $result || 'filled' === $result ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * @param string $option Opció neve.
	 * @param mixed  $value  Új érték.
	 */
	public function on_settings_added( $option, $value ) {
		$this->learn_from_settings( $value );
	}

	/**
	 * @param mixed $old   Régi érték.
	 * @param mixed $value Új érték.
	 */
	public function on_settings_updated( $old, $value ) {
		$this->learn_from_settings( $value );
	}

	/**
	 * @param mixed $settings A mentett beállítások.
	 */
	private function learn_from_settings( $settings ) {
		if ( is_array( $settings ) && ! empty( $settings['cookies'] ) && is_array( $settings['cookies'] ) ) {
			$this->learn( $settings['cookies'] );
		}
	}

	/* ---------------------------------------------------------------------
	 * Export / import
	 * ------------------------------------------------------------------ */

	/**
	 * Az exportálandó bejegyzések: a saját és importáltak, az oldal sütilistája
	 * (ami még nincs az adatbázisban), és kérésre a beépítettek.
	 *
	 * @param bool  $with_builtin A beépített bejegyzések is kerüljenek bele.
	 * @param array $site_cookies Az oldal sütilistája (a beállítások `cookies` tömbje).
	 * @return array kulcs => bejegyzés
	 */
	public static function export_entries( $with_builtin = false, array $site_cookies = array() ) {
		$list = self::entries();

		// A sütilista is benne van: egy friss oldalon a saját adatbázis még üres lehet,
		// a listán szereplő (akár beépített nevű, de itt átírt) sütik viszont kellenek.
		foreach ( $site_cookies as $cookie ) {
			if ( ! is_array( $cookie ) ) {
				continue;
			}
			$entry = self::normalize( $cookie );
			$key   = self::key( $entry['match'] );
			if ( '' === $entry['match'] || isset( $list[ $key ] ) ) {
				continue;
			}
			$entry['source'] = self::this_site();
			$list[ $key ]    = $entry;
		}

		if ( $with_builtin ) {
			$list = $list + self::builtin(); // A saját változat az erősebb.
		}

		uasort(
			$list,
			function ( $a, $b ) {
				return strcasecmp( $a['match'], $b['match'] );
			}
		);
		return $list;
	}

	/**
	 * Export JSON-ként.
	 *
	 * @param bool  $with_builtin A beépített bejegyzések is kerüljenek bele.
	 * @param array $site_cookies Az oldal sütilistája.
	 * @return string
	 */
	public function export_json( $with_builtin = false, array $site_cookies = array() ) {
		$out = array();
		foreach ( self::export_entries( $with_builtin, $site_cookies ) as $entry ) {
			$row = array();
			foreach ( array_merge( array( 'match' ), self::fields(), array( 'source' ) ) as $field ) {
				$row[ $field ] = isset( $entry[ $field ] ) ? $entry[ $field ] : '';
			}
			$out[] = $row;
		}

		return (string) wp_json_encode(
			array(
				'_szesuti_library' => true,
				'_version'         => SZESUTI_VERSION,
				'_exported'        => gmdate( 'c' ),
				'_site'            => home_url(),
				'_count'           => count( $out ),
				'entries'          => $out,
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);
	}

	/**
	 * Import. Elfogadja a háttéradatbázis exportját, a SZetemény beállítás-exportját
	 * (annak sütilistáját), vagy egy egyszerű bejegyzéstömböt.
	 *
	 * @param string $json JSON szöveg.
	 * @param bool   $replace A meglévő bejegyzések felülírása (alapból csak az üres mezők kitöltése).
	 * @return array|WP_Error {added, filled, skipped, builtin, invalid}
	 */
	public function import_json( $json, $replace = false ) {
		// Jegyzettömbben elmentett fájl elején UTF-8 BOM lehet – azzal a json_decode elhasal.
		$json = preg_replace( '/^\xEF\xBB\xBF/', '', trim( (string) $json ) );
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'szesuti_library_json', __( 'A fájl nem érvényes JSON.', 'szesuti' ) );
		}

		$source = '';
		if ( ! empty( $data['_site'] ) ) {
			$source = (string) wp_parse_url( (string) $data['_site'], PHP_URL_HOST );
		}

		if ( isset( $data['entries'] ) && is_array( $data['entries'] ) ) {
			$items = $data['entries'];
		} elseif ( isset( $data['settings']['cookies'] ) && is_array( $data['settings']['cookies'] ) ) {
			$items = $data['settings']['cookies'];
		} elseif ( isset( $data['cookies'] ) && is_array( $data['cookies'] ) ) {
			$items = $data['cookies'];
		} elseif ( array_values( $data ) === $data ) {
			$items = $data;
		} else {
			return new WP_Error( 'szesuti_library_shape', __( 'A fájlban nem található sütilista.', 'szesuti' ) );
		}

		$counts = array(
			'added'   => 0,
			'filled'  => 0,
			'skipped' => 0,
			'builtin' => 0,
			'invalid' => 0,
		);

		// Egyetlen adatbázis-írás a végén, nem bejegyzésenként.
		$before      = self::entries();
		$this->batch = true;

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				$counts['invalid']++;
				continue;
			}
			if ( empty( $item['match'] ) && ! empty( $item['name'] ) ) {
				$item['match'] = $item['name'];
			}
			$item_source = ! empty( $item['source'] ) ? sanitize_text_field( (string) $item['source'] ) : $source;
			$result      = $this->upsert( $item, $replace ? 'replace' : 'fill', $item_source );

			if ( 'added' === $result ) {
				$counts['added']++;
			} elseif ( 'filled' === $result || 'updated' === $result ) {
				$counts['filled']++;
			} elseif ( 'invalid' === $result ) {
				$counts['invalid']++;
			} elseif ( 'builtin' === $result ) {
				$counts['builtin']++;
			} else {
				$counts['skipped']++;
			}
		}

		$this->batch = false;
		if ( self::entries() !== $before ) {
			$this->store( self::entries() );
		}

		return $counts;
	}

	/* ---------------------------------------------------------------------
	 * Segédfüggvények
	 * ------------------------------------------------------------------ */

	/** Az adatmezők (a név nélkül). */
	public static function fields() {
		return array( 'provider', 'category', 'duration', 'type', 'desc_hu', 'desc_en' );
	}

	/**
	 * Egy bejegyzés tisztítása.
	 *
	 * @param array $raw Nyers adatok.
	 * @return array
	 */
	public static function normalize( array $raw ) {
		$match = isset( $raw['match'] ) ? $raw['match'] : ( isset( $raw['name'] ) ? $raw['name'] : '' );
		$match = trim( wp_strip_all_tags( (string) $match ) );
		if ( ! preg_match( '/^[A-Za-z0-9_\-\.\*\$!#%&+^|~:@]{1,100}$/', $match ) ) {
			$match = '';
		}

		$type = isset( $raw['type'] ) ? sanitize_key( (string) $raw['type'] ) : 'http';

		return array(
			'match'    => $match,
			'provider' => isset( $raw['provider'] ) ? substr( sanitize_text_field( (string) $raw['provider'] ), 0, 120 ) : '',
			'category' => ! empty( $raw['category'] ) ? sanitize_key( (string) $raw['category'] ) : 'necessary',
			'duration' => isset( $raw['duration'] ) ? substr( sanitize_text_field( (string) $raw['duration'] ), 0, 80 ) : '',
			'type'     => in_array( $type, array( 'http', 'html', 'pixel' ), true ) ? $type : 'http',
			'desc_hu'  => isset( $raw['desc_hu'] ) ? substr( sanitize_textarea_field( (string) $raw['desc_hu'] ), 0, 1000 ) : '',
			'desc_en'  => isset( $raw['desc_en'] ) ? substr( sanitize_textarea_field( (string) $raw['desc_en'] ), 0, 1000 ) : '',
			'source'   => isset( $raw['source'] ) ? substr( sanitize_text_field( (string) $raw['source'] ), 0, 120 ) : '',
			'added'    => isset( $raw['added'] ) ? (int) $raw['added'] : 0,
			'updated'  => isset( $raw['updated'] ) ? (int) $raw['updated'] : 0,
		);
	}

	/**
	 * Összehasonlító kulcs (kis- és nagybetűtől független).
	 *
	 * @param string $match Név vagy minta.
	 * @return string
	 */
	public static function key( $match ) {
		return strtolower( trim( (string) $match ) );
	}

	/**
	 * Illeszkedik-e egy sütinév egy * mintára.
	 *
	 * @param string $pattern Minta.
	 * @param string $name    Név.
	 * @return bool
	 */
	public static function matches( $pattern, $name ) {
		$regex = '/^' . str_replace( '\*', '.*', preg_quote( (string) $pattern, '/' ) ) . '$/i';
		return (bool) preg_match( $regex, (string) $name );
	}

	/** Az aktuális oldal hosztneve (a bejegyzések forrásához). */
	public static function this_site() {
		return (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
	}

	/**
	 * Mentés.
	 *
	 * @param array $entries Bejegyzések.
	 */
	private function store( array $entries ) {
		self::$cache = $entries;
		if ( ! $this->batch ) {
			update_option( self::OPTION, $entries, false );
		}
	}

	/** A memóriában tartott példány ürítése (teszteléshez, importhoz). */
	public static function flush() {
		self::$cache = null;
	}
}
