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
