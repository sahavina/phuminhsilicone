/**
 * Context dùng chung của ứng dụng builder.
 */
import { createContext, useContext } from '@wordpress/element';

export const config = window.sahaBuilder || {};

export const DEVICES = [
	{ key: 'desktop', width: 1280, icon: 'desktop' },
	{ key: 'tablet', width: 768, icon: 'tablet' },
	{ key: 'mobile', width: 375, icon: 'smartphone' },
];

/**
 * { state, dispatch, defs, advanced, device, setDevice, meta, drag }
 */
export const BuilderContext = createContext( null );

/**
 * Lấy context.
 *
 * @return {Object} Context.
 */
export function useBuilder() {
	return useContext( BuilderContext );
}

/**
 * Trạng thái kéo thả dùng chung giữa bảng Thêm, Navigator và canvas (iframe).
 *
 * dataTransfer không đọc được trong dragover, và kéo qua ranh giới iframe —
 * nên payload giữ ở đây: { kind: 'new', type } | { kind: 'move', id } | null.
 */
export const drag = { payload: null };
