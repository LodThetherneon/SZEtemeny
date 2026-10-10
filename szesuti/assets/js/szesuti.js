/**
 * SZetemény – sütikezelő frontend.
 *
 * Nincs külső függősége, IE11 alatt is elindul (nem tör el, csak nem szép).
 */
( function () {
	'use strict';

	var CFG = window.SZESUTI_CONFIG || null;
	if ( ! CFG ) {
		return;
	}

	var COOKIE_VERSION = 1;
	var root = null;
	var banner = null;
	var modal = null;
	var overlay = null;
	var reopen = null;
	var lastFocus = null;
	var observer = null;

	// Előnézeti mód (admin): a döntést csak memóriában tartjuk – nincs süti,
	// nincs naplózás, nincs újratöltés. A sandboxolt iframe-ben a document.cookie
	// elérése egyébként is hibát dobna.
	var PREVIEW = !! CFG.preview;
	var previewConsent = null;

	/* ------------------------------------------------------------------
	 * Segédfüggvények
	 * --------------------------------------------------------------- */

	function log() {
		if ( ! CFG.debug || ! window.console ) {
			return;
		}
		var args = Array.prototype.slice.call( arguments );
		args.unshift( '[SZetemény]' );
		window.console.log.apply( window.console, args );
	}

	function qs( selector, context ) {
		return ( context || document ).querySelector( selector );
	}

	function qsa( selector, context ) {
		return Array.prototype.slice.call( ( context || document ).querySelectorAll( selector ) );
	}

	function uuid() {
		try {
			if ( window.crypto && window.crypto.randomUUID ) {
				return window.crypto.randomUUID();
			}
			if ( window.crypto && window.crypto.getRandomValues ) {
				var buf = new Uint8Array( 16 );
				window.crypto.getRandomValues( buf );
				buf[ 6 ] = ( buf[ 6 ] & 0x0f ) | 0x40;
				buf[ 8 ] = ( buf[ 8 ] & 0x3f ) | 0x80;
				var hex = [];
				for ( var i = 0; i < buf.length; i++ ) {
					hex.push( ( buf[ i ] + 0x100 ).toString( 16 ).substr( 1 ) );
				}
				return (
					hex.slice( 0, 4 ).join( '' ) + '-' +
					hex.slice( 4, 6 ).join( '' ) + '-' +
					hex.slice( 6, 8 ).join( '' ) + '-' +
					hex.slice( 8, 10 ).join( '' ) + '-' +
					hex.slice( 10, 16 ).join( '' )
				);
			}
		} catch ( e ) {}
		return 'sz-' + Date.now().toString( 36 ) + '-' + Math.random().toString( 36 ).slice( 2, 10 );
	}

	function lockedCategories() {
		var out = [];
		for ( var i = 0; i < CFG.categories.length; i++ ) {
			if ( CFG.categories[ i ].locked ) {
				out.push( CFG.categories[ i ].slug );
			}
		}
		return out;
	}

	function allCategories() {
		var out = [];
		for ( var i = 0; i < CFG.categories.length; i++ ) {
			out.push( CFG.categories[ i ].slug );
		}
		return out;
	}

	function defaultCategories() {
		var out = [];
		for ( var i = 0; i < CFG.categories.length; i++ ) {
			if ( CFG.categories[ i ].locked || CFG.categories[ i ].default ) {
				out.push( CFG.categories[ i ].slug );
			}
		}
		return out;
	}

	/* ------------------------------------------------------------------
	 * Süti kezelés
	 * --------------------------------------------------------------- */

	function readRawCookie( name ) {
		var parts = document.cookie ? document.cookie.split( ';' ) : [];
		for ( var i = 0; i < parts.length; i++ ) {
			var part = parts[ i ].replace( /^\s+/, '' );
			if ( part.indexOf( name + '=' ) === 0 ) {
				return part.substring( name.length + 1 );
			}
		}
		return null;
	}

	function readConsent() {
		if ( PREVIEW ) {
			return previewConsent;
		}

		var raw = readRawCookie( CFG.cookieName );
		if ( ! raw ) {
			return null;
		}

		var data;
		try {
			data = JSON.parse( decodeURIComponent( raw ) );
		} catch ( e ) {
			return null;
		}

		if ( ! data || ! data.cats || ! data.cats.length ) {
			return null;
		}

		// Lejárt?
		if ( CFG.expiryDays > 0 && data.t ) {
			var ageDays = ( Date.now() / 1000 - data.t ) / 86400;
			if ( ageDays > CFG.expiryDays ) {
				return null;
			}
		}

		// Megváltozott a szabályzat verziója?
		if ( CFG.policyVersion && String( data.pv ) !== String( CFG.policyVersion ) ) {
			return null;
		}

		return data;
	}

	function writeConsent( categories, action ) {
		var existing = readConsent();
		var payload = {
			v: COOKIE_VERSION,
			pv: String( CFG.policyVersion ),
			t: Math.floor( Date.now() / 1000 ),
			id: existing && existing.id ? existing.id : uuid(),
			act: action,
			cats: categories
		};

		if ( PREVIEW ) {
			previewConsent = payload;
			return payload;
		}

		var value = encodeURIComponent( JSON.stringify( payload ) );
		var maxAge = Math.max( 1, CFG.expiryDays ) * 86400;
		var cookie = CFG.cookieName + '=' + value + ';path=/;max-age=' + maxAge + ';SameSite=Lax';

		if ( CFG.cookieDomain ) {
			cookie += ';domain=' + CFG.cookieDomain;
		}
		if ( location.protocol === 'https:' ) {
			cookie += ';Secure';
		}

		document.cookie = cookie;
		log( 'consent mentve', payload );

		return payload;
	}

	function currentCategories() {
		var data = readConsent();
		return data ? data.cats : defaultCategories();
	}

	function hasConsent( category ) {
		if ( lockedCategories().indexOf( category ) !== -1 ) {
			return true;
		}
		var data = readConsent();
		return !! data && data.cats.indexOf( category ) !== -1;
	}

	/* ------------------------------------------------------------------
	 * Blokkolt elemek feloldása
	 * --------------------------------------------------------------- */

	function copyAttributes( from, to ) {
		var attrs = from.attributes;
		for ( var i = 0; i < attrs.length; i++ ) {
			var name = attrs[ i ].name;
			if ( name === 'type' || name.indexOf( 'data-szesuti' ) === 0 ) {
				continue;
			}
			try {
				to.setAttribute( name, attrs[ i ].value );
			} catch ( e ) {}
		}
	}

	/**
	 * Külső szkriptek sorban töltése, hogy a függőségi sorrend megmaradjon.
	 *
	 * A külső szkriptek betöltésére várunk, mert egy beágyazott kód gyakran
	 * az előtte lévő fájlra épül. Hogy egy be nem töltődő (pl. reklámblokkolt
	 * vagy hálózati hibás) szkript ne akassza meg véglegesen a sort, minden
	 * várakozásra időkorlátot teszünk.
	 */
	function runScriptQueue( queue, done ) {
		if ( ! queue.length ) {
			if ( done ) {
				done();
			}
			return;
		}

		var node = queue.shift();
		var src = node.getAttribute( 'data-szesuti-src' );
		var fresh = document.createElement( 'script' );

		copyAttributes( node, fresh );

		if ( src ) {
			var advanced = false;
			var advance = function ( reason ) {
				if ( advanced ) {
					return;
				}
				advanced = true;
				if ( reason ) {
					log( reason, src );
				}
				runScriptQueue( queue, done );
			};

			fresh.src = src;
			fresh.onload = function () {
				advance();
			};
			fresh.onerror = function () {
				advance( 'szkript betöltése sikertelen:' );
			};

			// Biztonsági háló: ha sem onload, sem onerror nem jön, továbblépünk.
			window.setTimeout( function () {
				advance( 'szkript időtúllépés, továbblépünk:' );
			}, CFG.scriptTimeout || 3000 );
		} else {
			fresh.text = node.textContent || node.innerText || '';
		}

		node.setAttribute( 'data-szesuti-released', '1' );

		if ( node.parentNode ) {
			node.parentNode.insertBefore( fresh, node );
			node.parentNode.removeChild( node );
		} else {
			document.head.appendChild( fresh );
		}

		// A beágyazott kód azonnal lefut, mehet a következő.
		if ( ! src ) {
			runScriptQueue( queue, done );
		}
	}

	function releaseScripts( granted ) {
		var nodes = qsa( 'script[type="text/plain"][data-szesuti]:not([data-szesuti-released])' );
		var queue = [];

		for ( var i = 0; i < nodes.length; i++ ) {
			var cat = nodes[ i ].getAttribute( 'data-szesuti' );
			if ( granted.indexOf( cat ) !== -1 ) {
				queue.push( nodes[ i ] );
			}
		}

		if ( queue.length ) {
			log( 'feloldott szkriptek:', queue.length );
			runScriptQueue( queue );
		}
	}

	function releaseIframes( granted ) {
		var nodes = qsa( 'iframe[data-szesuti][data-szesuti-src]:not([data-szesuti-released])' );

		for ( var i = 0; i < nodes.length; i++ ) {
			var node = nodes[ i ];
			var cat = node.getAttribute( 'data-szesuti' );
			if ( granted.indexOf( cat ) === -1 ) {
				continue;
			}

			// A helyőrzőt teljesen eltávolítjuk, és az iframe visszakerül az eredeti
			// helyére. Ha a doboz ottmaradna, a téma abszolút pozicionált iframe-jei
			// (16:9-es keret, `height:100%`, levágott fejléc) a 0 magas dobozhoz
			// igazodnának, és láthatatlanok lennének. Mozgatni a src beállítása
			// ELŐTT kell, mert a DOM-ban mozgatott iframe újratöltődik.
			var wrap = node.parentNode;
			if ( wrap && wrap.className && ( ' ' + wrap.className + ' ' ).indexOf( ' szesuti-placeholder ' ) !== -1 && wrap.parentNode ) {
				wrap.parentNode.insertBefore( node, wrap );
				wrap.parentNode.removeChild( wrap );
			}

			node.setAttribute( 'src', node.getAttribute( 'data-szesuti-src' ) );
			node.removeAttribute( 'data-szesuti-src' );
			node.setAttribute( 'data-szesuti-released', '1' );
		}
	}

	/**
	 * Ha a téma az iframe-et abszolút pozícióval méretezi (pl. 16:9-es keret,
	 * `height:100%`), a helyőrző is töltse ki ugyanazt a keretet – különben a
	 * keret üres marad, a helyőrző pedig alá csúszik vagy kilóg belőle.
	 */
	function fitPlaceholders() {
		if ( ! window.getComputedStyle ) {
			return;
		}
		var nodes = qsa( '.szesuti-placeholder:not([data-szesuti-fit]) > iframe' );

		for ( var i = 0; i < nodes.length; i++ ) {
			var wrap = nodes[ i ].parentNode;
			var pos = '';
			try {
				pos = window.getComputedStyle( nodes[ i ] ).position;
			} catch ( e ) {}
			wrap.setAttribute( 'data-szesuti-fit', 'absolute' === pos || 'fixed' === pos ? 'fill' : 'flow' );
		}
	}

	function releaseLinks( granted ) {
		var nodes = qsa( 'link[data-szesuti][data-szesuti-href]:not([data-szesuti-released])' );

		for ( var i = 0; i < nodes.length; i++ ) {
			var node = nodes[ i ];
			var cat = node.getAttribute( 'data-szesuti' );
			if ( granted.indexOf( cat ) === -1 ) {
				continue;
			}
			node.setAttribute( 'href', node.getAttribute( 'data-szesuti-href' ) );
			node.removeAttribute( 'data-szesuti-href' );
			node.setAttribute( 'data-szesuti-released', '1' );
		}
	}

	function releaseImages( granted ) {
		var nodes = qsa( 'img[data-szesuti][data-szesuti-src]:not([data-szesuti-released])' );

		for ( var i = 0; i < nodes.length; i++ ) {
			var node = nodes[ i ];
			if ( granted.indexOf( node.getAttribute( 'data-szesuti' ) ) === -1 ) {
				continue;
			}
			var srcset = node.getAttribute( 'data-szesuti-srcset' );
			if ( srcset ) {
				node.setAttribute( 'srcset', srcset );
			}
			node.setAttribute( 'src', node.getAttribute( 'data-szesuti-src' ) );
			node.removeAttribute( 'data-szesuti-src' );
			node.setAttribute( 'data-szesuti-released', '1' );
		}
	}

	/** A futásidejű őr értesítése – mindig a feloldás ELŐTT, különben az őr újra blokkolna. */
	function syncGuard( granted ) {
		try {
			if ( window.__szesutiGuard && ! PREVIEW ) {
				window.__szesutiGuard.update( granted );
			}
		} catch ( e ) {}
	}

	function applyBlocked() {
		var granted = currentCategories().concat( lockedCategories() );
		syncGuard( granted );
		releaseLinks( granted );
		releaseImages( granted );
		releaseIframes( granted );
		fitPlaceholders();
		releaseScripts( granted );
	}

	/** Később, AJAX-szal beszúrt tartalom figyelése. */
	function startObserver() {
		if ( observer || ! window.MutationObserver ) {
			return;
		}

		observer = new window.MutationObserver( function ( mutations ) {
			for ( var i = 0; i < mutations.length; i++ ) {
				if ( mutations[ i ].addedNodes && mutations[ i ].addedNodes.length ) {
					applyBlocked();
					return;
				}
			}
		} );

		observer.observe( document.documentElement, { childList: true, subtree: true } );
	}

	/* ------------------------------------------------------------------
	 * Google Consent Mode
	 * --------------------------------------------------------------- */

	/**
	 * A gtag() a natív `arguments` objektumot várja a dataLayerben,
	 * ezért így kell pusholni – tömbbel a GTM nem érti meg.
	 */
	function makeGtag( dlName ) {
		window[ dlName ] = window[ dlName ] || [];
		return function () {
			window[ dlName ].push( arguments );
		};
	}

	function pushConsentMode( categories ) {
		if ( ! CFG.gcm || ! CFG.gcm.enabled ) {
			return;
		}

		var dlName = CFG.gcm.datalayer || 'dataLayer';
		var gtag = makeGtag( dlName );
		var map = CFG.gcm.map || {};
		var state = {};

		for ( var signal in map ) {
			if ( ! Object.prototype.hasOwnProperty.call( map, signal ) ) {
				continue;
			}
			var cat = map[ signal ];
			state[ signal ] = ( ! cat || categories.indexOf( cat ) !== -1 ) ? 'granted' : 'denied';
		}

		gtag( 'consent', 'update', state );

		if ( CFG.gcm.pushEvents ) {
			window[ dlName ].push( {
				event: 'szesuti_consent_update',
				szesuti_consent: categories.join( ',' ),
				szesuti_has_choice: true
			} );
		}

		log( 'Consent Mode update', state );
	}

	/* ------------------------------------------------------------------
	 * Naplózás
	 * --------------------------------------------------------------- */

	function sendLog( payload ) {
		if ( PREVIEW || ! CFG.log || ! CFG.log.enabled || ! CFG.rest || ! CFG.rest.consent ) {
			return;
		}

		try {
			var body = JSON.stringify( payload );

			if ( navigator.sendBeacon ) {
				var blob = new Blob( [ body ], { type: 'application/json' } );
				if ( navigator.sendBeacon( CFG.rest.consent, blob ) ) {
					return;
				}
			}

			if ( window.fetch ) {
				window.fetch( CFG.rest.consent, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: body,
					keepalive: true,
					credentials: 'omit'
				} ).catch( function () {} );
			}
		} catch ( e ) {
			log( 'naplózás sikertelen', e );
		}
	}

	/* ------------------------------------------------------------------
	 * Megjelenítés
	 * --------------------------------------------------------------- */

	function showRoot() {
		if ( root ) {
			root.hidden = false;
		}
	}

	function showBanner() {
		if ( ! banner ) {
			return;
		}
		showRoot();
		hideModal();
		banner.hidden = false;
		root.classList.add( 'is-open' );

		if ( overlay && needsOverlay() ) {
			overlay.hidden = false;
		}

		window.setTimeout( function () {
			root.classList.add( 'is-visible' );
		}, 10 );
	}

	function hideBanner() {
		if ( ! banner ) {
			return;
		}
		banner.hidden = true;
		root.classList.remove( 'is-open' );
		root.classList.remove( 'is-visible' );
		if ( overlay ) {
			overlay.hidden = true;
		}
	}

	function needsOverlay() {
		return !! overlay;
	}

	function showModal() {
		if ( ! modal ) {
			return;
		}

		lastFocus = document.activeElement;
		showRoot();
		hideBanner();

		modal.hidden = false;
		root.classList.add( 'is-modal-open' );
		document.documentElement.classList.add( 'szesuti-no-scroll' );

		syncToggles( currentCategories() );

		var focusable = getFocusable();
		// Előnézetben nem veszünk át fókuszt, különben elvenné a kurzort az admin mezőiből.
		if ( focusable.length && ! PREVIEW ) {
			focusable[ 0 ].focus();
		}
	}

	function hideModal() {
		if ( ! modal || modal.hidden ) {
			return;
		}
		modal.hidden = true;
		root.classList.remove( 'is-modal-open' );
		document.documentElement.classList.remove( 'szesuti-no-scroll' );

		if ( lastFocus && lastFocus.focus && ! PREVIEW ) {
			try {
				lastFocus.focus();
			} catch ( e ) {}
		}
	}

	function showReopen() {
		if ( reopen ) {
			showRoot();
			reopen.hidden = false;
		}
	}

	function getFocusable() {
		if ( ! modal ) {
			return [];
		}
		return qsa( 'button:not([disabled]), a[href], input, select, textarea, [tabindex]:not([tabindex="-1"])', modal ).filter( function ( el ) {
			return el.offsetParent !== null;
		} );
	}

	function syncToggles( categories ) {
		var toggles = qsa( '[data-szesuti-toggle]', root );
		for ( var i = 0; i < toggles.length; i++ ) {
			var slug = toggles[ i ].getAttribute( 'data-szesuti-toggle' );
			toggles[ i ].setAttribute( 'aria-checked', categories.indexOf( slug ) !== -1 ? 'true' : 'false' );
		}
	}

	function selectedCategories() {
		var out = lockedCategories().slice();
		var toggles = qsa( '[data-szesuti-toggle]', root );

		for ( var i = 0; i < toggles.length; i++ ) {
			if ( toggles[ i ].getAttribute( 'aria-checked' ) === 'true' ) {
				var slug = toggles[ i ].getAttribute( 'data-szesuti-toggle' );
				if ( out.indexOf( slug ) === -1 ) {
					out.push( slug );
				}
			}
		}

		return out;
	}

	/* ------------------------------------------------------------------
	 * Döntés feldolgozása
	 * --------------------------------------------------------------- */

	function commit( categories, action, options ) {
		options = options || {};

		var previous = readConsent();
		var unique = [];
		var locked = lockedCategories();
		var all = locked.concat( categories );

		for ( var i = 0; i < all.length; i++ ) {
			if ( unique.indexOf( all[ i ] ) === -1 && allCategories().indexOf( all[ i ] ) !== -1 ) {
				unique.push( all[ i ] );
			}
		}

		// Visszavont kategóriák: amiket korábban engedélyezett, most viszont nem.
		var revoked = [];
		if ( previous ) {
			for ( var j = 0; j < previous.cats.length; j++ ) {
				if ( unique.indexOf( previous.cats[ j ] ) === -1 ) {
					revoked.push( previous.cats[ j ] );
				}
			}
		}

		var payload = writeConsent( unique, action );

		pushConsentMode( unique );
		applyBlocked();

		if ( ! options.silent ) {
			sendLog( {
				consent_id: payload.id,
				action: action,
				categories: unique,
				policy_version: payload.pv,
				lang: CFG.lang,
				url: location.href
			} );
		}

		hideBanner();
		hideModal();
		showReopen();

		try {
			document.dispatchEvent( new CustomEvent( 'szesuti:change', {
				detail: { categories: unique, action: action }
			} ) );
		} catch ( e ) {}

		// Újratöltés. Visszavonásnál muszáj: a már lefutott szkripteket nem lehet
		// „kifuttatni” az oldalból. Elfogadásnál viszont felesleges, mert a
		// blokkolt szkripteket menet közben engedjük el – ezért az alapértelmezés 0.
		var reload = revoked.length ? !! CFG.reloadOnReject : !! CFG.reloadOnAccept;

		if ( reload && ! options.noReload && ! PREVIEW ) {
			window.setTimeout( function () {
				location.reload();
			}, 120 );
		}
	}

	/* ------------------------------------------------------------------
	 * Eseménykezelés
	 * --------------------------------------------------------------- */

	function onClick( event ) {
		var node = event.target;
		var action = null;
		var actionNode = null;

		// Felfelé keressük a legközelebbi vezérlőelemet a kattintás helyétől.
		while ( node && node !== document ) {
			if ( node.getAttribute ) {
				var found = node.getAttribute( 'data-szesuti-action' );
				if ( found ) {
					action = found;
					actionNode = node;
					break;
				}
				if ( node.getAttribute( 'data-szesuti-toggle' ) ) {
					toggleSwitch( node );
					event.preventDefault();
					return;
				}
				if ( node.getAttribute( 'data-szesuti-allow' ) ) {
					allowCategory( node.getAttribute( 'data-szesuti-allow' ) );
					event.preventDefault();
					return;
				}
			}
			node = node.parentNode;
		}

		if ( ! action ) {
			return;
		}

		event.preventDefault();

		switch ( action ) {
			case 'accept-all':
				commit( allCategories(), 'accept_all' );
				break;
			case 'reject-all':
				commit( lockedCategories(), 'reject_all' );
				break;
			case 'save':
				commit( selectedCategories(), 'custom' );
				break;
			case 'open-prefs':
				showModal();
				break;
			case 'close-prefs':
				hideModal();
				if ( ! readConsent() ) {
					showBanner();
				}
				break;
			case 'toggle-details':
				toggleDetails( actionNode );
				break;
		}
	}

	function toggleSwitch( node ) {
		var on = node.getAttribute( 'aria-checked' ) === 'true';
		node.setAttribute( 'aria-checked', on ? 'false' : 'true' );
	}

	function toggleDetails( button ) {
		var expanded = button.getAttribute( 'aria-expanded' ) === 'true';
		var panelId = button.getAttribute( 'aria-controls' );
		var panel = panelId ? document.getElementById( panelId ) : null;

		button.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
		if ( panel ) {
			panel.hidden = expanded;
		}
	}

	function allowCategory( category ) {
		var cats = currentCategories().slice();
		if ( cats.indexOf( category ) === -1 ) {
			cats.push( category );
		}
		commit( cats, 'update', { noReload: true } );
	}

	function onKeydown( event ) {
		if ( ! modal || modal.hidden ) {
			return;
		}

		if ( event.key === 'Escape' || event.keyCode === 27 ) {
			hideModal();
			if ( ! readConsent() ) {
				showBanner();
			}
			return;
		}

		if ( event.key !== 'Tab' && event.keyCode !== 9 ) {
			return;
		}

		var focusable = getFocusable();
		if ( ! focusable.length ) {
			return;
		}

		var first = focusable[ 0 ];
		var last = focusable[ focusable.length - 1 ];

		if ( event.shiftKey && document.activeElement === first ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && document.activeElement === last ) {
			event.preventDefault();
			first.focus();
		}
	}

	/* ------------------------------------------------------------------
	 * Indulás
	 * --------------------------------------------------------------- */

	function browserSignalsOptOut() {
		if ( CFG.respectGpc && navigator.globalPrivacyControl === true ) {
			return 'gpc';
		}
		if ( CFG.respectDnt ) {
			var dnt = navigator.doNotTrack || window.doNotTrack || navigator.msDoNotTrack;
			if ( dnt === '1' || dnt === 'yes' ) {
				return 'dnt';
			}
		}
		return null;
	}

	function decideVisibility() {
		if ( PREVIEW ) {
			if ( CFG.previewView === 'prefs' ) {
				showModal();
			} else if ( CFG.previewView === 'reopen' ) {
				writeConsent( defaultCategories(), 'custom' );
				showReopen();
			} else {
				openInitial();
			}
			return;
		}

		var existing = readConsent();

		if ( existing ) {
			pushConsentMode( existing.cats );
			applyBlocked();
			showReopen();
			return;
		}

		var optOut = browserSignalsOptOut();
		if ( optOut ) {
			log( 'böngésző adatvédelmi jelzés:', optOut );
			commit( lockedCategories(), 'reject_all', { silent: false, noReload: true } );
			return;
		}

		if ( CFG.isAdmin && ! CFG.showOnAdmin ) {
			log( 'adminnak elrejtve' );
			applyBlocked();
			return;
		}

		if ( CFG.geo && CFG.geo.enabled ) {
			resolveGeo();
			return;
		}

		openInitial();
	}

	function resolveGeo() {
		if ( ! window.fetch || ! CFG.rest || ! CFG.rest.geo ) {
			openInitial();
			return;
		}

		window.fetch( CFG.rest.geo, { credentials: 'omit' } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( data ) {
				if ( data && data.show === false ) {
					log( 'geo alapján nem kell banner, minden engedélyezve' );
					commit( allCategories(), 'implied', { silent: true, noReload: true } );
					if ( reopen ) {
						reopen.hidden = true;
					}
					return;
				}
				openInitial();
			} )
			.catch( function () {
				if ( CFG.geo.unknown === 'hide' ) {
					commit( allCategories(), 'implied', { silent: true, noReload: true } );
					return;
				}
				openInitial();
			} );
	}

	function openInitial() {
		showReopen();

		if ( CFG.autoOpenDelay > 0 ) {
			window.setTimeout( showBanner, CFG.autoOpenDelay );
		} else {
			showBanner();
		}
	}

	function init() {
		root = qs( '#szesuti-root' );
		if ( ! root ) {
			return;
		}

		banner = qs( '.szesuti-banner', root );
		modal = qs( '.szesuti-modal', root );
		overlay = qs( '.szesuti-overlay', root );
		reopen = qs( '.szesuti-reopen', root );

		// A teljes dokumentumot figyeljük, mert vezérlők a banneren kívül is lehetnek:
		// a helyőrzők „Tartalom engedélyezése” gombja és a [szesuti_settings_button].
		document.addEventListener( 'click', onClick );
		document.addEventListener( 'keydown', onKeydown );

		if ( overlay ) {
			overlay.addEventListener( 'click', function () {
				if ( modal && ! modal.hidden ) {
					hideModal();
					if ( ! readConsent() ) {
						showBanner();
					}
				}
			} );
		}

		syncToggles( defaultCategories() );
		fitPlaceholders();
		startObserver();
		decideVisibility();
	}

	/* ------------------------------------------------------------------
	 * Publikus API
	 * --------------------------------------------------------------- */

	window.SZESUTI = {
		version: CFG.version,
		show: showBanner,
		hide: hideBanner,
		showPreferences: showModal,
		hidePreferences: hideModal,
		acceptAll: function () {
			commit( allCategories(), 'accept_all' );
		},
		rejectAll: function () {
			commit( lockedCategories(), 'reject_all' );
		},
		accept: function ( categories ) {
			commit( categories || [], 'custom' );
		},
		withdraw: function () {
			commit( lockedCategories(), 'withdraw', { noReload: true } );
			window.setTimeout( function () {
				location.reload();
			}, 120 );
		},
		hasConsent: hasConsent,
		getConsent: function () {
			return currentCategories().slice();
		},
		refresh: applyBlocked,
		onChange: function ( callback ) {
			document.addEventListener( 'szesuti:change', function ( event ) {
				callback( event.detail );
			} );
		}
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
