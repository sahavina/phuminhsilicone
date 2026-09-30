import { describe, expect, it } from 'vitest';
import {
	ROOT,
	canContain,
	cloneWithNewIds,
	collectIds,
	createNode,
	findNode,
	insertNode,
	isWithin,
	moveNode,
	newId,
	nodeLabel,
	removeNode,
	replaceNode,
	resolveClickInsert,
	wrapChain,
} from '../tree';
import { defs, sampleDoc } from './fixtures';

const childIds = ( doc, id ) =>
	( id ? findNode( doc, id ).children : doc.elements ).map( ( n ) => n.id );

describe( 'quy tắc cha–con', () => {
	it( 'section chỉ ở cấp gốc', () => {
		expect( canContain( defs, ROOT, 'section' ) ).toBe( true );
		expect( canContain( defs, 'column', 'section' ) ).toBe( false );
	} );

	it( 'column chỉ nằm trong row; heading không nằm trong row', () => {
		expect( canContain( defs, 'row', 'column' ) ).toBe( true );
		expect( canContain( defs, 'section', 'column' ) ).toBe( false );
		expect( canContain( defs, 'row', 'heading' ) ).toBe( false );
	} );

	it( 'type không tồn tại → không chứa được', () => {
		expect( canContain( defs, 'section', 'slider' ) ).toBe( false );
	} );
} );

describe( 'wrapChain', () => {
	it( 'heading ở gốc → bọc section', () => {
		expect( wrapChain( defs, ROOT, 'heading' ) ).toEqual( [ 'section' ] );
	} );

	it( 'column ở gốc → bọc section > row', () => {
		expect( wrapChain( defs, ROOT, 'column' ) ).toEqual( [
			'section',
			'row',
		] );
	} );

	it( 'đặt thẳng được → []', () => {
		expect( wrapChain( defs, ROOT, 'section' ) ).toEqual( [] );
	} );
} );

describe( 'createNode', () => {
	it( 'row mới có sẵn 2 cột, ID không trùng', () => {
		const taken = new Set();
		const row = createNode( defs, 'row', taken );
		expect( row.children.map( ( c ) => c.type ) ).toEqual( [
			'column',
			'column',
		] );
		expect( taken.size ).toBe( 3 );
	} );

	it( 'ID đúng định dạng 8 ký tự', () => {
		expect( newId() ).toMatch( /^[a-z0-9]{8}$/ );
	} );
} );

describe( 'insert / remove / move', () => {
	it( 'chèn vào vị trí', () => {
		const doc = insertNode( sampleDoc(), 'c1', 1, {
			id: 'x',
			type: 'button',
			props: {},
		} );
		expect( childIds( doc, 'c1' ) ).toEqual( [ 'h1', 'x', 't1' ] );
	} );

	it( 'bất biến: tài liệu cũ không đổi, nhánh không liên quan giữ tham chiếu', () => {
		const before = sampleDoc();
		const after = insertNode( before, 'c1', 0, {
			id: 'x',
			type: 'button',
			props: {},
		} );
		expect( childIds( before, 'c1' ) ).toEqual( [ 'h1', 't1' ] );
		expect( after.elements[ 1 ] ).toBe( before.elements[ 1 ] );
	} );

	it( 'xoá node', () => {
		expect( childIds( removeNode( sampleDoc(), 'h1' ), 'c1' ) ).toEqual( [
			't1',
		] );
	} );

	it( 'di chuyển xuống trong cùng cha (index tính trước khi gỡ)', () => {
		expect(
			childIds( moveNode( sampleDoc(), 'h1', 'c1', 2 ), 'c1' )
		).toEqual( [ 't1', 'h1' ] );
	} );

	it( 'di chuyển sang cha khác', () => {
		const doc = moveNode( sampleDoc(), 't1', 'c2', 0 );
		expect( childIds( doc, 'c1' ) ).toEqual( [ 'h1' ] );
		expect( childIds( doc, 'c2' ) ).toEqual( [ 't1' ] );
	} );

	it( 'không cho thả node vào chính nó / con của nó', () => {
		const doc = sampleDoc();
		expect( moveNode( doc, 'r1', 'c1', 0 ) ).toBe( doc );
		expect( isWithin( doc, 's1', 'h1' ) ).toBe( true );
	} );

	it( 'di chuyển section ở cấp gốc', () => {
		expect(
			childIds( moveNode( sampleDoc(), 's2', null, 0 ), null )
		).toEqual( [ 's2', 's1' ] );
	} );
} );

describe( 'cloneWithNewIds', () => {
	it( 'mọi ID mới, không trùng tài liệu', () => {
		const doc = sampleDoc();
		const taken = collectIds( doc );
		const copy = cloneWithNewIds( findNode( doc, 's1' ), taken );
		const ids = [];
		const collect = ( n ) => {
			ids.push( n.id );
			( n.children || [] ).forEach( collect );
		};
		collect( copy );
		expect( ids.some( ( id ) => collectIds( doc ).has( id ) ) ).toBe(
			false
		);
		expect( findNode( { elements: [ copy ] }, ids[ 3 ] ).props.text ).toBe(
			'Xin chào'
		);
	} );
} );

describe( 'resolveClickInsert', () => {
	it( 'element đang chọn chứa được → thêm vào cuối', () => {
		expect(
			resolveClickInsert( sampleDoc(), defs, 'c2', 'heading' )
		).toEqual( { parentId: 'c2', index: 0, wrap: [] } );
	} );

	it( 'chọn heading → thêm ngay sau nó trong cột', () => {
		expect(
			resolveClickInsert( sampleDoc(), defs, 'h1', 'button' )
		).toEqual( { parentId: 'c1', index: 1, wrap: [] } );
	} );

	it( 'chọn heading, thêm section → sau section chứa nó ở cấp gốc', () => {
		expect(
			resolveClickInsert( sampleDoc(), defs, 'h1', 'section' )
		).toEqual( { parentId: null, index: 1, wrap: [] } );
	} );

	it( 'chọn row, thêm column → vào row', () => {
		expect(
			resolveClickInsert( sampleDoc(), defs, 'r1', 'column' )
		).toEqual( { parentId: 'r1', index: 2, wrap: [] } );
	} );

	it( 'không chọn gì, thêm heading → cuối trang, bọc section', () => {
		expect(
			resolveClickInsert( sampleDoc(), defs, null, 'heading' )
		).toEqual( { parentId: null, index: 2, wrap: [ 'section' ] } );
	} );
} );

describe( 'nodeLabel', () => {
	it( 'tên + trích nội dung, bỏ thẻ HTML', () => {
		expect( nodeLabel( defs, findNode( sampleDoc(), 't1' ) ) ).toBe(
			'Văn bản: Nội dung'
		);
	} );
} );

describe( 'replaceNode', () => {
	it( 'thay một node bằng nhiều node, giữ vị trí', () => {
		const doc = replaceNode( sampleDoc(), 's1', [
			{ id: 'x1', type: 'section', props: {} },
			{ id: 'x2', type: 'section', props: {} },
		] );
		expect( doc.elements.map( ( n ) => n.id ) ).toEqual( [
			'x1',
			'x2',
			's2',
		] );
	} );

	it( 'thay node lồng trong cột', () => {
		const doc = replaceNode( sampleDoc(), 'h1', [
			{ id: 'bk', type: 'block', props: { blockId: 5 } },
		] );
		expect( findNode( doc, 'c1' ).children.map( ( n ) => n.id ) ).toEqual( [
			'bk',
			't1',
		] );
	} );
} );
