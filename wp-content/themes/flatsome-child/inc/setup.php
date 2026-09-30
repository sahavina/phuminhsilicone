<?php
/**
 * Theme setup: text domain, supports, image size, nav menu.
 *
 * @package Flatsome_Child_Saha
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function (): void {
		load_child_theme_textdomain( 'flatsome-child', SAHA_THEME_PATH . '/languages' );

		add_theme_support( 'title-tag' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'script', 'style' ) );
		add_theme_support( 'responsive-embeds' );

		// Ảnh logo thương hiệu — dùng trong brand card/grid (phase 2).
		add_image_size( 'saha-brand-logo', 240, 120, false );

		// Ảnh card sản phẩm, crop vuông cho grid đều hàng.
		add_image_size( 'saha-product-card', 400, 400, true );
	}
);

/**
 * Cảnh báo trong admin nếu thiếu dependency, thay vì fatal ở frontend.
 */
add_action(
	'admin_notices',
	static function (): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$missing = array();

		if ( ! function_exists( 'saha_get_setting' ) ) {
			$missing[] = 'SAHA Core';
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			$missing[] = 'WooCommerce';
		}

		if ( ! $missing ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: danh sách plugin */
					__( 'Flatsome Child SAHA đang chạy thiếu: %s. Một số khối nội dung sẽ không hiển thị.', 'flatsome-child' ),
					implode( ', ', $missing )
				)
			)
		);
	}
);
