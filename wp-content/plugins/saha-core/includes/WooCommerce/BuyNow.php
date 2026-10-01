<?php
/**
 * Nút "Mua ngay": thêm vào giỏ rồi chuyển thẳng tới thanh toán.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * BuyNow (TECHNICAL-DESIGN §2 — WooCommerce/Product/BuyNow).
 *
 * Nút submit thứ hai trong form "Thêm vào giỏ" của trang sản phẩm → dùng nguyên
 * luồng thêm vào giỏ của WooCommerce (kiểm tồn kho, biến thể, số lượng, nonce
 * của form), chỉ đổi nơi chuyển hướng sau đó sang trang thanh toán.
 * Sản phẩm đơn giản: nút "Thêm vào giỏ" mang name="add-to-cart" → nút này tự đặt
 * add-to-cart khi được bấm. Sản phẩm biến thể/nhóm: form đã có trường add-to-cart.
 */
final class BuyNow {

	public const FIELD = 'saha-buy-now';

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'button' ) );
		// Trước WC_Form_Handler::add_to_cart_action (wp_loaded, 20).
		add_action( 'wp_loaded', array( $this, 'prepare' ), 15 );
		add_filter( 'woocommerce_add_to_cart_redirect', array( $this, 'redirect' ), 20 );
	}

	/**
	 * Bật cho sản phẩm này không.
	 *
	 * @param \WC_Product $product Sản phẩm.
	 */
	private static function enabled( $product ): bool {
		$enabled = $product instanceof \WC_Product
			&& ! CatalogMode::enabled()
			&& $product->is_purchasable()
			&& ! $product->is_type( 'external' );

		/**
		 * Bật/tắt nút Mua ngay theo sản phẩm.
		 *
		 * @param bool        $enabled Mặc định.
		 * @param \WC_Product $product Sản phẩm.
		 */
		return (bool) apply_filters( 'saha_buy_now_enabled', $enabled, $product );
	}

	/**
	 * In nút.
	 */
	public function button(): void {
		global $product;

		if ( ! self::enabled( $product ) ) {
			return;
		}

		printf(
			'<button type="submit" name="%1$s" value="%2$d" class="button alt saha-buy-now">%3$s</button>',
			esc_attr( self::FIELD ),
			(int) $product->get_id(),
			esc_html__( 'Mua ngay', 'saha-core' )
		);
	}

	/**
	 * Sản phẩm đơn giản: bấm "Mua ngay" thì form không gửi add-to-cart — đặt hộ.
	 */
	public function prepare(): void {
		// phpcs:disable WordPress.Security.NonceVerification -- WooCommerce tự kiểm khi thêm vào giỏ; ở đây chỉ chép ID sản phẩm.
		if ( empty( $_REQUEST[ self::FIELD ] ) || ! empty( $_REQUEST['add-to-cart'] ) ) {
			return;
		}

		$id = absint( wp_unslash( $_REQUEST[ self::FIELD ] ) );

		if ( $id > 0 ) {
			$_REQUEST['add-to-cart'] = $id;
		}
		// phpcs:enable WordPress.Security.NonceVerification
	}

	/**
	 * Thêm vào giỏ bằng "Mua ngay" → trang thanh toán.
	 *
	 * @param string $url URL mặc định.
	 */
	public function redirect( $url ) {
		return empty( $_REQUEST[ self::FIELD ] ) ? $url : wc_get_checkout_url(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- chỉ đọc cờ.
	}
}
