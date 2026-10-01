/**
 * Ô chọn biến thể (saha-core WooCommerce\Swatches).
 *
 * Ô bấm chỉ điều khiển <select> gốc của WooCommerce: chọn ô → đặt giá trị select +
 * bắn "change" (jQuery, vì wc-add-to-cart-variation nghe bằng jQuery). WooCommerce bỏ
 * các <option> không còn hợp lệ → ô tương ứng bị vô hiệu. Nhóm radio: ←/→ chuyển ô.
 *
 * Dùng được cho form nạp sau (Xem nhanh): gọi window.sahaSwatches( container ).
 */
( function ( $ ) {
	'use strict';

	if ( ! $ ) {
		return;
	}

	function setup( group ) {
		if ( group.getAttribute( 'data-saha-ready' ) ) {
			return;
		}

		group.setAttribute( 'data-saha-ready', '1' );

		var select = group.nextElementSibling ? group.nextElementSibling.querySelector( 'select' ) : null;
		var buttons = Array.prototype.slice.call( group.querySelectorAll( '.saha-swatch' ) );

		if ( ! select || ! buttons.length ) {
			return;
		}

		function sync() {
			var value = select.value;
			var available = {};
			var current = null;

			Array.prototype.forEach.call( select.options, function ( option ) {
				if ( option.value && ! option.disabled ) {
					available[ option.value ] = true;
				}
			} );

			buttons.forEach( function ( button ) {
				var on = button.getAttribute( 'data-value' ) === value;

				button.setAttribute( 'aria-checked', on ? 'true' : 'false' );
				button.disabled = ! available[ button.getAttribute( 'data-value' ) ];
				button.setAttribute( 'tabindex', '-1' );

				if ( on ) {
					current = button;
				}
			} );

			// Một ô luôn nhận Tab (ô đang chọn, hoặc ô dùng được đầu tiên).
			( current || buttons.filter( function ( b ) {
				return ! b.disabled;
			} )[ 0 ] || buttons[ 0 ] ).setAttribute( 'tabindex', '0' );
		}

		function choose( button ) {
			if ( button.disabled ) {
				return;
			}

			var value = button.getAttribute( 'data-value' );

			// Bấm lại ô đang chọn → bỏ chọn (như chọn "Chọn một tùy chọn").
			$( select ).val( select.value === value ? '' : value ).trigger( 'change' );
		}

		buttons.forEach( function ( button, i ) {
			button.addEventListener( 'click', function () {
				choose( button );
			} );

			button.addEventListener( 'keydown', function ( event ) {
				var step = 'ArrowRight' === event.key || 'ArrowDown' === event.key ? 1 : ( 'ArrowLeft' === event.key || 'ArrowUp' === event.key ? -1 : 0 );

				if ( ! step ) {
					return;
				}

				event.preventDefault();

				for ( var n = 1; n <= buttons.length; n++ ) {
					var next = buttons[ ( i + step * n + buttons.length * n ) % buttons.length ];

					if ( ! next.disabled ) {
						next.focus();
						choose( next );
						break;
					}
				}
			} );
		} );

		$( select ).on( 'change', sync );
		$( select ).closest( 'form' ).on( 'woocommerce_update_variation_values reset_data', function () {
			window.setTimeout( sync, 0 );
		} );

		sync();
	}

	window.sahaSwatches = function ( root ) {
		Array.prototype.forEach.call( ( root || document ).querySelectorAll( '[data-saha-swatches]' ), setup );
	};

	$( function () {
		window.sahaSwatches( document );
	} );
}( window.jQuery ) );
