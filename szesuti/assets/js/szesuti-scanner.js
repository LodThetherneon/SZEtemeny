/**
 * SZetemény – teljes (böngészős) sütiszkenner.
 *
 * Az oldal lapjait kis, látható keretekben sorra megnyitja „szkennelő módban”
 * (ott nincs blokkolás és banner, mintha a látogató mindent elfogadott volna),
 * legörget az aljukig, hogy a lustán betöltődő beágyazások is elinduljanak,
 * követi a belső linkeket, és feljegyzi a megjelenő sütiket, böngészős
 * tárolókat és külső kiszolgálókat. A végén törli az általa létrehozott
 * sütiket és tárolókat, az eredményt pedig elmenti.
 */
( function () {
	'use strict';

	var C = window.SZESUTI_SCANNER;
	var root = document.querySelector( '[data-szesuti-crawler]' );

	if ( ! C || ! root || ! window.fetch || ! window.URL ) {
		return;
	}

	var T = C.i18n || {};

	/** Egyszerre ennyi keret dolgozik. */
	var WORKERS = 3;
	/** Egy oldal betöltésére legfeljebb ennyit várunk (ms). */
	var LOAD_TIMEOUT = 25000;
	/** Görgetés és a késleltetett kódok lefutására szánt idő (ms). */
	var SCROLL_STEPS = 4;
	var SCROLL_WAIT = 350;
	var SETTLE = 1500;
	/**
	 * A linkekből talált oldalakból útvonal-csoportonként (pl. /hirek/...) ennyi kerül
	 * előre; a többi egy „később” sorba, amit a bejárás akkor dolgoz fel, ha a többi
	 * oldal már megvolt és a keretből még futja. Így előbb a sokféle oldal jön sorra.
	 */
	var GROUP_CAP = 12;
	/** Biztonsági felső korlát a teljes futásidőre (ms). */
	var MAX_RUNTIME = 15 * 60 * 1000;
	/** Ennyi URL fejléceit nézi meg a szerver is (HttpOnly sütik), a bejárás után. */
	var HTTP_PROBE = 10;
	/** Ha az oldal már olvasható, de egy lassú külső elem miatt nincs „kész”, ennyi után is feldolgozzuk (ms). */
	var READY_GRACE = 8000;

	var FILE_RE = /\.(pdf|jpe?g|png|gif|webp|svg|avif|bmp|ico|zip|rar|7z|gz|tar|docx?|xlsx?|pptx?|odt|ods|odp|csv|txt|xml|json|mp3|mp4|m4a|mov|avi|wmv|webm|ogg|wav|ics|vcf|exe|msi|dmg|apk|epub)$/i;
	var SKIP_PATH_RE = /\/(wp-admin|wp-login\.php|wp-json|xmlrpc\.php|wp-content|wp-includes|wp-cron\.php|wp-signup\.php|feed|trackback)(\/|$)/i;
	// Soha nem nyitjuk meg: kijelentkeztet vagy műveletet indít.
	var DANGER_RE = /(logout|log-out|kijelentkez|customer-logout|action=|_wpnonce|nonce=|add-to-cart|remove_item|undo_item|unsubscribe|leiratkoz|delete|torles)/i;
	var ALLOWED_QUERY = [ 'p', 'page_id', 'cat', 'tag', 'lang', 'post_type', 'product_cat', 'paged' ];
	// A WordPress és az admin felület saját sütijei / tárolói – ezek nem a látogatóknak szólnak.
	var WP_COOKIE_RE = /^(wp-settings|wordpress_|wp_lang$|wp-saving-post|szesuti)/i;
	var WP_STORAGE_RE = /^(WP_DATA_USER_|wp-autosave|wp-|wp_|wpEmoji|mce|tinymce|szesuti)/i;

	var startBtn = root.querySelector( '[data-crawl-start]' );
	var stopBtn = root.querySelector( '[data-crawl-stop]' );
	var limitSel = root.querySelector( '[data-crawl-limit]' );
	var panel = root.querySelector( '[data-crawl-panel]' );
	var barEl = root.querySelector( '[data-crawl-bar]' );
	var countEl = root.querySelector( '[data-crawl-count]' );
	var timeEl = root.querySelector( '[data-crawl-time]' );
	var statsEl = root.querySelector( '[data-crawl-stats]' );
	var framesEl = root.querySelector( '[data-crawl-frames]' );
	var msgEl = root.querySelector( '[data-crawl-msg]' );

	var S = null; // A futó szkennelés állapota.

	function fmt( str ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		return String( str || '' )
			.replace( /%(\d)\$[ds]/g, function ( m, n ) {
				return args[ n - 1 ] !== undefined ? args[ n - 1 ] : '';
			} )
			.replace( /%[ds]/, function () {
				return args[ 0 ] !== undefined ? args[ 0 ] : '';
			} );
	}

	function sleep( ms ) {
		return new Promise( function ( resolve ) {
			setTimeout( resolve, ms );
		} );
	}

	function post( action, fields ) {
		var data = new FormData();
		data.append( 'action', action );
		data.append( 'nonce', C.nonce );
		Object.keys( fields || {} ).forEach( function ( key ) {
			var value = fields[ key ];
			if ( Array.isArray( value ) ) {
				value.forEach( function ( v ) {
					data.append( key + '[]', v );
				} );
			} else {
				data.append( key, value );
			}
		} );
		return window.fetch( C.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' } )
			.then( function ( r ) {
				return r.json().catch( function () {
					throw new Error( 'HTTP ' + r.status );
				} );
			} )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					throw new Error( ( json && json.data && json.data.message ) || 'hiba' );
				}
				return json.data;
			} );
	}

	function setMsg( text, isError ) {
		msgEl.textContent = text || '';
		msgEl.classList.toggle( 'is-error', !! isError );
	}

	/* ------------------------------------------------------------------
	 * URL-ek
	 * --------------------------------------------------------------- */

	/**
	 * Bejárható-e az URL, és ha igen, egységes alakja. Csak az admin felülettel
	 * azonos kiszolgálón lévő, tartalmi oldalakat nyitjuk meg.
	 */
	function normalize( href ) {
		var u;
		try {
			u = new URL( href, window.location.href );
		} catch ( e ) {
			return null;
		}
		if ( u.protocol !== 'http:' && u.protocol !== 'https:' ) {
			return null;
		}
		if ( u.hostname.toLowerCase() !== window.location.hostname.toLowerCase() ) {
			return null;
		}
		// A keret tartalmát csak azonos eredetből lehet kiolvasni.
		u.protocol = window.location.protocol;
		u.port = window.location.port;
		u.hash = '';

		if ( SKIP_PATH_RE.test( u.pathname ) || FILE_RE.test( u.pathname ) || DANGER_RE.test( u.pathname + u.search ) ) {
			return null;
		}

		var params = Array.from( u.searchParams.keys() );
		for ( var i = 0; i < params.length; i++ ) {
			if ( ALLOWED_QUERY.indexOf( params[ i ] ) === -1 ) {
				return null;
			}
		}
		u.searchParams.sort();

		return u.href;
	}

	/** Útvonal-csoport a túl sok hasonló oldal (pl. hírek) korlátozásához. */
	function groupKey( href ) {
		var url = new URL( href );
		var parts = url.pathname.split( '/' ).filter( Boolean );
		// „Egyszerű” permalinkek (?p=12, ?page_id=5): a paraméter neve a csoport.
		if ( ! parts.length && url.search ) {
			return '?' + Array.from( url.searchParams.keys() ).sort().join( '&' );
		}
		if ( parts.length && /^[a-z]{2}(-[a-z]{2})?$/i.test( parts[ 0 ] ) && parts.length > 1 ) {
			return parts[ 0 ] + '/' + parts[ 1 ]; // Nyelvi előtag (pl. /en/...).
		}
		return parts.length ? parts[ 0 ] : '/';
	}

	function enqueue( href, fromSeed ) {
		var n = normalize( href );
		if ( ! n || S.queued[ n ] ) {
			return;
		}
		S.queued[ n ] = true;
		S.queuedCount++;
		if ( ! fromSeed ) {
			var key = groupKey( n );
			S.groups[ key ] = ( S.groups[ key ] || 0 ) + 1;
			if ( S.groups[ key ] > GROUP_CAP ) {
				S.deferred.push( n );
				return;
			}
		}
		S.queue.push( n );
	}

	function frameUrl( href ) {
		var u = new URL( href );
		u.searchParams.set( S.config.frameParam, S.config.frameNonce );
		return u.href;
	}

	function isFirstParty( host ) {
		host = host.toLowerCase();
		var base = S.config.base;
		return host === window.location.hostname.toLowerCase() || host === S.config.host || ( base && ( host === base || host.slice( -( base.length + 1 ) ) === '.' + base ) );
	}

	function isSafe( host ) {
		return S.config.safe.some( function ( safe ) {
			return host === safe || host.slice( -( safe.length + 1 ) ) === '.' + safe;
		} );
	}

	/* ------------------------------------------------------------------
	 * Gyűjtés
	 * --------------------------------------------------------------- */

	function cookieNames( doc ) {
		var out = [];
		( doc.cookie || '' ).split( ';' ).forEach( function ( part ) {
			var name = part.split( '=' )[ 0 ].trim();
			if ( name ) {
				out.push( name );
			}
		} );
		return out;
	}

	function storageKeys( store ) {
		var out = [];
		try {
			for ( var i = 0; i < store.length; i++ ) {
				out.push( store.key( i ) );
			}
		} catch ( e ) {}
		return out;
	}

	function isIgnoredName( name, type ) {
		return S.config.ignore.indexOf( name ) !== -1 || ( 'html' === type ? WP_STORAGE_RE : WP_COOKIE_RE ).test( name );
	}

	function addFound( name, type, page ) {
		if ( ! name || isIgnoredName( name, type ) || S.found[ name ] ) {
			return;
		}
		S.found[ name ] = { name: name, type: type, page: page };
		S.foundCount++;
	}

	/** Szolgáltatás felismerése egy URL-ben vagy kódrészletben. */
	function matchServices( text ) {
		var hit = null;
		text = String( text ).toLowerCase();
		S.config.services.forEach( function ( svc ) {
			if ( S.services[ svc.id ] ) {
				return;
			}
			for ( var i = 0; i < svc.patterns.length; i++ ) {
				var p = String( svc.patterns[ i ] ).toLowerCase();
				if ( p && text.indexOf( p ) !== -1 ) {
					S.services[ svc.id ] = svc.patterns[ i ];
					S.serviceCount++;
					hit = svc.id;
					break;
				}
			}
		} );
		return hit;
	}

	function serviceFor( url ) {
		var lower = String( url ).toLowerCase();
		for ( var s = 0; s < S.config.services.length; s++ ) {
			var svc = S.config.services[ s ];
			for ( var i = 0; i < svc.patterns.length; i++ ) {
				var p = String( svc.patterns[ i ] ).toLowerCase();
				if ( p && lower.indexOf( p ) !== -1 ) {
					if ( ! S.services[ svc.id ] ) {
						S.services[ svc.id ] = svc.patterns[ i ];
						S.serviceCount++;
					}
					return svc.id;
				}
			}
		}
		return null;
	}

	function collect( doc, win, page ) {
		// 1. Sütik (a JavaScriptből olvashatók; a HttpOnly-kat a szerver nézi meg).
		// A már korábban meglévőket is jelentjük (lehet, hogy az admin egyszer már
		// elfogadta a sütiket az oldalon), de a végén csak az újonnan létrejötteket töröljük.
		cookieNames( doc ).forEach( function ( name ) {
			if ( ! S.baseCookies[ name ] ) {
				S.created.cookies[ name ] = true;
			}
			addFound( name, 'http', page );
		} );

		// 2. Böngészős tárolók.
		try {
			storageKeys( win.localStorage ).forEach( function ( key ) {
				if ( ! S.baseLocal[ key ] ) {
					S.created.local[ key ] = true;
				}
				addFound( key, 'html', page );
			} );
			storageKeys( win.sessionStorage ).forEach( function ( key ) {
				if ( ! S.baseSession[ key ] ) {
					S.created.session[ key ] = true;
				}
				addFound( key, 'html', page );
			} );
		} catch ( e ) {}

		// 3. Betöltött külső erőforrások és beágyazások.
		var urls = [];
		try {
			win.performance.getEntriesByType( 'resource' ).forEach( function ( entry ) {
				urls.push( entry.name );
			} );
		} catch ( e ) {}
		Array.prototype.forEach.call( doc.querySelectorAll( 'script[src],iframe[src],img[src],link[href],source[src],video[src],audio[src],embed[src],object[data]' ), function ( el ) {
			urls.push( el.src || el.href || el.getAttribute( 'data' ) || '' );
		} );

		urls.forEach( function ( href ) {
			var u;
			try {
				u = new URL( href, page );
			} catch ( e ) {
				return;
			}
			if ( u.protocol !== 'http:' && u.protocol !== 'https:' ) {
				return;
			}
			var host = u.hostname.toLowerCase();
			var svc = serviceFor( u.href );
			if ( svc ) {
				// Egy felismert szolgáltatás kiszolgálója nem „ismeretlen”, akkor sem, ha
				// egy-egy kérése (pl. egy kép) egyik mintára sem illeszkedik.
				if ( S.hosts[ host ] && ! S.serviceHosts[ host ] ) {
					S.hostCount--;
				}
				S.serviceHosts[ host ] = true;
			} else if ( ! S.serviceHosts[ host ] && ! isFirstParty( host ) && ! isSafe( host ) && ! S.hosts[ host ] ) {
				S.hosts[ host ] = true;
				S.hostCount++;
			}
		} );

		// 4. Beágyazott (inline) kódok mintái, pl. fbq(, _paq.push.
		try {
			matchServices( doc.documentElement.outerHTML.slice( 0, 1500000 ) );
		} catch ( e ) {}
	}

	/* ------------------------------------------------------------------
	 * Keretek
	 * --------------------------------------------------------------- */

	function makeSlot( index ) {
		var wrap = document.createElement( 'div' );
		wrap.className = 'szesuti-crawler__slot';

		var label = document.createElement( 'div' );
		label.className = 'szesuti-crawler__slot-label';
		label.textContent = '…';

		var viewport = document.createElement( 'div' );
		viewport.className = 'szesuti-crawler__viewport';

		var frame = document.createElement( 'iframe' );
		frame.className = 'szesuti-crawler__frame';
		frame.title = 'Szkenner ' + ( index + 1 );
		frame.setAttribute( 'tabindex', '-1' );
		frame.setAttribute( 'aria-hidden', 'true' );
		// Szkriptek futhatnak, de az oldal nem nyithat felugró ablakot, nem
		// jeleníthet meg figyelmeztetést, és nem navigálhatja el az admin oldalt.
		frame.setAttribute( 'sandbox', 'allow-scripts allow-same-origin' );
		frame.setAttribute( 'allow', "autoplay 'none'; camera 'none'; microphone 'none'; geolocation 'none'; fullscreen 'none'" );
		frame.src = 'about:blank';

		viewport.appendChild( frame );
		wrap.appendChild( label );
		wrap.appendChild( viewport );
		framesEl.appendChild( wrap );

		return { frame: frame, label: label };
	}

	/** A keretben már a kért oldal van, és legalább a HTML feldolgozása kész? */
	function frameReady( slot, target ) {
		try {
			var doc = slot.frame.contentDocument;
			return !! doc && doc.location.href === target && 'loading' !== doc.readyState;
		} catch ( e ) {
			return false;
		}
	}

	function load( slot, href ) {
		var target = frameUrl( href );
		return new Promise( function ( resolve, reject ) {
			var done = false;
			var started = Date.now();

			function finish( ok ) {
				if ( done ) {
					return;
				}
				done = true;
				clearInterval( poll );
				slot.frame.onload = null;
				if ( ok ) {
					resolve();
				} else {
					reject( new Error( 'timeout' ) );
				}
			}

			// Egy lassú vagy elérhetetlen külső kiszolgáló (pl. nem létező domain) sokáig
			// visszatarthatja a load eseményt – ha az oldal már olvasható, nem várunk rá tovább.
			var poll = setInterval( function () {
				var waited = Date.now() - started;
				if ( waited > READY_GRACE && frameReady( slot, target ) ) {
					finish( true );
				} else if ( waited > LOAD_TIMEOUT ) {
					finish( frameReady( slot, target ) );
				}
			}, 250 );

			slot.frame.onload = function () {
				// A keret induló üres oldalának betöltése még nem az, amire várunk.
				try {
					if ( 'about:blank' === slot.frame.contentWindow.location.href ) {
						return;
					}
				} catch ( e ) {}
				finish( true );
			};
			slot.frame.src = target;
		} );
	}

	async function scanPage( slot, href ) {
		var u = new URL( href );
		var path = u.pathname + u.search;
		slot.label.textContent = path;

		try {
			await load( slot, href );
		} catch ( e ) {
			S.errors.push( path + ' – ' + T.timeout );
			return;
		}

		var doc;
		var win;
		try {
			doc = slot.frame.contentDocument;
			win = slot.frame.contentWindow;
			if ( ! doc || ! doc.documentElement || 'about:blank' === doc.location.href ) {
				throw new Error( 'blank' );
			}
		} catch ( e ) {
			S.errors.push( path + ' – ' + T.frameError );
			S.frameErrors++;
			return;
		}

		if ( ! doc.querySelector( 'meta[name="szesuti-scan"]' ) ) {
			S.scanModeFailed++;
		}

		// Legörgetés: így a lustán betöltődő beágyazások (videó, térkép, chat) is elindulnak.
		for ( var i = 1; i <= SCROLL_STEPS; i++ ) {
			try {
				win.scrollTo( 0, Math.round( doc.documentElement.scrollHeight * i / SCROLL_STEPS ) );
			} catch ( e ) {}
			await sleep( SCROLL_WAIT );
		}
		await sleep( SETTLE );

		try {
			collect( doc, win, href );
			S.done.push( href );
			if ( S.httpQueue.length < HTTP_PROBE ) {
				S.httpQueue.push( href );
			}

			// Belső linkek követése – ez a „végigkattintás”.
			Array.prototype.forEach.call( doc.querySelectorAll( 'a[href]' ), function ( a ) {
				if ( ! a.hasAttribute( 'download' ) ) {
					enqueue( a.getAttribute( 'href' ), false );
				}
			} );
		} catch ( e ) {
			S.errors.push( path + ' – ' + e.message );
		}
	}

	async function worker( slot ) {
		while ( ! S.stopped ) {
			if ( Date.now() - S.start > MAX_RUNTIME ) {
				S.stopped = true;
				break;
			}
			if ( S.done.length + S.inFlight >= S.config.limit ) {
				break;
			}
			var next = S.queue.shift() || S.deferred.shift();
			if ( ! next ) {
				if ( 0 === S.inFlight ) {
					break; // Nincs több oldal, és senki sem talál újat.
				}
				await sleep( 300 );
				continue;
			}
			S.inFlight++;
			await scanPage( slot, next );
			S.inFlight--;
			render();
		}
		slot.label.textContent = '✓';
		try {
			slot.frame.src = 'about:blank';
		} catch ( e ) {}
	}

	/**
	 * A szerver fejléc-vizsgálata (HttpOnly sütik). A bejárás UTÁN fut, hogy ne
	 * versenyezzen a keretekkel ugyanazért a kiszolgálóért; ha a szerver nem éri
	 * el önmagát (sok tárhelyen tiltott), az első hibánál abbahagyja.
	 */
	async function httpProbe() {
		var urls = S.httpQueue.slice( 0, HTTP_PROBE );
		for ( var i = 0; i < urls.length; i += 5 ) {
			try {
				var res = await post( 'szesuti_scan_http', { urls: urls.slice( i, i + 5 ) } );
				( res.cookies || [] ).forEach( function ( name ) {
					S.http[ name ] = true;
				} );
				Object.keys( res.services || {} ).forEach( function ( id ) {
					if ( ! S.services[ id ] ) {
						S.services[ id ] = res.services[ id ];
						S.serviceCount++;
					}
				} );
				if ( res.errors && res.errors.length ) {
					S.errors.push( T.httpProbe + ' ' + res.errors[ 0 ] );
					break;
				}
			} catch ( e ) {
				break;
			}
		}
	}

	/* ------------------------------------------------------------------
	 * Takarítás: a szkennelés közben létrejött sütik és tárolók törlése
	 * --------------------------------------------------------------- */

	function cleanup() {
		var host = window.location.hostname;
		var domains = [ '', host, '.' + host ];
		if ( S.config.base && S.config.base !== host ) {
			domains.push( '.' + S.config.base );
		}
		var paths = [ '/' ];
		S.done.slice( 0, 30 ).forEach( function ( href ) {
			var p = new URL( href ).pathname;
			if ( paths.indexOf( p ) === -1 ) {
				paths.push( p );
			}
		} );

		Object.keys( S.created.cookies ).forEach( function ( name ) {
			domains.forEach( function ( domain ) {
				paths.forEach( function ( path ) {
					document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; max-age=0; path=' + path + ( domain ? '; domain=' + domain : '' );
				} );
			} );
		} );
		Object.keys( S.created.local ).forEach( function ( key ) {
			try {
				window.localStorage.removeItem( key );
			} catch ( e ) {}
		} );
		Object.keys( S.created.session ).forEach( function ( key ) {
			try {
				window.sessionStorage.removeItem( key );
			} catch ( e ) {}
		} );
	}

	/* ------------------------------------------------------------------
	 * Felület
	 * --------------------------------------------------------------- */

	function clock( ms ) {
		var s = Math.floor( ms / 1000 );
		return Math.floor( s / 60 ) + ':' + ( '0' + ( s % 60 ) ).slice( -2 );
	}

	function render() {
		if ( ! S ) {
			return;
		}
		var total = Math.max( 1, Math.min( S.config.limit, S.queuedCount ) );
		var done = Math.min( S.done.length + S.errors.length, total );
		barEl.style.width = Math.min( 100, Math.round( ( S.done.length / total ) * 100 ) ) + '%';
		countEl.textContent = fmt( T.progress, S.done.length, total );
		timeEl.textContent = clock( Date.now() - S.start );
		statsEl.textContent = fmt( T.found, S.foundCount, S.serviceCount, S.hostCount );
		root.setAttribute( 'data-crawl-done', String( done ) );
	}

	function onLeave( event ) {
		if ( S && ! S.finished ) {
			event.preventDefault();
			event.returnValue = T.leave;
			return T.leave;
		}
		return undefined;
	}

	async function run() {
		var limit = parseInt( limitSel ? limitSel.value : '150', 10 ) || 150;

		startBtn.disabled = true;
		if ( limitSel ) {
			limitSel.disabled = true;
		}
		panel.hidden = false;
		stopBtn.hidden = false;
		setMsg( T.starting );

		var config;
		try {
			config = await post( 'szesuti_scan_start', { limit: limit } );
		} catch ( e ) {
			setMsg( fmt( T.failed, e.message ), true );
			startBtn.disabled = false;
			return;
		}

		S = {
			config: config,
			start: Date.now(),
			queue: [],
			deferred: [],
			queued: {},
			queuedCount: 0,
			groups: {},
			done: [],
			inFlight: 0,
			errors: [],
			found: {},
			foundCount: 0,
			services: {},
			serviceCount: 0,
			hosts: {},
			serviceHosts: {},
			hostCount: 0,
			http: {},
			httpQueue: [],
			frameErrors: 0,
			scanModeFailed: 0,
			stopped: false,
			finished: false,
			baseCookies: {},
			baseLocal: {},
			baseSession: {},
			created: { cookies: {}, local: {}, session: {} }
		};

		// Kiindulási állapot: az admin saját sütijei és tárolói nem számítanak találatnak.
		cookieNames( document ).forEach( function ( n ) {
			S.baseCookies[ n ] = true;
		} );
		storageKeys( window.localStorage ).forEach( function ( k ) {
			S.baseLocal[ k ] = true;
		} );
		storageKeys( window.sessionStorage ).forEach( function ( k ) {
			S.baseSession[ k ] = true;
		} );

		config.seeds.forEach( function ( href ) {
			enqueue( href, true );
		} );

		if ( ! S.queuedCount ) {
			setMsg( fmt( T.failed, T.noPages ), true );
			startBtn.disabled = false;
			if ( limitSel ) {
				limitSel.disabled = false;
			}
			stopBtn.hidden = true;
			S = null;
			return;
		}

		// A keretek legyenek a képernyőn: a látható oldalon töltődnek be a lusta beágyazások is.
		panel.scrollIntoView( { block: 'center', behavior: 'smooth' } );

		window.addEventListener( 'beforeunload', onLeave );
		setMsg( '' );
		render();
		var ticker = setInterval( render, 1000 );

		framesEl.innerHTML = '';
		var workers = [];
		for ( var i = 0; i < WORKERS; i++ ) {
			workers.push( worker( makeSlot( i ) ) );
		}
		await Promise.all( workers );

		// Utolsó söprés: egyes mérőkódok (pl. Clarity) csak késve írják ki a sütijüket.
		var lastPage = S.done.length ? S.done[ S.done.length - 1 ] : window.location.href;
		cookieNames( document ).forEach( function ( name ) {
			if ( ! S.baseCookies[ name ] ) {
				S.created.cookies[ name ] = true;
				addFound( name, 'http', lastPage );
			}
		} );
		storageKeys( window.localStorage ).forEach( function ( key ) {
			if ( ! S.baseLocal[ key ] ) {
				S.created.local[ key ] = true;
				addFound( key, 'html', lastPage );
			}
		} );

		setMsg( T.headers );
		await httpProbe();
		S.finished = true;
		clearInterval( ticker );
		render();

		cleanup();
		stopBtn.hidden = true;
		setMsg( T.saving );

		var found = Object.keys( S.found ).map( function ( k ) {
			return S.found[ k ];
		} );

		if ( ! S.done.length && S.frameErrors ) {
			S.errors.unshift( T.frameError );
		}

		try {
			await post( 'szesuti_scan_finish', {
				result: JSON.stringify( {
					urls: S.done,
					cookies: found,
					http: Object.keys( S.http ),
					services: S.services,
					hosts: Object.keys( S.hosts ).filter( function ( h ) {
						return ! S.serviceHosts[ h ];
					} ),
					errors: S.errors.slice( 0, 50 ),
					duration: Math.round( ( Date.now() - S.start ) / 1000 ),
					stopped: S.stopped ? 1 : 0,
					scanModeFailed: S.scanModeFailed,
					frameErrors: S.frameErrors
				} )
			} );
		} catch ( e ) {
			setMsg( fmt( T.failed, e.message ), true );
			startBtn.disabled = false;
			window.removeEventListener( 'beforeunload', onLeave );
			return;
		}

		window.removeEventListener( 'beforeunload', onLeave );
		window.location.href = C.resultUrl;
	}

	startBtn.addEventListener( 'click', function () {
		run();
	} );

	stopBtn.addEventListener( 'click', function () {
		if ( S ) {
			S.stopped = true;
			stopBtn.disabled = true;
			setMsg( T.stopping );
		}
	} );
} )();
