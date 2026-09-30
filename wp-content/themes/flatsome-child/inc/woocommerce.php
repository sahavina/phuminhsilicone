<?php
/**
 * WooCommerce presentation layer.
 *
 * Chế độ catalogue chỉ ẩn giá/giỏ hàng bằng filter — KHÔNG xoá WooCommerce,
 * để bật bán hàng đầy đủ về sau chỉ cần đổi 1 setting (spec §5, §66).
 *
 * @package Flatsome_Child_Saha
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

add_action(
	'init',
	static function (): void {
		if ( ! saha_theme_catalogue_mode() ) {
			return;
		}

		// Ẩn nút thêm vào giỏ ở loop và single.
		add_filter( 'woocommerce_is_purchasable', '__return_false' );

		// Ẩn giá.
		add_filter( 'woocommerce_get_price_html', 'saha_theme_hide_price_html', 20, 2 );

		// Bỏ nút add-to-cart ở loop.
		remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );

		// Bỏ form add-to-cart ở single.
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
	},
	20
);

if ( ! function_exists( 'saha_theme_hide_price_html' ) ) {
	/**
	 * Thay giá bằng nhãn CTA khi ở chế độ catalogue.
	 *
	 * @param string $price_html HTML giá gốc.
	 * @param mixed  $product    WC_Product.
	 */
	function saha_theme_hide_price_html( string $price_html, $product ): string {
		unset( $product );

		return sprintf(
			'<span class="saha-price-hidden">%s</span>',
			esc_html__( 'Liên hệ báo giá', 'flatsome-child' )
		);
	}
}

/**
 * Thêm SKU vào product card của loop (spec §53) — chỉ khi sản phẩm có SKU.
 */
add_action(
	'woocommerce_after_shop_loop_item_title',
	static function (): void {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$sku = (string) $product->get_sku();

		if ( '' === $sku ) {
			return;
		}

		printf(
			'<div class="saha-product-card__sku"><span class="saha-label">%1$s</span> <span class="saha-value">%2$s</span></div>',
			esc_html__( 'Mã:', 'flatsome-child' ),
			esc_html( $sku )
		);
	},
	9
);

/**
 * Số sản phẩm mỗi trang của archive — tránh query quá lớn (spec §28).
 */
add_filter(
	'loop_shop_per_page',
	static function ( $per_page ) {
		unset( $per_page );

		/**
		 * Số sản phẩm mỗi trang.
		 *
		 * @param int $per_page Số lượng.
		 */
		return (int) apply_filters( 'saha_theme_products_per_page', 24 );
	},
	20
);
