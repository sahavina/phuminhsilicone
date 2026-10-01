/**
 * Element builder cần JS (SCC D1): Slider.
 *
 * Slider là dải cuộn ngang CSS scroll-snap (không JS vẫn vuốt được). Script thêm:
 * nút trước/sau, chấm điều hướng, nhãn "n / N" cho từng slide, tự chạy — dừng khi
 * rê chuột, focus vào slider, tab bị ẩn, hoặc người dùng chọn giảm chuyển động.
 */
( function () {
	'use strict';

	var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	function init( root ) {
		if ( root.getAttribute( 'data-saha-ready' ) ) {
			return;
		}

		root.setAttribute( 'data-saha-ready', '1' );

		var track = root.querySelector( '.saha-slider__track' );
		var slides = track ? Array.prototype.slice.call( track.children ) : [];

		if ( ! track || slides.length < 2 ) {
			return;
		}

		slides.forEach( function ( slide, i ) {
			slide.setAttribute( 'aria-label', ( i + 1 ) + ' / ' + slides.length );
		} );

		function step() {
			return slides[ 0 ].getBoundingClientRect().width + ( parseFloat( getComputedStyle( track ).columnGap ) || 0 );
		}

		function index() {
			return Math.round( track.scrollLeft / Math.max( 1, step() ) );
		}

		function maxIndex() {
			return Math.max( 0, Math.round( ( track.scrollWidth - track.clientWidth ) / Math.max( 1, step() ) ) );
		}

		function go( i ) {
			var last = maxIndex();
			var target = i > last ? 0 : ( i < 0 ? last : i );

			track.scrollTo( { left: target * step(), behavior: reduce ? 'auto' : 'smooth' } );
		}

		var prev = root.querySelector( '[data-saha-slider-prev]' );
		var next = root.querySelector( '[data-saha-slider-next]' );

		if ( prev ) {
			prev.addEventListener( 'click', function () {
				go( index() - 1 );
			} );
		}

		if ( next ) {
			next.addEventListener( 'click', function () {
				go( index() + 1 );
			} );
		}

		var dotsBox = root.querySelector( '[data-saha-slider-dots]' );
		var dots = [];

		function renderDots() {
			if ( ! dotsBox ) {
				return;
			}

			dotsBox.innerHTML = '';
			dots = [];

			for ( var i = 0; i <= maxIndex(); i++ ) {
				var dot = document.createElement( 'button' );

				dot.type = 'button';
				dot.className = 'saha-slider__dot';
				dot.setAttribute( 'aria-label', 'Slide ' + ( i + 1 ) );
				dot.addEventListener( 'click', go.bind( null, i ) );
				dotsBox.appendChild( dot );
				dots.push( dot );
			}

			sync();
		}

		function sync() {
			var current = index();

			dots.forEach( function ( dot, i ) {
				dot.setAttribute( 'aria-current', i === current ? 'true' : 'false' );
			} );
		}

		var ticking = false;

		track.addEventListener( 'scroll', function () {
			if ( ! ticking ) {
				ticking = true;
				window.requestAnimationFrame( function () {
					ticking = false;
					sync();
				} );
			}
		}, { passive: true } );

		track.addEventListener( 'keydown', function ( event ) {
			if ( 'ArrowRight' === event.key || 'ArrowLeft' === event.key ) {
				event.preventDefault();
				go( index() + ( 'ArrowRight' === event.key ? 1 : -1 ) );
			}
		} );

		renderDots();
		window.addEventListener( 'resize', renderDots );

		var delay = parseInt( root.getAttribute( 'data-autoplay' ) || '0', 10 );

		if ( delay > 0 && ! reduce ) {
			var paused = false;

			root.addEventListener( 'mouseenter', function () {
				paused = true;
			} );
			root.addEventListener( 'mouseleave', function () {
				paused = false;
			} );
			root.addEventListener( 'focusin', function () {
				paused = true;
			} );
			root.addEventListener( 'focusout', function () {
				paused = false;
			} );

			window.setInterval( function () {
				if ( ! paused && ! document.hidden ) {
					go( index() + 1 );
				}
			}, delay );
		}
	}

	function initAll() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-saha-slider]' ), init );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initAll );
	} else {
		initAll();
	}
}() );
