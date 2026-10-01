/**
 * SAHA Builder — header frontend (không jQuery, nạp defer ở cuối trang).
 *
 * - Menu di động (off-canvas): nút [data-saha-offcanvas] mở bảng #id; aria-expanded,
 *   Esc để đóng, giữ focus trong bảng, trả focus về nút; khoá cuộn trang khi mở.
 * - Dính khi cuộn: header[data-saha-sticky="always|up"] → class is-stuck; chế độ "up"
 *   thêm is-hidden khi cuộn xuống, bỏ khi cuộn lên.
 */
( function () {
	'use strict';

	var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

	function initOffcanvas( toggle ) {
		var panel = document.getElementById( toggle.getAttribute( 'data-saha-offcanvas' ) );

		if ( ! panel ) {
			return;
		}

		var dialog = panel.querySelector( '.saha-hb-offcanvas__panel' );

		function focusables() {
			return Array.prototype.filter.call( dialog.querySelectorAll( FOCUSABLE ), function ( el ) {
				return el.offsetParent !== null;
			} );
		}

		function onKeydown( event ) {
			if ( 'Escape' === event.key ) {
				close();
				return;
			}

			if ( 'Tab' !== event.key ) {
				return;
			}

			var items = focusables();

			if ( ! items.length ) {
				return;
			}

			var first = items[ 0 ];
			var last = items[ items.length - 1 ];
			var active = dialog.ownerDocument.activeElement;

			if ( event.shiftKey && active === first ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && active === last ) {
				event.preventDefault();
				first.focus();
			}
		}

		function open() {
			panel.hidden = false;
			window.requestAnimationFrame( function () {
				panel.classList.add( 'is-open' );
			} );
			toggle.setAttribute( 'aria-expanded', 'true' );
			document.documentElement.classList.add( 'saha-hb-lock' );
			document.addEventListener( 'keydown', onKeydown );

			var items = focusables();
			( items[ 0 ] || dialog ).focus();
		}

		function close() {
			if ( panel.hidden ) {
				return;
			}

			panel.classList.remove( 'is-open' );
			panel.hidden = true;
			toggle.setAttribute( 'aria-expanded', 'false' );
			document.documentElement.classList.remove( 'saha-hb-lock' );
			document.removeEventListener( 'keydown', onKeydown );
			toggle.focus();
		}

		toggle.addEventListener( 'click', function () {
			if ( panel.hidden ) {
				open();
			} else {
				close();
			}
		} );

		panel.addEventListener( 'click', function ( event ) {
			// Nút đóng, nền mờ, hoặc link neo trong trang (#…) → đóng.
			var link = event.target.closest( 'a[href^="#"]' );

			if ( event.target.closest( '[data-saha-close]' ) || link ) {
				close();
			}
		} );

		window.matchMedia( '(min-width: 1025px)' ).addEventListener( 'change', function ( mq ) {
			if ( mq.matches ) {
				close();
			}
		} );
	}

	function initSticky( header ) {
		var mode = header.getAttribute( 'data-saha-sticky' );

		if ( 'always' !== mode && 'up' !== mode ) {
			return;
		}

		var lastY = window.scrollY;
		var ticking = false;

		function update() {
			var y = window.scrollY;

			header.classList.toggle( 'is-stuck', y > 0 );

			if ( 'up' === mode ) {
				header.classList.toggle( 'is-hidden', y > header.offsetHeight && y > lastY );
			}

			lastY = y;
			ticking = false;
		}

		window.addEventListener(
			'scroll',
			function () {
				if ( ! ticking ) {
					ticking = true;
					window.requestAnimationFrame( update );
				}
			},
			{ passive: true }
		);

		update();
	}

	function init() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-saha-offcanvas]' ), initOffcanvas );
		Array.prototype.forEach.call( document.querySelectorAll( 'header[data-saha-sticky]' ), initSticky );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
