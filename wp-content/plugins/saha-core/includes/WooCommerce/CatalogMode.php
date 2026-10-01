<?php
/**
 * Chế độ catalogue: ẩn giá, không cho mua — phía server.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * CatalogMode.
 *
 * Trước mốc 1.6 logic này nằm trong flatsome-child (theme) → đổi theme là mất ẩn
 * giá. Nay ở saha-core: mọi theme đều được ẩn giá thật (không chỉ ẩn bằng CSS),
 * sản phẩm không mua được qua form, AJAX hay Store API (Cart/Checkout block).
 *
 * Một nguồn bật/tắt: `saha_core_settings.catalogue_mode` (SAHA → Cấu hình hoặc
 * Theme Options → Chế độ catalogue). flatsome-child (đã đóng băng) vẫn giữ bản cũ;
 * hai bản cùng bật cho kết quả giống nhau.
 */
final class CatalogMode {

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'apply' ), 20 );
	}

	/**
	 * Đang bật chế độ catalogue không.
	 */
	public static function enabled(): bool {
		return function_exists( 'saha_is_catalogue_mode' ) && saha_is_catalogue_mode();
	}

	/**
	 * Áp dụng.
	 */
	public function apply(): void {
		if ( ! self::enabled() ) {
			return;
		}

		add_filter( 'woocommerce_is_purchasable', '__return_false', 20 );
		add_filter( 'woocommerce_variation_is_purchasable', '__return_false', 20 );
		add_filter( 'woocommerce_get_price_html', array( self::class, 'priceHtml' ), 20 );
		add_filter( 'woocommerce_cart_needs_payment', '__return_false', 20 );

		remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
	}

	/**
	 * Thay giá bằng nhãn "Liên hệ báo giá".
	 */
	public static function priceHtml(): string {
		return '<span class="saha-price-hidden">' . esc_html__( 'Liên hệ báo giá', 'saha-core' ) . '</span>';
	}
}
