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

		if ( function_exists( 'rank_math_the_breadcrumbs' ) ) {
			rank_math_the_breadcrumbs();
			return;
		}

		if ( function_exists( 'yoast_breadcrumb' ) ) {
			yoast_breadcrumb( '<nav class="saha-breadcrumb" aria-label="' . esc_attr__( 'Đường dẫn', 'flatsome-child' ) . '">', '</nav>' );
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
