<?php
/**
 * Plugin Name:       SAHA Core
 * Plugin URI:        https://tongkhokeodan.com
 * Description:       Business layer cho Tổng Kho Keo Dán SAHA: settings, roles, security, logger, database migration, brand, product, search, quote, lead, REST API.
 * Version:           1.30.5
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Author:            Công ty TNHH Thương mại Dịch vụ Trực tuyến SAHA
 * License:           GPL-2.0-or-later
 * Text Domain:       saha-core
 * Domain Path:       /languages
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/*
 * -------------------------------------------------------------------------
 * Constants
 * -------------------------------------------------------------------------
 */
define( 'SAHA_CORE_VERSION', '1.30.5' );
define( 'SAHA_CORE_DB_VERSION', '1.3.0' );
define( 'SAHA_CORE_FILE', __FILE__ );
define( 'SAHA_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'SAHA_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'SAHA_CORE_BASENAME', plugin_basename( __FILE__ ) );
define( 'SAHA_CORE_MIN_PHP', '8.2' );
// 6.4: register_post_meta( 'revisions_enabled' ) cho dữ liệu builder (TECHNICAL-DESIGN §5.2).
define( 'SAHA_CORE_MIN_WP', '6.4' );

/*
 * Phiên bản API mà saha-builder (ứng dụng soạn thảo) phụ thuộc. Tăng khi REST,
 * schema builder hoặc định nghĩa control thay đổi không tương thích ngược —
 * saha-builder sẽ tự tắt thay vì ghi dữ liệu sai (TECHNICAL-DESIGN R14).
 */
define( 'SAHA_BUILDER_API_VERSION', 1 );

/*
 * -------------------------------------------------------------------------
 * Environment guard — thoát sớm, không fatal.
 * -------------------------------------------------------------------------
 */
/**
 * Kiểm tra môi trường tối thiểu.
 *
 * @return string Thông báo lỗi, rỗng nếu hợp lệ.
 */
function saha_core_environment_error(): string {
	if ( version_compare( PHP_VERSION, SAHA_CORE_MIN_PHP, '<' ) ) {
		return sprintf(
			/* translators: 1: required PHP version, 2: current PHP version */
			__( 'SAHA Core yêu cầu PHP %1$s trở lên. Bản đang dùng: %2$s.', 'saha-core' ),
			SAHA_CORE_MIN_PHP,
			PHP_VERSION
		);
	}

	if ( version_compare( get_bloginfo( 'version' ), SAHA_CORE_MIN_WP, '<' ) ) {
		return sprintf(
			/* translators: %s: required WordPress version */
			__( 'SAHA Core yêu cầu WordPress %s trở lên.', 'saha-core' ),
			SAHA_CORE_MIN_WP
		);
	}

	return '';
}

/*
 * -------------------------------------------------------------------------
 * Autoloader — hai quy ước cùng tồn tại (TECHNICAL-DESIGN D7):
 *
 *   PSR-4 (code SCC mới):  Saha\Core\ThemeOptions\Schema => includes/ThemeOptions/Schema.php
 *   Cũ (Phase 1–8):        Saha\Core\Foo_Bar             => includes/class-foo-bar.php
 *
 * Thử PSR-4 trước; không có file thì rơi về quy ước cũ. Module PSR-4 không
 * được đặt tên trùng thư mục cũ (ví dụ "Tables") — Windows không phân biệt
 * hoa thường (TECHNICAL-DESIGN R9).
 * -------------------------------------------------------------------------
 */
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'Saha\\Core\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );

		$psr4 = SAHA_CORE_PATH . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $psr4 ) ) {
			require_once $psr4;
			return;
		}

		$parts  = explode( '\\', $relative );
		$base   = array_pop( $parts );
		$sub    = $parts ? strtolower( implode( '/', $parts ) ) . '/' : '';
		$legacy = SAHA_CORE_PATH . 'includes/' . $sub . 'class-' . strtolower( str_replace( '_', '-', $base ) ) . '.php';

		if ( is_readable( $legacy ) ) {
			require_once $legacy;
		}
	}
);

require_once SAHA_CORE_PATH . 'includes/functions.php';

/*
 * -------------------------------------------------------------------------
 * Activation / deactivation
 * -------------------------------------------------------------------------
 */
register_activation_hook(
	__FILE__,
	static function (): void {
		if ( '' !== saha_core_environment_error() ) {
			return;
		}
		( new Saha\Core\Install() )->activate();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		( new Saha\Core\Install() )->deactivate();
	}
);

/*
 * -------------------------------------------------------------------------
 * Bootstrap
 * -------------------------------------------------------------------------
 */
add_action(
	'plugins_loaded',
	static function (): void {
		$error = saha_core_environment_error();

		if ( '' !== $error ) {
			add_action(
				'admin_notices',
				static function () use ( $error ): void {
					printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $error ) );
				}
			);
			return;
		}

		load_plugin_textdomain( 'saha-core', false, dirname( SAHA_CORE_BASENAME ) . '/languages' );

		Saha\Core\Loader::instance()->run();
	},
	5
);
