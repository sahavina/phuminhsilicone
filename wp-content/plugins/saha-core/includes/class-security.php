<?php
/**
 * Security helpers: sanitize, nonce, capability, rate limit, honeypot.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Security: mọi input đi qua đây trước khi tới business logic.
 *
 * Nguyên tắc: nonce KHÔNG phải authorization (spec §93) — luôn kết hợp capability
 * khi action cần quyền.
 */
final class Security {

	/**
	 * Nonce action cho form frontend.
	 */
	public const PUBLIC_NONCE_ACTION = 'saha_public_form';

	/**
	 * Tên field honeypot.
	 */
	public const HONEYPOT_FIELD = 'saha_hp_email';

	/**
	 * Gắn hook bảo vệ upload.
	 */
	public function register(): void {
		add_filter( 'upload_mimes', array( $this, 'filter_upload_mimes' ), 99 );
	}

	/**
	 * Loại bỏ mime type thực thi được, dựa trên whitelist của WordPress.
	 *
	 * @param array<string, string> $mimes Mime hiện tại.
	 * @return array<string, string>
	 */
	public function filter_upload_mimes( array $mimes ): array {
		$blocked = array( 'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar', 'exe', 'js', 'jsp', 'asp', 'aspx', 'sh', 'bat', 'cgi', 'pl', 'htaccess' );

		foreach ( $mimes as $ext => $mime ) {
			foreach ( explode( '|', (string) $ext ) as $single ) {
				if ( in_array( strtolower( $single ), $blocked, true ) ) {
					unset( $mimes[ $ext ] );
					break;
				}
			}
		}

		return $mimes;
	}

	/**
	 * Sanitize theo type khai báo trong schema.
	 *
	 * @param mixed  $value Giá trị thô.
	 * @param string $type  text|textarea|html|email|url|phone|int|float|bool|key.
	 * @return mixed
	 */
	public static function sanitize_by_type( $value, string $type ) {
		switch ( $type ) {
			case 'bool':
				return (bool) $value;

			case 'int':
				return (int) $value;

			case 'float':
				return (float) $value;

			case 'email':
				$email = sanitize_email( (string) $value );
				return is_email( $email ) ? $email : '';

			case 'url':
				return esc_url_raw( trim( (string) $value ) );

			case 'phone':
				return self::sanitize_phone( (string) $value );

			case 'textarea':
				return sanitize_textarea_field( (string) $value );

			case 'html':
				return wp_kses_post( (string) $value );

			case 'key':
				return sanitize_key( (string) $value );

			case 'text':
			default:
				return sanitize_text_field( (string) $value );
		}
	}

	/**
	 * Sanitize số điện thoại — giữ định dạng người dùng nhập, không validate quá chặt.
	 *
	 * @param string $phone Số thô.
	 */
	public static function sanitize_phone( string $phone ): string {
		$phone = wp_strip_all_tags( $phone );
		$phone = preg_replace( '/[^0-9+().\-\s]/', '', $phone ) ?? '';

		return trim( mb_substr( $phone, 0, 32 ) );
	}

	/**
	 * Chỉ giữ ký tự hợp lệ cho href="tel:".
	 *
	 * @param string $phone Số hiển thị.
	 */
	public static function tel_digits( string $phone ): string {
		$digits = preg_replace( '/[^0-9+]/', '', $phone ) ?? '';

		return $digits;
	}

	/**
	 * Kiểm tra nonce của form/REST frontend.
	 *
	 * @param string|null $nonce  Nonce nhận được.
	 * @param string      $action Action.
	 */
	public static function verify_nonce( ?string $nonce, string $action = self::PUBLIC_NONCE_ACTION ): bool {
		if ( null === $nonce || '' === $nonce ) {
			return false;
		}

		return (bool) wp_verify_nonce( $nonce, $action );
	}

	/**
	 * Honeypot: field ẩn phải rỗng.
	 *
	 * @param array<string, mixed> $data Payload.
	 */
	public static function honeypot_passed( array $data ): bool {
		return '' === trim( (string) ( $data[ self::HONEYPOT_FIELD ] ?? '' ) );
	}

	/**
	 * Khoá định danh client — hash IP + salt, không lưu IP thô.
	 *
	 * @param string $scope Phạm vi rate limit.
	 */
	public static function client_key( string $scope ): string {
		$raw = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		$ip  = filter_var( $raw, FILTER_VALIDATE_IP );

		if ( false === $ip ) {
			$ip = 'unknown';
		}

		return 'saha_rl_' . md5( $scope . '|' . $ip . '|' . wp_salt( 'nonce' ) );
	}

	/**
	 * Rate limit đơn giản bằng transient.
	 *
	 * @param string $scope   Phạm vi, ví dụ `quote`.
	 * @param int    $limit   Số request tối đa.
	 * @param int    $window  Cửa sổ thời gian (giây).
	 * @return bool True nếu còn quota, false nếu vượt.
	 */
	public static function check_rate_limit( string $scope, int $limit, int $window ): bool {
		/**
		 * Cho phép tắt hoặc thay đổi rate limit.
		 *
		 * @param int    $limit Số request.
		 * @param string $scope Phạm vi.
		 */
		$limit = (int) apply_filters( 'saha_rate_limit', $limit, $scope );

		if ( $limit <= 0 ) {
			return true;
		}

		$key   = self::client_key( $scope );
		$count = (int) get_transient( $key );

		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $key, $count + 1, $window );

		return true;
	}

	/**
	 * Bảo vệ admin action: capability + nonce.
	 *
	 * @param string $capability Capability yêu cầu.
	 * @param string $nonce_action Nonce action.
	 * @param string $nonce_field  Tên field chứa nonce.
	 * @return bool
	 */
	public static function guard_admin_action( string $capability, string $nonce_action, string $nonce_field = '_wpnonce' ): bool {
		if ( ! current_user_can( $capability ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- đang verify ngay dưới.
		$nonce = isset( $_REQUEST[ $nonce_field ] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST[ $nonce_field ] ) ) : '';

		return (bool) wp_verify_nonce( $nonce, $nonce_action );
	}
}
