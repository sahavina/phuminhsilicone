/**
 * Mega menu (SCC 2.1): bảng mở bằng CSS (:hover, :focus-within); script chỉ
 * - đồng bộ aria-expanded của link cấp 1 với trạng thái mở;
 * - Esc đóng bảng và trả focus về link cấp 1.
 */
( function () {
	'use strict';

	var items = document.querySelectorAll( 'li.saha-mega-item' );

	function link( item ) {
		return item.querySelector( ':scope > a' );
	}

	function setExpanded( item, open ) {
		var a = link( item );

		if ( a ) {
			a.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		}
	}

	Array.prototype.forEach.call( items, function ( item ) {
		item.addEventListener( 'mouseenter', function () {
			item.classList.remove( 'is-closed' );
			setExpanded( item, true );
		} );

		item.addEventListener( 'mouseleave', function () {
			item.classList.remove( 'is-closed' );
			setExpanded( item, item.contains( document.activeElement ) );
		} );

		item.addEventListener( 'focusin', function () {
			if ( ! item.classList.contains( 'is-closed' ) ) {
				setExpanded( item, true );
			}
		} );

		item.addEventListener( 'focusout', function ( event ) {
			if ( ! item.contains( event.relatedTarget ) ) {
				item.classList.remove( 'is-closed' );
				setExpanded( item, false );
			}
		} );

		item.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' !== event.key ) {
				return;
			}

			item.classList.add( 'is-closed' );
			setExpanded( item, false );

			var a = link( item );

			if ( a ) {
				a.focus();
			}
		} );
	} );
}() );
