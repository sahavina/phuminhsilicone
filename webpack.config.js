/**
 * Build cho SAHA Commerce Core.
 *
 * Hai đầu ra độc lập, cùng dựa trên cấu hình chuẩn của @wordpress/scripts:
 *  - saha-builder: ứng dụng React trong wp-admin → wp-content/plugins/saha-builder/build
 *  - saha-theme:   JS/CSS frontend             → wp-content/themes/saha-theme/assets/build
 *
 * @wordpress/scripts tự external hoá các package `@wordpress/*` và React thành
 * biến toàn cục `wp.*` và sinh file `*.asset.php` (danh sách phụ thuộc + version)
 * để PHP enqueue đúng — lý do chọn công cụ này thay vì Vite (TECHNICAL-DESIGN D8).
 */

const path = require( 'path' );

const DEFAULT_CONFIG = require.resolve( '@wordpress/scripts/config/webpack.config' );

/**
 * Lấy một bản cấu hình mặc định MỚI (plugin instance riêng).
 *
 * Dùng chung instance plugin (MiniCssExtractPlugin, DependencyExtraction…)
 * giữa hai compiler dễ gây lỗi build → nạp lại module mỗi lần.
 */
function freshDefaults() {
	delete require.cache[ DEFAULT_CONFIG ];

	const config = require( DEFAULT_CONFIG );

	// Tuỳ phiên bản / cờ --experimental-modules, cấu hình có thể là mảng.
	return Array.isArray( config ) ? config[ 0 ] : config;
}

/**
 * Tạo cấu hình cho một package.
 *
 * @param {string}                 name    Tên (hiển thị trong log webpack).
 * @param {Object<string, string>} entry   Entry → file nguồn.
 * @param {string}                 outDir  Thư mục đầu ra (tương đối gốc repo).
 */
function packageConfig( name, entry, outDir ) {
	const base = freshDefaults();

	return {
		...base,
		name,
		entry,
		output: {
			...base.output,
			path: path.resolve( __dirname, outDir ),
		},
		// Không sao chép block.json / file PHP như dự án block mặc định.
		plugins: base.plugins.filter(
			( plugin ) => plugin.constructor.name !== 'CopyPlugin'
		),
	};
}

module.exports = [
	packageConfig(
		'saha-builder',
		{
			'theme-options': './wp-content/plugins/saha-builder/src/theme-options/index.js',
		},
		'wp-content/plugins/saha-builder/build'
	),
	packageConfig(
		'saha-theme',
		{
			frontend: './wp-content/themes/saha-theme/src/js/frontend.js',
		},
		'wp-content/themes/saha-theme/assets/build'
	),
];
