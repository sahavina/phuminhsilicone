<?php
/**
 * SEO phía giao diện: breadcrumb một nguồn.
 *
 * Theme không thay SEO plugin (spec SCC §72). Meta, canonical, schema,
 * sitemap do Rank Math / Yoast / saha-core Seo quản lý — ở đây chỉ bảo đảm
 * breadcrumb hiển thị đúng MỘT lần (logic chuyển nguyên từ flatsome-child,
 * đã QA ở Phase 6).
 *
 * @package Saha\Theme
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'woocommerce_breadcrumb' ) ) {
	/**
	 * Override hàm pluggable của WooCommerce.
	 *
	 * WooCommerce khai báo woocommerce_breadcrumb() trong `if ( ! function_exists() )`
	 * và chỉ nạp template functions ở after_setup_theme — sau khi theme đã nạp —
	 * nên đây là cách override chính thức, không sửa core.
	 *
	 * Render MỘT nguồn: Rank Math → Yoast → WooCommerce. Khi dùng Rank Math/Yoast,
	 * luồng WooCommerce không chạy nên BreadcrumbList schema của WooCommerce
	 * cũng không sinh ra — tránh schema trùng.
	 *
	 * @param array<string, mixed> $args Tham số breadcrumb của WooCommerce.
	 */
	function woocommerce_breadcrumb( $args = array() ) {
		$provider = function_exists( 'saha_breadcrumb_provider' ) ? saha_breadcrumb_provider() : 'woocommerce';

		if ( 'rank_math' === $provider ) {
			rank_math_the_breadcrumbs();
			return;
		}

		if ( 'yoast' === $provider ) {
			yoast_breadcrumb( '<nav class="woocommerce-breadcrumb saha-breadcrumb" aria-label="' . esc_attr__( 'Đường dẫn', 'saha' ) . '">', '</nav>' );
			return;
		}

		// Hành vi mặc định của WooCommerce — text domain "woocommerce" có chủ đích
		// để tái dùng bản dịch sẵn có của WooCommerce.
		$args = wp_parse_args(
			$args,
			apply_filters(
				'woocommerce_breadcrumb_defaults',
				array(
					'delimiter'   => '<span class="saha-breadcrumb__sep" aria-hidden="true">/</span>',
					'wrap_before' => '<nav class="woocommerce-breadcrumb saha-breadcrumb" aria-label="' . esc_attr__( 'Breadcrumb', 'woocommerce' ) . '">',
					'wrap_after'  => '</nav>',
					'before'      => '',
					'after'       => '',
					'home'        => _x( 'Home', 'breadcrumb', 'woocommerce' ),
				)
			)
		);

		$breadcrumbs = new WC_Breadcrumb();

		if ( ! empty( $args['home'] ) ) {
			$breadcrumbs->add_crumb( $args['home'], apply_filters( 'woocommerce_breadcrumb_home_url', home_url() ) );
		}

		$args['breadcrumb'] = $breadcrumbs->generate();

		// Hook gốc — WC_Structured_Data sinh BreadcrumbList schema tại đây.
		do_action( 'woocommerce_breadcrumb', $breadcrumbs, $args );

		wc_get_template( 'global/breadcrumb.php', $args );
	}
}

if ( ! function_exists( 'saha_theme_breadcrumb' ) ) {
	/**
	 * Breadcrumb cho trang không phải WooCommerce (bài viết, trang, archive).
	 */
	function saha_theme_breadcrumb(): void {
		if ( is_front_page() ) {
			return;
		}

		$provider = function_exists( 'saha_breadcrumb_provider' ) ? saha_breadcrumb_provider() : 'none';

		if ( 'rank_math' === $provider ) {
			rank_math_the_breadcrumbs();
			return;
		}

		if ( 'yoast' === $provider ) {
			yoast_breadcrumb( '<nav class="saha-breadcrumb" aria-label="' . esc_attr__( 'Đường dẫn', 'saha' ) . '">', '</nav>' );
			return;
		}

		// Không có plugin SEO: dùng breadcrumb của WooCommerce nếu có (hiểu cả bài viết/trang).
		if ( function_exists( 'woocommerce_breadcrumb' ) && class_exists( 'WC_Breadcrumb' ) ) {
			woocommerce_breadcrumb();
		}
	}
}
