import { describe, expect, it } from 'vitest';

import { resizeUnit, resizeValue } from '../../canvas/resize';

describe( 'resizeUnit', () => {
	it( 'giữ đơn vị đang có, mặc định % (rộng) / px (cao)', () => {
		expect( resizeUnit( '320px', 'x' ) ).toBe( 'px' );
		expect( resizeUnit( '50%', 'x' ) ).toBe( '%' );
		expect( resizeUnit( '80vw', 'x' ) ).toBe( 'vw' );
		expect( resizeUnit( undefined, 'x' ) ).toBe( '%' );
		expect( resizeUnit( '20rem', 'x' ) ).toBe( '%' );
		expect( resizeUnit( undefined, 'y' ) ).toBe( 'px' );
		expect( resizeUnit( '50vh', 'y' ) ).toBe( 'vh' );
		expect( resizeUnit( '50%', 'y' ) ).toBe( 'px' );
	} );
} );

describe( 'resizeValue', () => {
	it( 'độ rộng % theo vùng cha, làm tròn 1 chữ số, không vượt 100%', () => {
		expect(
			resizeValue( { axis: 'x', px: 485, unit: '%', parent: 1000 } )
		).toBe( '48.5%' );
		expect(
			resizeValue( { axis: 'x', px: 1400, unit: '%', parent: 1000 } )
		).toBe( '100%' );
	} );

	it( 'độ rộng px tối thiểu 20px; vw theo khung xem', () => {
		expect(
			resizeValue( { axis: 'x', px: 5, unit: 'px', parent: 800 } )
		).toBe( '20px' );
		expect(
			resizeValue( {
				axis: 'x',
				px: 640,
				unit: 'vw',
				parent: 1200,
				viewport: 1280,
			} )
		).toBe( '50vw' );
	} );

	it( 'chiều cao px không âm; vh theo chiều cao khung xem', () => {
		expect( resizeValue( { axis: 'y', px: -30, unit: 'px' } ) ).toBe(
			'0px'
		);
		expect( resizeValue( { axis: 'y', px: 333.6, unit: 'px' } ) ).toBe(
			'334px'
		);
		expect(
			resizeValue( { axis: 'y', px: 400, unit: 'vh', viewport: 800 } )
		).toBe( '50vh' );
	} );
} );
