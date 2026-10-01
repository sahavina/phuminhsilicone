/**
 * Tính giá trị khi kéo tay đổi kích thước trên canvas. Thuần dữ liệu (test được).
 */

/**
 * Đơn vị giữ theo giá trị đang có; chưa có → % cho độ rộng, px cho chiều cao.
 *
 * @param {string|undefined} current Giá trị hiện tại (vd "320px", "50%").
 * @param {'x'|'y'}          axis    Trục.
 * @return {string} Đơn vị.
 */
export function resizeUnit( current, axis ) {
	const match = /(px|%|vw|vh)$/.exec( String( current ?? '' ).trim() );
	const unit = match ? match[ 1 ] : '';

	if ( 'x' === axis ) {
		return [ 'px', '%', 'vw' ].includes( unit ) ? unit : '%';
	}

	return [ 'px', 'vh' ].includes( unit ) ? unit : 'px';
}

/**
 * Số pixel → chuỗi giá trị theo đơn vị (làm tròn: px số nguyên, %/vw/vh một chữ số thập phân).
 *
 * @param {Object}  args          Tham số.
 * @param {'x'|'y'} args.axis     Trục.
 * @param {number}  args.px       Kích thước mới (px).
 * @param {string}  args.unit     Đơn vị.
 * @param {number}  args.parent   Độ rộng vùng nội dung của element cha (px) — cho %.
 * @param {number}  args.viewport Độ rộng (x) / cao (y) của khung xem (px) — cho vw / vh.
 * @return {string} Giá trị, vd "48.5%", "320px".
 */
export function resizeValue( { axis, px, unit, parent, viewport } ) {
	const round1 = ( n ) => Math.round( n * 10 ) / 10;
	const min = 'x' === axis ? 20 : 0;
	let size = Math.max( min, px );

	// Độ rộng không vượt vùng của element cha.
	if ( 'x' === axis && parent > 0 ) {
		size = Math.min( size, parent );
	}

	if ( '%' === unit ) {
		return round1( ( size / Math.max( 1, parent ) ) * 100 ) + '%';
	}

	if ( 'vw' === unit || 'vh' === unit ) {
		return round1( ( size / Math.max( 1, viewport ) ) * 100 ) + unit;
	}

	return Math.round( size ) + 'px';
}
