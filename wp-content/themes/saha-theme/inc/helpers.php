<?php
/**
 * Hàm trợ giúp của theme.
 *
 * Theme KHÔNG phụ thuộc cứng vào saha-core: mọi lời gọi sang plugin đều qua
 * các hàm ở đây, có giá trị dự phòng khi plugin tắt.
 *
 * @package Saha\Theme
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'saha_theme_has_core' ) ) {
	/**
	 * saha-core có đang hoạt động không.
	 */
	function saha_theme_has_core(): bool {
		return class_exists( '\Saha\Core\ThemeOptions\Repository' );
	}
}

if ( ! function_exists( 'saha_theme_option' ) ) {
	/**
	 * Đọc Theme Options theo đường dẫn "nhóm.field".
	 *
	 * @param string $path     Ví dụ `header.sticky`.
	 * @param mixed  $fallback Giá trị khi không có saha-core.
	 * @return mixed
	 */
	function saha_theme_option( string $path, $fallback = null ) {
		if ( ! saha_theme_has_core() ) {
			return $fallback;
		}

		return \Saha\Core\ThemeOptions\Repository::get( $path, $fallback );
	}
}

if ( ! function_exists( 'saha_theme_is_catalog' ) ) {
	/**
	 * Chế độ catalogue (ẩn giá, giỏ hàng). Một nguồn: cài đặt của saha-core.
	 */
	function saha_theme_is_catalog(): bool {
		return function_exists( 'saha_is_catalogue_mode' ) && saha_is_catalogue_mode();
	}
}

if ( ! function_exists( 'saha_theme_hotline' ) ) {
	/**
	 * Hotline chính kèm link tel:, lấy từ SAHA → Cấu hình.
	 *
	 * @return array{number: string, href: string}|null
	 */
	function saha_theme_hotline(): ?array {
		if ( ! function_exists( 'saha_hotline' ) || ! function_exists( 'saha_tel_href' ) ) {
			return null;
		}

		$number = saha_hotline( 'north' );

		if ( '' === $number ) {
			return null;
		}

		return array(
			'number' => $number,
			'href'   => saha_tel_href( $number ),
		);
	}
}

if ( ! function_exists( 'saha_theme_icon' ) ) {
	/**
	 * Icon SVG nội tuyến (không tải font icon). Trang trí → aria-hidden.
	 *
	 * @param string $name menu|close|search|cart|user|phone|arrow-up.
	 */
	function saha_theme_icon( string $name ): string {
		$paths = array(
			'menu'     => '<path d="M3 6h18M3 12h18M3 18h18"/>',
			'close'    => '<path d="M6 6l12 12M18 6L6 18"/>',
			'search'   => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
			'cart'     => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.6 12.2a1 1 0 0 0 1 .8h9.6a1 1 0 0 0 1-.8L21 7H6"/>',
			'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
			'phone'    => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
			'arrow-up' => '<path d="M12 19V5M5 12l7-7 7 7"/>',
		);

		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}

		return '<svg class="saha-icon saha-icon--' . esc_attr( $name ) . '" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}
}

if ( ! function_exists( 'saha_theme_logo' ) ) {
	/**
	 * Logo: Theme Options → Custom Logo của WordPress → tên website.
	 *
	 * Ảnh logo có width/height (tránh CLS) và không lazy-load (nằm trên màn hình đầu).
	 */
	function saha_theme_logo(): string {
		$home   = esc_url( home_url( '/' ) );
		$name   = get_bloginfo( 'name' );
		$logo   = (int) saha_theme_option( 'general.logo', 0 );
		$mobile = (int) saha_theme_option( 'general.logo_mobile', 0 );

		if ( $logo <= 0 ) {
			$logo = (int) get_theme_mod( 'custom_logo' );
		}

		if ( $logo <= 0 ) {
			return '<a class="saha-logo saha-logo--text" href="' . $home . '" rel="home">' . esc_html( $name ) . '</a>';
		}

		$attrs = array(
			'class'         => 'saha-logo__img saha-logo__img--desktop',
			'alt'           => $name,
			'loading'       => 'eager',
			'fetchpriority' => 'high',
		);

		$html = wp_get_attachment_image( $logo, 'medium', false, $attrs );

		if ( $mobile > 0 && $mobile !== $logo ) {
			$attrs['class'] = 'saha-logo__img saha-logo__img--mobile';
			unset( $attrs['fetchpriority'] );
			$html          .= wp_get_attachment_image( $mobile, 'medium', false, $attrs );
		}

		return '<a class="saha-logo' . ( $mobile > 0 && $mobile !== $logo ? ' has-mobile' : '' ) . '" href="' . $home . '" rel="home">' . $html . '</a>';
	}
}

if ( ! function_exists( 'saha_theme_copyright' ) ) {
	/**
	 * Dòng bản quyền với {year} và {site}.
	 */
	function saha_theme_copyright(): string {
		$text = (string) saha_theme_option( 'footer.copyright', '© {year} {site}' );

		return strtr(
			$text,
			array(
				'{year}' => wp_date( 'Y' ),
				'{site}' => get_bloginfo( 'name' ),
			)
		);
	}
}
