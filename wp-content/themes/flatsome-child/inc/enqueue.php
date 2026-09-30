<?php
/**
 * Asset enqueue — conditional theo trang (spec §73).
 *
 * @package Flatsome_Child_Saha
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Đăng ký toàn bộ asset (chưa load), rồi enqueue theo ngữ cảnh.
 */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		$css = array(
			'saha-main'       => 'assets/css/main.css',
			'saha-responsive' => 'assets/css/responsive.css',
			'saha-product'    => 'assets/css/product.css',
			'saha-brand'      => 'assets/css/brand.css',
			'saha-search'     => 'assets/css/search.css',
			'saha-form'       => 'assets/css/form.css',
		);

		foreach ( $css as $handle => $relative ) {
			wp_register_style(
				$handle,
				SAHA_THEME_URI . '/' . $relative,
				array( 'flatsome-style' ),
				saha_theme_asset_version( $relative )
			);
		}

		$js = array(
			'saha-main'           => 'assets/js/main.js',
			'saha-search'         => 'assets/js/search.js',
			'saha-product-filter' => 'assets/js/product-filter.js',
			'saha-quote-form'     => 'assets/js/quote-form.js',
		);

		foreach ( $js as $handle => $relative ) {
			wp_register_script(
				$handle,
				SAHA_THEME_URI . '/' . $relative,
				'saha-main' === $handle ? array() : array( 'saha-main' ),
				saha_theme_asset_version( $relative ),
				true
			);
		}

		// Luôn cần: layout + component chung.
		wp_enqueue_style( 'saha-main' );
		wp_enqueue_style( 'saha-responsive' );
		wp_enqueue_script( 'saha-main' );

		wp_localize_script( 'saha-main', 'SAHA_CONFIG', saha_theme_script_config() );

		// Trang sản phẩm / archive sản phẩm.
		if ( function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_checkout() ) ) {
			wp_enqueue_style( 'saha-product' );
		}

		// Brand archive, hoặc trang có block/shortcode thương hiệu.
		if (
			is_tax( array( 'product_brand', 'product_application' ) )
			|| saha_theme_content_has_shortcode( 'saha_brand_grid' )
		) {
			wp_enqueue_style( 'saha-brand' );
		}

		// Grid sản phẩm cùng thương hiệu nằm trong trang single product.
		if ( is_singular( 'product' ) ) {
			wp_enqueue_style( 'saha-brand' );
		}

		// Trang tìm kiếm.
		if ( is_search() ) {
			wp_enqueue_style( 'saha-search' );
		}
	},
	20
);

if ( ! function_exists( 'saha_theme_script_config' ) ) {
	/**
	 * Cấu hình truyền cho JS. Không hardcode domain, không lộ dữ liệu nội bộ.
	 *
	 * @return array<string, mixed>
	 */
	function saha_theme_script_config(): array {
		$config = array(
			'restUrl'  => function_exists( 'saha_api_url' ) ? saha_api_url() : rest_url( 'saha/v1/' ),
			'nonce'    => function_exists( 'saha_public_nonce' ) ? saha_public_nonce() : '',
			'locale'   => get_locale(),
			'zaloUrl'  => saha_theme_zalo_url(),
			'quoteUrl' => function_exists( 'saha_quote_page_url' ) ? saha_quote_page_url() : '',
			'hotlines' => saha_theme_hotlines(),
			'i18n'     => array(
				'loading'   => __( 'Đang tải…', 'flatsome-child' ),
				'error'     => __( 'Có lỗi xảy ra, vui lòng thử lại.', 'flatsome-child' ),
				'noResult'  => __( 'Không tìm thấy sản phẩm phù hợp.', 'flatsome-child' ),
				'submitted' => __( 'Đã gửi yêu cầu. Chúng tôi sẽ liên hệ sớm nhất.', 'flatsome-child' ),
			),
		);

		/**
		 * Lọc config JS.
		 *
		 * @param array<string, mixed> $config Config.
		 */
		return (array) apply_filters( 'saha_theme_script_config', $config );
	}
}
