/**
 * Gọi REST của saha-core. apiFetch tự gửi nonce `wp_rest`.
 *
 * Lỗi: apiFetch reject với body JSON của server
 * `{ success:false, code, message, errors, data }`.
 */
import apiFetch from '@wordpress/api-fetch';

const BASE = '/saha/v1/builder';

export const api = {
	elements: () =>
		apiFetch( { path: `${ BASE }/elements` } ).then( ( r ) => r.data ),

	load: ( postId ) =>
		apiFetch( { path: `${ BASE }/${ postId }` } ).then( ( r ) => r.data ),

	save: ( postId, data, baseHash, enabled = true ) =>
		apiFetch( {
			path: `${ BASE }/save`,
			method: 'POST',
			data: { postId, data, baseHash, enabled },
		} ).then( ( r ) => r.data ),

	render: ( postId, node, signal ) =>
		apiFetch( {
			path: `${ BASE }/render`,
			method: 'POST',
			data: { postId, node },
			signal,
		} ).then( ( r ) => r.data ),

	blocks: () =>
		apiFetch( { path: '/saha/v1/blocks' } ).then( ( r ) => r.data ),

	createBlock: ( title, node ) =>
		apiFetch( {
			path: '/saha/v1/blocks',
			method: 'POST',
			data: { title, node },
		} ).then( ( r ) => r.data ),

	lock: ( postId ) =>
		apiFetch( { path: `${ BASE }/lock/${ postId }`, method: 'POST' } ).then(
			( r ) => r.data
		),
};

/**
 * Thông báo lỗi dễ đọc từ lỗi apiFetch.
 *
 * @param {Object} error    Lỗi.
 * @param {string} fallback Thông báo mặc định.
 * @return {string} Thông báo.
 */
export function errorMessage( error, fallback ) {
	return ( error && error.message ) || fallback;
}
