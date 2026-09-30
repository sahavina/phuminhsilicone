/**
 * Tiện ích thuần — tách riêng để test bằng Jest không cần DOM.
 */

/**
 * Lấy giá trị field trong object lồng theo nhóm.
 *
 * @param {Object} values Giá trị theo nhóm.
 * @param {string} group  Nhóm.
 * @param {string} key    Field.
 * @return {unknown} Giá trị.
 */
export function getValue( values, group, key ) {
	return values?.[ group ]?.[ key ];
}

/**
 * Trả về object mới với một field đã đổi (không sửa object cũ).
 *
 * @param {Object}  values Giá trị theo nhóm.
 * @param {string}  group  Nhóm.
 * @param {string}  key    Field.
 * @param {unknown} value  Giá trị mới.
 * @return {Object} Object mới.
 */
export function setValue( values, group, key, value ) {
	return {
		...values,
		[ group ]: {
			...( values?.[ group ] || {} ),
			[ key ]: value,
		},
	};
}

/**
 * Chỉ giữ các nhóm/field đã thay đổi so với bản đã lưu — gửi ít dữ liệu,
 * và field không đụng tới không bị sanitize lại.
 *
 * @param {Object} current Giá trị hiện tại.
 * @param {Object} saved   Giá trị đã lưu.
 * @return {Object} Phần thay đổi, lồng theo nhóm.
 */
export function diffValues( current, saved ) {
	const out = {};

	Object.keys( current || {} ).forEach( ( group ) => {
		Object.keys( current[ group ] || {} ).forEach( ( key ) => {
			const a = JSON.stringify( current[ group ][ key ] );
			const b = JSON.stringify( saved?.[ group ]?.[ key ] );

			if ( a !== b ) {
				out[ group ] = out[ group ] || {};
				out[ group ][ key ] = current[ group ][ key ];
			}
		} );
	} );

	return out;
}

/**
 * Giá trị responsive: chuẩn hoá scalar thành { desktop }.
 *
 * @param {unknown} value Giá trị.
 * @return {Object} { desktop, tablet?, mobile? }
 */
export function toResponsive( value ) {
	if ( value && typeof value === 'object' && ! Array.isArray( value ) ) {
		return value;
	}

	return { desktop: value ?? '' };
}
