/**
 * Thông tin hiển thị của một node trong bảng Cấu trúc. Thuần dữ liệu (test được).
 */

const TEXT_PROPS = [ 'title', 'text', 'content', 'label' ];

/**
 * Chữ thuần, rút gọn.
 *
 * @param {unknown} raw Giá trị.
 * @param {number}  max Độ dài tối đa.
 * @return {string} Chữ.
 */
function plain( raw, max ) {
	const text = String( raw ?? '' )
		.replace( /<[^>]*>/g, ' ' )
		.replace( /&nbsp;/g, ' ' )
		.replace( /\s+/g, ' ' )
		.trim();

	return text.length > max ? text.slice( 0, max ) + '…' : text;
}

/**
 * Chữ đầu tiên trong node hoặc con cháu (tiêu đề, văn bản, nút…), duyệt theo thứ tự.
 *
 * @param {Object} node Node.
 * @return {string} Chữ ('' nếu không có).
 */
function firstText( node ) {
	for ( const key of TEXT_PROPS ) {
		const value = node.props?.[ key ];

		if ( 'string' === typeof value && plain( value, 60 ) ) {
			return value;
		}
	}

	for ( const child of node.children || [] ) {
		const found = firstText( child );

		if ( found ) {
			return found;
		}
	}

	return '';
}

/**
 * Mô tả ngắn cạnh tên loại: tên tự đặt (Nâng cao → "Tên trong Cấu trúc"), không có thì chữ
 * đầu tiên bên trong — "Section · Danh mục sản phẩm".
 *
 * @param {Object} node Node.
 * @param {number} max  Độ dài tối đa.
 * @return {string} Mô tả ('' nếu không có).
 */
export function nodeCaption( node, max = 32 ) {
	const custom = plain( node.advanced?.label, max );

	if ( custom ) {
		return custom;
	}

	return plain( firstText( node ), max );
}

/**
 * Node có tên tự đặt.
 *
 * @param {Object} node Node.
 * @return {boolean} Có tên riêng.
 */
export function hasCustomLabel( node ) {
	return !! plain( node.advanced?.label, 60 );
}

/**
 * Trạng thái ẩn (tab Nâng cao).
 *
 * @param {Object} node Node.
 * @return {{ off: boolean, all: boolean, devices: string[] }} off = đã tắt khối
 *         (không xuất ra website); all = ẩn trên mọi thiết bị.
 */
export function hiddenState( node ) {
	const a = node.advanced || {};
	const devices = [
		a.hideDesktop && 'desktop',
		a.hideTablet && 'tablet',
		a.hideMobile && 'mobile',
	].filter( Boolean );

	return { off: !! a.hidden, all: 3 === devices.length, devices };
}

/**
 * Action bật / tắt khối (Nâng cao → Tắt khối).
 *
 * @param {Object} node Node.
 * @return {Object} Action UPDATE cho reducer.
 */
export function toggleHiddenAction( node ) {
	return {
		type: 'UPDATE',
		id: node.id,
		scope: 'advanced',
		key: 'hidden',
		// Bật lại = xoá cờ (không lưu `hidden: false`).
		value: node.advanced?.hidden ? null : true,
	};
}
