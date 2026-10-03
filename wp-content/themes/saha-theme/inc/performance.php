<?php
/**
 * Hiệu năng phía giao diện (spec SCC §68).
 *
 * Chuyển từ flatsome-child (đã QA Phase 7). Preload ảnh hero sẽ gắn với
 * element Banner của builder ở mốc 1.4.
 *
 * @package Saha\Theme
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Chế độ catalogue không có giỏ hàng → bỏ wc-cart-fragments (AJAX mỗi lượt tải trang).
 */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( saha_theme_is_catalog() && ! apply_filters( 'saha_theme_keep_cart_fragments', false ) ) {
			wp_dequeue_script( 'wc-cart-fragments' );
		}
	},
	99
);

/**
 * Script core/WooCommerce đang chặn hiển thị → `defer`.
 *
 * WP tự trả về chặn nếu có script phụ thuộc không defer được hoặc có inline
 * `after` → không làm hỏng thứ tự chạy. Chỉ `-extra` (wp_localize_script) là an toàn.
 * Lưu ý: plugin in `jQuery(...)` inline trong HTML sẽ chạy trước jQuery → tắt bằng filter.
 */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( apply_filters( 'saha_theme_keep_blocking_scripts', false ) ) {
			return;
		}

		$scripts = wp_scripts();

		// Code của dự án không dùng API jQuery đã gỡ ở 3.x.
		if ( isset( $scripts->registered['jquery'] ) && ! apply_filters( 'saha_theme_keep_jquery_migrate', false ) ) {
			$scripts->registered['jquery']->deps = array_values( array_diff( $scripts->registered['jquery']->deps, array( 'jquery-migrate' ) ) );
		}

		foreach ( array( 'jquery', 'jquery-core', 'jquery-migrate', 'underscore', 'wp-util', 'sourcebuster-js', 'wc-order-attribution' ) as $handle ) {
			if ( isset( $scripts->registered[ $handle ] ) && ! $scripts->get_data( $handle, 'strategy' ) ) {
				$scripts->add_data( $handle, 'strategy', 'defer' );
			}
		}
	},
	100
);

/**
 * CSS không cần cho lần vẽ đầu → tải không chặn (media=print rồi đổi khi tải xong).
 *
 * Font Google có `display=swap` (chữ hiện bằng font hệ thống trước); giỏ hàng mini
 * là `<dialog>` và gợi ý tìm kiếm có `hidden` → trình duyệt tự ẩn khi chưa có CSS.
 * Swatches chỉ dùng trong quick view ở trang ngoài sản phẩm.
 */
add_filter(
	'style_loader_tag',
	static function ( string $tag, string $handle ): string {
		$handles = array( 'saha-webfonts', 'saha-mini-cart', 'saha-live-search' );

		if ( ! ( function_exists( 'is_product' ) && is_product() ) ) {
			$handles[] = 'saha-swatches';
		}

		/**
		 * Handle CSS tải không chặn hiển thị.
		 *
		 * @param string[] $handles Handle.
		 */
		$handles = (array) apply_filters( 'saha_theme_async_styles', $handles );

		if ( is_admin() || ! in_array( $handle, $handles, true ) || ! preg_match( "/media=(['\"])([^'\"]*)\\1/", $tag, $media ) || 'print' === $media[2] ) {
			return $tag;
		}

		$async = str_replace( $media[0], "media='print' onload=\"this.media='" . esc_attr( $media[2] ) . "';this.onload=null\"", $tag );

		return $async . '<noscript>' . trim( $tag ) . "</noscript>\n";
	},
	10,
	2
);

/**
 * Tải trước trang khi rê chuột / chạm vào link (Speculation Rules của WP core).
 *
 * Core mặc định `conservative` (chỉ khi bắt đầu bấm) → `moderate` (rê chuột ~200ms).
 * Giữ `prefetch` (chỉ tải HTML), không `prerender`: prerender chạy cả JS của trang
 * (đếm lượt xem sản phẩm, analytics) khi khách chưa thực sự mở.
 */
add_filter(
	'wp_speculation_rules_configuration',
	static function ( $config ) {
		if ( ! is_array( $config ) || apply_filters( 'saha_theme_conservative_prefetch', false ) ) {
			return $config;
		}

		$config['mode']      = 'prefetch';
		$config['eagerness'] = 'moderate';

		return $config;
	}
);

/**
 * Không tải trước trang theo phiên khách (giỏ, thanh toán, tài khoản, danh sách báo giá)
 * và link có thao tác (thêm vào giỏ, wc-ajax).
 */
add_filter(
	'wp_speculation_rules_href_exclude_paths',
	static function ( array $paths ): array {
		if ( function_exists( 'wc_get_page_id' ) ) {
			foreach ( array( 'cart', 'checkout', 'myaccount' ) as $page ) {
				$id = wc_get_page_id( $page );

				if ( $id > 0 ) {
					$paths[] = wp_make_link_relative( (string) get_permalink( $id ) ) . '*';
				}
			}
		}

		$quote_list = (int) get_option( 'saha_quote_list_page' );

		if ( $quote_list > 0 ) {
			$paths[] = wp_make_link_relative( (string) get_permalink( $quote_list ) ) . '*';
		}

		$paths[] = '/*\\?*add-to-cart=*';
		$paths[] = '/*\\?*wc-ajax=*';

		return $paths;
	}
);

/**
 * Tắt script/style emoji của WordPress core.
 */
add_action(
	'init',
	static function (): void {
		if ( apply_filters( 'saha_theme_keep_emoji', false ) ) {
			return;
		}

		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	}
);
