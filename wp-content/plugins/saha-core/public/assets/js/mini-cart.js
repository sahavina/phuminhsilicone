/**
 * Ngăn giỏ hàng trượt (saha-core WooCommerce\MiniCart).
 *
 * Icon giỏ [data-saha-mini-cart] → <dialog> bên phải (Esc / nền / × đóng, focus trả về icon).
 * Dữ liệu: Store API /wc/store/v1/cart. Mở tự động sau `added_to_cart` của WooCommerce
 * (thẻ sản phẩm) hoặc khi Xem nhanh gọi window.sahaMiniCart.open( cart ).
 * Không JS: icon vẫn là link tới trang giỏ hàng.
 */
( function () {
	'use strict';

	var cfg = window.sahaMiniCart;

	if ( ! cfg || ! window.HTMLDialogElement || ! window.fetch ) {
		return;
	}

	var dialog = null;
	var body = null;
	var foot = null;
	var status = null;
	var countEl = null;
	var opener = null;
	var busy = false;

	function decode( value ) {
		var t = document.createElement( 'textarea' );

		t.innerHTML = null == value ? '' : String( value );

		return t.value;
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

	function format( minor, money ) {
		var unit = parseInt( money.currency_minor_unit, 10 ) || 0;
		var value = ( parseInt( minor, 10 ) || 0 ) / Math.pow( 10, unit );
		var parts = value.toFixed( unit ).split( '.' );

		parts[ 0 ] = parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, money.currency_thousand_separator || '' );

		return decode( money.currency_prefix ) + parts.join( money.currency_decimal_separator || '.' ) + decode( money.currency_suffix );
	}

	function sprintf( template, value ) {
		return String( template ).replace( /%[sd]/, value );
	}

	function request( path, method, data ) {
		var init = {
			method: method || 'GET',
			credentials: 'same-origin',
			headers: { Accept: 'application/json' }
		};

		if ( data ) {
			init.headers[ 'Content-Type' ] = 'application/json';
			init.headers.Nonce = cfg.nonce;
			init.body = JSON.stringify( data );
		}

		return window.fetch( cfg.storeApi + path, init ).then( function ( response ) {
			var nonce = response.headers.get( 'Nonce' );

			if ( nonce ) {
				cfg.nonce = nonce;
			}

			return response.json().then( function ( json ) {
				if ( ! response.ok ) {
					throw new Error( decode( ( json && json.message ) || cfg.i18n.error ).replace( /<[^>]*>/g, '' ) );
				}

				return json;
			} );
		} );
	}

	/** Cập nhật số trên mọi icon giỏ (+ chữ cho trình đọc màn hình). */
	function syncCount( count ) {
		Array.prototype.forEach.call( document.querySelectorAll( '.saha-cart-count' ), function ( node ) {
			node.textContent = String( count );
		} );

		Array.prototype.forEach.call( document.querySelectorAll( '.saha-cart-sr' ), function ( node ) {
			node.textContent = sprintf( cfg.i18n.countText, count );
		} );
	}

	function build() {
		dialog = el( 'dialog', 'saha-mc' );
		dialog.setAttribute( 'aria-labelledby', 'saha-mc-title' );

		var head = el( 'div', 'saha-mc__head' );
		var title = el( 'h2', 'saha-mc__title', cfg.i18n.title + ' ' );
		var close = el( 'button', 'saha-mc__close' );

		title.id = 'saha-mc-title';
		title.setAttribute( 'tabindex', '-1' );
		countEl = el( 'span', 'saha-mc__count', '' );
		title.appendChild( countEl );
		close.type = 'button';
		close.setAttribute( 'aria-label', cfg.i18n.close );
		close.innerHTML = '&times;';
		head.appendChild( title );
		head.appendChild( close );

		status = el( 'p', 'screen-reader-text' );
		status.setAttribute( 'role', 'status' );

		body = el( 'div', 'saha-mc__body' );
		foot = el( 'div', 'saha-mc__foot' );

		dialog.appendChild( head );
		dialog.appendChild( status );
		dialog.appendChild( body );
		dialog.appendChild( foot );
		document.body.appendChild( dialog );

		close.addEventListener( 'click', function () {
			dialog.close();
		} );

		// Bấm nền mờ (ngoài ngăn) → đóng.
		dialog.addEventListener( 'click', function ( event ) {
			if ( event.target === dialog ) {
				dialog.close();
			}
		} );

		dialog.addEventListener( 'close', function () {
			document.documentElement.classList.remove( 'saha-mc-open' );

			if ( opener && document.contains( opener ) ) {
				opener.focus();
			}

			opener = null;
		} );

		body.addEventListener( 'click', onBodyClick );
		body.addEventListener( 'change', onQtyChange );
	}

	function render( cart ) {
		var items = ( cart && cart.items ) || [];
		var count = parseInt( cart && cart.items_count, 10 ) || 0;

		syncCount( count );
		countEl.textContent = '(' + count + ')';
		body.innerHTML = '';
		foot.innerHTML = '';

		if ( ! items.length ) {
			var empty = el( 'div', 'saha-mc__empty' );
			var shop = el( 'a', 'button saha-mc__btn', cfg.i18n.continue );

			shop.href = cfg.shopUrl;
			empty.appendChild( el( 'p', '', cfg.i18n.empty ) );
			empty.appendChild( shop );
			body.appendChild( empty );
			return;
		}

		var list = el( 'ul', 'saha-mc__items' );

		items.forEach( function ( item ) {
			var name = decode( item.name );
			var li = el( 'li', 'saha-mc__item' );
			var image = item.images && item.images[ 0 ];
			var thumb = el( 'a', 'saha-mc__thumb' );

			li.setAttribute( 'data-key', item.key );
			thumb.href = item.permalink;
			thumb.tabIndex = -1;
			thumb.setAttribute( 'aria-hidden', 'true' );

			if ( image && image.thumbnail ) {
				var img = el( 'img' );

				img.src = image.thumbnail;
				img.alt = '';
				img.width = 64;
				img.height = 64;
				img.loading = 'lazy';
				thumb.appendChild( img );
			}

			var info = el( 'div', 'saha-mc__info' );
			var link = el( 'a', 'saha-mc__name', name );

			link.href = item.permalink;
			info.appendChild( link );

			var variation = ( item.variation || [] ).map( function ( v ) {
				return decode( v.attribute ) + ': ' + decode( v.value );
			} ).join( ', ' );

			if ( variation ) {
				info.appendChild( el( 'span', 'saha-mc__variation', variation ) );
			}

			var row = el( 'div', 'saha-mc__row' );
			var limits = item.quantity_limits || {};
			var editable = false !== limits.editable;

			if ( editable ) {
				var qty = el( 'div', 'saha-mc__qty' );
				var minus = el( 'button', 'saha-mc__step', '−' );
				var input = el( 'input', 'saha-mc__input' );
				var plus = el( 'button', 'saha-mc__step', '+' );

				minus.type = 'button';
				plus.type = 'button';
				minus.setAttribute( 'data-step', '-1' );
				plus.setAttribute( 'data-step', '1' );
				minus.setAttribute( 'aria-label', cfg.i18n.decrease + ' ' + name );
				plus.setAttribute( 'aria-label', cfg.i18n.increase + ' ' + name );
				input.type = 'number';
				input.inputMode = 'numeric';
				input.value = item.quantity;
				input.min = limits.minimum || 1;
				input.step = limits.multiple_of || 1;

				if ( limits.maximum ) {
					input.max = limits.maximum;
				}

				input.setAttribute( 'aria-label', sprintf( cfg.i18n.qty, name ) );
				qty.appendChild( minus );
				qty.appendChild( input );
				qty.appendChild( plus );
				row.appendChild( qty );
			} else {
				row.appendChild( el( 'span', 'saha-mc__qty-fixed', '× ' + item.quantity ) );
			}

			row.appendChild( el( 'span', 'saha-mc__price', format( item.totals.line_subtotal, item.totals ) ) );
			info.appendChild( row );

			var remove = el( 'button', 'saha-mc__remove' );

			remove.type = 'button';
			remove.setAttribute( 'data-remove', '1' );
			remove.setAttribute( 'aria-label', sprintf( cfg.i18n.remove, name ) );
			remove.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>';

			li.appendChild( thumb );
			li.appendChild( info );
			li.appendChild( remove );
			list.appendChild( li );
		} );

		body.appendChild( list );

		var subtotal = el( 'p', 'saha-mc__subtotal' );

		subtotal.appendChild( el( 'span', '', cfg.i18n.subtotal ) );
		subtotal.appendChild( el( 'strong', '', format( cart.totals.total_items, cart.totals ) ) );

		var actions = el( 'div', 'saha-mc__actions' );
		var view = el( 'a', 'button saha-mc__btn saha-mc__btn--ghost', cfg.i18n.viewCart );
		var checkout = el( 'a', 'button saha-mc__btn', cfg.i18n.checkout );

		view.href = cfg.cartUrl;
		checkout.href = cfg.checkout;
		actions.appendChild( view );
		actions.appendChild( checkout );
		foot.appendChild( subtotal );
		foot.appendChild( actions );
	}

	function showError( message ) {
		var p = el( 'p', 'saha-mc__error', message || cfg.i18n.error );

		p.setAttribute( 'role', 'alert' );

		var old = body.querySelector( '.saha-mc__error' );

		if ( old ) {
			old.remove();
		}

		body.insertBefore( p, body.firstChild );
	}

	function setBusy( on ) {
		busy = on;
		dialog.classList.toggle( 'is-busy', on );
		body.setAttribute( 'aria-busy', on ? 'true' : 'false' );
	}

	function mutate( path, data ) {
		if ( busy ) {
			return;
		}

		// Danh sách được dựng lại → nhớ nút đang focus (dòng + loại nút) để focus lại sau đó.
		var focused = document.activeElement;
		var row = focused && body.contains( focused ) ? focused.closest( '.saha-mc__item' ) : null;
		var restore = row ? {
			key: row.getAttribute( 'data-key' ),
			selector: focused.hasAttribute( 'data-step' ) ? '[data-step="' + focused.getAttribute( 'data-step' ) + '"]' : '.saha-mc__input'
		} : null;

		setBusy( true );

		request( path, 'POST', data )
			.then( function ( cart ) {
				render( cart );

				var again = restore && Array.prototype.filter.call( body.querySelectorAll( '.saha-mc__item' ), function ( li ) {
					return li.getAttribute( 'data-key' ) === restore.key;
				} )[ 0 ];
				var target = again ? again.querySelector( restore.selector ) : null;

				( target || dialog.querySelector( '#saha-mc-title' ) ).focus();
				status.textContent = cfg.i18n.updated;
				document.dispatchEvent( new window.CustomEvent( 'saha:cart:updated', { detail: cart } ) );
			} )
			.catch( function ( error ) {
				showError( error.message );
			} )
			.then( function () {
				setBusy( false );
			} );
	}

	function onBodyClick( event ) {
		var li = event.target.closest( '.saha-mc__item' );

		if ( ! li ) {
			return;
		}

		var key = li.getAttribute( 'data-key' );

		if ( event.target.closest( '[data-remove]' ) ) {
			// Focus không rơi về <body> khi dòng bị xoá.
			dialog.querySelector( '#saha-mc-title' ).focus();
			mutate( '/remove-item', { key: key } );
			return;
		}

		var step = event.target.closest( '[data-step]' );

		if ( step ) {
			var input = li.querySelector( '.saha-mc__input' );
			var by = ( parseInt( input.step, 10 ) || 1 ) * parseInt( step.getAttribute( 'data-step' ), 10 );
			var next = Math.max( 0, ( parseInt( input.value, 10 ) || 0 ) + by );

			if ( input.max && next > parseInt( input.max, 10 ) ) {
				return;
			}

			if ( next < 1 ) {
				dialog.querySelector( '#saha-mc-title' ).focus();
				mutate( '/remove-item', { key: key } );
				return;
			}

			mutate( '/update-item', { key: key, quantity: next } );
		}
	}

	function onQtyChange( event ) {
		if ( ! event.target.classList.contains( 'saha-mc__input' ) ) {
			return;
		}

		var li = event.target.closest( '.saha-mc__item' );
		var value = parseInt( event.target.value, 10 );

		if ( isNaN( value ) || value < 0 ) {
			return;
		}

		mutate( value < 1 ? '/remove-item' : '/update-item', value < 1 ? { key: li.getAttribute( 'data-key' ) } : { key: li.getAttribute( 'data-key' ), quantity: value } );
	}

	/**
	 * Mở ngăn. `cart` (đối tượng giỏ Store API) có sẵn thì hiện luôn, không thì tải.
	 *
	 * @param {Object|undefined}      cart      Giỏ.
	 * @param {boolean}               justAdded Vừa thêm sản phẩm.
	 * @param {HTMLElement|undefined} returnTo  Phần tử nhận focus khi đóng ngăn.
	 */
	function open( cart, justAdded, returnTo ) {
		if ( ! dialog ) {
			build();
		}

		if ( returnTo ) {
			opener = returnTo;
		}

		if ( ! dialog.open ) {
			opener = opener || ( document.activeElement !== document.body ? document.activeElement : null );
			dialog.showModal();
			document.documentElement.classList.add( 'saha-mc-open' );
		}

		dialog.querySelector( '#saha-mc-title' ).focus();

		if ( cart && cart.items ) {
			render( cart );
			status.textContent = justAdded ? cfg.i18n.added : '';
			return;
		}

		if ( ! body.firstChild ) {
			body.appendChild( el( 'p', 'saha-mc__loading', cfg.i18n.loading ) );
		}

		setBusy( true );

		request( '', 'GET' )
			.then( function ( data ) {
				render( data );
				status.textContent = justAdded ? cfg.i18n.added : '';
			} )
			.catch( function ( error ) {
				body.innerHTML = '';
				showError( error.message );
			} )
			.then( function () {
				setBusy( false );
			} );
	}

	window.sahaMiniCart.open = open;

	document.addEventListener( 'click', function ( event ) {
		var trigger = event.target.closest( '[data-saha-mini-cart]' );

		// Ctrl/Cmd/Shift + bấm, chuột giữa → để trình duyệt mở trang giỏ hàng như link thường.
		if ( ! trigger || event.defaultPrevented || event.button || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ) {
			return;
		}

		event.preventDefault();
		opener = trigger;
		open();
	} );

	// Thêm vào giỏ bằng AJAX ở thẻ sản phẩm (wc-add-to-cart.js, sự kiện jQuery).
	if ( window.jQuery ) {
		window.jQuery( document.body ).on( 'added_to_cart', function ( event, fragments, hash, $button ) {
			opener = $button && $button[ 0 ] ? $button[ 0 ] : null;
			open( undefined, true );
		} );
	}
}() );
