import { describe, expect, it } from 'vitest';
import {
	COALESCE_MS,
	HISTORY_LIMIT,
	initialState,
	isDirty,
	reducer,
} from '../reducer';
import { findNode } from '../tree';
import { sampleDoc } from './fixtures';

const loaded = () =>
	reducer( initialState(), { type: 'LOAD', doc: sampleDoc(), hash: 'abc' } );
const run = ( state, ...actions ) => actions.reduce( reducer, state );

describe( 'reducer', () => {
	it( 'LOAD: chưa có thay đổi', () => {
		expect( isDirty( loaded() ) ).toBe( false );
	} );

	it( 'UPDATE → dirty, UNDO → sạch lại', () => {
		const s1 = reducer( loaded(), {
			type: 'UPDATE',
			id: 'h1',
			key: 'text',
			value: 'Mới',
			now: 1000,
		} );
		expect( findNode( s1.doc, 'h1' ).props.text ).toBe( 'Mới' );
		expect( isDirty( s1 ) ).toBe( true );
		const s2 = reducer( s1, { type: 'UNDO' } );
		expect( findNode( s2.doc, 'h1' ).props.text ).toBe( 'Xin chào' );
		expect( isDirty( s2 ) ).toBe( false );
		expect(
			findNode( reducer( s2, { type: 'REDO' } ).doc, 'h1' ).props.text
		).toBe( 'Mới' );
	} );

	it( 'gõ liên tục cùng field gộp thành 1 bước undo', () => {
		const s = run(
			loaded(),
			{ type: 'UPDATE', id: 'h1', key: 'text', value: 'M', now: 1000 },
			{ type: 'UPDATE', id: 'h1', key: 'text', value: 'Mớ', now: 1200 },
			{ type: 'UPDATE', id: 'h1', key: 'text', value: 'Mới', now: 1400 }
		);
		expect( s.past.length ).toBe( 1 );
		expect(
			findNode( reducer( s, { type: 'UNDO' } ).doc, 'h1' ).props.text
		).toBe( 'Xin chào' );
	} );

	it( 'ngừng gõ quá lâu → bước undo mới', () => {
		const s = run(
			loaded(),
			{ type: 'UPDATE', id: 'h1', key: 'text', value: 'A', now: 1000 },
			{
				type: 'UPDATE',
				id: 'h1',
				key: 'text',
				value: 'AB',
				now: 1000 + COALESCE_MS + 1,
			}
		);
		expect( s.past.length ).toBe( 2 );
	} );

	it( 'giá trị rỗng → xoá key (dùng mặc định)', () => {
		const s = reducer( loaded(), {
			type: 'UPDATE',
			id: 'h1',
			key: 'text',
			value: '',
		} );
		expect( 'text' in findNode( s.doc, 'h1' ).props ).toBe( false );
	} );

	it( 'advanced rỗng bị bỏ hẳn', () => {
		const s = run(
			loaded(),
			{
				type: 'UPDATE',
				id: 'h1',
				scope: 'advanced',
				key: 'cssId',
				value: 'x',
				now: 1,
			},
			{
				type: 'UPDATE',
				id: 'h1',
				scope: 'advanced',
				key: 'cssId',
				value: '',
				now: 2,
			}
		);
		expect( findNode( s.doc, 'h1' ).advanced ).toBeUndefined();
	} );

	it( 'DUPLICATE chèn ngay sau, chọn bản sao', () => {
		const s = reducer( loaded(), { type: 'DUPLICATE', id: 'h1' } );
		const kids = findNode( s.doc, 'c1' ).children;
		expect( kids.length ).toBe( 3 );
		expect( kids[ 1 ].id ).toBe( s.selectedId );
		expect( kids[ 1 ].props.text ).toBe( 'Xin chào' );
	} );

	it( 'REMOVE chọn element cha', () => {
		const s = run(
			loaded(),
			{ type: 'SELECT', id: 'h1' },
			{ type: 'REMOVE', id: 'h1' }
		);
		expect( s.selectedId ).toBe( 'c1' );
		expect( findNode( s.doc, 'h1' ) ).toBeNull();
	} );

	it( 'MOVE_SIBLING lên/xuống', () => {
		const down = reducer( loaded(), {
			type: 'MOVE_SIBLING',
			id: 'h1',
			delta: 1,
		} );
		expect(
			findNode( down.doc, 'c1' ).children.map( ( n ) => n.id )
		).toEqual( [ 't1', 'h1' ] );
		const up = reducer( down, {
			type: 'MOVE_SIBLING',
			id: 'h1',
			delta: -1,
		} );
		expect(
			findNode( up.doc, 'c1' ).children.map( ( n ) => n.id )
		).toEqual( [ 'h1', 't1' ] );
		expect(
			reducer( loaded(), { type: 'MOVE_SIBLING', id: 'h1', delta: -1 } )
				.past.length
		).toBe( 0 );
	} );

	it( `lịch sử giữ tối đa ${ HISTORY_LIMIT } bước`, () => {
		let s = loaded();
		for ( let i = 0; i < HISTORY_LIMIT + 20; i++ ) {
			s = reducer( s, {
				type: 'UPDATE',
				id: 'h1',
				key: 'text',
				value: 'v' + i,
				now: i * ( COALESCE_MS + 1 ),
			} );
		}
		expect( s.past.length ).toBe( HISTORY_LIMIT );
	} );

	it( 'chế độ chỉ xem: không sửa được', () => {
		const s = run(
			loaded(),
			{ type: 'SET_READ_ONLY', readOnly: true },
			{ type: 'REMOVE', id: 'h1' }
		);
		expect( findNode( s.doc, 'h1' ) ).not.toBeNull();
	} );

	it( 'SAVED: tài liệu server thành bản chuẩn, hết dirty, giữ lịch sử', () => {
		const edited = reducer( loaded(), {
			type: 'UPDATE',
			id: 'h1',
			key: 'text',
			value: 'X',
		} );
		const s = reducer( edited, {
			type: 'SAVED',
			doc: edited.doc,
			hash: 'def',
		} );
		expect( isDirty( s ) ).toBe( false );
		expect( s.hash ).toBe( 'def' );
		expect( s.past.length ).toBe( 1 );
	} );

	it( 'sửa field đang lỗi → bỏ lỗi của field đó, giữ lỗi khác', () => {
		const s = run(
			loaded(),
			{
				type: 'SET_ERRORS',
				errors: { 'h1.text': 'x', 't1.content': 'y' },
			},
			{ type: 'UPDATE', id: 'h1', key: 'text', value: 'Sửa' }
		);
		expect( s.errors ).toEqual( { 't1.content': 'y' } );
	} );
} );
