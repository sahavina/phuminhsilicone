/**
 * Thẻ tiêu đề trong cây node — giữ mỗi trang một H1 khi chèn khối mẫu.
 * Thuần dữ liệu (không phụ thuộc trình duyệt) để test được.
 */

const TAG_KEYS = [ 'tag', 'titleTag' ];

/**
 * Thẻ tiêu đề thực tế của node (prop đã đặt, hoặc mặc định của control) — Tiêu đề,
 * Tiêu đề khối, Banner, Tiêu đề danh sách / bài viết (động)…
 *
 * @param {Object} defs Định nghĩa.
 * @param {Object} node Node.
 * @param {string} key  tag | titleTag.
 * @return {string|undefined} Thẻ.
 */
function tagOf( defs, node, key ) {
	if ( node.props && undefined !== node.props[ key ] ) {
		return node.props[ key ];
	}

	return ( defs[ node.type ]?.controls || [] ).find( ( c ) => c.key === key )
		?.default;
}

/**
 * Cây có H1.
 *
 * @param {Object}   defs  Định nghĩa.
 * @param {Object[]} nodes Node.
 * @return {boolean} Có H1.
 */
export function hasH1( defs, nodes ) {
	return ( nodes || [] ).some(
		( n ) =>
			TAG_KEYS.some( ( key ) => 'h1' === tagOf( defs, n, key ) ) ||
			hasH1( defs, n.children )
	);
}

/**
 * Hạ mọi H1 trong cây thành H2 (sửa tại chỗ).
 *
 * @param {Object} defs Định nghĩa.
 * @param {Object} node Node.
 */
export function demoteH1( defs, node ) {
	TAG_KEYS.forEach( ( key ) => {
		if ( 'h1' === tagOf( defs, node, key ) ) {
			node.props = { ...( node.props || {} ), [ key ]: 'h2' };
		}
	} );

	( node.children || [] ).forEach( ( child ) => demoteH1( defs, child ) );
}
