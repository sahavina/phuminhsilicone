/**
 * Bảng "Thêm": ba mục
 * - Element: kéo element vào canvas, hoặc bấm để thêm cạnh/vào element đang chọn;
 * - Khối mẫu: section dựng sẵn (saha-core Builder\Patterns) — chèn bản sao, sửa tự do;
 * - Block đã lưu: Block dùng chung — chèn element "Block dùng chung" trỏ tới block đó
 *   (sửa block một nơi, mọi trang dùng nó cùng đổi).
 */
import { Button, Icon, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { useActions } from '../actions';
import { api } from '../api';
import { config, drag, useBuilder } from '../context';
import { collectIds, createNode, placeableTypes } from '../store/tree';

const CATEGORIES = [
	[ 'layout', __( 'Bố cục', 'saha-builder' ) ],
	[ 'content', __( 'Nội dung', 'saha-builder' ) ],
	[ 'marketing', __( 'Marketing', 'saha-builder' ) ],
	[ 'woocommerce', __( 'Sản phẩm', 'saha-builder' ) ],
	[ 'blog', __( 'Blog', 'saha-builder' ) ],
	[ 'header', __( 'Header & Footer', 'saha-builder' ) ],
	[ 'dynamic', __( 'Template (động)', 'saha-builder' ) ],
	[ 'product-template', __( 'Trang sản phẩm (động)', 'saha-builder' ) ],
];

const PATTERN_GROUPS = [
	[ 'store', __( 'Kiểu cửa hàng', 'saha-builder' ) ],
	[ 'basic', __( 'Cơ bản', 'saha-builder' ) ],
];

const MODES = [
	[ 'elements', __( 'Element', 'saha-builder' ) ],
	[ 'patterns', __( 'Khối mẫu', 'saha-builder' ) ],
	[ 'blocks', __( 'Block đã lưu', 'saha-builder' ) ],
];

// Tải một lần mỗi lần mở editor (chuyển qua lại giữa các mục không gọi lại API).
const cache = { patterns: null, blocks: null };

/**
 * Một ô trong lưới: bấm để thêm, kéo vào canvas.
 *
 * @param {Object}       props             Props.
 * @param {string}       props.icon        Dashicon.
 * @param {string}       props.label       Tên.
 * @param {string}       [props.title]     Mô tả (tooltip).
 * @param {boolean}      props.disabled    Chỉ xem.
 * @param {() => Object} props.payload     () => payload kéo thả.
 * @param {() => void}   props.onClick     Bấm.
 * @param {string}       [props.className] Class thêm.
 */
function Tile( { icon, label, title, disabled, payload, onClick, className } ) {
	return (
		<button
			type="button"
			className={
				'saha-b-inserter__item' + ( className ? ' ' + className : '' )
			}
			title={ title || undefined }
			draggable={ ! disabled }
			disabled={ disabled }
			onDragStart={ ( event ) => {
				drag.payload = payload();
				event.dataTransfer.effectAllowed = 'copy';
				event.dataTransfer.setData( 'text/plain', label );
			} }
			onDragEnd={ () => {
				drag.payload = null;
			} }
			onClick={ onClick }
		>
			<Icon icon={ icon || 'block-default' } />
			<span>{ label }</span>
		</button>
	);
}

/**
 * Tải dữ liệu một lần (khối mẫu / block).
 *
 * @param {string}               key  patterns | blocks.
 * @param {() => Promise<Array>} load Hàm gọi API.
 * @return {Array|null|false} null = đang tải, false = lỗi.
 */
function useCached( key, load ) {
	const [ data, setData ] = useState( cache[ key ] );

	useEffect( () => {
		if ( null !== cache[ key ] ) {
			return;
		}

		let alive = true;

		load()
			.then( ( result ) => {
				cache[ key ] = Array.isArray( result ) ? result : [];

				if ( alive ) {
					setData( cache[ key ] );
				}
			} )
			.catch( () => {
				if ( alive ) {
					setData( false );
				}
			} );

		return () => {
			alive = false;
		};
	}, [ key, load ] );

	return data;
}

function ElementsPanel( { elements, readOnly } ) {
	const { insertType } = useActions();

	const known = CATEGORIES.map( ( [ key ] ) => key );
	const groups = [
		...CATEGORIES,
		...[ ...new Set( elements.map( ( e ) => e.category ) ) ]
			.filter( ( c ) => ! known.includes( c ) )
			.map( ( c ) => [ c, c ] ),
	];

	return groups.map( ( [ key, label ] ) => {
		const items = elements.filter( ( e ) => e.category === key );

		if ( ! items.length ) {
			return null;
		}

		return (
			<section key={ key } className="saha-b-inserter__group">
				<h3>{ label }</h3>
				<div className="saha-b-inserter__grid">
					{ items.map( ( element ) => (
						<Tile
							key={ element.type }
							icon={ element.icon }
							label={ element.name }
							disabled={ readOnly }
							payload={ () => ( {
								kind: 'new',
								type: element.type,
							} ) }
							onClick={ () => insertType( element.type ) }
						/>
					) ) }
				</div>
			</section>
		);
	} );
}

function PatternsPanel( { placeable, readOnly } ) {
	const { insertCopy } = useActions();
	const patterns = useCached( 'patterns', api.patterns );

	if ( null === patterns ) {
		return <Spinner />;
	}

	if ( false === patterns ) {
		return (
			<p className="saha-b-hint">
				{ __( 'Không tải được khối mẫu.', 'saha-builder' ) }
			</p>
		);
	}

	const usable = patterns.filter( ( p ) => placeable.has( p.node.type ) );

	if ( ! usable.length ) {
		return (
			<p className="saha-b-hint">
				{ __(
					'Khối mẫu là section cho trang và template — không dùng trong header/footer.',
					'saha-builder'
				) }
			</p>
		);
	}

	const known = PATTERN_GROUPS.map( ( [ key ] ) => key );
	const groups = [
		...PATTERN_GROUPS,
		...[ ...new Set( usable.map( ( p ) => p.group ) ) ]
			.filter( ( g ) => ! known.includes( g ) )
			.map( ( g ) => [ g, g ] ),
	];

	return (
		<>
			<p className="saha-b-hint">
				{ __(
					'Chèn một section dựng sẵn (bản sao — sửa thoải mái, không ảnh hưởng trang khác).',
					'saha-builder'
				) }
			</p>
			{ groups.map( ( [ key, label ] ) => {
				const items = usable.filter( ( p ) => p.group === key );

				if ( ! items.length ) {
					return null;
				}

				return (
					<section key={ key } className="saha-b-inserter__group">
						<h3>{ label }</h3>
						<div className="saha-b-inserter__grid">
							{ items.map( ( pattern ) => (
								<Tile
									key={ pattern.id }
									icon={ pattern.icon }
									label={ pattern.name }
									title={ pattern.description }
									disabled={ readOnly }
									payload={ () => ( {
										kind: 'node',
										type: pattern.node.type,
										node: pattern.node,
									} ) }
									onClick={ () => insertCopy( pattern.node ) }
								/>
							) ) }
						</div>
					</section>
				);
			} ) }
		</>
	);
}

function BlocksPanel( { placeable, readOnly } ) {
	const { state, defs } = useBuilder();
	const { insertCopy } = useActions();
	const blocks = useCached( 'blocks', api.blocks );

	if ( ! placeable.has( 'block' ) ) {
		return (
			<p className="saha-b-hint">
				{ __(
					'Không chèn được Block dùng chung trong tài liệu này.',
					'saha-builder'
				) }
			</p>
		);
	}

	if ( null === blocks ) {
		return <Spinner />;
	}

	if ( false === blocks ) {
		return (
			<p className="saha-b-hint">
				{ __( 'Không tải được danh sách block.', 'saha-builder' ) }
			</p>
		);
	}

	// Không chèn block vào chính nó.
	const list = blocks.filter( ( b ) => b.id !== config.postId );

	// Node "Block dùng chung" trỏ tới block — ID tạm, insertCopy cấp ID mới khi chèn.
	const refNode = ( id ) => {
		const node = createNode( defs, 'block', collectIds( state.doc ) );

		node.props = { ...node.props, blockId: id };

		return node;
	};

	const draft = __( '(nháp)', 'saha-builder' );

	return (
		<>
			<p className="saha-b-hint">
				{ __(
					'Chèn block dùng chung: sửa block một nơi, mọi trang dùng nó cùng đổi.',
					'saha-builder'
				) }
			</p>
			{ list.length ? (
				<div className="saha-b-inserter__grid">
					{ list.map( ( block ) => (
						<Tile
							key={ block.id }
							icon="screenoptions"
							label={
								block.title +
								( 'publish' === block.status
									? ''
									: ' ' + draft )
							}
							disabled={ readOnly }
							payload={ () => ( {
								kind: 'node',
								type: 'block',
								node: refNode( block.id ),
							} ) }
							onClick={ () => insertCopy( refNode( block.id ) ) }
						/>
					) ) }
				</div>
			) : (
				<p className="saha-b-hint">
					{ __(
						'Chưa có block nào. Chọn một element → cột thiết lập, tab Nội dung → "Lưu thành block".',
						'saha-builder'
					) }
				</p>
			) }
			{ config.blocksUrl ? (
				<Button
					variant="link"
					href={ config.blocksUrl }
					target="_blank"
					rel="noopener noreferrer"
				>
					{ __( 'Quản lý blocks', 'saha-builder' ) }
				</Button>
			) : null }
		</>
	);
}

export default function Inserter() {
	const { elements: all, state, defs } = useBuilder();
	const [ mode, setMode ] = useState( 'elements' );

	// Chỉ element đặt được trong tài liệu này (ví dụ Section không có trong header).
	const placeable = placeableTypes( defs );
	const elements = all.filter( ( e ) => placeable.has( e.type ) );
	const readOnly = state.readOnly;

	return (
		<div className="saha-b-inserter">
			<div
				className="saha-b-inserter__modes"
				role="tablist"
				aria-label={ __( 'Loại nội dung thêm', 'saha-builder' ) }
			>
				{ MODES.map( ( [ key, label ] ) => (
					<button
						key={ key }
						type="button"
						role="tab"
						id={ 'saha-b-ins-' + key }
						aria-selected={ key === mode }
						aria-controls="saha-b-ins-panel"
						tabIndex={ key === mode ? 0 : -1 }
						className={
							'saha-b-inserter__mode' +
							( key === mode ? ' is-active' : '' )
						}
						onClick={ () => setMode( key ) }
						onKeyDown={ ( event ) => {
							const step =
								{ ArrowRight: 1, ArrowLeft: -1 }[ event.key ] ||
								0;

							if ( ! step ) {
								return;
							}

							event.preventDefault();

							const keys = MODES.map( ( [ k ] ) => k );
							const next =
								keys[
									( keys.indexOf( mode ) +
										step +
										keys.length ) %
										keys.length
								];

							setMode( next );
							event.currentTarget.parentNode
								.querySelector( '#saha-b-ins-' + next )
								?.focus();
						} }
					>
						{ label }
					</button>
				) ) }
			</div>
			<div
				id="saha-b-ins-panel"
				role="tabpanel"
				aria-labelledby={ 'saha-b-ins-' + mode }
			>
				{ 'elements' === mode ? (
					<>
						<p className="saha-b-hint">
							{ __(
								'Kéo vào trang, hoặc bấm để thêm vào/cạnh element đang chọn.',
								'saha-builder'
							) }
						</p>
						<ElementsPanel
							elements={ elements }
							readOnly={ readOnly }
						/>
					</>
				) : null }
				{ 'patterns' === mode ? (
					<PatternsPanel
						placeable={ placeable }
						readOnly={ readOnly }
					/>
				) : null }
				{ 'blocks' === mode ? (
					<BlocksPanel
						placeable={ placeable }
						readOnly={ readOnly }
					/>
				) : null }
			</div>
		</div>
	);
}
