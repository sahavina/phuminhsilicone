<?php
/**
 * Public helper API — đây là interface duy nhất child theme được dùng.
 *
 * Mọi hàm phải an toàn khi gọi sớm và không throw.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'saha_get_setting' ) ) {
	/**
	 * Lấy một setting toàn cục.
	 *
	 * @param string $key      Key setting.
	 * @param mixed  $fallback Giá trị mặc định.
	 * @return mixed
	 */
	function saha_get_setting( string $key, $fallback = '' ) {
		return Saha\Core\Settings::get( $key, $fallback );
	}
}

if ( ! function_exists( 'saha_hotline' ) ) {
	/**
	 * Hotline theo vùng.
	 *
	 * @param string $region north|south.
	 */
	function saha_hotline( string $region = 'north' ): string {
		$key = 'south' === $region ? 'hotline_south' : 'hotline_north';

		return (string) saha_get_setting( $key );
	}
}

if ( ! function_exists( 'saha_tel_href' ) ) {
	/**
	 * Sinh href tel: an toàn từ số hiển thị.
	 *
	 * @param string $phone Số hiển thị.
	 */
	function saha_tel_href( string $phone ): string {
		$digits = Saha\Core\Security::tel_digits( $phone );

		return '' === $digits ? '' : 'tel:' . $digits;
	}
}

if ( ! function_exists( 'saha_zalo_url' ) ) {
	/**
	 * Link Zalo sinh từ setting, không hardcode.
	 */
	function saha_zalo_url(): string {
		$digits = Saha\Core\Security::tel_digits( (string) saha_get_setting( 'zalo_phone' ) );

		if ( '' === $digits ) {
			return '';
		}

		return 'https://zalo.me/' . ltrim( $digits, '+' );
	}
}

if ( ! function_exists( 'saha_is_catalogue_mode' ) ) {
	/**
	 * Website đang chạy chế độ catalogue (ẩn giá/giỏ hàng)?
	 */
	function saha_is_catalogue_mode(): bool {
		return (bool) saha_get_setting( 'catalogue_mode', true );
	}
}

if ( ! function_exists( 'saha_cta_label' ) ) {
	/**
	 * Nhãn CTA mặc định.
	 */
	function saha_cta_label(): string {
		$label = (string) saha_get_setting( 'default_cta', 'Yêu cầu báo giá' );

		return '' !== $label ? $label : __( 'Yêu cầu báo giá', 'saha-core' );
	}
}

if ( ! function_exists( 'saha_quote_recipient' ) ) {
	/**
	 * Email nhận yêu cầu báo giá, fallback về admin email.
	 */
	function saha_quote_recipient(): string {
		$email = (string) saha_get_setting( 'quote_email' );

		if ( '' !== $email && is_email( $email ) ) {
			return $email;
		}

		return (string) get_option( 'admin_email' );
	}
}

if ( ! function_exists( 'saha_log' ) ) {
	/**
	 * Ghi log qua logger abstraction.
	 *
	 * @param string               $message Nội dung.
	 * @param string               $level   debug|info|warning|error.
	 * @param string               $channel Kênh.
	 * @param array<string, mixed> $context Context (đã tự động redact key nhạy cảm).
	 */
	function saha_log( string $message, string $level = 'info', string $channel = 'core', array $context = array() ): void {
		Saha\Core\Logger::log( $message, $level, $channel, $context );
	}
}

if ( ! function_exists( 'saha_public_nonce' ) ) {
	/**
	 * Nonce dùng cho form/REST frontend.
	 */
	function saha_public_nonce(): string {
		return wp_create_nonce( Saha\Core\Security::PUBLIC_NONCE_ACTION );
	}
}

if ( ! function_exists( 'saha_honeypot_field' ) ) {
	/**
	 * Render field honeypot cho form.
	 */
	function saha_honeypot_field(): string {
		return sprintf(
			'<p class="saha-hp" aria-hidden="true"><label>%1$s<input type="text" name="%2$s" value="" tabindex="-1" autocomplete="off"></label></p>',
			esc_html__( 'Để trống ô này', 'saha-core' ),
			esc_attr( Saha\Core\Security::HONEYPOT_FIELD )
		);
	}
}

if ( ! function_exists( 'saha_api_url' ) ) {
	/**
	 * URL REST của plugin, không hardcode domain.
	 *
	 * @param string $path Đường dẫn sau namespace, ví dụ `search`.
	 */
	function saha_api_url( string $path = '' ): string {
		return rest_url( 'saha/v1/' . ltrim( $path, '/' ) );
	}
}
