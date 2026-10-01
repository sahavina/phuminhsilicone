/**
 * Danh sách báo giá nhiều sản phẩm (saha-core WooCommerce\QuoteList, SCC 2.5).
 *
 * - Danh sách lưu ở localStorage của khách (đồng bộ giữa các tab) — server không giữ phiên.
 * - [data-saha-ql-add]: thêm sản phẩm (biến thể đang chọn trong form gần nhất, nếu có).
 * - [data-saha-ql-link] .saha-ql-count: số sản phẩm trên icon header.
 * - [data-saha-quote-list]: trang danh sách — sửa số lượng / ghi chú, xoá, form liên hệ,
 *   gửi một lần POST /saha/v1/quote/list (nonce lấy mới từ /saha/v1/nonce, trang cache được).
 */
( function () {
	'use strict';

	var cfg = window.sahaQuoteList;

	if ( ! cfg ) {
		return;
	}

	var KEY = 'saha_quote_list_v1';
	var i18n = cfg.i18n;
	var toastEl = null;
	var toastTimer = 0;

	function sprintf( template, value ) {
		return String( template ).replace( /%[sd]/, value );
	}

	function el( tag, className, text ) {
		var node = document.createElement( tag );

		if ( className ) {
			node.className = className;
		}

		if ( null != text ) {
			node.textContent = text;
		}

		return node;
	}

	/* ------------------------------------------------------------------
	 * Lưu trữ
	 * ------------------------------------------------------------------ */

	function load() {
		try {
			var data = JSON.parse( window.localStorage.getItem( KEY ) || '[]' );

			return Array.isArray( data ) ? data.filter( function ( item ) {
				return item && parseInt( item.id, 10 ) > 0;
			} ) : [];
		} catch ( e ) {
			return [];
		}
	}

	function save( items ) {
		try {
			window.localStorage.setItem( KEY, JSON.stringify( items ) );
		} catch ( e ) {
			// Chế độ riêng tư / đầy bộ nhớ: danh sách chỉ sống trong trang hiện tại.
		}

		badges( items );
	}

	function keyOf( item ) {
		return item.id + ':' + ( item.vid || 0 );
	}

	function badges( items ) {
		var count = ( items || load() ).length;

		Array.prototype.forEach.call( document.querySelectorAll( '.saha-ql-count' ), function ( node ) {
			node.textContent = String( count );
			node.hidden = 0 === count;
		} );

		Array.prototype.forEach.call( document.querySelectorAll( '.saha-ql-sr' ), function ( node ) {
			node.textContent = sprintf( i18n.count, count );
		} );

		Array.prototype.forEach.call( document.querySelectorAll( '[data-saha-ql-add]' ), function ( button ) {
			var id = button.getAttribute( 'data-product-id' );
			var has = ( items || load() ).some( function ( item ) {
				return String( item.id ) === id;
			} );

			button.classList.toggle( 'is-added', has );
		} );
	}

	/* ------------------------------------------------------------------
	 * Thông báo nổi
	 * ------------------------------------------------------------------ */

	function toast( text, withLink ) {
		if ( ! toastEl ) {
			toastEl = el( 'div', 'saha-ql-toast' );
			toastEl.setAttribute( 'role', 'status' );
			toastEl.setAttribute( 'aria-live', 'polite' );
			document.body.appendChild( toastEl );
		}

		toastEl.innerHTML = '';
		toastEl.appendChild( el( 'span', '', text ) );

		if ( withLink && cfg.pageUrl ) {
			var a = el( 'a', 'saha-ql-toast__link', i18n.viewList + ' →' );

			a.href = cfg.pageUrl;
			toastEl.appendChild( a );
		}

		toastEl.classList.add( 'is-visible' );
		window.clearTimeout( toastTimer );
		toastTimer = window.setTimeout( function () {
			toastEl.classList.remove( 'is-visible' );
		}, 6000 );
	}

	/* ------------------------------------------------------------------
	 * Nút thêm
	 * ------------------------------------------------------------------ */

	function variationOf( button ) {
		var scope = button.closest( '.saha-qv, .product, .summary' ) || document;
		var form = scope.querySelector( 'form.variations_form' );
		var qtyInput = scope.querySelector( 'form.cart input.qty' );
		var result = { vid: 0, attrs: '', qty: qtyInput ? parseInt( qtyInput.value, 10 ) || 1 : 1, needsChoice: false };

		if ( ! form ) {
			return result;
		}

		var vid = form.querySelector( 'input[name="variation_id"]' );

		result.vid = vid ? parseInt( vid.value, 10 ) || 0 : 0;
		result.needsChoice = 0 === result.vid;
		result.attrs = Array.prototype.map.call( form.querySelectorAll( 'select[name^="attribute_"]' ), function ( select ) {
			var option = select.options[ select.selectedIndex ];

			return option && option.value ? option.text : '';
		} ).filter( Boolean ).join( ', ' );

		return result;
	}

	function add( button ) {
		var items = load();
		var variation = variationOf( button );

		if ( variation.needsChoice ) {
			toast( i18n.chooseVar, false );
			return;
		}

		var item = {
			id: parseInt( button.getAttribute( 'data-product-id' ), 10 ),
			vid: variation.vid,
			qty: Math.max( 1, Math.min( cfg.maxQty, variation.qty ) ),
			note: '',
			name: button.getAttribute( 'data-name' ) || '',
			sku: button.getAttribute( 'data-sku' ) || '',
			image: button.getAttribute( 'data-image' ) || '',
			url: button.getAttribute( 'data-url' ) || '',
			attrs: variation.attrs
		};

		var existing = items.filter( function ( row ) {
			return keyOf( row ) === keyOf( item );
		} )[ 0 ];

		if ( existing ) {
			existing.qty = Math.min( cfg.maxQty, ( parseInt( existing.qty, 10 ) || 1 ) + item.qty );
		} else {
			if ( items.length >= cfg.maxItems ) {
				toast( i18n.full, true );
				return;
			}

			items.push( item );
		}

		save( items );
		toast( i18n.added, true );
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-saha-ql-add]' );

		if ( button ) {
			event.preventDefault();
			add( button );
		}
	} );

	/* ------------------------------------------------------------------
	 * Trang danh sách
	 * ------------------------------------------------------------------ */

	function field( name, label, type, required, autocomplete ) {
		var wrap = el( 'p', 'saha-ql__field saha-ql__field--' + name );
		var id = 'saha-ql-' + name;
		var lab = el( 'label', '', label );
		var input = 'textarea' === type ? el( 'textarea' ) : el( 'input' );

		lab.setAttribute( 'for', id );

		if ( required ) {
			var req = el( 'span', 'saha-ql__req', ' *' );
			var sr = el( 'span', 'screen-reader-text', ' ' + i18n.required );

			req.setAttribute( 'aria-hidden', 'true' );
			lab.appendChild( req );
			lab.appendChild( sr );
			input.required = true;
		}

		if ( 'textarea' !== type ) {
			input.type = type;
		} else {
			input.rows = 3;
		}

		input.id = id;
		input.name = name;

		if ( autocomplete ) {
			input.setAttribute( 'autocomplete', autocomplete );
		}

		var error = el( 'span', 'saha-ql__error' );

		error.id = id + '-error';
		error.hidden = true;
		wrap.appendChild( lab );
		wrap.appendChild( input );
		wrap.appendChild( error );

		return wrap;
	}

	function initPage( root ) {
		var invalid = [];
		var listWrap = el( 'div', 'saha-ql__list' );
		var formWrap = el( 'form', 'saha-ql__form' );
		var status = el( 'div', 'saha-ql__status' );

		status.setAttribute( 'role', 'alert' );
		status.setAttribute( 'tabindex', '-1' );
		status.hidden = true;
		formWrap.noValidate = true;

		var legend = el( 'h2', 'saha-ql__form-title', i18n.formTitle );
		var grid = el( 'div', 'saha-ql__grid' );

		grid.appendChild( field( 'name', i18n.name, 'text', true, 'name' ) );
		grid.appendChild( field( 'phone', i18n.phone, 'tel', true, 'tel' ) );
		grid.appendChild( field( 'email', i18n.email, 'email', false, 'email' ) );
		grid.appendChild( field( 'company', i18n.company, 'text', false, 'organization' ) );
		formWrap.appendChild( legend );
		formWrap.appendChild( grid );
		formWrap.appendChild( field( 'message', i18n.message, 'textarea', false, '' ) );

		// Honeypot: người thật không thấy, bot điền vào → server trả "thành công giả".
		var trap = el( 'div', 'saha-ql__trap' );
		var trapInput = el( 'input' );

		trap.setAttribute( 'aria-hidden', 'true' );
		trapInput.type = 'text';
		trapInput.name = 'saha_hp_email';
		trapInput.tabIndex = -1;
		trapInput.setAttribute( 'autocomplete', 'off' );
		trap.appendChild( trapInput );
		formWrap.appendChild( trap );

		var submit = el( 'button', 'saha-btn saha-btn--primary saha-btn--lg saha-ql__submit', i18n.submit );

		submit.type = 'submit';
		formWrap.appendChild( submit );

		root.appendChild( status );
		root.appendChild( listWrap );
		root.appendChild( formWrap );

		function showStatus( text, ok ) {
			status.hidden = false;
			status.className = 'saha-ql__status ' + ( ok ? 'is-success' : 'is-error' );
			status.textContent = text;
		}

		function render( focusKey ) {
			var items = load();

			listWrap.innerHTML = '';
			formWrap.hidden = ! items.length;

			if ( ! items.length ) {
				var empty = el( 'div', 'saha-ql__empty' );
				var browse = el( 'a', 'saha-btn saha-btn--outline', i18n.browse );

				browse.href = cfg.shopUrl;
				empty.appendChild( el( 'p', '', i18n.empty ) );
				empty.appendChild( browse );
				listWrap.appendChild( empty );
				return;
			}

			var head = el( 'div', 'saha-ql__head' );
			var clear = el( 'button', 'saha-ql__clear', i18n.clear );

			head.appendChild( el( 'p', 'saha-ql__count', sprintf( i18n.count, items.length ) ) );
			clear.type = 'button';
			clear.addEventListener( 'click', function () {
				if ( window.confirm( i18n.clearAsk ) ) {
					save( [] );
					render();
					listWrap.setAttribute( 'tabindex', '-1' );
					listWrap.focus();
				}
			} );
			head.appendChild( clear );
			listWrap.appendChild( head );

			var ul = el( 'ul', 'saha-ql__items' );

			items.forEach( function ( item ) {
				var key = keyOf( item );
				var li = el( 'li', 'saha-ql__item' );
				var media = el( 'span', 'saha-ql__thumb' );

				li.setAttribute( 'data-key', key );

				if ( invalid.indexOf( parseInt( item.id, 10 ) ) > -1 ) {
					li.classList.add( 'is-invalid' );
				}

				if ( item.image ) {
					var img = el( 'img' );

					img.src = item.image;
					img.alt = '';
					img.width = 64;
					img.height = 64;
					img.loading = 'lazy';
					media.appendChild( img );
				}

				var info = el( 'div', 'saha-ql__info' );
				var name = item.url ? el( 'a', 'saha-ql__name', item.name ) : el( 'span', 'saha-ql__name', item.name );

				if ( item.url ) {
					name.href = item.url;
				}

				info.appendChild( name );

				var meta = [ item.attrs, item.sku ? i18n.sku + ': ' + item.sku : '' ].filter( Boolean ).join( ' · ' );

				if ( meta ) {
					info.appendChild( el( 'span', 'saha-ql__meta', meta ) );
				}

				if ( li.classList.contains( 'is-invalid' ) ) {
					info.appendChild( el( 'span', 'saha-ql__invalid', i18n.invalid ) );
				}

				var note = el( 'input', 'saha-ql__note' );

				note.type = 'text';
				note.maxLength = 255;
				note.value = item.note || '';
				note.placeholder = i18n.note;
				note.setAttribute( 'aria-label', sprintf( i18n.noteOf, item.name ) );
				note.setAttribute( 'data-field', 'note' );
				info.appendChild( note );

				var qty = el( 'input', 'saha-ql__qty' );

				qty.type = 'number';
				qty.inputMode = 'numeric';
				qty.min = 1;
				qty.max = cfg.maxQty;
				qty.value = item.qty;
				qty.setAttribute( 'aria-label', sprintf( i18n.qtyOf, item.name ) );
				qty.setAttribute( 'data-field', 'qty' );

				var remove = el( 'button', 'saha-ql__remove' );

				remove.type = 'button';
				remove.setAttribute( 'aria-label', sprintf( i18n.remove, item.name ) );
				remove.setAttribute( 'data-remove', '1' );
				remove.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>';

				li.appendChild( media );
				li.appendChild( info );
				li.appendChild( qty );
				li.appendChild( remove );
				ul.appendChild( li );
			} );

			listWrap.appendChild( ul );

			if ( focusKey ) {
				var next = ul.querySelector( '[data-key="' + focusKey + '"] [data-remove]' ) || ul.querySelector( '[data-remove]' );

				if ( next ) {
					next.focus();
				}
			}
		}

		// Sửa số lượng / ghi chú: lưu ngay, không dựng lại danh sách (giữ focus khi gõ).
		listWrap.addEventListener( 'input', function ( event ) {
			var input = event.target;
			var li = input.closest( '.saha-ql__item' );

			if ( ! li || ! input.getAttribute( 'data-field' ) ) {
				return;
			}

			// Lưu ngay (localStorage rẻ) — không debounce chung, tránh sửa ô này làm mất thay đổi ô kia.
			( function () {
				var items = load();

				items.forEach( function ( item ) {
					if ( keyOf( item ) !== li.getAttribute( 'data-key' ) ) {
						return;
					}

					if ( 'qty' === input.getAttribute( 'data-field' ) ) {
						var value = parseInt( input.value, 10 );

						if ( value >= 1 ) {
							item.qty = Math.min( cfg.maxQty, value );
						}
					} else {
						item.note = input.value.slice( 0, 255 );
					}
				} );

				save( items );
			}() );
		} );

		listWrap.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '[data-remove]' );

			if ( ! button ) {
				return;
			}

			var li = button.closest( '.saha-ql__item' );
			var key = li.getAttribute( 'data-key' );
			var after = li.nextElementSibling || li.previousElementSibling;
			var items = load().filter( function ( item ) {
				return keyOf( item ) !== key;
			} );

			save( items );
			render( after ? after.getAttribute( 'data-key' ) : '' );
			toast( i18n.removed, false );
		} );

		function setFieldError( name, message ) {
			var input = formWrap.querySelector( '[name="' + name + '"]' );
			var error = formWrap.querySelector( '#saha-ql-' + name + '-error' );

			if ( ! input || ! error ) {
				return;
			}

			error.textContent = message || '';
			error.hidden = ! message;

			if ( message ) {
				input.setAttribute( 'aria-invalid', 'true' );
				input.setAttribute( 'aria-describedby', error.id );
			} else {
				input.removeAttribute( 'aria-invalid' );
				input.removeAttribute( 'aria-describedby' );
			}
		}

		function clearErrors() {
			[ 'name', 'phone', 'email', 'company', 'message' ].forEach( function ( name ) {
				setFieldError( name, '' );
			} );
		}

		/**
		 * POST danh sách. Nonce in trong trang trước; trang lấy từ page cache (nonce hết hạn) →
		 * 403 → lấy nonce mới ở /saha/v1/nonce rồi gửi lại một lần (giống form báo giá của theme).
		 */
		function send( payload, nonce, isRetry ) {
			return window.fetch( cfg.endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-WP-Nonce': nonce || '' },
				body: payload
			} )
				.then( function ( response ) {
					return response.json().catch( function () {
						return {};
					} ).then( function ( json ) {
						return { ok: response.ok, status: response.status, json: json || {} };
					} );
				} )
				.then( function ( result ) {
					if ( isRetry || 403 !== result.status ) {
						return result;
					}

					return window.fetch( cfg.nonceUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } } )
						.then( function ( response ) {
							return response.json();
						} )
						.then( function ( json ) {
							cfg.nonce = ( json && json.data && json.data.nonce ) || '';

							return send( payload, cfg.nonce, true );
						} );
				} );
		}

		formWrap.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			clearErrors();

			var items = load();
			var data = new window.FormData( formWrap );
			var firstBad = null;

			[ 'name', 'phone' ].forEach( function ( name ) {
				if ( '' === String( data.get( name ) || '' ).trim() ) {
					setFieldError( name, formWrap.querySelector( '[name="' + name + '"]' ).validationMessage || i18n.required );
					firstBad = firstBad || formWrap.querySelector( '[name="' + name + '"]' );
				}
			} );

			if ( firstBad ) {
				firstBad.focus();
				return;
			}

			submit.disabled = true;
			submit.textContent = i18n.sending;

			var payload = JSON.stringify( {
							name: data.get( 'name' ),
							phone: data.get( 'phone' ),
							email: data.get( 'email' ),
							company: data.get( 'company' ),
							message: data.get( 'message' ),
							saha_hp_email: data.get( 'saha_hp_email' ),
							source_url: window.location.href,
							items: items.map( function ( item ) {
								return {
									product_id: parseInt( item.id, 10 ),
									variation_id: parseInt( item.vid, 10 ) || 0,
									quantity: parseInt( item.qty, 10 ) || 1,
									note: String( item.note || '' )
								};
							} )
						} );

			send( payload, cfg.nonce, false )
				.then( function ( result ) {
					if ( result.ok && result.json.success !== false ) {
						save( [] );
						formWrap.reset();
						invalid = [];
						render();
						showStatus( ( result.json.message || '' ) + ' ' + i18n.successNext, true );
						status.focus();
						return;
					}

					var errors = result.json.errors || {};

					invalid = ( result.json.data && result.json.data.invalid ) || [];

					Object.keys( errors ).forEach( function ( name ) {
						setFieldError( name, errors[ name ] );
					} );

					if ( invalid.length || errors.items ) {
						render();
					}

					showStatus( errors.items || result.json.message || i18n.error, false );

					var bad = formWrap.querySelector( '[aria-invalid="true"]' );

					( bad || status ).focus();
				} )
				.catch( function () {
					showStatus( i18n.error, false );
				} )
				.then( function () {
					submit.disabled = false;
					submit.textContent = i18n.submit;
				} );
		} );

		render();

		window.addEventListener( 'storage', function ( event ) {
			if ( KEY === event.key ) {
				render();
			}
		} );
	}

	function boot() {
		badges();
		Array.prototype.forEach.call( document.querySelectorAll( '[data-saha-quote-list]' ), initPage );
	}

	window.addEventListener( 'storage', function ( event ) {
		if ( KEY === event.key ) {
			badges();
		}
	} );

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
