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
import { findNode, nodeLabel, walk } from '../store/tree';
import { inheritedAt, setAt, valueAt } from '../controls/responsive';
import { CANVAS_UI_CSS } from './canvas-ui';
import { computeDrop } from './drop';
import { resizeUnit as resizeUnitFor, resizeValue } from './resize';

const RENDER_DELAY = 250;

/**
 * Element có tay kéo đổi kích thước: trục → prop (control `size`, responsive).
 * x = cạnh phải (độ rộng), y = cạnh dưới (chiều cao tối thiểu).
 */
const RESIZABLE = {
	container: { x: 'width', y: 'minHeight' },
};

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
	const [ refresh, setRefresh ] = useState( 0 );
	const [ size, setSize ] = useState( { width: 0, height: 0 } );

	// Bản mới nhất cho các listener gắn một lần trong iframe.
	const latest = useRef( {} );
	latest.current = { state, defs, dispatch, actions, onShortcut, device };

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
	}, [ state.doc, refresh, renderSection, publishErrors ] );

	// Quay lại tab builder (có thể vừa sửa block ở tab khác) → render lại section chứa block.
	useEffect( () => {
		const onFocus = () => {
			let stale = false;

			latest.current.state.doc.elements.forEach( ( section ) => {
				let hasBlock = 'block' === section.type;
				walk( section.children, ( node ) => {
					hasBlock = hasBlock || 'block' === node.type;
				} );

				const cached = cacheRef.current.get( section.id );

				// Đánh dấu cũ (key rỗng) nhưng giữ HTML → không nháy "Đang tải…".
				if ( hasBlock && cached ) {
					cacheRef.current.set( section.id, { ...cached, key: '' } );
					stale = true;
				}
			} );

			if ( stale ) {
				setRefresh( ( n ) => n + 1 );
			}
		};

		window.addEventListener( 'focus', onFocus );

		return () => window.removeEventListener( 'focus', onFocus );
	}, [] );

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

		const handles = [ 'x', 'y' ].map( ( axis ) =>
			idoc.getElementById( 'saha-canvas-resize-' + axis )
		);

		if ( ! target || latest.current.state.readOnly ) {
			toolbar.hidden = true;
			handles.forEach( ( h ) => ( h.hidden = true ) );
			return;
		}

		const rect = target.getBoundingClientRect();
		const win = idoc.defaultView;
		const node = findNode( latest.current.state.doc, selected );
		const resizable = node ? RESIZABLE[ node.type ] : null;

		// Tay kéo: giữa cạnh phải (độ rộng) và giữa cạnh dưới (chiều cao tối thiểu).
		handles[ 0 ].hidden = ! resizable;
		handles[ 1 ].hidden = ! resizable;

		if ( resizable ) {
			handles[ 0 ].style.left = rect.right + win.scrollX + 'px';
			handles[ 0 ].style.top =
				rect.top + win.scrollY + rect.height / 2 + 'px';
			handles[ 1 ].style.left =
				rect.left + win.scrollX + rect.width / 2 + 'px';
			handles[ 1 ].style.top = rect.bottom + win.scrollY + 'px';
		}

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

	/*
	 * ---------------------------------------------------------------
	 * Kéo đổi kích thước (Hộp): xem trước bằng style inline, thả chuột
	 * → UPDATE prop cho thiết bị đang xem (một bước undo).
	 * ---------------------------------------------------------------
	 */
	const commitResize = ( axis, value ) => {
		const { state: s, dispatch: d, device: dev } = latest.current;
		const node = s.selectedId ? findNode( s.doc, s.selectedId ) : null;
		const key = node && RESIZABLE[ node.type ]?.[ axis ];

		if ( ! key || s.readOnly ) {
			return;
		}

		d( {
			type: 'UPDATE',
			id: node.id,
			scope: 'props',
			key,
			value: setAt( node.props?.[ key ], dev, value ),
		} );
	};

	const startResize = ( idoc, axis, event ) => {
		const { state: s, device: dev } = latest.current;
		const node = s.selectedId ? findNode( s.doc, s.selectedId ) : null;
		const key = node && RESIZABLE[ node.type ]?.[ axis ];
		const target =
			key && idoc.querySelector( `[data-saha-id="${ s.selectedId }"]` );

		if ( ! target || s.readOnly || 0 !== event.button ) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();

		const handle = event.currentTarget;
		const win = idoc.defaultView;
		const rect = target.getBoundingClientRect();
		const parentStyle = win.getComputedStyle( target.parentElement );
		const parent =
			target.parentElement.clientWidth -
			parseFloat( parentStyle.paddingLeft || 0 ) -
			parseFloat( parentStyle.paddingRight || 0 );
		const current =
			valueAt( node.props?.[ key ], dev ) ??
			inheritedAt( node.props?.[ key ], dev );
		const unit = resizeUnitFor( current, axis );
		const property = 'x' === axis ? 'width' : 'minHeight';
		const label = idoc.getElementById( 'saha-canvas-size' );
		let value = null;

		try {
			handle.setPointerCapture( event.pointerId );
		} catch {
			// Con trỏ không bắt được (thiết bị lạ / sự kiện giả lập): vẫn kéo được khi chuột còn trên tay kéo.
		}
		target.setAttribute( 'data-saha-resized', '1' );

		const move = ( e ) => {
			const delta =
				'x' === axis
					? e.clientX - event.clientX
					: e.clientY - event.clientY;

			value = resizeValue( {
				axis,
				px: ( 'x' === axis ? rect.width : rect.height ) + delta,
				unit,
				parent,
				viewport: 'x' === axis ? win.innerWidth : win.innerHeight,
			} );

			target.style[ property ] = value;
			label.hidden = false;
			label.textContent = value;
			label.style.left = e.clientX + win.scrollX + 12 + 'px';
			label.style.top = e.clientY + win.scrollY + 12 + 'px';
			positionToolbar();
		};

		const up = () => {
			handle.removeEventListener( 'pointermove', move );
			handle.removeEventListener( 'pointerup', up );
			handle.removeEventListener( 'pointercancel', up );
			label.hidden = true;

			if ( null !== value ) {
				commitResize( axis, value );
			} else {
				target.removeAttribute( 'data-saha-resized' );
			}
		};

		handle.addEventListener( 'pointermove', move );
		handle.addEventListener( 'pointerup', up );
		handle.addEventListener( 'pointercancel', up );
	};

	// CSS mới từ server đã về → bỏ style inline lúc kéo (giá trị thật nằm trong CSS sinh ra).
	useEffect( () => {
		idocRef.current
			?.querySelectorAll( '[data-saha-resized]' )
			.forEach( ( el ) => {
				el.style.width = '';
				el.style.minHeight = '';
				el.removeAttribute( 'data-saha-resized' );
			} );
	}, [ version ] );

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

		const sizeLabel = idoc.createElement( 'div' );
		sizeLabel.id = 'saha-canvas-size';
		sizeLabel.className = 'saha-canvas-size';
		sizeLabel.hidden = true;
		idoc.body.appendChild( sizeLabel );

		[ 'x', 'y' ].forEach( ( axis ) => {
			const handle = idoc.createElement( 'div' );

			handle.id = 'saha-canvas-resize-' + axis;
			handle.className = 'saha-canvas-resize saha-canvas-resize--' + axis;
			handle.hidden = true;
			handle.title =
				'x' === axis
					? __(
							'Kéo để đổi độ rộng (theo thiết bị đang xem). Bấm đúp: bỏ độ rộng đã đặt.',
							'saha-builder'
						)
					: __(
							'Kéo để đổi chiều cao tối thiểu (theo thiết bị đang xem). Bấm đúp: bỏ.',
							'saha-builder'
						);
			handle.addEventListener( 'pointerdown', ( event ) =>
				startResize( idoc, axis, event )
			);
			handle.addEventListener( 'dblclick', ( event ) => {
				event.preventDefault();
				event.stopPropagation();
				commitResize( axis, null );
			} );
			idoc.body.appendChild( handle );
		} );

		idoc.addEventListener(
			'click',
			( event ) => {
				event.preventDefault();

				// Bấm / thả tay kéo kích thước không đổi lựa chọn.
				if ( event.target.closest( '.saha-canvas-resize' ) ) {
					return;
				}

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
			} else if ( 'node' === payload.kind ) {
				latest.current.actions.insertCopy( payload.node, result );
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
