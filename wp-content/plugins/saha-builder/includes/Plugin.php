<?php
/**
 * Khởi động SAHA Builder.
 *
 * @package Saha\Builder
 */

declare( strict_types=1 );

namespace Saha\Builder;

use Saha\Builder\Admin\ThemeOptionsScreen;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin.
 */
final class Plugin {

	/**
	 * Kiểm tra phụ thuộc rồi đăng ký màn hình admin.
	 */
	public function boot(): void {
		load_plugin_textdomain( 'saha-builder', false, dirname( plugin_basename( SAHA_BUILDER_FILE ) ) . '/languages' );

		$problem = $this->dependencyProblem();

		if ( '' !== $problem ) {
			add_action(
				'admin_notices',
				static function () use ( $problem ): void {
					if ( current_user_can( 'activate_plugins' ) ) {
						printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $problem ) );
					}
				}
			);
			return;
		}

		if ( is_admin() ) {
			( new ThemeOptionsScreen() )->register();
		}
	}

	/**
	 * Lý do không chạy được; rỗng nếu đủ điều kiện.
	 */
	private function dependencyProblem(): string {
		if ( ! defined( 'SAHA_BUILDER_API_VERSION' ) ) {
			return __( 'SAHA Builder cần plugin SAHA Core đang hoạt động.', 'saha-builder' );
		}

		if ( (int) SAHA_BUILDER_API_VERSION !== SAHA_BUILDER_REQUIRES_API ) {
			return sprintf(
				/* translators: 1: API version SAHA Core, 2: API version builder cần */
				__( 'SAHA Builder không tương thích với phiên bản SAHA Core hiện tại (API %1$d, builder cần API %2$d). Hãy cập nhật cả hai plugin cùng phiên bản.', 'saha-builder' ),
				(int) SAHA_BUILDER_API_VERSION,
				SAHA_BUILDER_REQUIRES_API
			);
		}

		return '';
	}
}
