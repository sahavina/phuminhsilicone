<?php
/**
 * Presentation hooks: body class, sticky CTA mobile, breadcrumb.
 *
 * @package Flatsome_Child_Saha
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Body class phục vụ CSS theo chế độ hoạt động.
 */
add_filter(
	'body_class',
	static function ( array $classes ): array {
		$classes[] = 'saha-site';

		if ( saha_theme_catalogue_mode() ) {
			$classes[] = 'saha-catalogue-mode';
		}

		if ( saha_theme_mobile_cta_enabled() ) {
			$classes[] = 'saha-has-sticky-cta';
		}

		return $classes;
	}
);

if ( ! function_exists( 'saha_theme_mobile_cta_enabled' ) ) {
	/**
	 * Có hiển thị sticky CTA mobile không.
	 */
	function saha_theme_mobile_cta_enabled(): bool {
		$enabled = ! is_admin() && ! is_404();

		/**
		 * Bật/tắt sticky CTA mobile.
		 *
		 * @param bool $enabled Trạng thái.
		 */
		return (bool) apply_filters( 'saha_theme_mobile_cta_enabled', $enabled );
	}
}

/**
 * Sticky CTA mobile: Gọi điện · Zalo · Báo giá (spec §17).
 *
 * CSS thêm padding-bottom cho body để không che nội dung.
 */
add_action(
	'wp_footer',
	static function (): void {
		if ( ! saha_theme_mobile_cta_enabled() ) {
			return;
		}

		saha_theme_part( 'common/sticky-cta' );
	},
	20
);

/**
 * Breadcrumb: ưu tiên Rank Math, fallback custom, không render 2 lần (spec §71).
 */
if ( ! function_exists( 'saha_theme_breadcrumb' ) ) {
	/**
	 * Render breadcrumb.
	 */
	function saha_theme_breadcrumb(): void {
		static $rendered = false;

		if ( $rendered ) {
			return;
		}

		$rendered = true;

		// Cùng một nguồn với woocommerce_breadcrumb() (inc/woocommerce.php).
		$provider = function_exists( 'saha_breadcrumb_provider' ) ? saha_breadcrumb_provider() : 'none';

		if ( 'rank_math' === $provider ) {
			rank_math_the_breadcrumbs();
			return;
		}

		if ( 'yoast' === $provider ) {
			yoast_breadcrumb( '<nav class="saha-breadcrumb" aria-label="' . esc_attr__( 'Đường dẫn', 'flatsome-child' ) . '">', '</nav>' );
			return;
		}

		// Trang WooCommerce đã có breadcrumb của Flatsome/WooCommerce — không render thêm.
		if ( 'woocommerce' === $provider && function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
			return;
		}

		saha_theme_part( 'common/breadcrumb' );
	}
}

/**
 * Trang 404 phải có search box + danh mục phổ biến, không redirect về trang chủ (spec §81).
 *
 * Flatsome cho phép chèn nội dung vào 404 qua hook này.
 */
add_action(
	'flatsome_after_404_content',
	static function (): void {
		saha_theme_part(
			'common/empty-state',
			array(
				'title'       => __( 'Không tìm thấy trang bạn cần', 'flatsome-child' ),
				'description' => __( 'Thử tìm theo tên sản phẩm hoặc mã SKU, hoặc chọn một danh mục bên dưới.', 'flatsome-child' ),
				'show_search' => true,
				'show_cats'   => true,
			)
		);
	}
);

/*
 * -------------------------------------------------------------------------
 * PHASE 6 — Trang bài viết (spec §21): breadcrumb + bài viết liên quan
 * -------------------------------------------------------------------------
 */

/**
 * Breadcrumb đầu nội dung bài viết + bài viết liên quan cuối bài.
 *
 * Dùng the_content (chỉ main query, trong loop) thay vì hook riêng của
 * Flatsome để không phụ thuộc phiên bản theme cha.
 */
add_filter(
	'the_content',
	static function ( $content ) {
		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		/**
		 * Bật/tắt breadcrumb chèn đầu bài (tắt nếu Flatsome/plugin khác đã hiện).
		 *
		 * @param bool $enabled Có chèn không.
		 */
		$show_breadcrumb = (bool) apply_filters( 'saha_theme_post_breadcrumb', true );
		$show_related    = (bool) saha_theme_setting( 'blog_related_posts', true );

		$before = '';
		$after  = '';

		if ( $show_breadcrumb ) {
			ob_start();
			saha_theme_breadcrumb();
			$before = (string) ob_get_clean();
		}

		if ( $show_related && function_exists( 'saha_related_post_ids' ) ) {
			ob_start();
			saha_theme_part(
				'blog/related',
				array( 'ids' => saha_related_post_ids( (int) get_the_ID(), 3 ) )
			);
			$after = (string) ob_get_clean();
		}

		return $before . $content . $after;
	},
	20
);
