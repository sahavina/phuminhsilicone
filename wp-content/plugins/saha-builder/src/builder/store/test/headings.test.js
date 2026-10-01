import { describe, expect, it } from 'vitest';

import { demoteH1, hasH1 } from '../headings';

// Mặc định thẻ như server: Tiêu đề h2, Tiêu đề danh sách (động) h1, Banner h2.
const defs = {
	section: { controls: [] },
	heading: { controls: [ { key: 'tag', default: 'h2' } ] },
	'archive-title': { controls: [ { key: 'tag', default: 'h1' } ] },
	banner: { controls: [ { key: 'titleTag', default: 'h2' } ] },
};

describe( 'hasH1', () => {
	it( 'nhận H1 đặt trong prop, kể cả lồng sâu', () => {
		expect(
			hasH1( defs, [
				{
					type: 'section',
					children: [ { type: 'heading', props: { tag: 'h1' } } ],
				},
			] )
		).toBe( true );
	} );

	it( 'nhận H1 mặc định của element (không đặt prop)', () => {
		expect( hasH1( defs, [ { type: 'archive-title', props: {} } ] ) ).toBe(
			true
		);
	} );

	it( 'nhận titleTag của Banner', () => {
		expect(
			hasH1( defs, [ { type: 'banner', props: { titleTag: 'h1' } } ] )
		).toBe( true );
	} );

	it( 'không có H1', () => {
		expect(
			hasH1( defs, [
				{ type: 'heading', props: {} },
				{ type: 'banner', props: {} },
			] )
		).toBe( false );
	} );
} );

describe( 'demoteH1', () => {
	it( 'hạ mọi H1 (prop và mặc định) thành H2, giữ thẻ khác', () => {
		const node = {
			type: 'section',
			children: [
				{ type: 'heading', props: { tag: 'h1', text: 'A' } },
				{ type: 'heading', props: { tag: 'h3' } },
				{ type: 'archive-title' },
				{ type: 'banner', props: { titleTag: 'h1' } },
			],
		};

		demoteH1( defs, node );

		expect( node.children[ 0 ].props ).toEqual( { tag: 'h2', text: 'A' } );
		expect( node.children[ 1 ].props.tag ).toBe( 'h3' );
		expect( node.children[ 2 ].props.tag ).toBe( 'h2' );
		expect( node.children[ 3 ].props.titleTag ).toBe( 'h2' );
		expect( hasH1( defs, [ node ] ) ).toBe( false );
	} );
} );
