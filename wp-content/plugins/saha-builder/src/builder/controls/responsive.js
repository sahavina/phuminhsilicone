/**
 * Giá trị responsive: scalar (= desktop) hoặc { desktop, tablet, mobile }.
 * Cùng quy tắc với Saha\Core\Builder\Responsive (PHP).
 */
export const DEVICE_KEYS = [ 'desktop', 'tablet', 'mobile' ];

/**
 * Có phải dạng { desktop?, tablet?, mobile? } không.
 *
 * @param {unknown} value Giá trị.
 * @return {boolean} Kết quả.
 */
export function isResponsive( value ) {
	if ( ! value || 'object' !== typeof value || Array.isArray( value ) ) {
		return false;
	}

	const keys = Object.keys( value );

	return keys.length > 0 && keys.every( ( k ) => DEVICE_KEYS.includes( k ) );
}

/**
 * Giá trị đặt riêng cho một thiết bị (không kế thừa).
 *
 * @param {unknown} value  Giá trị.
 * @param {string}  device Thiết bị.
 * @return {unknown} Giá trị hoặc undefined.
 */
export function valueAt( value, device ) {
	if ( isResponsive( value ) ) {
		return value[ device ];
	}

	return 'desktop' === device ? value : undefined;
}

/**
 * Giá trị thiết bị này kế thừa từ thiết bị lớn hơn (để làm placeholder).
 *
 * @param {unknown} value  Giá trị.
 * @param {string}  device Thiết bị.
 * @return {unknown} Giá trị kế thừa hoặc undefined.
 */
export function inheritedAt( value, device ) {
	const order = DEVICE_KEYS.slice(
		0,
		DEVICE_KEYS.indexOf( device )
	).reverse();

	for ( const d of order ) {
		const v = valueAt( value, d );

		if ( undefined !== v && null !== v && '' !== v ) {
			return v;
		}
	}

	return undefined;
}

/**
 * Đặt giá trị cho một thiết bị; rỗng = bỏ (kế thừa).
 *
 * @param {unknown} value  Giá trị hiện tại.
 * @param {string}  device Thiết bị.
 * @param {unknown} next   Giá trị mới.
 * @return {Object|null} Giá trị responsive mới; null nếu không còn gì.
 */
export function setAt( value, device, next ) {
	let base = {};

	if ( isResponsive( value ) ) {
		base = { ...value };
	} else if ( undefined !== value && null !== value && '' !== value ) {
		base = { desktop: value };
	}

	if ( undefined === next || null === next || '' === next ) {
		delete base[ device ];
	} else {
		base[ device ] = next;
	}

	return Object.keys( base ).length ? base : null;
}
