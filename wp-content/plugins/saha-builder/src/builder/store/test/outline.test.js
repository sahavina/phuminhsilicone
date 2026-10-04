import { describe, expect, it } from 'vitest';

import {
	hasCustomLabel,
	hiddenState,
	nodeCaption,
	toggleHiddenAction,
} from '../outline';

const section = {
	type: 'section',
	props: {},
	children: [
		{
			type: 'row',
			props: {},
			children: [
				{
					type: 'column',
					props: {},
					children: [
						{
							type: 'section-title',
							props: { title: 'Danh mục <b>sản phẩm</b>' },
						},
						{ type: 'text', props: { content: '<p>Đoạn sau</p>' } },
					],
				},
			],
		},
	],
};

describe( 'nodeCaption', () => {
	it( 'lấy chữ đầu tiên bên trong (bỏ thẻ HTML)', () => {
		expect( nodeCaption( section ) ).toBe( 'Danh mục sản phẩm' );
	} );

	it( 'tên tự đặt (Nâng cao) được ưu tiên', () => {
		expect(
			nodeCaption( { ...section, advanced: { label: 'Keo Apollo' } } )
		).toBe( 'Keo Apollo' );
		expect(
			hasCustomLabel( { ...section, advanced: { label: 'Keo Apollo' } } )
		).toBe( true );
		expect( hasCustomLabel( section ) ).toBe( false );
	} );

	it( 'rút gọn chữ dài, không có chữ → rỗng', () => {
		expect(
			nodeCaption(
				{ type: 'heading', props: { text: 'a'.repeat( 50 ) } },
				10
			)
		).toBe( 'aaaaaaaaaa…' );
		expect( nodeCaption( { type: 'spacer', props: {} } ) ).toBe( '' );
	} );
} );

describe( 'hiddenState', () => {
	it( 'ẩn mọi thiết bị / một số / không ẩn', () => {
		expect(
			hiddenState( {
				advanced: {
					hideDesktop: true,
					hideTablet: true,
					hideMobile: true,
				},
			} )
		).toEqual( {
			off: false,
			all: true,
			devices: [ 'desktop', 'tablet', 'mobile' ],
		} );
		expect( hiddenState( { advanced: { hideMobile: true } } ) ).toEqual( {
			off: false,
			all: false,
			devices: [ 'mobile' ],
		} );
		expect( hiddenState( {} ) ).toEqual( {
			off: false,
			all: false,
			devices: [],
		} );
	} );

	it( 'khối đã tắt', () => {
		expect( hiddenState( { advanced: { hidden: true } } ).off ).toBe(
			true
		);
	} );
} );

describe( 'toggleHiddenAction', () => {
	it( 'đang hiện → tắt (hidden: true)', () => {
		expect( toggleHiddenAction( { id: 'a1' } ) ).toEqual( {
			type: 'UPDATE',
			id: 'a1',
			scope: 'advanced',
			key: 'hidden',
			value: true,
		} );
	} );

	it( 'đang tắt → bật lại (xoá cờ, value null)', () => {
		expect(
			toggleHiddenAction( { id: 'a1', advanced: { hidden: true } } ).value
		).toBeNull();
	} );
} );
