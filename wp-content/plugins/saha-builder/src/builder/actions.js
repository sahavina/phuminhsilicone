/**
 * Thao tác cấp cao (thêm, thả, copy/paste) dựng trên reducer + tree.
 */
import { useMemo } from '@wordpress/element';

import { useBuilder } from './context';
import {
	cloneWithNewIds,
	collectIds,
	createNode,
	findNode,
	insertNode,
	locate,
	removeNode,
	resolveClickInsert,
	wrap,
} from './store/tree';

const CLIPBOARD_KEY = 'saha-builder-clipboard';

/**
 * Chèn `node` (có thể bọc) vào vị trí đã tính.
 *
 * @param {Object} defs   Định nghĩa.
 * @param {Object} doc    Tài liệu.
 * @param {Object} target { parentId, index, wrap }.
 * @param {Object} node   Node.
 * @param {Set}    taken  ID đã dùng.
 * @return {Object} Tài liệu mới.
 */
export function placeNode( defs, doc, target, node, taken ) {
	const outer = target.wrap?.length
		? wrap( defs, target.wrap, node, taken )
		: node;
	return insertNode( doc, target.parentId, target.index, outer );
}

/**
 * Hook thao tác.
 *
 * @return {Object} Hàm thao tác.
 */
export function useActions() {
	const { state, dispatch, defs } = useBuilder();

	return useMemo( () => {
		/**
		 * Thêm element mới theo vị trí đã tính (từ kéo thả) hoặc theo lựa chọn hiện tại.
		 *
		 * @param {string} type     Type.
		 * @param {Object} [target] { parentId, index, wrap }.
		 */
		const insertType = ( type, target ) => {
			const where =
				target ??
				resolveClickInsert( state.doc, defs, state.selectedId, type );

			if ( ! where ) {
				return false;
			}

			const taken = collectIds( state.doc );
			const node = createNode( defs, type, taken );

			dispatch( {
				type: 'APPLY',
				doc: placeNode( defs, state.doc, where, node, taken ),
				selectId: node.id,
			} );

			return true;
		};

		/**
		 * Di chuyển node tới vị trí đã tính (có thể phải bọc).
		 *
		 * @param {string} id     Node.
		 * @param {Object} target { parentId, index, wrap }.
		 */
		const moveTo = ( id, target ) => {
			if ( ! target.wrap?.length ) {
				dispatch( {
					type: 'MOVE',
					id,
					parentId: target.parentId,
					index: target.index,
				} );
				return;
			}

			const where = locate( state.doc, id );

			if ( ! where ) {
				return;
			}

			// Gỡ trước rồi chèn (đã bọc); chỉ số gốc cần chỉnh nếu cùng cấp gốc và đứng trước.
			let index = target.index;

			if (
				null === target.parentId &&
				! where.parent &&
				where.index < index
			) {
				index -= 1;
			}

			const doc = placeNode(
				defs,
				removeNode( state.doc, id ),
				{ ...target, index },
				where.node,
				collectIds( state.doc )
			);

			dispatch( { type: 'APPLY', doc, selectId: id } );
		};

		const copy = ( id ) => {
			const node = findNode( state.doc, id );

			if ( node ) {
				try {
					window.localStorage.setItem(
						CLIPBOARD_KEY,
						JSON.stringify( node )
					);
				} catch {
					// Trình duyệt chặn localStorage: bỏ qua, copy/paste chỉ không hoạt động.
				}
			}
		};

		const hasClipboard = () => {
			try {
				return !! window.localStorage.getItem( CLIPBOARD_KEY );
			} catch {
				return false;
			}
		};

		/**
		 * Dán: vị trí như khi bấm thêm element cùng loại, ID mới hoàn toàn.
		 */
		const paste = () => {
			let node;

			try {
				node = JSON.parse(
					window.localStorage.getItem( CLIPBOARD_KEY ) || 'null'
				);
			} catch {
				node = null;
			}

			if ( ! node || ! node.type || ! defs[ node.type ] ) {
				return false;
			}

			const where = resolveClickInsert(
				state.doc,
				defs,
				state.selectedId,
				node.type
			);

			if ( ! where ) {
				return false;
			}

			const taken = collectIds( state.doc );
			const copyNode = cloneWithNewIds( node, taken );

			dispatch( {
				type: 'APPLY',
				doc: placeNode( defs, state.doc, where, copyNode, taken ),
				selectId: copyNode.id,
			} );

			return true;
		};

		return { insertType, moveTo, copy, paste, hasClipboard };
	}, [ state.doc, state.selectedId, defs, dispatch ] );
}
