/**
 * SZetemény – futásidejű őr.
 *
 * A <head> legelején, minden más szkript előtt fut (a PHP inline írja ki, a
 * window.SZESUTI_GUARD_CONFIG beállításokkal). A szerveroldali blokkoló a HTML-ben
 * lévő elemeket kezeli; ez az őr azokat fogja el, amiket más szkriptek később,
 * futás közben hoznak létre:
 *
 *   - document.createElement + .src / setAttribute('src')  (script, iframe, img)
 *   - innerHTML / outerHTML / insertAdjacentHTML / document.write
 *   - new Image().src követőképpontok
 *   - navigator.sendBeacon, fetch, XMLHttpRequest az ismert mérők felé
 *
 * A döntési sorrend ugyanaz, mint a szerveren (SZESUTI_Blocker::classify()).
 * A blokkolt elemek ugyanazt a jelölést kapják, mint a szerveroldaliak, így a
 * hozzájárulás után a banner szkriptje ugyanúgy feloldja őket.
 *
 * Minden hiba el van nyelve: az őr soha nem törheti el az oldalt.
 */
( function () {
	'use strict';

	var C = window.SZESUTI_GUARD_CONFIG;
	if ( ! C || window.__szesutiGuard ) {
		return;
	}

	/* ------------------------------------------------------------------
	 * A látogató döntése (a sütiből – így oldalgyorsítótárral is helyes)
	 * --------------------------------------------------------------- */

	function readConsent() {
		try {
			var parts = document.cookie ? document.cookie.split( ';' ) : [];
			for ( var i = 0; i < parts.length; i++ ) {
				var part = parts[ i ].replace( /^\s+/, '' );
				if ( part.indexOf( C.cookie + '=' ) !== 0 ) {
					continue;
				}
				var data = JSON.parse( decodeURIComponent( part.substring( C.cookie.length + 1 ) ) );
				if ( ! data || ! data.cats || ! data.cats.length ) {
					return [];
				}
				if ( C.days > 0 && data.t && ( Date.now() / 1000 - data.t ) / 86400 > C.days ) {
					return [];
				}
				if ( C.pv && String( data.pv ) !== String( C.pv ) ) {
					return [];
				}
				return data.cats;
			}
		} catch ( e ) {}
		return [];
	}

	var granted = readConsent();

	function allowed( cat ) {
		return ! cat || C.locked.indexOf( cat ) !== -1 || granted.indexOf( cat ) !== -1;
	}

	/* ------------------------------------------------------------------
	 * Döntés egy URL-ről
	 * --------------------------------------------------------------- */

	function hostOf( url ) {
		try {
			var u = new URL( url, document.baseURI );
			if ( u.protocol !== 'http:' && u.protocol !== 'https:' ) {
				return '';
			}
			return u.hostname.toLowerCase().replace( /\.$/, '' );
		} catch ( e ) {
			return '';
		}
	}

	function endsWith( str, suffix ) {
		return str.length >= suffix.length && str.substr( str.length - suffix.length ) === suffix;
	}

	function contains( haystack, list ) {
		var h = haystack.toLowerCase();
		for ( var i = 0; i < list.length; i++ ) {
			if ( list[ i ] && h.indexOf( String( list[ i ] ).toLowerCase() ) !== -1 ) {
				return true;
			}
		}
		return false;
	}

	function typeApplies( type, kind ) {
		if ( type === 'both' ) {
			return true;
		}
		if ( kind === 'iframe' ) {
			return type === 'iframe';
		}
		return type === 'script';
	}

	/**
	 * @param {string}  url
	 * @param {string}  kind  script | iframe | img | beacon | fetch
	 * @param {boolean} pixel Képnél: 1×1-es kép-e.
	 * @return {string} A blokkoló kategória, vagy '' ha átengedhető.
	 */
	function categoryFor( url, kind, pixel ) {
		if ( ! url ) {
			return '';
		}

		var s = String( url );

		if ( /^(data|blob|about|javascript):/i.test( s ) ) {
			return '';
		}
		if ( C.own && s.indexOf( C.own ) !== -1 ) {
			return '';
		}
		if ( contains( s, C.skip ) ) {
			return '';
		}

		for ( var i = 0; i < C.rules.length; i++ ) {
			var rule = C.rules[ i ];
			if ( typeApplies( rule.t, kind ) && contains( s, rule.p ) ) {
				return rule.c;
			}
		}

		if ( contains( s, C.allow ) ) {
			return '';
		}

		var host = hostOf( s );
		if ( ! host ) {
			return '';
		}
		if ( host === C.host || host === C.base || ( C.base && endsWith( host, '.' + C.base ) ) ) {
			return '';
		}
		for ( var j = 0; j < C.safe.length; j++ ) {
			if ( host === C.safe[ j ] || endsWith( host, '.' + C.safe[ j ] ) ) {
				return '';
			}
		}

		if ( ! C.strict ) {
			return '';
		}

		// Tartalmi képeket és API-hívásokat (fetch/XHR) nem blokkolunk ismeretlenként,
		// csak a követőképpontokat és a jeladókat.
		if ( kind === 'fetch' || ( kind === 'img' && ! pixel ) ) {
			return '';
		}

		return C.unknown;
	}

	function blockedCategory( url, kind, pixel ) {
		var cat = categoryFor( url, kind, pixel );
		return cat && ! allowed( cat ) ? cat : '';
	}

	/* ------------------------------------------------------------------
	 * Elemek: .src és setAttribute('src')
	 * --------------------------------------------------------------- */

	var nativeSetAttribute = Element.prototype.setAttribute;

	function kindOf( el ) {
		var tag = el && el.tagName;
		if ( tag === 'SCRIPT' ) {
			return 'script';
		}
		if ( tag === 'IFRAME' ) {
			return 'iframe';
		}
		if ( tag === 'IMG' ) {
			return 'img';
		}
		return '';
	}

	/**
	 * Követőképpontnak látszik-e egy kép: 1×1-es, vagy egyáltalán nincs a dokumentumban
	 * (a `new Image().src = ...` a klasszikus jeladó-minta).
	 */
	function isPixelElement( el ) {
		var w = parseInt( el.getAttribute( 'width' ), 10 );
		var h = parseInt( el.getAttribute( 'height' ), 10 );
		if ( w <= 1 && h <= 1 ) {
			return true;
		}
		return el.isConnected === false;
	}

	function neutralize( el, url, cat, kind ) {
		nativeSetAttribute.call( el, 'data-szesuti-src', url );
		nativeSetAttribute.call( el, 'data-szesuti', cat );
		nativeSetAttribute.call( el, 'data-szesuti-el', kind );
		nativeSetAttribute.call( el, 'data-szesuti-svc', 'guard' );
		if ( kind === 'script' ) {
			nativeSetAttribute.call( el, 'type', 'text/plain' );
		}
	}

	function patchSrc( proto, kind ) {
		try {
			var desc = Object.getOwnPropertyDescriptor( proto, 'src' );
			if ( ! desc || ! desc.set || ! desc.configurable ) {
				return;
			}
			Object.defineProperty( proto, 'src', {
				configurable: true,
				enumerable: desc.enumerable,
				get: desc.get,
				set: function ( value ) {
					var cat = blockedCategory( value, kind, kind === 'img' && isPixelElement( this ) );
					if ( cat ) {
						neutralize( this, String( value ), cat, kind );
						return;
					}
					desc.set.call( this, value );
				}
			} );
		} catch ( e ) {}
	}

	patchSrc( window.HTMLScriptElement && HTMLScriptElement.prototype, 'script' );
	patchSrc( window.HTMLIFrameElement && HTMLIFrameElement.prototype, 'iframe' );
	patchSrc( window.HTMLImageElement && HTMLImageElement.prototype, 'img' );

	try {
		Element.prototype.setAttribute = function ( name, value ) {
			if ( typeof name === 'string' && name.toLowerCase() === 'src' ) {
				var kind = kindOf( this );
				if ( kind ) {
					var cat = blockedCategory( value, kind, kind === 'img' && isPixelElement( this ) );
					if ( cat ) {
						neutralize( this, String( value ), cat, kind );
						return;
					}
				}
			}
			return nativeSetAttribute.apply( this, arguments );
		};
	} catch ( e ) {}

	/* ------------------------------------------------------------------
	 * HTML-szöveg beszúrása: innerHTML, outerHTML, insertAdjacentHTML, document.write
	 * --------------------------------------------------------------- */

	var TAG_RE = /<(script|iframe|img)\b([^>]*)>/gi;
	var SRC_RE = /(^|\s)src\s*=\s*("([^"]*)"|'([^']*)'|([^\s"'>]+))/i;

	function rewriteHtml( html ) {
		if ( typeof html !== 'string' || html.indexOf( '<' ) === -1 || ! /\ssrc\s*=/i.test( html ) ) {
			return html;
		}

		return html.replace( TAG_RE, function ( full, tag, attrs ) {
			if ( /data-szesuti/i.test( attrs ) ) {
				return full;
			}
			var m = attrs.match( SRC_RE );
			if ( ! m ) {
				return full;
			}
			var url = m[ 3 ] || m[ 4 ] || m[ 5 ] || '';
			var kind = tag.toLowerCase();
			var pixel = kind === 'img' && /\swidth\s*=\s*["']?[01]["'\s>]/i.test( attrs ) && /\sheight\s*=\s*["']?[01]["'\s>]/i.test( attrs );
			var cat = blockedCategory( url, kind, pixel );
			if ( ! cat ) {
				return full;
			}
			var extra = ' data-szesuti="' + cat + '" data-szesuti-el="' + kind + '" data-szesuti-svc="guard"';
			if ( kind === 'script' ) {
				attrs = attrs.replace( /(^|\s)type\s*=\s*("[^"]*"|'[^']*'|[^\s"'>]+)/i, '' );
				extra += ' type="text/plain"';
			}
			return '<' + tag + attrs.replace( SRC_RE, '$1data-szesuti-src=$2' ) + extra + '>';
		} );
	}

	function patchHtmlSetter( proto, prop ) {
		try {
			var desc = Object.getOwnPropertyDescriptor( proto, prop );
			if ( ! desc || ! desc.set || ! desc.configurable ) {
				return;
			}
			Object.defineProperty( proto, prop, {
				configurable: true,
				enumerable: desc.enumerable,
				get: desc.get,
				set: function ( value ) {
					desc.set.call( this, rewriteHtml( value ) );
				}
			} );
		} catch ( e ) {}
	}

	patchHtmlSetter( Element.prototype, 'innerHTML' );
	patchHtmlSetter( Element.prototype, 'outerHTML' );

	try {
		var nativeAdjacent = Element.prototype.insertAdjacentHTML;
		Element.prototype.insertAdjacentHTML = function ( position, html ) {
			return nativeAdjacent.call( this, position, rewriteHtml( html ) );
		};
	} catch ( e ) {}

	try {
		var nativeWrite = document.write;
		var nativeWriteln = document.writeln;
		document.write = function () {
			var args = Array.prototype.map.call( arguments, rewriteHtml );
			return nativeWrite.apply( document, args );
		};
		document.writeln = function () {
			var args = Array.prototype.map.call( arguments, rewriteHtml );
			return nativeWriteln.apply( document, args );
		};
	} catch ( e ) {}

	/* ------------------------------------------------------------------
	 * Jeladók és kérések az ismert mérők felé
	 * --------------------------------------------------------------- */

	try {
		if ( navigator.sendBeacon ) {
			var nativeBeacon = navigator.sendBeacon;
			navigator.sendBeacon = function ( url ) {
				if ( blockedCategory( url, 'beacon', false ) ) {
					// A hívó sikert kap, de semmi nem megy ki.
					return true;
				}
				return nativeBeacon.apply( navigator, arguments );
			};
		}
	} catch ( e ) {}

	try {
		if ( window.fetch ) {
			var nativeFetch = window.fetch;
			window.fetch = function ( input ) {
				var url = typeof input === 'string' ? input : ( input && input.url ) || '';
				if ( blockedCategory( url, 'fetch', false ) ) {
					return Promise.reject( new TypeError( 'SZetemény: hozzájárulás nélkül blokkolva' ) );
				}
				return nativeFetch.apply( this, arguments );
			};
		}
	} catch ( e ) {}

	try {
		var nativeOpen = XMLHttpRequest.prototype.open;
		var nativeSend = XMLHttpRequest.prototype.send;
		XMLHttpRequest.prototype.open = function ( method, url ) {
			this.__szesutiBlocked = !! blockedCategory( url, 'fetch', false );
			return nativeOpen.apply( this, arguments );
		};
		XMLHttpRequest.prototype.send = function () {
			if ( this.__szesutiBlocked ) {
				return;
			}
			return nativeSend.apply( this, arguments );
		};
	} catch ( e ) {}

	/* ------------------------------------------------------------------
	 * Kapcsolat a banner szkriptjével
	 * --------------------------------------------------------------- */

	window.__szesutiGuard = {
		/** A banner hívja döntéskor, a feloldás ELŐTT. */
		update: function ( categories ) {
			granted = categories && categories.length ? categories.slice() : [];
		},
		category: categoryFor,
		rewrite: rewriteHtml
	};
} )();
