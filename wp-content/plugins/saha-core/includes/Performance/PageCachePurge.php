<?php
/**
 * Xoá cache trang trên máy chủ (WP Super Cache) khi nội dung đổi.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Performance;

use Saha\Core\ThemeOptions\Repository as ThemeOptions;

defined( 'ABSPATH' ) || exit;

/**
 * PageCachePurge.
 *
 * WP Super Cache tự xoá trang khi lưu bài, nhưng không biết dữ liệu SAHA: sản phẩm
 * / thương hiệu / danh mục đổi (hiện ở lưới trang chủ, trang thương hiệu…), layout
 * builder, menu, Theme Options. Catalogue nhỏ → xoá toàn bộ, gom 1 lần cuối request.
 *
 * Không có plugin cache trang → không làm gì. Plugin khác cắm `saha_page_cache_purge`.
 */
final class PageCachePurge {

	/**
	 * Đã hẹn xoá ở cuối request.
	 *
	 * @var bool
	 */
	private static bool $scheduled = false;

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		$hooks = array(
			'saha_cache_bumped', // Sản phẩm, tồn kho, danh mục, thương hiệu, bài viết (class-cache.php).
			'saha_builder_saved',
			'wp_update_nav_menu',
			'customize_save_after',
			'switch_theme',
			'update_option_' . ThemeOptions::OPTION,
			'update_option_woocommerce_currency',
		);

		foreach ( $hooks as $hook ) {
			add_action( $hook, array( __CLASS__, 'schedule' ) );
		}
	}

	/**
	 * Hẹn xoá ở cuối request (nhiều thay đổi trong một request → xoá một lần).
	 */
	public static function schedule(): void {
		if ( ! self::$scheduled ) {
			self::$scheduled = true;
			add_action( 'shutdown', array( __CLASS__, 'flush' ) );
		}
	}

	/**
	 * Xoá toàn bộ cache trang.
	 */
	public static function flush(): void {
		self::$scheduled = false;

		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}

		/**
		 * Đã yêu cầu xoá toàn bộ cache trang — cắm plugin cache khác vào đây.
		 */
		do_action( 'saha_page_cache_purge' );
	}
}
