/**
 * SZetemény – admin felület viselkedése.
 */
( function () {
	'use strict';

	var L = window.SZESUTI_ADMIN || {};
	var SCROLL_KEY = 'szesuti_scroll';

	function qsa( selector, context ) {
		return Array.prototype.slice.call( ( context || document ).querySelectorAll( selector ) );
	}

	/** Jelzés a beállítások űrlapnak, hogy valami változott (az előnézet ebből frissül). */
	function notifyChange( el ) {
		var target = el || document.querySelector( '.szesuti-settings-form' );
		if ( ! target ) {
			return;
		}
		target.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	/* ------------------------------------------------------------------
	 * Színválasztó: a színmező és a hex mező szinkronban tartása
	 * --------------------------------------------------------------- */

	function initColors() {
		qsa( 'input[type="color"][data-szesuti-color-for]' ).forEach( function ( picker ) {
			var text = document.getElementById( picker.getAttribute( 'data-szesuti-color-for' ) );
			if ( ! text ) {
				return;
			}

			picker.addEventListener( 'input', function () {
				text.value = picker.value;
				notifyChange( text );
			} );

			text.addEventListener( 'input', function () {
				var value = text.value.trim();
				if ( /^#[0-9a-fA-F]{6}$/.test( value ) ) {
					picker.value = value;
				}
			} );
		} );

		// Opcionális színek: vissza „automatikus” (üres) értékre.
		qsa( '[data-szesuti-color-clear]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var text = document.getElementById( btn.getAttribute( 'data-szesuti-color-clear' ) );
				if ( text ) {
					text.value = '';
					notifyChange( text );
				}
			} );
		} );
	}

	/* ------------------------------------------------------------------
	 * Csúszkák: kitöltött sáv és élő érték
	 * --------------------------------------------------------------- */

	function paintRange( input ) {
		var min = parseFloat( input.min || 0 );
		var max = parseFloat( input.max || 100 );
		var val = parseFloat( input.value || 0 );
		var pct = max > min ? ( ( val - min ) / ( max - min ) ) * 100 : 0;
		input.style.setProperty( '--fill', pct + '%' );
	}

	function initRanges() {
		qsa( '.szesuti-range input[type="range"]' ).forEach( function ( input ) {
			var output = input.parentNode.querySelector( 'output' );
			var unit = output ? output.textContent.replace( /[\d.\-]/g, '' ) : '';

			paintRange( input );

			input.addEventListener( 'input', function () {
				paintRange( input );
				if ( output ) {
					output.textContent = input.value + unit;
				}
			} );
		} );
	}

	/* ------------------------------------------------------------------
	 * Ismétlődő sorok (kategóriák, szabályok, sütik)
	 * --------------------------------------------------------------- */

	function nextIndex( container ) {
		var highest = -1;

		qsa( 'input, select, textarea', container ).forEach( function ( field ) {
			var name = field.getAttribute( 'name' ) || '';
			var match = name.match( /\[(\d+)\]/ );
			if ( match ) {
				highest = Math.max( highest, parseInt( match[ 1 ], 10 ) );
			}
		} );

		return highest + 1;
	}

	function initRepeaters() {
		qsa( '[data-szesuti-repeater]' ).forEach( function ( repeater ) {
			var rows = repeater.querySelector( '.szesuti-repeater__rows' );
			var template = repeater.querySelector( '.szesuti-repeater__template' );
			var addButton = repeater.querySelector( '.szesuti-repeater__add' );

			if ( ! rows || ! template || ! addButton ) {
				return;
			}

			addButton.addEventListener( 'click', function () {
				var index = nextIndex( rows );
				var html = template.innerHTML.replace( /__INDEX__/g, index );
				var wrapper = document.createElement( 'div' );

				wrapper.innerHTML = html.trim();

				var row = wrapper.firstElementChild;
				if ( row ) {
					rows.appendChild( row );
					var firstField = row.querySelector( 'input[type="text"]' );
					if ( firstField ) {
						firstField.focus();
					}
					notifyChange( rows );
				}
			} );

			repeater.addEventListener( 'click', function ( event ) {
				var button = event.target.closest( '.szesuti-repeater__remove' );
				if ( ! button ) {
					return;
				}

				event.preventDefault();

				if ( L.confirmRemove && ! window.confirm( L.confirmRemove ) ) {
					return;
				}

				var row = button.closest( '.szesuti-repeater__row' );
				if ( row && row.parentNode ) {
					row.parentNode.removeChild( row );
					notifyChange( rows );
				}
			} );
		} );
	}

	/* ------------------------------------------------------------------
	 * Médiaablak (médiatár + feltöltés a gépről)
	 * --------------------------------------------------------------- */

	function openMedia( onSelect ) {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}

		var frame = window.wp.media( {
			title: L.mediaTitle || 'Kép kiválasztása',
			button: { text: L.mediaButton || 'Ezt használom' },
			library: { type: 'image' },
			multiple: false
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			onSelect( attachment.url );
		} );

		frame.open();
	}

	/* ------------------------------------------------------------------
	 * Ikonválasztó
	 * --------------------------------------------------------------- */

	function initIconPickers() {
		qsa( '[data-szesuti-iconpick]' ).forEach( function ( box ) {
			var urlInput = box.querySelector( '[data-szesuti-iconpick-url]' );
			var customRadio = box.querySelector( '[data-szesuti-iconpick-custom]' );
			var chooseBtn = box.querySelector( '[data-szesuti-iconpick-choose]' );
			var clearBtn = box.querySelector( '[data-szesuti-iconpick-clear]' );
			var customTile = customRadio ? customRadio.parentNode.querySelector( '.szesuti-iconpick__tile' ) : null;

			function sync() {
				var hasImage = !! ( urlInput && urlInput.value );
				box.classList.toggle( 'is-custom', !! ( customRadio && customRadio.checked ) );
				if ( clearBtn ) {
					clearBtn.hidden = ! hasImage;
				}
				if ( customTile ) {
					customTile.innerHTML = hasImage
						? '<img src="' + urlInput.value.replace( /"/g, '&quot;' ) + '" alt="" />'
						: '<span class="szesuti-iconpick__plus" aria-hidden="true">+</span>';
				}
			}

			function choose() {
				openMedia( function ( url ) {
					urlInput.value = url;
					if ( customRadio ) {
						customRadio.checked = true;
					}
					sync();
					notifyChange( urlInput );
				} );
			}

			box.addEventListener( 'change', function ( event ) {
				if ( event.target === customRadio && customRadio.checked && ! urlInput.value ) {
					// Első kattintásra rögtön nyíljon a médiaablak.
					choose();
				}
				sync();
			} );

			if ( chooseBtn ) {
				chooseBtn.addEventListener( 'click', function ( event ) {
					event.preventDefault();
					choose();
				} );
			}

			if ( clearBtn ) {
				clearBtn.addEventListener( 'click', function ( event ) {
					event.preventDefault();
					urlInput.value = '';
					if ( customRadio && customRadio.checked ) {
						var fallback = box.querySelector( 'input[type="radio"][value="cookie"]' );
						if ( fallback ) {
							fallback.checked = true;
						}
					}
					sync();
					notifyChange( urlInput );
				} );
			}

			sync();
		} );
	}

	/* ------------------------------------------------------------------
	 * Megerősítést kérő gombok
	 * --------------------------------------------------------------- */

	function initConfirms() {
		document.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '[data-szesuti-confirm]' );
			if ( ! button ) {
				return;
			}

			var kind = button.getAttribute( 'data-szesuti-confirm' );
			var messages = { purge: L.confirmPurge, 'lib-delete': L.confirmLibDelete, wipe: L.confirmWipe };
			var message = messages[ kind ] || L.confirmReset;

			if ( message && ! window.confirm( message ) ) {
				event.preventDefault();
			}
		} );
	}

	/* ------------------------------------------------------------------
	 * Teljes törlés (wipe): a gomb csak a megerősítő szó beírása után él
	 * --------------------------------------------------------------- */

	function initWipe() {
		var form = document.querySelector( '[data-szesuti-wipe]' );
		if ( ! form ) {
			return;
		}

		var input = form.querySelector( '[data-wipe-input]' );
		var button = form.querySelector( '[data-wipe-button]' );
		// A kis- és nagybetű számít: a szót tényleg végig be kell gépelni.
		var word = input.getAttribute( 'data-wipe-input' ) || '';

		function sync() {
			button.disabled = input.value.trim() !== word;
		}

		input.addEventListener( 'input', sync );
		sync();

		// Beillesztés (billentyűzet, jobb klikk, húzd-és-ejtsd) tiltva – így csak begépelve
		// fogadja el. A megjelenített szó (data-wipe-nocopy) pedig nem kijelölhető/másolható.
		[ 'paste', 'drop' ].forEach( function ( type ) {
			input.addEventListener( type, function ( event ) {
				event.preventDefault();
			} );
		} );

		var shown = form.querySelector( '[data-wipe-nocopy]' );
		if ( shown ) {
			[ 'copy', 'cut', 'dragstart', 'contextmenu' ].forEach( function ( type ) {
				shown.addEventListener( type, function ( event ) {
					event.preventDefault();
				} );
			} );
		}
	}

	/** Az admin felület böngészőben tárolt beállításai (előnézet mérete stb.) – wipe után. */
	function clearStoredPrefs() {
		[ 'localStorage', 'sessionStorage' ].forEach( function ( kind ) {
			try {
				var store = window[ kind ];
				for ( var i = store.length - 1; i >= 0; i-- ) {
					var key = store.key( i );
					if ( key && key.indexOf( 'szesuti_' ) === 0 ) {
						store.removeItem( key );
					}
				}
			} catch ( e ) {}
		} );
	}

	/* ------------------------------------------------------------------
	 * Mentés ugrás nélkül: a görgetési pozíció megtartása
	 * --------------------------------------------------------------- */

	function initScrollKeeper() {
		var form = document.querySelector( '.szesuti-settings-form' );

		if ( form ) {
			form.addEventListener( 'submit', function () {
				try {
					sessionStorage.setItem( SCROLL_KEY, JSON.stringify( { tab: L.tab, y: window.scrollY } ) );
				} catch ( e ) {}
			} );
		}

		var params = new URLSearchParams( window.location.search );

		if ( params.get( 'szesuti_msg' ) === 'saved' ) {
			try {
				var saved = JSON.parse( sessionStorage.getItem( SCROLL_KEY ) || 'null' );
				sessionStorage.removeItem( SCROLL_KEY );
				if ( saved && saved.tab === L.tab ) {
					window.scrollTo( 0, saved.y );
				}
			} catch ( e ) {}
		}

		if ( params.get( 'szesuti_msg' ) === 'wiped' ) {
			clearStoredPrefs();
		}

		document.documentElement.classList.remove( 'szesuti-restoring' );

		// Az üzenet paraméterét levesszük a címből, hogy frissítéskor ne jelenjen meg újra.
		if ( params.has( 'szesuti_msg' ) && window.history && window.history.replaceState ) {
			params.delete( 'szesuti_msg' );
			params.delete( 'count' );
			params.delete( 'lib' );
			params.delete( 'pv' );
			var clean = window.location.pathname + '?' + params.toString() + window.location.hash;
			window.history.replaceState( null, '', clean );
		}
	}

	/* ------------------------------------------------------------------
	 * Élő előnézet
	 * --------------------------------------------------------------- */

	function initPreview() {
		var box = document.querySelector( '[data-szesuti-preview]' );
		var form = document.querySelector( '.szesuti-settings-form' );

		if ( ! box || ! form || ! window.fetch || ! window.FormData ) {
			return;
		}

		var frame = box.querySelector( 'iframe' );
		var stage = box.querySelector( '[data-preview-stage]' );
		var status = box.querySelector( '[data-preview-status]' );
		var toggle = box.querySelector( '[data-preview-toggle]' );
		var langSelect = box.querySelector( '[data-preview-lang]' );

		var state = {
			view: 'banner',
			device: 'desktop',
			lang: box.getAttribute( 'data-lang' ) || '',
			collapsed: false
		};

		var timer = null;
		var seq = 0;

		try {
			state.collapsed = localStorage.getItem( 'szesuti_preview_collapsed' ) === '1';
		} catch ( e ) {}

		function setStatus( text ) {
			if ( status ) {
				status.textContent = text || '';
			}
		}

		function refresh() {
			if ( state.collapsed ) {
				return;
			}

			var data = new FormData( form );
			// Az előnézet soha nem menthet: a mentés jelzőjét és biztonsági kódját kivesszük.
			data.delete( 'szesuti_action' );
			data.delete( 'szesuti_nonce' );
			data.append( 'action', 'szesuti_preview' );
			data.append( 'nonce', L.previewNonce || '' );
			data.append( 'view', state.view );
			data.append( 'lang', state.lang );

			var mine = ++seq;
			box.classList.add( 'is-loading' );
			setStatus( 'frissítés…' );

			window.fetch( L.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' } )
				.then( function ( response ) {
					if ( ! response.ok ) {
						throw new Error( 'HTTP ' + response.status );
					}
					return response.text();
				} )
				.then( function ( html ) {
					if ( mine !== seq ) {
						return;
					}
					frame.srcdoc = html;
					setStatus( '' );
				} )
				.catch( function () {
					if ( mine === seq ) {
						setStatus( 'Az előnézet most nem érhető el.' );
					}
				} )
				.then( function () {
					if ( mine === seq ) {
						box.classList.remove( 'is-loading' );
					}
				} );
		}

		function schedule() {
			window.clearTimeout( timer );
			timer = window.setTimeout( refresh, 350 );
		}

		function applyCollapsed() {
			box.classList.toggle( 'is-collapsed', state.collapsed );
			if ( toggle ) {
				toggle.setAttribute( 'aria-expanded', state.collapsed ? 'false' : 'true' );
				toggle.innerHTML = '';
				var icon = document.createElement( 'span' );
				icon.className = 'dashicons ' + ( state.collapsed ? 'dashicons-visibility' : 'dashicons-hidden' );
				icon.setAttribute( 'aria-hidden', 'true' );
				toggle.appendChild( icon );
				toggle.appendChild( document.createTextNode( state.collapsed ? 'Előnézet megjelenítése' : 'Előnézet elrejtése' ) );
			}
			if ( stage ) {
				stage.hidden = state.collapsed;
			}
			if ( state.collapsed && frame ) {
				// Elrejtve ne fusson a háttérben sem.
				frame.removeAttribute( 'srcdoc' );
			}
		}

		function activate( selector, attr, value ) {
			qsa( selector, box ).forEach( function ( btn ) {
				btn.classList.toggle( 'is-active', btn.getAttribute( attr ) === value );
			} );
		}

		box.addEventListener( 'click', function ( event ) {
			var viewBtn = event.target.closest( '[data-preview-view]' );
			var deviceBtn = event.target.closest( '[data-preview-device]' );

			if ( viewBtn ) {
				state.view = viewBtn.getAttribute( 'data-preview-view' );
				activate( '[data-preview-view]', 'data-preview-view', state.view );
				refresh();
			}

			if ( deviceBtn ) {
				state.device = deviceBtn.getAttribute( 'data-preview-device' );
				activate( '[data-preview-device]', 'data-preview-device', state.device );
				box.classList.toggle( 'is-mobile', state.device === 'mobile' );
				applyHeight();
			}
		} );

		/* --- Átméretezés: húzható sáv az előnézet alatt --- */

		var resizer = box.querySelector( '[data-preview-resize]' );
		var MIN_HEIGHT = 200;

		/** A legnagyobb magasság: a felragadt előnézet alatt a beállításokból mindig maradjon látható egy sáv. */
		function maxHeight() {
			return Math.max( 300, Math.min( 1400, window.innerHeight - 260 ) );
		}

		function heightKey() {
			return 'szesuti_preview_h_' + state.device;
		}

		function clampHeight( h ) {
			return Math.round( Math.min( maxHeight(), Math.max( MIN_HEIGHT, h ) ) );
		}

		function setHeight( h, persist ) {
			if ( ! stage ) {
				return;
			}
			if ( null === h ) {
				stage.style.removeProperty( 'height' );
			} else {
				stage.style.height = clampHeight( h ) + 'px';
			}
			if ( resizer ) {
				resizer.setAttribute( 'aria-valuenow', String( stage.offsetHeight ) );
				resizer.setAttribute( 'aria-valuemin', String( MIN_HEIGHT ) );
				resizer.setAttribute( 'aria-valuemax', String( maxHeight() ) );
			}
			if ( persist ) {
				try {
					if ( null === h ) {
						localStorage.removeItem( heightKey() );
					} else {
						localStorage.setItem( heightKey(), String( clampHeight( h ) ) );
					}
				} catch ( e ) {}
			}
		}

		/** Az eszközhöz (asztali / mobil) elmentett magasság visszaállítása. */
		function applyHeight() {
			var saved = null;
			try {
				saved = parseInt( localStorage.getItem( heightKey() ), 10 );
			} catch ( e ) {}
			setHeight( saved > 0 ? saved : null, false );
		}

		if ( resizer && stage ) {
			var dragStartY = 0;
			var dragStartH = 0;

			resizer.addEventListener( 'pointerdown', function ( event ) {
				event.preventDefault();
				dragStartY = event.clientY;
				dragStartH = stage.offsetHeight;
				box.classList.add( 'is-resizing' );
				resizer.setPointerCapture( event.pointerId );
			} );

			resizer.addEventListener( 'pointermove', function ( event ) {
				if ( ! box.classList.contains( 'is-resizing' ) ) {
					return;
				}
				setHeight( dragStartH + ( event.clientY - dragStartY ), false );
			} );

			function endDrag( event ) {
				if ( ! box.classList.contains( 'is-resizing' ) ) {
					return;
				}
				box.classList.remove( 'is-resizing' );
				try {
					resizer.releasePointerCapture( event.pointerId );
				} catch ( e ) {}
				setHeight( stage.offsetHeight, true );
			}

			resizer.addEventListener( 'pointerup', endDrag );
			resizer.addEventListener( 'pointercancel', endDrag );

			// Dupla kattintás: vissza az alapméretre.
			resizer.addEventListener( 'dblclick', function () {
				setHeight( null, true );
			} );

			// Billentyűzettel is: fel/le nyíl 20 px, Shift-tel 80 px.
			resizer.addEventListener( 'keydown', function ( event ) {
				var step = event.shiftKey ? 80 : 20;
				if ( 'ArrowUp' === event.key || 'ArrowDown' === event.key ) {
					event.preventDefault();
					setHeight( stage.offsetHeight + ( 'ArrowDown' === event.key ? step : -step ), true );
				} else if ( 'Home' === event.key ) {
					event.preventDefault();
					setHeight( null, true );
				}
			} );

			// Kisebb ablakra méretezve se lógjon ki a képernyőből.
			window.addEventListener( 'resize', function () {
				if ( stage.style.height ) {
					setHeight( parseInt( stage.style.height, 10 ), false );
				}
			} );
		}

		if ( langSelect ) {
			langSelect.addEventListener( 'change', function () {
				state.lang = langSelect.value;
				refresh();
			} );
		}

		if ( toggle ) {
			toggle.addEventListener( 'click', function () {
				state.collapsed = ! state.collapsed;
				try {
					localStorage.setItem( 'szesuti_preview_collapsed', state.collapsed ? '1' : '0' );
				} catch ( e ) {}
				applyCollapsed();
				refresh();
			} );
		}

		/**
		 * Mindig azt mutassuk, amit épp szerkesztenek: a lebegő gomb mezőinél a gombot,
		 * a beállítások ablak szövegeinél és a kategóriáknál az ablakot.
		 */
		function viewFor( field ) {
			var name = ( field && field.getAttribute && field.getAttribute( 'name' ) ) || '';
			if ( /\[reopen_/.test( name ) ) {
				return 'reopen';
			}
			if ( /^szesuti\[categories\]/.test( name ) || /\[(prefs_title|prefs_intro|always_active|accept_selected|cookie_table_[a-z]+|cat)\]/.test( name ) ) {
				return 'prefs';
			}
			return null;
		}

		function onEdit( event ) {
			var wanted = viewFor( event.target );
			if ( wanted && wanted !== state.view ) {
				state.view = wanted;
				activate( '[data-preview-view]', 'data-preview-view', state.view );
			}
			schedule();
		}

		form.addEventListener( 'input', onEdit );
		form.addEventListener( 'change', onEdit );

		applyCollapsed();
		applyHeight();
		refresh();
	}

	/* ------------------------------------------------------------------
	 * Háttéradatbázis: keresés, szűrés, szerkesztés
	 * --------------------------------------------------------------- */

	function initLibrary() {
		var list = document.querySelector( '.szesuti-lib-list' );
		var editor = document.querySelector( '[data-lib-editor]' );
		if ( ! list || ! editor ) {
			return;
		}

		var rows = qsa( '[data-lib-row]', list );
		var search = list.querySelector( '[data-lib-search]' );
		var source = list.querySelector( '[data-lib-filter-source]' );
		var category = list.querySelector( '[data-lib-filter-category]' );
		var counter = list.querySelector( '[data-lib-count]' );
		var empty = list.querySelector( '[data-lib-empty]' );
		var all = list.querySelector( '[data-lib-all]' );
		var title = editor.querySelector( '[data-lib-editor-title]' );

		function applyFilter() {
			var q = ( search.value || '' ).trim().toLowerCase();
			var src = source.value;
			var cat = category.value;
			var shown = 0;
			rows.forEach( function ( row ) {
				var ok = ( ! q || row.getAttribute( 'data-search' ).indexOf( q ) !== -1 ) &&
					( ! src || row.getAttribute( 'data-source' ) === src ) &&
					( ! cat || row.getAttribute( 'data-category' ) === cat );
				row.hidden = ! ok;
				if ( ok ) {
					shown++;
				} else {
					// Rejtett sort ne érintsen a tömeges művelet.
					row.querySelector( 'input[type="checkbox"]' ).checked = false;
				}
			} );
			counter.textContent = ( L.libCount || '%1$d / %2$d' ).replace( '%1$d', shown ).replace( '%2$d', rows.length );
			empty.hidden = shown > 0;
		}

		[ search, source, category ].forEach( function ( el ) {
			el.addEventListener( 'input', applyFilter );
			el.addEventListener( 'change', applyFilter );
		} );

		all.addEventListener( 'change', function () {
			rows.forEach( function ( row ) {
				if ( ! row.hidden ) {
					row.querySelector( 'input[type="checkbox"]' ).checked = all.checked;
				}
			} );
		} );

		function field( name ) {
			return editor.querySelector( '[data-lib-field="' + name + '"]' );
		}

		function openEditor( data ) {
			data = data || {};
			[ 'match', 'provider', 'duration', 'desc_hu', 'desc_en', 'original' ].forEach( function ( key ) {
				field( key ).value = data[ key ] || '';
			} );
			field( 'category' ).value = data.category || 'necessary';
			field( 'type' ).value = data.type || 'http';
			title.textContent = data.match ? title.getAttribute( 'data-title-edit' ) : title.getAttribute( 'data-title-new' );
			editor.hidden = false;
			editor.scrollIntoView( { block: 'start', behavior: 'smooth' } );
			field( 'match' ).focus( { preventScroll: true } );
		}

		list.addEventListener( 'click', function ( event ) {
			var edit = event.target.closest( '[data-lib-edit]' );
			if ( edit ) {
				try {
					openEditor( JSON.parse( edit.getAttribute( 'data-lib-edit' ) ) );
				} catch ( e ) {}
			}
			if ( event.target.closest( '[data-lib-new]' ) ) {
				openEditor( null );
			}
		} );

		editor.querySelector( '[data-lib-cancel]' ).addEventListener( 'click', function () {
			editor.hidden = true;
		} );

		// Az Enter a keresőben ne küldje el a tömeges űrlapot.
		search.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key ) {
				event.preventDefault();
			}
		} );

		applyFilter();
	}

	/* ------------------------------------------------------------------
	 * Sütilista: kitöltés a háttéradatbázisból a név beírásakor
	 * --------------------------------------------------------------- */

	function wildcardRegExp( pattern ) {
		var escaped = String( pattern ).replace( /[.+?^${}()|[\]\\]/g, '\\$&' ).replace( /\*/g, '.*' );
		return new RegExp( '^' + escaped + '$', 'i' );
	}

	function libraryMatch( name ) {
		var known = window.SZESUTI_LIBRARY || [];
		var lower = String( name || '' ).trim().toLowerCase();
		var i;
		if ( ! lower ) {
			return null;
		}
		for ( i = 0; i < known.length; i++ ) {
			if ( known[ i ].m.toLowerCase() === lower ) {
				return known[ i ];
			}
		}
		for ( i = 0; i < known.length; i++ ) {
			if ( known[ i ].m.indexOf( '*' ) !== -1 && wildcardRegExp( known[ i ].m ).test( lower ) ) {
				return known[ i ];
			}
		}
		return null;
	}

	function initCookieAutofill() {
		var rowsEl = document.querySelector( '.szesuti-cookie-rows' );
		if ( ! rowsEl || ! window.SZESUTI_LIBRARY ) {
			return;
		}

		rowsEl.addEventListener( 'change', function ( event ) {
			var input = event.target;
			if ( ! input.name || ! /\[name\]$/.test( input.name ) ) {
				return;
			}
			var row = input.closest( '.szesuti-cookie-row' );
			var hit = libraryMatch( input.value );
			if ( ! row || ! hit ) {
				return;
			}

			function get( suffix ) {
				return row.querySelector( '[name$="[' + suffix + ']"]' );
			}

			// Csak az üres mezőket töltjük ki – amit már beírtak, azt nem írjuk felül.
			var fresh = ! get( 'provider' ).value && ! get( 'desc_hu' ).value && ! get( 'desc_en' ).value;
			var pairs = { provider: hit.p, duration: hit.d, desc_hu: hit.h, desc_en: hit.e };
			Object.keys( pairs ).forEach( function ( key ) {
				var el = get( key );
				if ( el && ! el.value && pairs[ key ] ) {
					el.value = pairs[ key ];
				}
			} );
			if ( fresh ) {
				var cat = get( 'category' );
				if ( cat && cat.querySelector( 'option[value="' + hit.c + '"]' ) ) {
					cat.value = hit.c;
				}
				if ( get( 'type' ) && hit.t ) {
					get( 'type' ).value = hit.t;
				}
			}

			var note = row.querySelector( '.szesuti-lib-filled' );
			if ( ! note ) {
				note = document.createElement( 'span' );
				note.className = 'szesuti-lib-filled';
				input.parentNode.appendChild( note );
			}
			note.textContent = '✓ ' + ( L.libFilled || '' );
		} );
	}

	/* ------------------------------------------------------------------
	 * Indulás
	 * --------------------------------------------------------------- */

	function init() {
		initScrollKeeper();
		initColors();
		initRanges();
		initRepeaters();
		initIconPickers();
		initConfirms();
		initWipe();
		initPreview();
		initLibrary();
		initCookieAutofill();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
