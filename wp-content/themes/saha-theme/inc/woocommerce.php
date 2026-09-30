<?php
/**
 * Tích hợp WooCommerce — mức nền (mốc 1.1).
 *
 * Mốc 1.6 bổ sung: product card, gallery layout, buy now, catalog mode đầy đủ,
 * style Cart/Checkout block (TECHNICAL-DESIGN §10).
 *
 * @package Saha\Theme
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

// Wrapper của theme thay cho wrapper mặc định của WooCommerce.
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );

// Breadcrumb đã đặt ở woocommerce.php của theme (một chỗ duy nhất).
remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );

/**
 * Số sản phẩm mỗi trang — SAHA Theme Options → Cửa hàng.
 */
add_filter(
	'loop_shop_per_page',
	static function ( $per_page ) {
		return (int) saha_theme_option( 'shop.per_page', $per_page );
	},
	20
);

/**
 * Số cột desktop cho WooCommerce; tablet/mobile xử lý bằng CSS (class trên <ul.products>).
 */
add_filter(
	'loop_shop_columns',
	static function ( $columns ) {
		return (int) saha_theme_option( 'shop.columns_desktop', $columns );
	},
	20
);

/**
 * Class số cột tablet/mobile cho lưới sản phẩm.
 */
add_filter(
	'woocommerce_product_loop_start',
	static function ( string $html ): string {
		$tablet = max( 1, min( 4, (int) saha_theme_option( 'shop.columns_tablet', 3 ) ) );
		$mobile = max( 1, min( 2, (int) saha_theme_option( 'shop.columns_mobile', 2 ) ) );

		return str_replace(
			'class="products',
			'class="products saha-products--tablet-' . $tablet . ' saha-products--mobile-' . $mobile,
			$html
		);
	}
);
