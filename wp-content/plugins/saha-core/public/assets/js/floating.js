/**
 * Nút nổi: mở/đóng danh sách liên hệ (aria-expanded, Esc, bấm ra ngoài), nút lên đầu trang.
 */
( function () {
	'use strict';

	var root = document.querySelector( '[data-saha-fab]' );

	if ( ! root ) {
		return;
	}

	var toggle = root.querySelector( '.saha-fab__toggle' );
	var menu = root.querySelector( '.saha-fab__menu' );
	var top = root.querySelector( '[data-saha-fab-top]' );

	function setOpen( open ) {
		if ( ! toggle || ! menu ) {
			return;
		}

		toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		menu.hidden = ! open;

		if ( open ) {
			var first = menu.querySelector( 'a, button' );

			if ( first ) {
				first.focus();
			}
		}
	}

	if ( toggle ) {
		toggle.addEventListener( 'click', function () {
			setOpen( 'true' !== toggle.getAttribute( 'aria-expanded' ) );
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( 'true' === toggle.getAttribute( 'aria-expanded' ) && ! root.contains( event.target ) ) {
				setOpen( false );
			}
		} );

		root.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && 'true' === toggle.getAttribute( 'aria-expanded' ) ) {
				setOpen( false );
				toggle.focus();
			}
		} );

		// Bấm "Yêu cầu báo giá" → đóng danh sách (modal của theme mở).
		menu.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-saha-open-quote]' ) ) {
				setOpen( false );
			}
		} );
	}

	if ( top ) {
		var ticking = false;

		window.addEventListener( 'scroll', function () {
			if ( ticking ) {
				return;
			}

			ticking = true;
			window.requestAnimationFrame( function () {
				ticking = false;
				top.hidden = window.scrollY < window.innerHeight;
			} );
		}, { passive: true } );

		top.addEventListener( 'click', function ( event ) {
			event.preventDefault();

			var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

			window.scrollTo( { top: 0, behavior: reduce ? 'auto' : 'smooth' } );
		} );
	}
}() );
