/**
 * Bảng "Cấu trúc": cây element dạng khối — chọn, thu gọn, kéo để sắp xếp lại.
 *
 * - Section (cấp ngoài cùng) mặc định thu gọn; element đang chọn luôn được mở ra và cuộn tới.
 * - Mỗi dòng: tên loại + mô tả (tên tự đặt ở Nâng cao, không có thì chữ đầu tiên bên trong).
 * - Ẩn trên mọi thiết bị → nền sọc; ẩn trên một số thiết bị → nhãn nhỏ.
 * - "+ Thêm vào …" dưới Section / Hàng đang mở và "+ Thêm element" cuối danh sách:
 *   chọn đúng chỗ rồi mở bảng Thêm.
 */
import { Button } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { useActions } from '../actions';
import { drag, useBuilder } from '../context';
import {
	hasCustomLabel,
	hiddenState,
	nodeCaption,
	toggleHiddenAction,
} from '../store/outline';
import { ROOT, ancestors, canContain, findNode, isWithin } from '../store/tree';

const DEVICE_LABELS = {
	desktop: __( 'desktop', 'saha-builder' ),
	tablet: __( 'tablet', 'saha-builder' ),
	mobile: __( 'mobile', 'saha-builder' ),
};

/**
 * Vị trí thả trên một dòng: 1/4 trên = trước, 1/4 dưới = sau, giữa = vào trong (nếu chứa được).
 *
 * @param {Object}      args          Tham số.
 * @param {Object}      args.doc      Tài liệu.
 * @param {Object}      args.defs     Định nghĩa element.
 * @param {Object}      args.node     Node của dòng.
 * @param {string|null} args.parentId Cha của node.
 * @param {number}      args.index    Vị trí node trong cha.
 * @param {DragEvent}   args.event    Sự kiện kéo.
 * @return {Object|null} { parentId, index, mode } hoặc null.
 */
function dropOnRow( { doc, defs, node, parentId, index, event } ) {
	const payload = drag.payload;

	if ( ! payload ) {
		return null;
	}

	const type =
		'move' === payload.kind
			? findNode( doc, payload.id )?.type
			: payload.type;

	if (
		! type ||
		( 'move' === payload.kind && isWithin( doc, payload.id, node.id ) )
	) {
		return null;
	}

	const rect = event.currentTarget.getBoundingClientRect();
	const offset = ( event.clientY - rect.top ) / rect.height;
	const parentType = parentId ? findNode( doc, parentId ).type : ROOT;

	if (
		offset > 0.25 &&
		offset < 0.75 &&
		canContain( defs, node.type, type )
	) {
		return {
			parentId: node.id,
			index: ( node.children || [] ).length,
			wrap: [],
			mode: 'inside',
		};
	}

	if ( canContain( defs, parentType, type ) ) {
		return offset < 0.5
			? { parentId, index, wrap: [], mode: 'before' }
			: { parentId, index: index + 1, wrap: [], mode: 'after' };
	}

	return null;
}

function Row( { node, parentId, index, depth, collapsed, toggle, onAdd } ) {
	const { state, dispatch, defs } = useBuilder();
	const { insertType, insertCopy, moveTo } = useActions();
	const [ dropMode, setDropMode ] = useState( null );
	const rowRef = useRef( null );
	const children = node.children || [];
	const hasChildren = children.length > 0;
	const isCollapsed = collapsed.has( node.id );
	const isSelected = state.selectedId === node.id;
	const canHaveChildren = ( defs[ node.type ]?.allowedChildren || [] ).length;
	const hasError = Object.keys( state.errors ).some(
		( key ) => key === node.id || key.startsWith( node.id + '.' )
	);
	const name = defs[ node.type ]?.name || node.type;
	const caption = nodeCaption( node );
	const hidden = hiddenState( node );

	// Element vừa được chọn (trên canvas hoặc ở đây) → cuộn tới dòng của nó.
	useEffect( () => {
		if ( isSelected && rowRef.current ) {
			rowRef.current.scrollIntoView( { block: 'nearest' } );
		}
	}, [ isSelected ] );

	const select = () => dispatch( { type: 'SELECT', id: node.id } );

	return (
		<li
			role="treeitem"
			aria-expanded={ hasChildren ? ! isCollapsed : undefined }
			aria-selected={ isSelected }
			className={ 'saha-b-nav__item is-depth-' + Math.min( depth, 3 ) }
		>
			<div
				ref={ rowRef }
				className={ [
					'saha-b-nav__row',
					0 === depth && 'is-top',
					isSelected && 'is-selected',
					( hidden.all || hidden.off ) && 'is-hidden',
					hidden.off && 'is-off',
					dropMode && 'is-drop-' + dropMode,
					hasError && 'has-error',
				]
					.filter( Boolean )
					.join( ' ' ) }
				draggable={ ! state.readOnly }
				onDragStart={ ( event ) => {
					event.stopPropagation();
					drag.payload = { kind: 'move', id: node.id };
					event.dataTransfer.effectAllowed = 'move';
					event.dataTransfer.setData( 'text/plain', node.id );
				} }
				onDragEnd={ () => {
					drag.payload = null;
				} }
				onDragOver={ ( event ) => {
					const result = dropOnRow( {
						doc: state.doc,
						defs,
						node,
						parentId,
						index,
						event,
					} );
					setDropMode( result?.mode ?? null );

					if ( result ) {
						event.preventDefault();
						event.stopPropagation();
					}
				} }
				onDragLeave={ () => setDropMode( null ) }
				onDrop={ ( event ) => {
					event.preventDefault();
					event.stopPropagation();
					const result = dropOnRow( {
						doc: state.doc,
						defs,
						node,
						parentId,
						index,
						event,
					} );
					const payload = drag.payload;
					setDropMode( null );
					drag.payload = null;

					if ( ! result || ! payload ) {
						return;
					}

					if ( 'new' === payload.kind ) {
						insertType( payload.type, result );
					} else if ( 'node' === payload.kind ) {
						insertCopy( payload.node, result );
					} else {
						moveTo( payload.id, result );
					}
				} }
			>
				{ hasChildren ? (
					<button
						type="button"
						className="saha-b-nav__toggle"
						aria-label={
							isCollapsed
								? sprintf(
										/* translators: %s: tên element */
										__( 'Mở rộng %s', 'saha-builder' ),
										name
									)
								: sprintf(
										/* translators: %s: tên element */
										__( 'Thu gọn %s', 'saha-builder' ),
										name
									)
						}
						onClick={ () => toggle( node.id ) }
					>
						{ isCollapsed ? '▸' : '▾' }
					</button>
				) : (
					<span className="saha-b-nav__toggle" />
				) }
				<button
					type="button"
					className="saha-b-nav__label"
					onClick={ select }
				>
					<span className="saha-b-nav__type">{ name }</span>
					{ caption ? (
						<span
							className={
								'saha-b-nav__caption' +
								( hasCustomLabel( node ) ? ' is-custom' : '' )
							}
						>
							{ caption }
						</span>
					) : null }
					{ hidden.off ? (
						<span className="saha-b-nav__tag is-off">
							{ __( 'Đã tắt', 'saha-builder' ) }
						</span>
					) : null }
					{ ! hidden.off && hidden.all ? (
						<span className="saha-b-nav__tag">
							{ __( 'Ẩn', 'saha-builder' ) }
						</span>
					) : null }
					{ ! hidden.off && ! hidden.all && hidden.devices.length ? (
						<span className="saha-b-nav__tag">
							{ sprintf(
								/* translators: %s: danh sách thiết bị */
								__( 'Ẩn: %s', 'saha-builder' ),
								hidden.devices
									.map( ( d ) => DEVICE_LABELS[ d ] )
									.join( ', ' )
							) }
						</span>
					) : null }
				</button>
				{ ! state.readOnly && (
					<button
						type="button"
						className={
							'saha-b-nav__eye' + ( hidden.off ? ' is-off' : '' )
						}
						aria-pressed={ hidden.off }
						aria-label={
							hidden.off
								? sprintf(
										/* translators: %s: tên element */
										__( 'Bật lại %s', 'saha-builder' ),
										name
									)
								: sprintf(
										/* translators: %s: tên element */
										__(
											'Tắt %s (không hiển thị trên website)',
											'saha-builder'
										),
										name
									)
						}
						title={
							hidden.off
								? __(
										'Đang tắt — bấm để hiện lại trên website',
										'saha-builder'
									)
								: __(
										'Tắt khối (ẩn khỏi website)',
										'saha-builder'
									)
						}
						onClick={ () => dispatch( toggleHiddenAction( node ) ) }
					>
						{ hidden.off ? '◌' : '◉' }
					</button>
				) }
				{ /* Cùng việc với bấm tên (mở thiết lập) — chỉ là điểm bấm quen tay, không thêm vào thứ tự Tab. */ }
				<button
					type="button"
					className="saha-b-nav__gear"
					tabIndex={ -1 }
					aria-hidden="true"
					title={ __( 'Thiết lập', 'saha-builder' ) }
					onClick={ select }
				>
					⚙
				</button>
			</div>
			{ hasChildren && ! isCollapsed && (
				<ul role="group">
					{ children.map( ( child, i ) => (
						<Row
							key={ child.id }
							node={ child }
							parentId={ node.id }
							index={ i }
							depth={ depth + 1 }
							collapsed={ collapsed }
							toggle={ toggle }
							onAdd={ onAdd }
						/>
					) ) }
				</ul>
			) }
			{ ! state.readOnly &&
			canHaveChildren &&
			depth <= 1 &&
			( ! hasChildren || ! isCollapsed ) ? (
				<button
					type="button"
					className="saha-b-nav__add"
					onClick={ () => {
						select();
						onAdd();
					} }
				>
					{ sprintf(
						/* translators: %s: tên element chứa */
						__( '+ Thêm vào %s', 'saha-builder' ),
						name
					) }
				</button>
			) : null }
		</li>
	);
}

export default function Navigator( { onAdd = () => {} } ) {
	const { state, dispatch } = useBuilder();

	// Mặc định: mọi section (cấp ngoài cùng) thu gọn — trừ nhánh chứa element đang chọn.
	const [ collapsed, setCollapsed ] = useState( () => {
		const open = new Set(
			state.selectedId
				? [
						...ancestors( state.doc, state.selectedId ).map(
							( a ) => a.id
						),
						state.selectedId,
					]
				: []
		);

		return new Set(
			state.doc.elements
				.filter(
					( n ) => ( n.children || [] ).length && ! open.has( n.id )
				)
				.map( ( n ) => n.id )
		);
	} );

	// Chọn element nằm trong nhánh đang thu gọn → mở các nhánh cha.
	useEffect( () => {
		if ( ! state.selectedId ) {
			return;
		}

		const chain = ancestors( state.doc, state.selectedId ).map(
			( a ) => a.id
		);

		setCollapsed( ( prev ) =>
			chain.some( ( id ) => prev.has( id ) )
				? new Set(
						[ ...prev ].filter( ( id ) => ! chain.includes( id ) )
					)
				: prev
		);
	}, [ state.selectedId, state.doc ] );

	const toggle = ( id ) =>
		setCollapsed( ( prev ) => {
			const next = new Set( prev );
			if ( next.has( id ) ) {
				next.delete( id );
			} else {
				next.add( id );
			}
			return next;
		} );

	const collapseAll = () =>
		setCollapsed(
			new Set(
				state.doc.elements
					.filter( ( n ) => ( n.children || [] ).length )
					.map( ( n ) => n.id )
			)
		);

	const addAtEnd = () => {
		dispatch( { type: 'SELECT', id: null } );
		onAdd();
	};

	if ( ! state.doc.elements.length ) {
		return (
			<div className="saha-b-nav">
				<p className="saha-b-hint">
					{ __( 'Trang chưa có element nào.', 'saha-builder' ) }
				</p>
				{ ! state.readOnly && (
					<button
						type="button"
						className="saha-b-nav__add saha-b-nav__add--end"
						onClick={ addAtEnd }
					>
						{ __( '+ Thêm element', 'saha-builder' ) }
					</button>
				) }
			</div>
		);
	}

	return (
		<div className="saha-b-nav">
			<div className="saha-b-nav__tools">
				<Button
					variant="link"
					onClick={ () => setCollapsed( new Set() ) }
				>
					{ __( 'Mở hết', 'saha-builder' ) }
				</Button>
				<Button variant="link" onClick={ collapseAll }>
					{ __( 'Thu gọn hết', 'saha-builder' ) }
				</Button>
				<Button
					variant="link"
					onClick={ () => dispatch( { type: 'SELECT', id: null } ) }
				>
					{ __( 'Bỏ chọn', 'saha-builder' ) }
				</Button>
			</div>
			<ul
				role="tree"
				aria-label={ __( 'Cấu trúc trang', 'saha-builder' ) }
			>
				{ state.doc.elements.map( ( node, i ) => (
					<Row
						key={ node.id }
						node={ node }
						parentId={ null }
						index={ i }
						depth={ 0 }
						collapsed={ collapsed }
						toggle={ toggle }
						onAdd={ onAdd }
					/>
				) ) }
			</ul>
			{ ! state.readOnly && (
				<button
					type="button"
					className="saha-b-nav__add saha-b-nav__add--end"
					onClick={ addAtEnd }
				>
					{ __( '+ Thêm element', 'saha-builder' ) }
				</button>
			) }
		</div>
	);
}
