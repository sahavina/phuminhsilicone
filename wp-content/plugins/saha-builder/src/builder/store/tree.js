/**
 * Thao tác cây layout — hàm thuần, không đụng DOM/React (có unit test).
 *
 * Tài liệu: { version, elements: Node[] }, Node: { id, type, props, advanced?, children? }.
 * Mọi hàm trả tài liệu MỚI (bất biến) để undo/redo chỉ cần giữ tham chiếu.
 *
 * Quy tắc cha–con lấy từ định nghĩa element của server (allowedParents /
 * allowedChildren) — cùng quy tắc mà Sanitizer PHP kiểm khi lưu.
 */

export const ROOT = 'root';

const ID_CHARS = 'abcdefghijklmnopqrstuvwxyz0123456789';

/**
 * ID 8 ký tự [a-z0-9], không trùng với tập đã có.
 *
 * @param {Set<string>} [taken] ID đã dùng.
 * @return {string} ID mới.
 */
export function newId( taken = new Set() ) {
	let id;

	do {
		id = '';
		for ( let i = 0; i < 8; i++ ) {
			id += ID_CHARS[ Math.floor( Math.random() * ID_CHARS.length ) ];
		}
	} while ( taken.has( id ) );

	return id;
}

/**
 * Tập ID trong tài liệu.
 *
 * @param {Object} doc Tài liệu.
 * @return {Set<string>} ID.
 */
export function collectIds( doc ) {
	const ids = new Set();
	walk( doc.elements, ( node ) => ids.add( node.id ) );
	return ids;
}

/**
 * Duyệt mọi node (tiền thứ tự).
 *
 * @param {Object[]}                        nodes    Danh sách node.
 * @param {(...args: unknown[]) => unknown} visit    fn( node, parent, index ).
 * @param {Object}                          [parent] Node cha.
 */
export function walk( nodes, visit, parent = null ) {
	( nodes || [] ).forEach( ( node, index ) => {
		visit( node, parent, index );
		walk( node.children, visit, node );
	} );
}

/**
 * Tìm node.
 *
 * @param {Object} doc Tài liệu.
 * @param {string} id  ID.
 * @return {{node: Object, parent: Object|null, index: number}|null} Vị trí.
 */
export function locate( doc, id ) {
	let found = null;

	walk( doc.elements, ( node, parent, index ) => {
		if ( ! found && node.id === id ) {
			found = { node, parent, index };
		}
	} );

	return found;
}

/**
 * Node theo ID.
 *
 * @param {Object} doc Tài liệu.
 * @param {string} id  ID.
 * @return {Object|null} Node.
 */
export function findNode( doc, id ) {
	return locate( doc, id )?.node ?? null;
}

/**
 * Chuỗi tổ tiên từ gốc tới node (không gồm node).
 *
 * @param {Object} doc Tài liệu.
 * @param {string} id  ID.
 * @return {Object[]} Tổ tiên.
 */
export function ancestors( doc, id ) {
	const path = [];

	const search = ( nodes, trail ) => {
		for ( const node of nodes || [] ) {
			if ( node.id === id ) {
				path.push( ...trail );
				return true;
			}
			if ( search( node.children, [ ...trail, node ] ) ) {
				return true;
			}
		}
		return false;
	};

	search( doc.elements, [] );

	return path;
}

/**
 * `id` có nằm trong subtree của `rootId` (kể cả chính nó) không.
 *
 * @param {Object} doc    Tài liệu.
 * @param {string} rootId Gốc subtree.
 * @param {string} id     ID cần kiểm.
 * @return {boolean} Kết quả.
 */
export function isWithin( doc, rootId, id ) {
	if ( rootId === id ) {
		return true;
	}
	return ancestors( doc, id ).some( ( node ) => node.id === rootId );
}

/**
 * Element loại `parentType` có chứa được loại `childType` không.
 *
 * @param {Object} defs       Định nghĩa theo type.
 * @param {string} parentType Type cha hoặc ROOT.
 * @param {string} childType  Type con.
 * @return {boolean} Kết quả.
 */
export function canContain( defs, parentType, childType ) {
	const child = defs[ childType ];

	if ( ! child ) {
		return false;
	}

	if ( ! ( child.allowedParents || [] ).includes( parentType ) ) {
		return false;
	}

	if ( ROOT === parentType ) {
		return true;
	}

	const allowed = defs[ parentType ]?.allowedChildren || [];

	return allowed.includes( '*' ) || allowed.includes( childType );
}

/**
 * Element có chứa con được không.
 *
 * @param {Object} defs Định nghĩa.
 * @param {string} type Type.
 * @return {boolean} Kết quả.
 */
export function isContainer( defs, type ) {
	return ( defs[ type ]?.allowedChildren || [] ).length > 0;
}

/**
 * Chuỗi element bọc ngắn nhất để đặt `type` vào `parentType`
 * (ví dụ Heading ở cấp gốc → ['section']; Column ở gốc → ['section', 'row']).
 *
 * @param {Object} defs       Định nghĩa.
 * @param {string} parentType Type cha.
 * @param {string} type       Type cần đặt.
 * @param {number} [maxDepth] Số lớp bọc tối đa.
 * @return {string[]|null} Danh sách type bọc (ngoài → trong), [] nếu đặt thẳng được, null nếu không thể.
 */
export function wrapChain( defs, parentType, type, maxDepth = 2 ) {
	if ( canContain( defs, parentType, type ) ) {
		return [];
	}

	const containers = Object.keys( defs ).filter( ( t ) =>
		isContainer( defs, t )
	);
	let frontier = [ [] ];

	for ( let depth = 1; depth <= maxDepth; depth++ ) {
		const next = [];

		for ( const chain of frontier ) {
			const outer = chain.length ? chain[ chain.length - 1 ] : parentType;

			for ( const candidate of containers ) {
				if ( ! canContain( defs, outer, candidate ) ) {
					continue;
				}

				const extended = [ ...chain, candidate ];

				if ( canContain( defs, candidate, type ) ) {
					return extended;
				}

				next.push( extended );
			}
		}

		frontier = next;
	}

	return null;
}

/**
 * Tạo node mới với cấu trúc khởi đầu hợp lý.
 *
 * @param {Object}      defs  Định nghĩa.
 * @param {string}      type  Type.
 * @param {Set<string>} taken ID đã dùng (được cập nhật).
 * @return {Object} Node.
 */
export function createNode( defs, type, taken ) {
	const id = newId( taken );
	taken.add( id );

	const node = { id, type, props: {} };

	// Hàng mới có sẵn 2 cột — hàng rỗng không dùng được.
	if ( 'row' === type && defs.column ) {
		node.children = [
			createNode( defs, 'column', taken ),
			createNode( defs, 'column', taken ),
		];
	}

	return node;
}

/**
 * Bọc node trong chuỗi element (ngoài → trong).
 *
 * @param {Object}      defs  Định nghĩa.
 * @param {string[]}    chain Type bọc.
 * @param {Object}      node  Node trong cùng.
 * @param {Set<string>} taken ID đã dùng.
 * @return {Object} Node ngoài cùng.
 */
export function wrap( defs, chain, node, taken ) {
	return chain.reduceRight( ( inner, type ) => {
		const id = newId( taken );
		taken.add( id );
		return { id, type, props: {}, children: [ inner ] };
	}, node );
}

/**
 * Bản sao sâu với ID mới (duplicate / paste).
 *
 * @param {Object}      node  Node.
 * @param {Set<string>} taken ID đã dùng (được cập nhật).
 * @return {Object} Bản sao.
 */
export function cloneWithNewIds( node, taken ) {
	const id = newId( taken );
	taken.add( id );

	const copy = {
		...node,
		id,
		props: structuredCloneSafe( node.props || {} ),
	};

	if ( node.advanced ) {
		copy.advanced = structuredCloneSafe( node.advanced );
	}

	if ( node.children ) {
		copy.children = node.children.map( ( child ) =>
			cloneWithNewIds( child, taken )
		);
	}

	return copy;
}

/**
 * Sao chép sâu dữ liệu JSON.
 *
 * @param {unknown} value Giá trị.
 * @return {unknown} Bản sao.
 */
function structuredCloneSafe( value ) {
	return JSON.parse( JSON.stringify( value ) );
}

/**
 * Thay danh sách con của một cha (null = cấp gốc).
 *
 * @param {Object}                          doc      Tài liệu.
 * @param {string|null}                     parentId Cha.
 * @param {(...args: unknown[]) => unknown} fn       fn( children[] ): children[].
 * @return {Object} Tài liệu mới.
 */
function mapChildren( doc, parentId, fn ) {
	if ( null === parentId ) {
		return { ...doc, elements: fn( [ ...doc.elements ] ) };
	}

	return mapNode( doc, parentId, ( node ) => ( {
		...node,
		children: fn( [ ...( node.children || [] ) ] ),
	} ) );
}

/**
 * Thay một node.
 *
 * @param {Object}                          doc Tài liệu.
 * @param {string}                          id  ID.
 * @param {(...args: unknown[]) => unknown} fn  fn( node ): node.
 * @return {Object} Tài liệu mới (giữ nguyên tham chiếu nhánh không đổi).
 */
export function mapNode( doc, id, fn ) {
	const visit = ( nodes ) => {
		let changed = false;

		const next = nodes.map( ( node ) => {
			if ( node.id === id ) {
				changed = true;
				return fn( node );
			}

			if ( node.children?.length ) {
				const children = visit( node.children );

				if ( children !== node.children ) {
					changed = true;
					return { ...node, children };
				}
			}

			return node;
		} );

		return changed ? next : nodes;
	};

	const elements = visit( doc.elements );

	return elements === doc.elements ? doc : { ...doc, elements };
}

/**
 * Chèn node.
 *
 * @param {Object}      doc      Tài liệu.
 * @param {string|null} parentId Cha (null = gốc).
 * @param {number}      index    Vị trí.
 * @param {Object}      node     Node.
 * @return {Object} Tài liệu mới.
 */
export function insertNode( doc, parentId, index, node ) {
	return mapChildren( doc, parentId, ( children ) => {
		const at = Math.max( 0, Math.min( index, children.length ) );
		children.splice( at, 0, node );
		return children;
	} );
}

/**
 * Xoá node.
 *
 * @param {Object} doc Tài liệu.
 * @param {string} id  ID.
 * @return {Object} Tài liệu mới.
 */
export function removeNode( doc, id ) {
	const where = locate( doc, id );

	if ( ! where ) {
		return doc;
	}

	return mapChildren(
		doc,
		where.parent ? where.parent.id : null,
		( children ) => children.filter( ( child ) => child.id !== id )
	);
}

/**
 * Di chuyển node.
 *
 * `index` tính theo danh sách con của cha đích TRƯỚC khi gỡ node (như vị trí
 * vạch chỉ báo người dùng thấy lúc kéo).
 *
 * @param {Object}      doc      Tài liệu.
 * @param {string}      id       Node di chuyển.
 * @param {string|null} parentId Cha đích.
 * @param {number}      index    Vị trí đích.
 * @return {Object} Tài liệu mới (không đổi nếu đích nằm trong chính node).
 */
export function moveNode( doc, id, parentId, index ) {
	const where = locate( doc, id );

	if ( ! where || ( null !== parentId && isWithin( doc, id, parentId ) ) ) {
		return doc;
	}

	const fromParent = where.parent ? where.parent.id : null;
	let target = index;

	if ( fromParent === parentId && where.index < index ) {
		target -= 1;
	}

	if ( fromParent === parentId && where.index === target ) {
		return doc;
	}

	return insertNode( removeNode( doc, id ), parentId, target, where.node );
}

/**
 * Vị trí chèn khi bấm vào element trong bảng "Thêm" (không kéo).
 *
 * - Element đang chọn chứa được loại mới → thêm vào cuối element đó.
 * - Không → thêm ngay sau element đang chọn, ở cấp tổ tiên gần nhất chứa được.
 * - Không có gì chọn / không cấp nào chứa được → cuối trang, tự bọc (Section…).
 *
 * @param {Object}      doc        Tài liệu.
 * @param {Object}      defs       Định nghĩa.
 * @param {string|null} selectedId Element đang chọn.
 * @param {string}      type       Type cần chèn.
 * @return {{parentId: string|null, index: number, wrap: string[]}|null} Vị trí.
 */
export function resolveClickInsert( doc, defs, selectedId, type ) {
	if ( selectedId ) {
		const where = locate( doc, selectedId );

		if ( where ) {
			if ( canContain( defs, where.node.type, type ) ) {
				return {
					parentId: where.node.id,
					index: ( where.node.children || [] ).length,
					wrap: [],
				};
			}

			// Leo dần lên: chèn sau nhánh chứa element đang chọn.
			const chain = [ ...ancestors( doc, selectedId ), where.node ];

			for ( let i = chain.length - 1; i >= 1; i-- ) {
				const parent = chain[ i - 1 ];

				if ( canContain( defs, parent.type, type ) ) {
					const index = parent.children.findIndex(
						( c ) => c.id === chain[ i ].id
					);
					return { parentId: parent.id, index: index + 1, wrap: [] };
				}
			}

			const top = chain[ 0 ];
			const wrapTypes = wrapChain( defs, ROOT, type );

			if ( wrapTypes ) {
				return {
					parentId: null,
					index:
						doc.elements.findIndex( ( n ) => n.id === top.id ) + 1,
					wrap: wrapTypes,
				};
			}

			return null;
		}
	}

	const wrapTypes = wrapChain( defs, ROOT, type );

	return wrapTypes
		? { parentId: null, index: doc.elements.length, wrap: wrapTypes }
		: null;
}

/**
 * Nhãn ngắn của node cho Navigator / thanh công cụ.
 *
 * @param {Object} defs Định nghĩa.
 * @param {Object} node Node.
 * @return {string} Nhãn.
 */
export function nodeLabel( defs, node ) {
	const name = defs[ node.type ]?.name || node.type;
	const raw = node.props?.text || node.props?.content || '';
	const text = String( raw )
		.replace( /<[^>]*>/g, ' ' )
		.replace( /\s+/g, ' ' )
		.trim();

	return text
		? `${ name }: ${ text.length > 28 ? text.slice( 0, 28 ) + '…' : text }`
		: name;
}
