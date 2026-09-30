<?php
/**
 * Theme setup.
 *
 * @package Saha\Theme
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function (): void {
		load_theme_textdomain( 'saha', SAHA_THEME_DIR . '/languages' );

		add_theme_support( 'title-tag' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 96,
				'width'       => 320,
				'flex-height' => true,
				'flex-width'  => true,
			)
		);

		// saha-core chỉ nạp CSS biến Theme Options cho theme khai báo hỗ trợ.
		add_theme_support( 'saha-theme-options' );

		// WooCommerce + gallery có sẵn của WooCommerce (spec SCC §36 — không viết lại).
		add_theme_support(
			'woocommerce',
			array(
				'thumbnail_image_width' => 400,
				'single_image_width'    => 800,
			)
		);
		add_theme_support( 'wc-product-gallery-zoom' );
		add_theme_support( 'wc-product-gallery-lightbox' );
		add_theme_support( 'wc-product-gallery-slider' );

		register_nav_menus(
			array(
				'primary' => __( 'Menu chính', 'saha' ),
				'mobile'  => __( 'Menu mobile (để trống = dùng menu chính)', 'saha' ),
				'footer'  => __( 'Menu footer', 'saha' ),
			)
		);
	}
);

/**
 * Độ rộng nội dung cho embed (oEmbed, ảnh trong bài).
 */
add_action(
	'after_setup_theme',
	static function (): void {
		$GLOBALS['content_width'] = (int) apply_filters( 'saha_content_width', 800 );
	},
	0
);

/**
 * Vùng widget footer (dự phòng khi chưa dựng footer bằng builder — mốc 1.5).
 */
add_action(
	'widgets_init',
	static function (): void {
		for ( $i = 1; $i <= 4; $i++ ) {
			register_sidebar(
				array(
					/* translators: %d: số thứ tự cột */
					'name'          => sprintf( __( 'Footer — cột %d', 'saha' ), $i ),
					'id'            => 'footer-' . $i,
					'before_widget' => '<section id="%1$s" class="saha-widget %2$s">',
					'after_widget'  => '</section>',
					'before_title'  => '<h2 class="saha-widget__title">',
					'after_title'   => '</h2>',
				)
			);
		}
	}
);
