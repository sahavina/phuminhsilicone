/**
 * Xem nhanh (saha-core WooCommerce\QuickView).
 *
 * Bấm [data-saha-quick-view] → lấy HTML từ REST → hiện trong <dialog> (showModal: Esc đóng,
 * focus nằm trong hộp; đóng → focus về nút đã bấm). Form thêm giỏ của WooCommerce được
 * gửi bằng Store API (không rời trang); "Mua ngay" → trang thanh toán.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.sahaQuickView;

	if ( ! cfg || ! window.HTMLDialogElement ) {
		return;
	}

	var dialog = null;
	var body = null;
	var opener = null;

	function build() {
		dialog = document.createElement( 'dialog' );
		dialog.className = 'saha-qv-dialog';
		dialog.setAttribute( 'aria-labelledby', 'saha-qv-title' );
		dialog.innerHTML = '<button type="button" class="saha-qv-dialog__close" aria-label="' + cfg.i18n.close + '">&times;</button><div class="saha-qv-dialog__body"></div>';
		document.body.appendChild( dialog );

		body = dialog.querySelector( '.saha-qv-dialog__body' );

		dialog.querySelector( '.saha-qv-dialog__close' ).addEventListener( 'click', function () {
			dialog.close();
		} );

		// Bấm nền mờ (ngoài hộp) → đóng.
		dialog.addEventListener( 'click', function ( event ) {
			if ( event.target === dialog ) {
				dialog.close();
			}

			// Nút báo giá: đóng hộp trước để modal báo giá của theme hiện lên trên.
			if ( event.target.closest( '[data-saha-open-quote]' ) ) {
				dialog.close();
			}
		} );

		dialog.addEventListener( 'close', function () {
			body.innerHTML = '';

			if ( opener ) {
				opener.focus();
			}
		} );

		dialog.addEventListener( 'submit', submit );
	}

	function notice( text, isError, link ) {
		var box = body.querySelector( '.saha-qv__notice' );

		if ( ! box ) {
			return;
		}

		box.className = 'saha-qv__notice' + ( isError ? ' is-error' : ' is-success' );
		box.textContent = text;

		if ( link ) {
			var a = document.createElement( 'a' );

			a.href = cfg.cartUrl;
			a.textContent = ' ' + cfg.i18n.viewCart + ' →';
			box.appendChild( a );
		}
	}

	function open( id ) {
		if ( ! dialog ) {
			build();
		}

		body.innerHTML = '<p class="saha-qv__loading">' + cfg.i18n.loading + '</p>';
		dialog.showModal();

		window.fetch( cfg.endpoint + encodeURIComponent( id ) + '/quick-view', { credentials: 'same-origin', headers: { Accept: 'application/json' } } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				if ( ! json || ! json.success || ! json.data || ! json.data.html ) {
					throw new Error( 'empty' );
				}

				body.innerHTML = json.data.html;

				var form = body.querySelector( '.variations_form' );

				if ( form && $ && $.fn.wc_variation_form ) {
					$( form ).wc_variation_form();
				}

				if ( window.sahaSwatches ) {
					window.sahaSwatches( body );
				}

				var title = body.querySelector( '#saha-qv-title' );

				if ( title ) {
					title.setAttribute( 'tabindex', '-1' );
					title.focus();
				}
			} )
			.catch( function () {
				body.innerHTML = '<p class="saha-qv__loading">' + cfg.i18n.error + '</p>';
			} );
	}

	function submit( event ) {
		var form = event.target;

		if ( ! form.classList || ! form.classList.contains( 'cart' ) ) {
			return;
		}

		event.preventDefault();

		var data = new window.FormData( form );
		var buyNow = event.submitter && 'saha-buy-now' === event.submitter.name;
		var base = form.querySelector( '[name="add-to-cart"]' );
		var id = parseInt( data.get( 'variation_id' ) || '0', 10 ) || parseInt( data.get( 'add-to-cart' ) || ( base && base.value ) || '0', 10 );
		var variation = [];

		data.forEach( function ( value, key ) {
			if ( 0 === key.indexOf( 'attribute_' ) && value ) {
				variation.push( { attribute: key.replace( /^attribute_/, '' ), value: value } );
			}
		} );

		var buttons = form.querySelectorAll( 'button' );

		Array.prototype.forEach.call( buttons, function ( b ) {
			b.disabled = true;
		} );

		window.fetch( cfg.storeApi, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', Nonce: cfg.nonce },
			body: JSON.stringify( { id: id, quantity: parseFloat( data.get( 'quantity' ) || '1' ), variation: variation } )
		} )
			.then( function ( response ) {
				return response.json().then( function ( json ) {
					return { ok: response.ok, json: json };
				} );
			} )
			.then( function ( result ) {
				if ( ! result.ok ) {
					throw new Error( ( result.json && result.json.message ) || cfg.i18n.error );
				}

				if ( buyNow ) {
					window.location.href = cfg.checkout;
					return;
				}

				notice( cfg.i18n.added, false, true );

				// Số trên icon giỏ (element Giỏ hàng / header theme): lấy từ giỏ Store API trả về —
				// không phụ thuộc wc-cart-fragments (saha-core có thể tắt để trang nhẹ hơn).
				if ( result.json && 'number' === typeof result.json.items_count ) {
					Array.prototype.forEach.call( document.querySelectorAll( '.saha-cart-count' ), function ( el ) {
						el.textContent = String( result.json.items_count );
					} );
				}

				if ( $ ) {
					$( document.body ).trigger( 'wc_fragment_refresh' );
				}
			} )
			.catch( function ( error ) {
				var tmp = document.createElement( 'div' );

				tmp.innerHTML = error.message;
				notice( tmp.textContent, true, false );
			} )
			.then( function () {
				Array.prototype.forEach.call( buttons, function ( b ) {
					b.disabled = false;
				} );
			} );
	}

	document.addEventListener( 'click', function ( event ) {
		var trigger = event.target.closest( '[data-saha-quick-view]' );

		if ( ! trigger ) {
			return;
		}

		event.preventDefault();
		opener = trigger;
		open( trigger.getAttribute( 'data-saha-quick-view' ) );
	} );
}( window.jQuery ) );
