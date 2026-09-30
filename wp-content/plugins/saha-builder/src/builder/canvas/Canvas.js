/**
 * Canvas: iframe nạp CSS frontend thật; HTML do PHP renderer sinh (REST /builder/render).
 *
 * Mỗi section cấp gốc được render và cache riêng theo nội dung JSON — sửa một
 * section chỉ gọi server cho section đó. Server từ chối giá trị sai (422) →
 * giữ HTML cũ và báo lỗi ngay dưới field.
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { api } from '../api';
import { DEVICES, config, drag, useBuilder } from '../context';
import { useActions } from '../actions';
import { findNode, nodeLabel } from '../store/tree';
import { CANVAS_UI_CSS } from './canvas-ui';
import { computeDrop } from './drop';

const RENDER_DELAY = 250;

/**
 * Escape chuỗi đưa vào HTML của canvas.
 *
 * @param {string} text Chuỗi.
 * @return {string} Đã escape.
 */
function esc( text ) {
	return String( text ).replace(
		/[&<>"']/g,
		( c ) => `&#${ c.charCodeAt( 0 ) };`
	);
}

export default function Canvas( { onShortcut } ) {
	const { state, dispatch, defs, device } = useBuilder();
	const actions = useActions();
	const wrapRef = useRef( null );
	const frameRef = useRef( null );
	const idocRef = useRef( null );
	const cacheRef = useRef( new Map() );
	const inflightRef = useRef( new Map() );
	const sectionErrorsRef = useRef( new Map() );
	const timerRef = useRef( null );
	const lastHtmlRef = useRef( '' );
	const hoverRef = useRef( null );
	const dropRef = useRef( null );
	const [ ready, setReady ] = useState( false );
	const [ version, setVersion ] = useState( 0 );
	const [ size, setSize ] = useState( { width: 0, height: 0 } );

	// Bản mới nhất cho các listener gắn một lần trong iframe.
	const latest = useRef( {} );
	latest.current = { state, defs, dispatch, actions, onShortcut };

	/*
	 * ---------------------------------------------------------------
	 * Render qua server
	 * ---------------------------------------------------------------
	 */
	const publishErrors = useCallback( () => {
		const all = {};
		sectionErrorsRef.current.forEach( ( errors ) =>
			Object.assign( all, errors )
		);
		latest.current.dispatch( { type: 'SET_ERRORS', errors: all } );
	}, [] );

	const renderSection = useCallback(
		( node, key ) => {
			inflightRef.current.get( node.id )?.controller.abort();

			const controller = new window.AbortController();
			inflightRef.current.set( node.id, { key, controller } );

			api.render( config.postId, node, controller.signal )
				.then( ( data ) => {
					cacheRef.current.set( node.id, {
						key,
						html: data.html,
						css: data.css,
					} );

					if ( sectionErrorsRef.current.delete( node.id ) ) {
						publishErrors();
					}
				} )
				.catch( ( error ) => {
					if ( 'AbortError' === error?.name ) {
						return;
					}

					if ( 'validation_failed' === error?.code ) {
						sectionErrorsRef.current.set(
							node.id,
							error.errors || {}
						);
						publishErrors();
					}

					// Giữ HTML cũ; section mới chưa có HTML thì hiện thông báo.
					if ( ! cacheRef.current.has( node.id ) ) {
						cacheRef.current.set( node.id, {
							key,
							html: `<div class="saha-canvas-pending" data-saha-id="${ esc( node.id ) }">${ esc( error?.message || __( 'Không hiển thị được phần này.', 'saha-builder' ) ) }</div>`,
							css: '',
						} );
					}
				} )
				.finally( () => {
					if ( inflightRef.current.get( node.id )?.key === key ) {
						inflightRef.current.delete( node.id );
					}
					setVersion( ( v ) => v + 1 );
				} );
		},
		[ publishErrors ]
	);

	useEffect( () => {
		window.clearTimeout( timerRef.current );

		const pending = state.doc.elements.filter( ( node ) => {
			const key = JSON.stringify( node );
			return (
				cacheRef.current.get( node.id )?.key !== key &&
				inflightRef.current.get( node.id )?.key !== key
			);
		} );

		if ( ! pending.length ) {
			return;
		}

		// Section chưa từng render: gọi ngay; đang sửa: đợi người dùng ngừng gõ.
		const fresh = pending.every(
			( node ) => ! cacheRef.current.has( node.id )
		);

		timerRef.current = window.setTimeout(
			() =>
				pending.forEach( ( node ) =>
					renderSection( node, JSON.stringify( node ) )
				),
			fresh ? 0 : RENDER_DELAY
		);

		// Section đã xoá: bỏ lỗi của nó.
		const ids = new Set( state.doc.elements.map( ( n ) => n.id ) );
		let removed = false;
		sectionErrorsRef.current.forEach( ( _, id ) => {
			if ( ! ids.has( id ) ) {
				sectionErrorsRef.current.delete( id );
				removed = true;
			}
		} );
		if ( removed ) {
			publishErrors();
		}
	}, [ state.doc, renderSection, publishErrors ] );

	/*
	 * ---------------------------------------------------------------
	 * Vẽ vào iframe
	 * ---------------------------------------------------------------
	 */
	const positionToolbar = useCallback( () => {
		const idoc = idocRef.current;

		if ( ! idoc ) {
			return;
		}

		const toolbar = idoc.getElementById( 'saha-canvas-toolbar' );
		const selected = latest.current.state.selectedId;
		const target = selected
			? idoc.querySelector( `[data-saha-id="${ selected }"]` )
			: null;

		if ( ! target || latest.current.state.readOnly ) {
			toolbar.hidden = true;
			return;
		}

		const rect = target.getBoundingClientRect();
		const win = idoc.defaultView;
		const node = findNode( latest.current.state.doc, selected );

		toolbar.querySelector( 'span' ).textContent = node
			? nodeLabel( latest.current.defs, node ).slice( 0, 40 )
			: '';
		toolbar.hidden = false;
		toolbar.style.top =
			Math.max(
				win.scrollY,
				rect.top + win.scrollY - toolbar.offsetHeight
			) + 'px';
		toolbar.style.left = Math.max( 0, rect.left + win.scrollX ) + 'px';
	}, [] );

	useEffect( () => {
		const idoc = idocRef.current;

		if ( ! ready || ! idoc ) {
			return;
		}

		const root = idoc.getElementById( 'saha-canvas' );
		const elements = state.doc.elements;
		const html = elements.length
			? elements
					.map(
						( node ) =>
							cacheRef.current.get( node.id )?.html ??
							`<div class="saha-canvas-pending" data-saha-id="${ esc( node.id ) }">${ esc( __( 'Đang tải…', 'saha-builder' ) ) }</div>`
					)
					.join( '' )
			: `<div class="saha-canvas-empty">${ esc( __( 'Trang trống. Kéo element từ bảng "Thêm" vào đây, hoặc bấm vào một element để thêm.', 'saha-builder' ) ) }</div>`;

		if ( html !== lastHtmlRef.current ) {
			root.innerHTML = html;
			lastHtmlRef.current = html;
			hoverRef.current = null;
		}

		idoc.getElementById( 'saha-live-css' ).textContent = elements
			.map( ( node ) => cacheRef.current.get( node.id )?.css || '' )
			.join( '\n' );

		idoc.querySelectorAll( '.saha-is-selected' ).forEach( ( node ) =>
			node.classList.remove( 'saha-is-selected' )
		);

		if ( state.selectedId ) {
			idoc.querySelector(
				`[data-saha-id="${ state.selectedId }"]`
			)?.classList.add( 'saha-is-selected' );
		}

		positionToolbar();
	}, [
		ready,
		version,
		state.doc,
		state.selectedId,
		state.readOnly,
		positionToolbar,
	] );

	// Chọn từ Navigator → cuộn tới element trong canvas.
	useEffect( () => {
		const target =
			state.selectedId &&
			idocRef.current?.querySelector(
				`[data-saha-id="${ state.selectedId }"]`
			);

		if ( target ) {
			target.scrollIntoView( { block: 'nearest', behavior: 'smooth' } );
		}
	}, [ state.selectedId ] );

	/*
	 * ---------------------------------------------------------------
	 * Tương tác trong iframe (gắn một lần khi iframe tải xong)
	 * ---------------------------------------------------------------
	 */
	const showDrop = ( idoc, result ) => {
		const indicator = idoc.getElementById( 'saha-canvas-drop' );

		if ( ! result ) {
			indicator.hidden = true;
			return;
		}

		const win = idoc.defaultView;
		indicator.hidden = false;
		indicator.classList.toggle( 'is-box', !! result.line.box );
		indicator.style.top = result.line.top + win.scrollY + 'px';
		indicator.style.left = result.line.left + win.scrollX + 'px';
		indicator.style.width = result.line.width + 'px';
		indicator.style.height = result.line.height + 'px';
	};

	const onLoad = () => {
		const idoc = frameRef.current?.contentDocument;

		if ( ! idoc || ! idoc.getElementById( 'saha-canvas' ) ) {
			return;
		}

		idocRef.current = idoc;

		const ui = idoc.createElement( 'style' );
		ui.textContent = CANVAS_UI_CSS;
		idoc.head.appendChild( ui );

		const live = idoc.createElement( 'style' );
		live.id = 'saha-live-css';
		idoc.head.appendChild( live );

		const toolbar = idoc.createElement( 'div' );
		toolbar.id = 'saha-canvas-toolbar';
		toolbar.className = 'saha-canvas-toolbar';
		toolbar.hidden = true;
		toolbar.innerHTML = [
			`<button type="button" data-saha-action="drag" draggable="true" title="${ esc( __( 'Kéo để di chuyển', 'saha-builder' ) ) }">⠿</button>`,
			'<span></span>',
			`<button type="button" data-saha-action="parent" title="${ esc( __( 'Chọn element cha', 'saha-builder' ) ) }">⤒</button>`,
			`<button type="button" data-saha-action="up" title="${ esc( __( 'Lên trên', 'saha-builder' ) ) }">↑</button>`,
			`<button type="button" data-saha-action="down" title="${ esc( __( 'Xuống dưới', 'saha-builder' ) ) }">↓</button>`,
			`<button type="button" data-saha-action="duplicate" title="${ esc( __( 'Nhân bản', 'saha-builder' ) ) }">⧉</button>`,
			`<button type="button" data-saha-action="remove" title="${ esc( __( 'Xoá', 'saha-builder' ) ) }">✕</button>`,
		].join( '' );
		idoc.body.appendChild( toolbar );

		const indicator = idoc.createElement( 'div' );
		indicator.id = 'saha-canvas-drop';
		indicator.className = 'saha-canvas-drop';
		indicator.hidden = true;
		idoc.body.appendChild( indicator );

		idoc.addEventListener(
			'click',
			( event ) => {
				event.preventDefault();

				const { state: s, dispatch: d } = latest.current;
				const button = event.target.closest( '[data-saha-action]' );

				if ( button && s.selectedId ) {
					const id = s.selectedId;
					switch ( button.getAttribute( 'data-saha-action' ) ) {
						case 'up':
							d( { type: 'MOVE_SIBLING', id, delta: -1 } );
							break;
						case 'down':
							d( { type: 'MOVE_SIBLING', id, delta: 1 } );
							break;
						case 'duplicate':
							d( { type: 'DUPLICATE', id } );
							break;
						case 'remove':
							d( { type: 'REMOVE', id } );
							break;
						case 'parent': {
							const parent = idoc
								.querySelector( `[data-saha-id="${ id }"]` )
								?.parentElement?.closest( '[data-saha-id]' );
							d( {
								type: 'SELECT',
								id: parent
									? parent.getAttribute( 'data-saha-id' )
									: null,
							} );
							break;
						}
					}
					return;
				}

				const target = event.target.closest( '[data-saha-id]' );
				d( {
					type: 'SELECT',
					id: target ? target.getAttribute( 'data-saha-id' ) : null,
				} );
			},
			true
		);

		// Chặn submit form (form liên hệ… trong layout).
		idoc.addEventListener(
			'submit',
			( event ) => event.preventDefault(),
			true
		);

		idoc.addEventListener( 'mouseover', ( event ) => {
			const target = event.target.closest( '[data-saha-id]' );

			if ( target !== hoverRef.current ) {
				hoverRef.current?.classList.remove( 'saha-is-hover' );
				target?.classList.add( 'saha-is-hover' );
				hoverRef.current = target;
			}
		} );

		idoc.addEventListener( 'mouseleave', () => {
			hoverRef.current?.classList.remove( 'saha-is-hover' );
			hoverRef.current = null;
		} );

		idoc.addEventListener( 'keydown', ( event ) =>
			latest.current.onShortcut?.( event )
		);
		idoc.defaultView.addEventListener( 'scroll', positionToolbar, {
			passive: true,
		} );
		idoc.defaultView.addEventListener( 'resize', positionToolbar );

		idoc.addEventListener( 'dragstart', ( event ) => {
			if (
				event.target.closest?.( '[data-saha-action="drag"]' ) &&
				latest.current.state.selectedId
			) {
				drag.payload = {
					kind: 'move',
					id: latest.current.state.selectedId,
				};
				event.dataTransfer.effectAllowed = 'move';
				event.dataTransfer.setData(
					'text/plain',
					latest.current.state.selectedId
				);
			}
		} );

		idoc.addEventListener( 'dragover', ( event ) => {
			if ( ! drag.payload || latest.current.state.readOnly ) {
				return;
			}

			const { state: s, defs: d } = latest.current;
			const result = computeDrop( {
				doc: s.doc,
				defs: d,
				payload: drag.payload,
				target: event.target,
				x: event.clientX,
				y: event.clientY,
				idoc,
			} );

			dropRef.current = result;
			showDrop( idoc, result );

			if ( result ) {
				event.preventDefault();
				event.dataTransfer.dropEffect =
					'kind' in drag.payload && 'move' === drag.payload.kind
						? 'move'
						: 'copy';
			}
		} );

		idoc.addEventListener( 'dragleave', ( event ) => {
			if ( ! event.relatedTarget ) {
				showDrop( idoc, null );
			}
		} );

		idoc.addEventListener( 'drop', ( event ) => {
			event.preventDefault();

			const result = dropRef.current;
			const payload = drag.payload;

			showDrop( idoc, null );
			dropRef.current = null;
			drag.payload = null;

			if ( ! result || ! payload ) {
				return;
			}

			if ( 'new' === payload.kind ) {
				latest.current.actions.insertType( payload.type, result );
			} else {
				latest.current.actions.moveTo( payload.id, result );
			}
		} );

		idoc.addEventListener( 'dragend', () => {
			showDrop( idoc, null );
			drag.payload = null;
		} );

		setReady( true );
	};

	/*
	 * ---------------------------------------------------------------
	 * Kích thước theo thiết bị (thu nhỏ desktop để breakpoint đúng)
	 * ---------------------------------------------------------------
	 */
	useEffect( () => {
		const wrap = wrapRef.current;

		if ( ! wrap ) {
			return undefined;
		}

		const observer = new window.ResizeObserver( ( [ entry ] ) => {
			setSize( {
				width: entry.contentRect.width,
				height: entry.contentRect.height,
			} );
		} );

		observer.observe( wrap );

		return () => observer.disconnect();
	}, [] );

	const deviceWidth =
		DEVICES.find( ( d ) => d.key === device )?.width ?? 1280;
	const frameWidth =
		'desktop' === device
			? Math.max( size.width, deviceWidth )
			: deviceWidth;
	const scale = size.width ? Math.min( 1, size.width / frameWidth ) : 1;

	return (
		<div className="saha-canvas" ref={ wrapRef }>
			<div
				className={ 'saha-canvas__stage saha-canvas__stage--' + device }
				style={ { width: frameWidth * scale, height: size.height } }
			>
				<iframe
					ref={ frameRef }
					title={ __( 'Vùng soạn thảo', 'saha-builder' ) }
					src={ config.canvasUrl }
					onLoad={ onLoad }
					style={ {
						width: frameWidth,
						height: size.height / scale,
						transform: `scale(${ scale })`,
					} }
				/>
			</div>
			{ ! ready && (
				<div className="saha-canvas__loading">
					{ __( 'Đang tải vùng soạn thảo…', 'saha-builder' ) }
				</div>
			) }
		</div>
	);
}
