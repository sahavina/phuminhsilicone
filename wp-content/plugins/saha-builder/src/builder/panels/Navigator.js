/**
 * Bảng "Cấu trúc": cây element — chọn, thu gọn, kéo để sắp xếp lại.
 */
import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { useActions } from '../actions';
import { drag, useBuilder } from '../context';
import {
	ROOT,
	ancestors,
	canContain,
	findNode,
	isWithin,
	nodeLabel,
} from '../store/tree';

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

function Row( { node, parentId, index, depth, collapsed, toggle } ) {
	const { state, dispatch, defs } = useBuilder();
	const { insertType, moveTo } = useActions();
	const [ dropMode, setDropMode ] = useState( null );
	const hasChildren = ( node.children || [] ).length > 0;
	const isCollapsed = collapsed.has( node.id );
	const hasError = Object.keys( state.errors ).some(
		( key ) => key === node.id || key.startsWith( node.id + '.' )
	);

	return (
		<li
			role="treeitem"
			aria-expanded={ hasChildren ? ! isCollapsed : undefined }
			aria-selected={ state.selectedId === node.id }
		>
			<div
				className={ [
					'saha-b-nav__row',
					state.selectedId === node.id && 'is-selected',
					dropMode && 'is-drop-' + dropMode,
					hasError && 'has-error',
				]
					.filter( Boolean )
					.join( ' ' ) }
				style={ { paddingLeft: 8 + depth * 14 } }
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
								? __( 'Mở rộng', 'saha-builder' )
								: __( 'Thu gọn', 'saha-builder' )
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
					onClick={ () =>
						dispatch( { type: 'SELECT', id: node.id } )
					}
				>
					{ nodeLabel( defs, node ) }
				</button>
			</div>
			{ hasChildren && ! isCollapsed && (
				<ul role="group">
					{ node.children.map( ( child, i ) => (
						<Row
							key={ child.id }
							node={ child }
							parentId={ node.id }
							index={ i }
							depth={ depth + 1 }
							collapsed={ collapsed }
							toggle={ toggle }
						/>
					) ) }
				</ul>
			) }
		</li>
	);
}

export default function Navigator() {
	const { state, dispatch } = useBuilder();
	const [ collapsed, setCollapsed ] = useState( () => new Set() );

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

	// Element đang chọn luôn nhìn thấy: mở các nhánh cha.
	const hidden = state.selectedId
		? ancestors( state.doc, state.selectedId ).some( ( a ) =>
				collapsed.has( a.id )
			)
		: false;

	if ( ! state.doc.elements.length ) {
		return (
			<p className="saha-b-hint">
				{ __( 'Trang chưa có element nào.', 'saha-builder' ) }
			</p>
		);
	}

	return (
		<div className="saha-b-nav">
			{ hidden && (
				<Button
					variant="link"
					onClick={ () => setCollapsed( new Set() ) }
				>
					{ __( 'Mở hết để thấy element đang chọn', 'saha-builder' ) }
				</Button>
			) }
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
					/>
				) ) }
			</ul>
			<Button
				variant="link"
				onClick={ () => dispatch( { type: 'SELECT', id: null } ) }
			>
				{ __( 'Bỏ chọn', 'saha-builder' ) }
			</Button>
		</div>
	);
}
