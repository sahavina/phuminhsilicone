/**
 * Tính vị trí thả trong canvas từ toạ độ con trỏ.
 *
 * Thứ tự ưu tiên khi con trỏ nằm trên một element:
 * 1. Sát mép (≤ EDGE px) và cha chứa được → trước/sau element đó.
 * 2. Element là container chứa được → vào trong, vị trí theo các con.
 * 3. Cha chứa được → trước/sau theo nửa element.
 * 4. Không được → leo lên element cha và thử lại; hết thì cấp gốc (tự bọc Section…).
 */
import {
	ROOT,
	ancestors,
	canContain,
	findNode,
	isWithin,
	wrapChain,
} from '../store/tree';

const EDGE = 10;

/**
 * Element DOM của node.
 *
 * @param {Document} idoc Document của iframe.
 * @param {string}   id   ID node.
 * @return {Element|null} Element.
 */
function el( idoc, id ) {
	return idoc.querySelector( `[data-saha-id="${ id }"]` );
}

/**
 * Các con xếp ngang hay dọc (theo bố cục thật — hàng xếp chồng ở mobile là dọc).
 *
 * @param {DOMRect[]} rects Khung các con.
 * @return {boolean} true nếu ngang.
 */
function isHorizontal( rects ) {
	return (
		rects.length > 1 &&
		Math.abs( rects[ 0 ].top - rects[ 1 ].top ) < 4 &&
		rects[ 1 ].left > rects[ 0 ].left
	);
}

/**
 * Vị trí chèn trong danh sách con + vạch chỉ báo.
 *
 * @param {Document} idoc     Document iframe.
 * @param {Object[]} children Node con.
 * @param {number}   x        Toạ độ.
 * @param {number}   y        Toạ độ.
 * @param {DOMRect}  box      Khung container (khi không có con).
 * @return {{index: number, line: Object}} Kết quả.
 */
function indexAmong( idoc, children, x, y, box ) {
	const rects = children
		.map( ( c ) => el( idoc, c.id )?.getBoundingClientRect() )
		.filter( Boolean );

	if ( ! rects.length ) {
		return {
			index: 0,
			line: {
				box: true,
				top: box.top,
				left: box.left,
				width: box.width,
				height: Math.max( box.height, 24 ),
			},
		};
	}

	const horizontal = isHorizontal( rects );
	let index = rects.length;

	for ( let i = 0; i < rects.length; i++ ) {
		const r = rects[ i ];
		const before = horizontal
			? x < r.left + r.width / 2
			: y < r.top + r.height / 2;

		if ( before ) {
			index = i;
			break;
		}
	}

	return { index, line: lineAt( rects, index, horizontal ) };
}

/**
 * Vạch chỉ báo trước con thứ `index` (hoặc sau con cuối).
 *
 * @param {DOMRect[]} rects      Khung các con.
 * @param {number}    index      Vị trí.
 * @param {boolean}   horizontal Ngang.
 * @return {Object} { top, left, width, height }.
 */
function lineAt( rects, index, horizontal ) {
	const r = rects[ Math.min( index, rects.length - 1 ) ];
	const after = index >= rects.length;

	if ( horizontal ) {
		return {
			top: r.top,
			left: ( after ? r.right : r.left ) - 2,
			width: 4,
			height: r.height,
		};
	}

	return {
		top: ( after ? r.bottom : r.top ) - 2,
		left: r.left,
		width: r.width,
		height: 4,
	};
}

/**
 * Tính vị trí thả.
 *
 * @param {Object}   args         Tham số.
 * @param {Object}   args.doc     Tài liệu.
 * @param {Object}   args.defs    Định nghĩa.
 * @param {Object}   args.payload { kind: 'new', type } | { kind: 'move', id }.
 * @param {Element}  args.target  Element dưới con trỏ.
 * @param {number}   args.x       clientX.
 * @param {number}   args.y       clientY.
 * @param {Document} args.idoc    Document iframe.
 * @return {{parentId: string|null, index: number, wrap: string[], line: Object}|null} Vị trí.
 */
export function computeDrop( { doc, defs, payload, target, x, y, idoc } ) {
	const movingId = 'move' === payload.kind ? payload.id : null;
	const type = movingId ? findNode( doc, movingId )?.type : payload.type;

	if ( ! type ) {
		return null;
	}

	let current =
		target && target.closest ? target.closest( '[data-saha-id]' ) : null;

	while ( current ) {
		const id = current.getAttribute( 'data-saha-id' );
		const node = findNode( doc, id );

		if ( node && ! ( movingId && isWithin( doc, movingId, id ) ) ) {
			const trail = ancestors( doc, id );
			const parent = trail.length ? trail[ trail.length - 1 ] : null;
			const parentType = parent ? parent.type : ROOT;
			const siblings = parent ? parent.children : doc.elements;
			const index = siblings.findIndex( ( n ) => n.id === id );
			const rect = current.getBoundingClientRect();
			const siblingRects = siblings
				.map( ( n ) => el( idoc, n.id )?.getBoundingClientRect() )
				.filter( Boolean );
			const horizontal = isHorizontal( siblingRects );
			const start = horizontal ? x - rect.left : y - rect.top;
			const size = horizontal ? rect.width : rect.height;
			const parentOk = canContain( defs, parentType, type );

			if ( parentOk && ( start <= EDGE || start >= size - EDGE ) ) {
				const at = start <= EDGE ? index : index + 1;
				return {
					parentId: parent ? parent.id : null,
					index: at,
					wrap: [],
					line: lineAt( siblingRects, at, horizontal ),
				};
			}

			if ( canContain( defs, node.type, type ) ) {
				const inner =
					current.querySelector( ':scope > .saha-section__inner' ) ||
					current;
				const { index: at, line } = indexAmong(
					idoc,
					node.children || [],
					x,
					y,
					inner.getBoundingClientRect()
				);
				return { parentId: node.id, index: at, wrap: [], line };
			}

			if ( parentOk ) {
				const at = start < size / 2 ? index : index + 1;
				return {
					parentId: parent ? parent.id : null,
					index: at,
					wrap: [],
					line: lineAt( siblingRects, at, horizontal ),
				};
			}
		}

		current = current.parentElement
			? current.parentElement.closest( '[data-saha-id]' )
			: null;
	}

	// Cấp gốc: theo các section.
	const chain = wrapChain( defs, ROOT, type );

	if ( ! chain ) {
		return null;
	}

	const canvas = idoc.getElementById( 'saha-canvas' );
	const { index, line } = indexAmong(
		idoc,
		doc.elements,
		x,
		y,
		canvas.getBoundingClientRect()
	);

	return { parentId: null, index, wrap: chain, line };
}
