/**
 * ESLint cho saha-builder và saha-theme: config mặc định của @wordpress/scripts
 * + khai báo các gói @wordpress/* là "core module".
 *
 * Các gói này do WordPress nạp sẵn lúc chạy (webpack biến thành wp.* external),
 * không cài vào node_modules — nếu không khai báo, import/no-unresolved và
 * import/no-extraneous-dependencies báo lỗi sai.
 */
const defaults = require( '@wordpress/scripts/config/eslint.config.cjs' );

const WORDPRESS_EXTERNALS = [
	'@wordpress/api-fetch',
	'@wordpress/components',
	'@wordpress/compose',
	'@wordpress/core-data',
	'@wordpress/data',
	'@wordpress/element',
	'@wordpress/i18n',
	'@wordpress/media-utils',
];

module.exports = [
	...defaults,
	{
		settings: {
			'import/core-modules': WORDPRESS_EXTERNALS,
		},
	},
];
