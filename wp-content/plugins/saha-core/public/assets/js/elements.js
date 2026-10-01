/**
 * Element builder cần JS (SCC D1): Slider. D5: ngăn lọc của trang danh mục trên mobile.
 * 2.7: Tabs (dùng chung tab Sản phẩm), Thư viện ảnh (phóng to), Video (tải khi bấm), Đếm ngược, Đăng ký nhận tin.
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

	/**
	 * Nút "Danh mục sản phẩm": link tới shop khi không có JS; có JS → mở/đóng bảng danh mục
	 * (aria-expanded, Space/Enter, Esc trả focus, bấm ra ngoài để đóng).
	 */
	function initCatMenu( root ) {
		var toggle = root.querySelector( '[data-saha-catmenu-toggle]' );
		var panel = toggle ? document.getElementById( toggle.getAttribute( 'aria-controls' ) ) : null;

		if ( ! toggle || ! panel || root.getAttribute( 'data-saha-ready' ) ) {
			return;
		}

		root.setAttribute( 'data-saha-ready', '1' );

		function setOpen( open ) {
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			panel.hidden = ! open;
			root.classList.toggle( 'is-open', open );
		}

		toggle.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			setOpen( 'true' !== toggle.getAttribute( 'aria-expanded' ) );
		} );

		toggle.addEventListener( 'keydown', function ( event ) {
			if ( ' ' === event.key ) {
				event.preventDefault();
				toggle.click();
			}
		} );

		root.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && ! panel.hidden ) {
				setOpen( false );
				toggle.focus();
			}
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( ! panel.hidden && ! root.contains( event.target ) ) {
				setOpen( false );
			}
		} );
	}

	/**
	 * Tab lọc sản phẩm (mẫu ARIA tabs: một tab được Tab tới, ←/→/Home/End chuyển tab).
	 */
	function initTabs( root ) {
		if ( root.getAttribute( 'data-saha-ready' ) ) {
			return;
		}

		root.setAttribute( 'data-saha-ready', '1' );

		// Chỉ tab của chính nhóm này (Tabs lồng trong Tabs có nhóm riêng).
		var list = root.querySelector( ':scope > [role="tablist"]' ) || root.querySelector( '[role="tablist"]' );
		var tabs = list ? Array.prototype.slice.call( list.querySelectorAll( ':scope > [role="tab"]' ) ) : [];

		function select( tab, focus ) {
			tabs.forEach( function ( item ) {
				var on = item === tab;
				var panel = document.getElementById( item.getAttribute( 'aria-controls' ) );

				item.setAttribute( 'aria-selected', on ? 'true' : 'false' );

				if ( on ) {
					item.removeAttribute( 'tabindex' );
				} else {
					item.setAttribute( 'tabindex', '-1' );
				}

				if ( panel ) {
					panel.hidden = ! on;
				}
			} );

			if ( focus ) {
				tab.focus();
			}
		}

		tabs.forEach( function ( tab, i ) {
			tab.addEventListener( 'click', function () {
				select( tab, false );
			} );

			tab.addEventListener( 'keydown', function ( event ) {
				var next = null;

				if ( 'ArrowRight' === event.key ) {
					next = tabs[ ( i + 1 ) % tabs.length ];
				} else if ( 'ArrowLeft' === event.key ) {
					next = tabs[ ( i - 1 + tabs.length ) % tabs.length ];
				} else if ( 'Home' === event.key ) {
					next = tabs[ 0 ];
				} else if ( 'End' === event.key ) {
					next = tabs[ tabs.length - 1 ];
				}

				if ( next ) {
					event.preventDefault();
					select( next, true );
				}
			} );
		} );
	}

	/**
	 * Trang danh mục kiểu cửa hàng: dưới 1024px cột lọc là ngăn trượt mở bằng nút "Danh mục & bộ lọc"
	 * (aria-expanded, focus vào ngăn và giữ trong ngăn, Esc / bấm nền / "Xem kết quả" để đóng).
	 * Số trên nút = số điều kiện đang chọn, cập nhật sau khi lọc bằng AJAX.
	 */
	function initShop( root ) {
		var toggle = root.querySelector( '[data-saha-shop-toggle]' );
		var panel = root.querySelector( '[data-saha-shop-panel]' );
		var badge = root.querySelector( '[data-saha-shop-count]' );
		var desktop = window.matchMedia ? window.matchMedia( '(min-width: 1024px)' ) : null;

		if ( ! toggle || ! panel || root.getAttribute( 'data-saha-ready' ) ) {
			return;
		}

		root.setAttribute( 'data-saha-ready', '1' );

		function isOpen() {
			return root.classList.contains( 'is-open' );
		}

		function setOpen( open ) {
			root.classList.toggle( 'is-open', open );
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			document.documentElement.classList.toggle( 'saha-shop-locked', open );

			if ( open ) {
				panel.setAttribute( 'role', 'dialog' );
				panel.setAttribute( 'aria-modal', 'true' );
				var close = panel.querySelector( '[data-saha-shop-close]' );

				if ( close ) {
					close.focus();
				}
			} else {
				panel.removeAttribute( 'role' );
				panel.removeAttribute( 'aria-modal' );
			}
		}

		function focusables() {
			return Array.prototype.filter.call(
				panel.querySelectorAll( 'a[href], button:not([disabled]), input:not([disabled]), select, [tabindex]:not([tabindex="-1"])' ),
				function ( el ) {
					return el.offsetParent !== null;
				}
			);
		}

		toggle.addEventListener( 'click', function () {
			setOpen( ! isOpen() );
		} );

		Array.prototype.forEach.call( panel.querySelectorAll( '[data-saha-shop-close]' ), function ( btn ) {
			btn.addEventListener( 'click', function () {
				setOpen( false );
				toggle.focus();
			} );
		} );

		// Nền tối là ::before của root → bấm vào nền thì target là chính root.
		root.addEventListener( 'click', function ( event ) {
			if ( isOpen() && event.target === root ) {
				setOpen( false );
				toggle.focus();
			}
		} );

		root.addEventListener( 'keydown', function ( event ) {
			if ( ! isOpen() ) {
				return;
			}

			if ( 'Escape' === event.key ) {
				setOpen( false );
				toggle.focus();
				return;
			}

			if ( 'Tab' === event.key ) {
				var items = focusables();

				if ( ! items.length ) {
					return;
				}

				var first = items[ 0 ];
				var last = items[ items.length - 1 ];

				if ( event.shiftKey && document.activeElement === first ) {
					event.preventDefault();
					last.focus();
				} else if ( ! event.shiftKey && document.activeElement === last ) {
					event.preventDefault();
					first.focus();
				}
			}
		} );

		if ( desktop && desktop.addEventListener ) {
			desktop.addEventListener( 'change', function ( event ) {
				if ( event.matches && isOpen() ) {
					setOpen( false );
				}
			} );
		}

		document.addEventListener( 'saha:filter:updated', function () {
			var form = panel.querySelector( 'form' );

			if ( ! badge || ! form ) {
				return;
			}

			var count = Array.prototype.filter.call( form.querySelectorAll( 'input:checked' ), function ( input ) {
				return '' !== input.value;
			} ).length;

			badge.textContent = String( count );
			badge.hidden = 0 === count;
		} );
	}

	/**
	 * Thư viện ảnh (mốc 2.7): ảnh không gắn link → bấm mở <dialog> xem lớn; ←/→ chuyển ảnh,
	 * Esc đóng, focus trả về ảnh vừa bấm. Ảnh lớn = ứng viên rộng nhất trong srcset.
	 */
	var lightbox = null;

	function largest( img ) {
		var best = img.currentSrc || img.src;
		var width = 0;

		( img.getAttribute( 'srcset' ) || '' ).split( ',' ).forEach( function ( part ) {
			var bits = part.trim().split( /\s+/ );
			var w = parseInt( bits[ 1 ], 10 ) || 0;

			if ( bits[ 0 ] && w > width ) {
				width = w;
				best = bits[ 0 ];
			}
		} );

		return best;
	}

	function openLightbox( images, index, opener, label ) {
		if ( ! window.HTMLDialogElement ) {
			return;
		}

		if ( ! lightbox ) {
			lightbox = document.createElement( 'dialog' );
			lightbox.className = 'saha-lightbox';
			lightbox.innerHTML = '<img class="saha-lightbox__img" alt="">'
				+ '<button type="button" class="saha-lightbox__btn saha-lightbox__close" data-act="close">&times;</button>'
				+ '<button type="button" class="saha-lightbox__btn saha-lightbox__prev" data-act="prev">&lsaquo;</button>'
				+ '<button type="button" class="saha-lightbox__btn saha-lightbox__next" data-act="next">&rsaquo;</button>'
				+ '<p class="saha-lightbox__count" aria-live="polite"></p>';
			document.body.appendChild( lightbox );

			lightbox.addEventListener( 'click', function ( event ) {
				var act = event.target.getAttribute( 'data-act' );

				if ( 'close' === act || event.target === lightbox ) {
					lightbox.close();
				} else if ( act ) {
					lightbox.saha.go( 'next' === act ? 1 : -1 );
				}
			} );

			lightbox.addEventListener( 'keydown', function ( event ) {
				if ( 'ArrowRight' === event.key || 'ArrowLeft' === event.key ) {
					event.preventDefault();
					lightbox.saha.go( 'ArrowRight' === event.key ? 1 : -1 );
				}
			} );

			lightbox.addEventListener( 'close', function () {
				if ( lightbox.saha && lightbox.saha.opener ) {
					lightbox.saha.opener.focus();
				}
			} );
		}

		var img = lightbox.querySelector( '.saha-lightbox__img' );
		var count = lightbox.querySelector( '.saha-lightbox__count' );
		var many = images.length > 1;

		lightbox.querySelector( '[data-act="close"]' ).setAttribute( 'aria-label', label.close );
		lightbox.querySelector( '[data-act="prev"]' ).setAttribute( 'aria-label', label.prev );
		lightbox.querySelector( '[data-act="next"]' ).setAttribute( 'aria-label', label.next );
		lightbox.querySelector( '[data-act="prev"]' ).hidden = ! many;
		lightbox.querySelector( '[data-act="next"]' ).hidden = ! many;
		lightbox.setAttribute( 'aria-label', label.title );

		lightbox.saha = {
			opener: opener,
			index: index,
			go: function ( step ) {
				this.index = ( this.index + step + images.length ) % images.length;
				this.show();
			},
			show: function () {
				var source = images[ this.index ];

				img.src = largest( source );
				img.alt = source.alt || '';
				count.textContent = many ? ( this.index + 1 ) + ' / ' + images.length : '';
			}
		};

		lightbox.saha.show();
		lightbox.showModal();
		lightbox.querySelector( '[data-act="close"]' ).focus();
	}

	function initGallery( root ) {
		if ( root.getAttribute( 'data-saha-ready' ) ) {
			return;
		}

		root.setAttribute( 'data-saha-ready', '1' );

		var label = {
			title: root.getAttribute( 'data-label' ) || '',
			close: root.getAttribute( 'data-close' ) || '',
			prev: root.getAttribute( 'data-prev' ) || '',
			next: root.getAttribute( 'data-next' ) || ''
		};

		// Chỉ ảnh không nằm trong link (ảnh có link giữ hành vi link).
		var images = Array.prototype.filter.call( root.querySelectorAll( '.saha-image__img' ), function ( img ) {
			return ! img.closest( 'a' );
		} );

		images.forEach( function ( img, i ) {
			var button = document.createElement( 'button' );

			button.type = 'button';
			button.className = 'saha-gallery__zoom';
			button.setAttribute( 'aria-label', label.title + ( img.alt ? ': ' + img.alt : ' ' + ( i + 1 ) ) );
			img.parentNode.insertBefore( button, img );
			button.appendChild( img );
			button.addEventListener( 'click', function () {
				openLightbox( images, i, button, label );
			} );
		} );
	}

	/**
	 * Video (mốc 2.7): bấm ảnh bìa → thay bằng iframe (YouTube nocookie / Vimeo, tự phát).
	 */
	function initVideo( root ) {
		var play = root.querySelector( '.saha-video__play' );

		if ( ! play || root.getAttribute( 'data-saha-ready' ) ) {
			return;
		}

		root.setAttribute( 'data-saha-ready', '1' );

		play.addEventListener( 'click', function ( event ) {
			if ( event.metaKey || event.ctrlKey || event.shiftKey ) {
				return;
			}

			event.preventDefault();

			var frame = document.createElement( 'iframe' );

			frame.src = root.getAttribute( 'data-saha-video' );
			frame.title = root.getAttribute( 'data-title' ) || 'Video';
			frame.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
			frame.setAttribute( 'allowfullscreen', '' );
			frame.setAttribute( 'loading', 'lazy' );
			root.innerHTML = '';
			root.appendChild( frame );
			frame.focus();
		} );
	}

	/**
	 * Đếm ngược (mốc 2.7): cập nhật mỗi giây từ số server in sẵn.
	 */
	function initCountdown( root ) {
		if ( root.getAttribute( 'data-saha-ready' ) ) {
			return;
		}

		root.setAttribute( 'data-saha-ready', '1' );

		var target = parseInt( root.getAttribute( 'data-saha-countdown' ), 10 ) * 1000;
		var parts = {};
		var timer = 0;

		Array.prototype.forEach.call( root.querySelectorAll( '[data-part]' ), function ( el ) {
			parts[ el.getAttribute( 'data-part' ) ] = el;
		} );

		function pad( n ) {
			return ( n < 10 ? '0' : '' ) + n;
		}

		function tick() {
			var left = Math.max( 0, Math.floor( ( target - Date.now() ) / 1000 ) );

			if ( left <= 0 ) {
				window.clearInterval( timer );

				if ( 'hide' === root.getAttribute( 'data-expired' ) ) {
					root.hidden = true;
				} else if ( root.getAttribute( 'data-done' ) ) {
					root.innerHTML = '';

					var p = document.createElement( 'p' );

					p.className = 'saha-countdown__done';
					p.textContent = root.getAttribute( 'data-done' );
					root.appendChild( p );
				}

				return;
			}

			var d = parts.d ? Math.floor( left / 86400 ) : 0;
			var rest = left - d * 86400;
			var values = { d: d, h: Math.floor( rest / 3600 ), m: Math.floor( ( rest % 3600 ) / 60 ), s: rest % 60 };

			Object.keys( parts ).forEach( function ( key ) {
				var text = pad( values[ key ] );

				if ( parts[ key ].textContent !== text ) {
					parts[ key ].textContent = text;
				}
			} );
		}

		if ( ! parts.s ) {
			return;
		}

		tick();
		timer = window.setInterval( tick, 1000 );
	}

	/**
	 * Đăng ký nhận tin (mốc 2.7): lấy nonce mới (trang có thể từ page cache) rồi POST /saha/v1/newsletter.
	 */
	function initNewsletter( form ) {
		if ( form.getAttribute( 'data-saha-ready' ) || ! window.fetch ) {
			return;
		}

		form.setAttribute( 'data-saha-ready', '1' );

		var box = form.parentNode;
		var input = form.querySelector( 'input[name="email"]' );
		var button = form.querySelector( 'button[type="submit"]' );
		var status = box.querySelector( '.saha-nl__status' );

		function show( text, error ) {
			status.textContent = text;
			status.classList.toggle( 'is-error', !! error );
			input.setAttribute( 'aria-invalid', error ? 'true' : 'false' );
		}

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			if ( ! input.value.trim() || ( input.validity && ! input.validity.valid ) ) {
				show( input.validationMessage || form.getAttribute( 'data-error' ), true );
				input.focus();
				return;
			}

			button.disabled = true;

			window.fetch( form.getAttribute( 'data-nonce-url' ), { credentials: 'same-origin', headers: { Accept: 'application/json' } } )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( json ) {
					return window.fetch( form.getAttribute( 'data-endpoint' ), {
						method: 'POST',
						credentials: 'same-origin',
						// Nonce form (không header X-WP-Nonce): chạy như khách, đúng cả khi đang đăng nhập.
						headers: {
							Accept: 'application/json',
							'Content-Type': 'application/json'
						},
						body: JSON.stringify( {
							saha_nonce: ( json && json.data && json.data.form ) || '',
							email: input.value.trim(),
							saha_hp_email: form.querySelector( '[name="saha_hp_email"]' ).value,
							source_url: window.location.href
						} )
					} );
				} )
				.then( function ( response ) {
					return response.json().then( function ( json ) {
						return { ok: response.ok, json: json || {} };
					} );
				} )
				.then( function ( result ) {
					if ( result.ok ) {
						form.reset();
						show( result.json.message || '', false );
					} else {
						show( ( result.json.errors && result.json.errors.email ) || result.json.message || form.getAttribute( 'data-error' ), true );
						input.focus();
					}
				} )
				.catch( function () {
					show( form.getAttribute( 'data-error' ), true );
				} )
				.then( function () {
					button.disabled = false;
				} );
		} );
	}

	function initAll() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-saha-slider]' ), init );
		Array.prototype.forEach.call( document.querySelectorAll( '[data-saha-catmenu]' ), initCatMenu );
		Array.prototype.forEach.call( document.querySelectorAll( '[data-saha-tabs]' ), initTabs );
		Array.prototype.forEach.call( document.querySelectorAll( '[data-saha-shop]' ), initShop );
		Array.prototype.forEach.call( document.querySelectorAll( '[data-saha-gallery]' ), initGallery );
		Array.prototype.forEach.call( document.querySelectorAll( '[data-saha-video]' ), initVideo );
		Array.prototype.forEach.call( document.querySelectorAll( '[data-saha-countdown]' ), initCountdown );
		Array.prototype.forEach.call( document.querySelectorAll( '[data-saha-newsletter]' ), initNewsletter );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initAll );
	} else {
		initAll();
	}
}() );
