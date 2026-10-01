/**
 * Thanh "Thêm vào giỏ" dính (saha-core WooCommerce\StickyCart).
 *
 * Hiện khi khối mua trên trang (form giỏ / CTA báo giá) đã cuộn lên khỏi màn hình.
 * Nút trên thanh bấm hộ nút gốc của WooCommerce; biến thể chưa chọn → cuộn về form.
 */
( function () {
	'use strict';

	var bar = document.querySelector( '[data-saha-sticky-cart]' );

	if ( ! bar || ! ( 'IntersectionObserver' in window ) ) {
		return;
	}

	var form = document.querySelector( '.product form.cart, .saha-template-product form.cart' );
	var anchor = form || document.querySelector( '.saha-product-cta, .product .summary, .saha-product-summary' );

	if ( ! anchor ) {
		return;
	}

	new IntersectionObserver( function ( entries ) {
		var entry = entries[ 0 ];

		// Chỉ hiện khi khối mua nằm phía trên màn hình (đã cuộn qua), không phải chưa tới.
		bar.hidden = entry.isIntersecting || entry.boundingClientRect.top > 0;
		document.body.classList.toggle( 'saha-sticky-cart-open', ! bar.hidden );
	} ).observe( anchor );

	function needsChoice() {
		var variation = form ? form.querySelector( 'input[name="variation_id"]' ) : null;

		return !! variation && ( ! variation.value || '0' === variation.value );
	}

	function goToForm() {
		var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

		form.scrollIntoView( { behavior: reduce ? 'auto' : 'smooth', block: 'center' } );

		var first = form.querySelector( '.saha-swatch:not([disabled]), select, input.qty' );

		if ( first ) {
			first.focus( { preventScroll: true } );
		}
	}

	function press( selector ) {
		if ( ! form ) {
			return;
		}

		if ( needsChoice() ) {
			goToForm();
			return;
		}

		var button = form.querySelector( selector );

		if ( button && ! button.disabled && ! button.classList.contains( 'disabled' ) ) {
			button.click();
		} else {
			goToForm();
		}
	}

	var add = bar.querySelector( '[data-saha-sticky-add]' );
	var buy = bar.querySelector( '[data-saha-sticky-buy]' );

	if ( add ) {
		add.addEventListener( 'click', function () {
			press( '.single_add_to_cart_button' );
		} );
	}

	if ( buy ) {
		buy.addEventListener( 'click', function () {
			press( '.saha-buy-now' );
		} );
	}
}() );
