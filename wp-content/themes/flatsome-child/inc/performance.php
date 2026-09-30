<?php
/**
 * Tối ưu hiệu năng phía giao diện (spec §25, §26, §73).
 *
 * Không hy sinh chức năng để "gian lận điểm" (spec §25): mọi tối ưu ở đây
 * đều giữ nguyên hành vi, chỉ bỏ phần không dùng hoặc tải sớm phần quan trọng.
 *
 * @package Flatsome_Child_Saha
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/*
 * -------------------------------------------------------------------------
 * 1. LCP — preload ảnh hero
 * -------------------------------------------------------------------------
 */

if ( ! function_exists( 'saha_theme_find_hero_image_id' ) ) {
	/**
	 * Tìm ID ảnh nền của [ux_banner] ĐẦU TIÊN trong nội dung UX Builder.
	 *
	 * Ảnh hero gần như luôn là phần tử LCP của trang chủ/landing page. Đọc
	 * thẳng từ nội dung trang nên không cần admin khai báo lại ở chỗ khác.
	 *
	 * @param string $content post_content.
	 * @return int 0 nếu không tìm thấy.
	 */
	function saha_theme_find_hero_image_id( string $content ): int {
		// Chỉ xét phần đầu nội dung: hero luôn nằm trên cùng.
		$head = substr( $content, 0, 4000 );

		if ( ! preg_match( '/\[ux_banner\b[^\]]*?\sbg="(\d+)"/', $head, $match ) ) {
			return 0;
		}

		return (int) $match[1];
	}
}

if ( ! function_exists( 'saha_theme_lcp_image_id' ) ) {
	/**
	 * Ảnh LCP của trang hiện tại (0 nếu không xác định được).
	 */
	function saha_theme_lcp_image_id(): int {
		static $cached = null;

		if ( null !== $cached ) {
			return $cached;
		}

		$id = 0;

		if ( is_singular( 'page' ) || is_front_page() ) {
			$post = get_post( (int) get_queried_object_id() );

			if ( $post instanceof WP_Post ) {
				$id = saha_theme_find_hero_image_id( (string) $post->post_content );
			}
		}

		/**
		 * Chỉ định ảnh LCP thủ công (ví dụ landing page dùng khối khác ux_banner).
		 *
		 * @param int $id Attachment ID.
		 */
		$id = (int) apply_filters( 'saha_theme_lcp_image_id', $id );

		$cached = ( $id > 0 && wp_attachment_is_image( $id ) ) ? $id : 0;

		return $cached;
	}
}

/**
 * <link rel="preload"> cho ảnh hero, kèm srcset để mobile tải bản nhỏ.
 *
 * Trình duyệt tải ảnh ngay khi đọc <head>, không phải chờ CSS/JS của Flatsome
 * hay lazy-load quyết định — đây là cách giảm LCP an toàn với mọi plugin cache.
 */
add_action(
	'wp_head',
	static function (): void {
		$id = saha_theme_lcp_image_id();

		if ( $id <= 0 ) {
			return;
		}

		$src = wp_get_attachment_image_src( $id, 'full' );

		if ( ! $src ) {
			return;
		}

		$srcset = (string) wp_get_attachment_image_srcset( $id, 'full' );

		printf(
			'<link rel="preload" as="image" href="%1$s"%2$s fetchpriority="high">' . "\n",
			esc_url( $src[0] ),
			'' !== $srcset ? ' imagesrcset="' . esc_attr( $srcset ) . '" imagesizes="100vw"' : ''
		);
	},
	1
);

/**
 * Ảnh hero render qua wp_get_attachment_image: không lazy-load, ưu tiên cao.
 *
 * @param array<string, string> $attr       Thuộc tính ảnh.
 * @param WP_Post               $attachment Attachment.
 * @return array<string, string>
 */
add_filter(
	'wp_get_attachment_image_attributes',
	static function ( $attr, $attachment ) {
		if ( ! is_array( $attr ) || ! $attachment instanceof WP_Post || is_admin() ) {
			return $attr;
		}

		if ( (int) $attachment->ID !== saha_theme_lcp_image_id() ) {
			return $attr;
		}

		$attr['loading']       = 'eager';
		$attr['fetchpriority'] = 'high';
		$attr['decoding']      = 'async';

		// Class chung mà các plugin lazy-load (LiteSpeed, Flatsome, WP Rocket) bỏ qua.
		$attr['class'] = trim( ( $attr['class'] ?? '' ) . ' skip-lazy no-lazy' );

		return $attr;
	},
	20,
	2
);

/*
 * -------------------------------------------------------------------------
 * 2. Bỏ asset không dùng
 * -------------------------------------------------------------------------
 */

/**
 * Chế độ catalogue không có giỏ hàng → bỏ wc-cart-fragments.
 *
 * Script này gọi AJAX lên server ở MỌI lượt tải trang để làm mới giỏ hàng,
 * và thường là request làm chậm nhất trên site WooCommerce có page cache.
 */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( ! saha_theme_catalogue_mode() ) {
			return;
		}

		/**
		 * Giữ cart fragments dù đang ở chế độ catalogue.
		 *
		 * @param bool $keep Có giữ không.
		 */
		if ( apply_filters( 'saha_theme_keep_cart_fragments', false ) ) {
			return;
		}

		wp_dequeue_script( 'wc-cart-fragments' );
	},
	99
);

/**
 * Tắt script/style emoji của WordPress core (~15 KB, thêm 1 request).
 *
 * Trình duyệt hiện đại tự hiển thị emoji; spec §55 không hỗ trợ IE.
 */
add_action(
	'init',
	static function (): void {
		/**
		 * Giữ emoji script của WordPress.
		 *
		 * @param bool $keep Có giữ không.
		 */
		if ( apply_filters( 'saha_theme_keep_emoji', false ) ) {
			return;
		}

		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

		add_filter(
			'wp_resource_hints',
			static function ( $urls, $relation ) {
				if ( 'dns-prefetch' !== $relation || ! is_array( $urls ) ) {
					return $urls;
				}

				return array_values(
					array_filter(
						$urls,
						static fn( $url ): bool => ! is_string( $url ) || false === strpos( $url, 's.w.org/images/core/emoji' )
					)
				);
			},
			10,
			2
		);
	}
);
