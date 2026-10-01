/**
 * Gợi ý khi gõ cho element Tìm kiếm (saha-core Builder\Elements\Search, `live`).
 *
 * [data-saha-live-search] chứa form tìm kiếm → ô nhập thành combobox (ARIA 1.2): gõ ≥ 2 ký tự,
 * chờ 250ms → GET /saha/v1/search → danh sách ảnh, tên, mã, giá + "Xem tất cả N kết quả".
 * Mũi tên lên/xuống chọn, Enter mở, Esc đóng. Kết quả nhớ theo từ khoá (đỡ gọi lại API).
 * Không JS / lỗi: form vẫn gửi tới trang kết quả tìm kiếm như cũ.
 */
( function () {
	'use strict';

	var cfg = window.sahaLiveSearch;

	if ( ! cfg || ! window.fetch ) {
		return;
	}

	var DELAY = 250;
	var cache = {};
	var uid = 0;

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

	function init( root ) {
		var form = root.querySelector( 'form' );
		var input = root.querySelector( 'input[type="search"], input[name="s"]' );

		if ( ! form || ! input || root.getAttribute( 'data-saha-ready' ) ) {
			return;
		}

		root.setAttribute( 'data-saha-ready', '1' );
		uid += 1;

		var listId = 'saha-ls-' + uid;
		var panel = el( 'div', 'saha-ls' );
		var list = el( 'ul', 'saha-ls__list' );
		var footer = el( 'div', 'saha-ls__foot' );
		var status = el( 'p', 'screen-reader-text' );
		var timer = 0;
		var controller = null;
		var active = -1;
		var last = '';

		panel.hidden = true;
		list.id = listId;
		list.setAttribute( 'role', 'listbox' );
		list.setAttribute( 'aria-label', cfg.i18n.suggested );
		status.setAttribute( 'role', 'status' );
		panel.appendChild( list );
		panel.appendChild( footer );
		root.appendChild( panel );
		root.appendChild( status );
		root.classList.add( 'saha-ls-root' );

		input.setAttribute( 'role', 'combobox' );
		input.setAttribute( 'aria-autocomplete', 'list' );
		input.setAttribute( 'aria-expanded', 'false' );
		input.setAttribute( 'aria-controls', listId );
		input.setAttribute( 'autocomplete', 'off' );

		function options() {
			return list.querySelectorAll( '[role="option"]' );
		}

		function setActive( index ) {
			var items = options();

			active = index;

			Array.prototype.forEach.call( items, function ( item, i ) {
				item.setAttribute( 'aria-selected', i === index ? 'true' : 'false' );
			} );

			if ( index >= 0 && items[ index ] ) {
				input.setAttribute( 'aria-activedescendant', items[ index ].id );
				items[ index ].scrollIntoView( { block: 'nearest' } );
			} else {
				input.removeAttribute( 'aria-activedescendant' );
			}
		}

		function close() {
			panel.hidden = true;
			input.setAttribute( 'aria-expanded', 'false' );
			setActive( -1 );
		}

		function show() {
			panel.hidden = false;
			input.setAttribute( 'aria-expanded', 'true' );
		}

		function message( text ) {
			list.innerHTML = '';
			footer.innerHTML = '';
			footer.appendChild( el( 'p', 'saha-ls__msg', text ) );
			setActive( -1 );
			show();
		}

		function allUrl( term ) {
			var url = new window.URL( form.getAttribute( 'action' ) || cfg.searchUrl, window.location.href );

			url.searchParams.set( 's', term );
			url.searchParams.set( 'post_type', 'product' );

			return url.toString();
		}

		function render( term, data ) {
			var items = ( data && data.items ) || [];

			list.innerHTML = '';
			footer.innerHTML = '';
			setActive( -1 );

			if ( ! items.length ) {
				message( cfg.i18n.none );
				status.textContent = cfg.i18n.none;
				return;
			}

			items.forEach( function ( item, i ) {
				var li = el( 'li', 'saha-ls__item' );
				var a = el( 'a', 'saha-ls__link' );
				var body = el( 'span', 'saha-ls__body' );

				li.setAttribute( 'role', 'option' );
				li.id = listId + '-' + i;
				li.setAttribute( 'aria-selected', 'false' );
				a.href = item.url;
				a.tabIndex = -1;

				if ( item.thumbnail ) {
					var img = el( 'img', 'saha-ls__thumb' );

					img.src = item.thumbnail;
					img.alt = '';
					img.width = 48;
					img.height = 48;
					img.loading = 'lazy';
					a.appendChild( img );
				} else {
					a.appendChild( el( 'span', 'saha-ls__thumb' ) );
				}

				body.appendChild( el( 'span', 'saha-ls__name', item.name ) );

				var meta = [ item.sku ? cfg.i18n.sku + ': ' + item.sku : '', item.brand ].filter( Boolean ).join( ' · ' );

				if ( meta ) {
					body.appendChild( el( 'span', 'saha-ls__meta', meta ) );
				}

				a.appendChild( body );

				if ( item.price ) {
					a.appendChild( el( 'span', 'saha-ls__price', item.price ) );
				}

				li.appendChild( a );
				list.appendChild( li );
			} );

			var all = el( 'a', 'saha-ls__all', String( cfg.i18n.all ).replace( '%d', data.total ) );

			all.href = allUrl( term );
			footer.appendChild( all );
			status.textContent = String( cfg.i18n.count ).replace( '%d', items.length );
			show();
		}

		function search( term ) {
			if ( cache[ term ] ) {
				render( term, cache[ term ] );
				return;
			}

			if ( controller ) {
				controller.abort();
			}

			controller = window.AbortController ? new window.AbortController() : null;
			root.classList.add( 'is-loading' );

			window.fetch( cfg.endpoint + '?q=' + encodeURIComponent( term ) + '&limit=' + cfg.limit, {
				credentials: 'same-origin',
				headers: { Accept: 'application/json' },
				signal: controller ? controller.signal : undefined
			} )
				.then( function ( response ) {
					if ( ! response.ok ) {
						throw new Error( String( response.status ) );
					}

					return response.json();
				} )
				.then( function ( json ) {
					var data = ( json && json.data ) || { items: [], total: 0 };

					cache[ term ] = data;

					// Người dùng đã gõ tiếp → bỏ kết quả cũ.
					if ( term === input.value.trim() ) {
						render( term, data );
					}
				} )
				.catch( function ( error ) {
					if ( error && 'AbortError' === error.name ) {
						return;
					}

					message( cfg.i18n.error );
				} )
				.then( function () {
					root.classList.remove( 'is-loading' );
				} );
		}

		input.addEventListener( 'input', function () {
			var term = input.value.trim();

			window.clearTimeout( timer );

			if ( term.length < cfg.minLength ) {
				last = '';
				close();
				return;
			}

			if ( term === last ) {
				return;
			}

			last = term;
			timer = window.setTimeout( function () {
				search( term );
			}, DELAY );
		} );

		input.addEventListener( 'focus', function () {
			if ( list.firstChild && input.value.trim() === last ) {
				show();
			}
		} );

		input.addEventListener( 'keydown', function ( event ) {
			var items = options();

			if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
				if ( panel.hidden || ! items.length ) {
					return;
				}

				event.preventDefault();

				var next = active + ( 'ArrowDown' === event.key ? 1 : -1 );

				if ( next < -1 ) {
					next = items.length - 1;
				} else if ( next >= items.length ) {
					next = -1;
				}

				setActive( next );
			} else if ( 'Enter' === event.key && ! panel.hidden && active >= 0 && items[ active ] ) {
				event.preventDefault();
				window.location.href = items[ active ].querySelector( 'a' ).href;
			} else if ( 'Escape' === event.key && ! panel.hidden ) {
				event.preventDefault();
				close();
			}
		} );

		// Rê chuột chọn như phím mũi tên (giữ một mục "đang chọn").
		list.addEventListener( 'mousemove', function ( event ) {
			var option = event.target.closest( '[role="option"]' );

			if ( option ) {
				setActive( Array.prototype.indexOf.call( options(), option ) );
			}
		} );

		// Bấm ra ngoài / Tab ra khỏi khối → đóng.
		root.addEventListener( 'focusout', function ( event ) {
			if ( ! event.relatedTarget || ! root.contains( event.relatedTarget ) ) {
				window.setTimeout( function () {
					if ( ! root.contains( document.activeElement ) ) {
						close();
					}
				}, 150 );
			}
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( ! root.contains( event.target ) ) {
				close();
			}
		} );
	}

	function boot() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-saha-live-search]' ), init );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
