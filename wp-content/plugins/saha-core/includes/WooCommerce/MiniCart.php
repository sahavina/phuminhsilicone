<?php
/**
 * Ngăn giỏ hàng trượt (mini cart drawer).
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\WooCommerce;

use Saha\Core\ThemeOptions\Repository as ThemeOptions;

defined( 'ABSPATH' ) || exit;

/**
 * MiniCart (TECHNICAL-DESIGN §10):
 *
 * - bấm icon giỏ (element Giỏ hàng, `[data-saha-mini-cart]`) → ngăn bên phải (`<dialog>`) liệt kê
 *   sản phẩm, đổi số lượng, xoá, tạm tính, nút Xem giỏ hàng / Thanh toán;
 * - dữ liệu từ Store API (`GET /wc/store/v1/cart`, `POST cart/update-item`, `cart/remove-item`) —
 *   không cần wc-cart-fragments;
 * - thêm vào giỏ bằng AJAX ở thẻ sản phẩm (`added_to_cart` của WooCommerce) hoặc từ Xem nhanh → mở ngăn;
 * - không JS / lỗi: icon giỏ vẫn là link tới trang giỏ hàng;
 * - tắt ở chế độ catalogue, trang giỏ hàng và trang thanh toán.
 */
final class MiniCart {

	public const HANDLE = 'saha-mini-cart';

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 20 );
	}

	/**
	 * Tuỳ chọn đang bật (chưa xét trang).
	 */
	public static function enabled(): bool {
		if ( ! current_theme_supports( 'saha-theme-options' ) || ! (bool) ThemeOptions::get( 'shop.mini_cart', true ) ) {
			return false;
		}

		return ! ( function_exists( 'saha_is_catalogue_mode' ) && saha_is_catalogue_mode() );
	}

	/**
	 * Bật ở request hiện tại: không phải trang giỏ / thanh toán (ở đó đã có giỏ đầy đủ).
	 */
	public static function active(): bool {
		if ( ! self::enabled() || is_admin() ) {
			return false;
		}

		return ! ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) );
	}

	/**
	 * CSS/JS.
	 */
	public function enqueue(): void {
		if ( ! self::active() ) {
			return;
		}

		wp_enqueue_style( self::HANDLE, SAHA_CORE_URL . 'public/assets/css/mini-cart.css', array(), SAHA_CORE_VERSION );
		wp_enqueue_script(
			self::HANDLE,
			SAHA_CORE_URL . 'public/assets/js/mini-cart.js',
			array(),
			SAHA_CORE_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_localize_script( self::HANDLE, 'sahaMiniCart', self::config() );
	}

	/**
	 * Cấu hình cho JS.
	 *
	 * @return array<string, mixed>
	 */
	public static function config(): array {
		return array(
			'storeApi' => rest_url( 'wc/store/v1/cart' ),
			'nonce'    => wp_create_nonce( 'wc_store_api' ),
			'cartUrl'  => wc_get_cart_url(),
			'checkout' => wc_get_checkout_url(),
			'shopUrl'  => wc_get_page_permalink( 'shop' ),
			'i18n'     => array(
				'title'     => __( 'Giỏ hàng', 'saha-core' ),
				'close'     => __( 'Đóng giỏ hàng', 'saha-core' ),
				'loading'   => __( 'Đang tải giỏ hàng…', 'saha-core' ),
				'empty'     => __( 'Giỏ hàng đang trống.', 'saha-core' ),
				'continue'  => __( 'Tiếp tục mua sắm', 'saha-core' ),
				'error'     => __( 'Không tải được giỏ hàng.', 'saha-core' ),
				'subtotal'  => __( 'Tạm tính', 'saha-core' ),
				'viewCart'  => __( 'Xem giỏ hàng', 'saha-core' ),
				'checkout'  => __( 'Thanh toán', 'saha-core' ),
				/* translators: %s: tên sản phẩm */
				'remove'    => __( 'Xoá “%s” khỏi giỏ', 'saha-core' ),
				/* translators: %s: tên sản phẩm */
				'qty'       => __( 'Số lượng “%s”', 'saha-core' ),
				'decrease'  => __( 'Giảm', 'saha-core' ),
				'increase'  => __( 'Tăng', 'saha-core' ),
				'added'     => __( 'Đã thêm vào giỏ hàng.', 'saha-core' ),
				'updated'   => __( 'Đã cập nhật giỏ hàng.', 'saha-core' ),
				/* translators: %d: số sản phẩm */
				'countText' => __( 'Giỏ hàng: %d sản phẩm', 'saha-core' ),
			),
		);
	}
}
