<?php
/**
 * Plugin Name:       SAHA Builder
 * Plugin URI:        https://tongkhokeodan.com
 * Description:       Ứng dụng soạn thảo của SAHA Commerce Core: Theme Options, page builder, header/footer builder. Dữ liệu và hiển thị nằm ở SAHA Core — tắt plugin này, website vẫn hiển thị bình thường.
 * Version:           0.8.1
 * Requires at least: 6.6
 * Requires PHP:      8.2
 * Requires Plugins:  saha-core
 * Author:            Công ty TNHH Thương mại Dịch vụ Trực tuyến SAHA
 * License:           GPL-2.0-or-later
 * Text Domain:       saha-builder
 * Domain Path:       /languages
 *
 * @package Saha\Builder
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'SAHA_BUILDER_VERSION', '0.8.1' );
define( 'SAHA_BUILDER_FILE', __FILE__ );
define( 'SAHA_BUILDER_PATH', plugin_dir_path( __FILE__ ) );
define( 'SAHA_BUILDER_URL', plugin_dir_url( __FILE__ ) );

/**
 * Phiên bản API của saha-core mà bản builder này được viết cho.
 * Lệch → builder tự tắt thay vì ghi dữ liệu sai (TECHNICAL-DESIGN R14).
 */
define( 'SAHA_BUILDER_REQUIRES_API', 1 );

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'Saha\\Builder\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$file = SAHA_BUILDER_PATH . 'includes/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

// Sau saha-core (plugins_loaded priority 5) để kiểm tra được hằng API của nó.
add_action(
	'plugins_loaded',
	static function (): void {
		( new Saha\Builder\Plugin() )->boot();
	},
	20
);
