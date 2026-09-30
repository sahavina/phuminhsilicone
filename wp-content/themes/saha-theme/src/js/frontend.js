/**
 * JS frontend của SAHA Theme — không phụ thuộc jQuery.
 *
 * - Off-canvas menu di động: aria-expanded, Esc để đóng, giữ focus trong panel,
 *   trả focus về nút mở.
 * - Header dính: thêm `is-stuck` khi đã cuộn; chế độ scrollUp ẩn header khi cuộn xuống.
 */
import '../scss/frontend.scss';

const FOCUSABLE =
	'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

function initOffcanvas() {
	const burger = document.querySelector( '.saha-header__burger' );
	const panel = document.getElementById( 'saha-offcanvas' );

	if ( ! burger || ! panel ) {
		return;
	}

	const dialog = panel.querySelector( '.saha-offcanvas__panel' );

	const onKeydown = ( event ) => {
		if ( event.key === 'Escape' ) {
			close();
			return;
		}

		if ( event.key !== 'Tab' ) {
			return;
		}

		const items = [ ...dialog.querySelectorAll( FOCUSABLE ) ];

		if ( ! items.length ) {
			return;
		}

		const first = items[ 0 ];
		const last = items[ items.length - 1 ];

		if ( event.shiftKey && dialog.ownerDocument.activeElement === first ) {
			event.preventDefault();
			last.focus();
		} else if (
			! event.shiftKey &&
			dialog.ownerDocument.activeElement === last
		) {
			event.preventDefault();
			first.focus();
		}
	};

	function open() {
		panel.hidden = false;
		// Frame kế tiếp mới thêm class để transition chạy.
		window.requestAnimationFrame( () => panel.classList.add( 'is-open' ) );
		burger.setAttribute( 'aria-expanded', 'true' );
		document.body.classList.add( 'saha-no-scroll' );
		document.addEventListener( 'keydown', onKeydown );
		dialog.querySelector( FOCUSABLE )?.focus();
	}

	function close() {
		if ( panel.hidden ) {
			return;
		}

		panel.classList.remove( 'is-open' );
		panel.hidden = true;
		burger.setAttribute( 'aria-expanded', 'false' );
		document.body.classList.remove( 'saha-no-scroll' );
		document.removeEventListener( 'keydown', onKeydown );
		burger.focus();
	}

	burger.addEventListener( 'click', () =>
		panel.hidden ? open() : close()
	);

	panel.addEventListener( 'click', ( event ) => {
		if ( event.target.closest( '[data-saha-close]' ) ) {
			close();
		}
	} );

	// Chuyển sang desktop khi panel đang mở → đóng.
	window
		.matchMedia( '(min-width: 1025px)' )
		.addEventListener( 'change', ( mq ) => {
			if ( mq.matches ) {
				close();
			}
		} );
}

function initStickyHeader() {
	const header = document.querySelector( '.saha-header--sticky' );

	if ( ! header ) {
		return;
	}

	const hideOnScrollDown = header.classList.contains(
		'saha-header--sticky-up'
	);
	let lastY = window.scrollY;
	let ticking = false;

	const update = () => {
		const y = window.scrollY;
		const threshold = header.offsetHeight;

		header.classList.toggle( 'is-stuck', y > 0 );

		if ( hideOnScrollDown ) {
			header.classList.toggle( 'is-hidden', y > threshold && y > lastY );
		}

		lastY = y;
		ticking = false;
	};

	window.addEventListener(
		'scroll',
		() => {
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
	initOffcanvas();
	initStickyHeader();
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
