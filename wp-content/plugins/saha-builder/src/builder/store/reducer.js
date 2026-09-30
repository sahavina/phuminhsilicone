/**
 * Reducer của builder: tài liệu + lịch sử (undo/redo) + lựa chọn.
 *
 * Hàm thuần — test được không cần trình duyệt. Chọn element không vào lịch sử;
 * gõ liên tục vào cùng một field được gộp thành MỘT bước undo.
 */
import {
	cloneWithNewIds,
	collectIds,
	insertNode,
	locate,
	mapNode,
	moveNode,
	removeNode,
} from './tree';

export const HISTORY_LIMIT = 100;
export const COALESCE_MS = 800;

/**
 * Trạng thái rỗng.
 *
 * @return {Object} State.
 */
export function initialState() {
	return {
		doc: { version: 1, elements: [] },
		savedJson: JSON.stringify( { version: 1, elements: [] } ),
		hash: '',
		selectedId: null,
		past: [],
		future: [],
		coalesceKey: null,
		coalesceAt: 0,
		errors: {},
		readOnly: false,
	};
}

/**
 * Đẩy tài liệu mới vào lịch sử.
 *
 * @param {Object} state State.
 * @param {Object} doc   Tài liệu mới.
 * @param {Object} extra Thay đổi khác của state.
 * @return {Object} State mới.
 */
function commit( state, doc, extra = {} ) {
	if ( doc === state.doc ) {
		return { ...state, ...extra };
	}

	const past = [ ...state.past, state.doc ].slice( -HISTORY_LIMIT );

	return { ...state, ...extra, doc, past, future: [], coalesceKey: null };
}

/**
 * Đặt/xoá một giá trị (null/'' = xoá → dùng mặc định).
 *
 * @param {Object}  bag   props hoặc advanced.
 * @param {string}  key   Key.
 * @param {unknown} value Giá trị.
 * @return {Object} Bản mới.
 */
function setValue( bag, key, value ) {
	const next = { ...( bag || {} ) };

	if ( null === value || undefined === value || '' === value ) {
		delete next[ key ];
	} else {
		next[ key ] = value;
	}

	return next;
}

/**
 * Reducer.
 *
 * @param {Object} state  State.
 * @param {Object} action Action.
 * @return {Object} State mới.
 */
export function reducer( state, action ) {
	const editing = ! state.readOnly;

	switch ( action.type ) {
		case 'LOAD':
			return {
				...initialState(),
				doc: action.doc,
				savedJson: JSON.stringify( action.savedDoc ?? action.doc ),
				hash: action.hash || '',
				readOnly: !! action.readOnly,
			};

		case 'SELECT':
			return { ...state, selectedId: action.id };

		case 'INSERT': {
			if ( ! editing ) {
				return state;
			}
			const doc = insertNode(
				state.doc,
				action.parentId,
				action.index,
				action.node
			);
			return commit( state, doc, {
				selectedId: action.selectId ?? action.node.id,
			} );
		}

		case 'APPLY': {
			// Thao tác gộp đã tính sẵn bằng hàm trong tree.js (ví dụ di chuyển + bọc) = một bước undo.
			if ( ! editing ) {
				return state;
			}
			return commit( state, action.doc, {
				selectedId: action.selectId ?? state.selectedId,
			} );
		}

		case 'MOVE': {
			if ( ! editing ) {
				return state;
			}
			return commit(
				state,
				moveNode( state.doc, action.id, action.parentId, action.index ),
				{ selectedId: action.id }
			);
		}

		case 'UPDATE': {
			if ( ! editing ) {
				return state;
			}

			const scope = 'advanced' === action.scope ? 'advanced' : 'props';
			const doc = mapNode( state.doc, action.id, ( node ) => {
				const bag = setValue( node[ scope ], action.key, action.value );
				const next = { ...node, [ scope ]: bag };

				if ( 'advanced' === scope && ! Object.keys( bag ).length ) {
					delete next.advanced;
				}

				return next;
			} );

			if ( doc === state.doc ) {
				return state;
			}

			const key = `${ action.id }:${ scope }:${ action.key }`;
			const now = action.now ?? Date.now();

			// Người dùng đang sửa field lỗi → bỏ lỗi cũ (server kiểm lại khi render/lưu).
			const errorKey = `${ action.id }.${ action.key }`;
			const errors =
				errorKey in state.errors
					? Object.fromEntries(
							Object.entries( state.errors ).filter(
								( [ k ] ) => k !== errorKey
							)
						)
					: state.errors;

			// Gõ liên tục cùng field → thay bản hiện tại, không thêm bước undo.
			if (
				state.coalesceKey === key &&
				now - state.coalesceAt < COALESCE_MS
			) {
				return { ...state, doc, errors, coalesceAt: now, future: [] };
			}

			return {
				...commit( state, doc ),
				errors,
				coalesceKey: key,
				coalesceAt: now,
			};
		}

		case 'REMOVE': {
			if ( ! editing ) {
				return state;
			}
			const where = locate( state.doc, action.id );
			if ( ! where ) {
				return state;
			}
			return commit( state, removeNode( state.doc, action.id ), {
				selectedId: where.parent ? where.parent.id : null,
			} );
		}

		case 'DUPLICATE': {
			if ( ! editing ) {
				return state;
			}
			const where = locate( state.doc, action.id );
			if ( ! where ) {
				return state;
			}
			const copy = cloneWithNewIds( where.node, collectIds( state.doc ) );
			const doc = insertNode(
				state.doc,
				where.parent ? where.parent.id : null,
				where.index + 1,
				copy
			);
			return commit( state, doc, { selectedId: copy.id } );
		}

		case 'MOVE_SIBLING': {
			if ( ! editing ) {
				return state;
			}
			const where = locate( state.doc, action.id );
			if ( ! where ) {
				return state;
			}
			const siblings = where.parent
				? where.parent.children
				: state.doc.elements;
			const target = where.index + ( action.delta > 0 ? 2 : -1 );
			if ( target < 0 || target > siblings.length ) {
				return state;
			}
			return commit(
				state,
				moveNode(
					state.doc,
					action.id,
					where.parent ? where.parent.id : null,
					target
				),
				{ selectedId: action.id }
			);
		}

		case 'UNDO': {
			if ( ! editing || ! state.past.length ) {
				return state;
			}
			const doc = state.past[ state.past.length - 1 ];
			return {
				...state,
				doc,
				past: state.past.slice( 0, -1 ),
				future: [ state.doc, ...state.future ],
				coalesceKey: null,
				selectedId:
					state.selectedId && locate( doc, state.selectedId )
						? state.selectedId
						: null,
			};
		}

		case 'REDO': {
			if ( ! editing || ! state.future.length ) {
				return state;
			}
			const [ doc, ...future ] = state.future;
			return {
				...state,
				doc,
				past: [ ...state.past, state.doc ].slice( -HISTORY_LIMIT ),
				future,
				coalesceKey: null,
				selectedId:
					state.selectedId && locate( doc, state.selectedId )
						? state.selectedId
						: null,
			};
		}

		case 'SAVED':
			// Server trả tài liệu đã sanitize (ID có thể được cấp lại) — dùng làm bản chuẩn, không thêm bước undo.
			return {
				...state,
				doc: action.doc,
				savedJson: JSON.stringify( action.doc ),
				hash: action.hash,
				errors: {},
				coalesceKey: null,
				selectedId:
					state.selectedId && locate( action.doc, state.selectedId )
						? state.selectedId
						: null,
			};

		case 'SET_ERRORS':
			return { ...state, errors: action.errors || {} };

		case 'SET_READ_ONLY':
			return { ...state, readOnly: !! action.readOnly };

		default:
			return state;
	}
}

/**
 * Có thay đổi chưa lưu không.
 *
 * @param {Object} state State.
 * @return {boolean} Kết quả.
 */
export function isDirty( state ) {
	return JSON.stringify( state.doc ) !== state.savedJson;
}
