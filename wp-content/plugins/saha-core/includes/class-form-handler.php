<?php
/**
 * Form handler: submit không qua JavaScript (admin-post.php).
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Form_Handler.
 *
 * Form báo giá/liên hệ mặc định gửi qua REST bằng quote-form.js. File này là
 * đường dự phòng khi JS lỗi hoặc bị tắt: dùng chung validate + create + rate
 * limit + honeypot, rồi redirect về trang gốc kèm cờ kết quả.
 */
final class Form_Handler {

	/**
	 * Query var mang kết quả về trang gốc.
	 */
	public const RESULT_VAR = 'saha_form';

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'prevent_caching_result_page' ), 0 );

		foreach ( array( 'saha_quote', 'saha_contact' ) as $action ) {
			add_action( 'admin_post_nopriv_' . $action, array( $this, 'handle_' . substr( $action, 5 ) ) );
			add_action( 'admin_post_' . $action, array( $this, 'handle_' . substr( $action, 5 ) ) );
		}
	}

	/**
	 * Trang mang thông báo kết quả (?saha_form=…) không được lưu vào page cache,
	 * nếu không khách sau sẽ thấy thông báo của khách trước (spec §27).
	 */
	public function prevent_caching_result_page(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- chỉ kiểm tra có tham số.
		if ( ! isset( $_GET[ self::RESULT_VAR ] ) ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // Chuẩn chung: LiteSpeed, WP Rocket, W3TC, WP Super Cache.
		}

		do_action( 'litespeed_control_set_nocache', 'saha form result' );

		nocache_headers();
	}

	/**
	 * Xử lý form báo giá.
	 */
	public function handle_quote(): void {
		$input = $this->guard( 'quote' );

		$result = Quote::validate( $input );

		if ( $result['errors'] ) {
			$this->redirect( 'quote_invalid', $input );
		}

		$created = Quote::create( $result['data'] );

		$this->redirect( $created['id'] > 0 ? 'quote_sent' : 'quote_error', $input );
	}

	/**
	 * Xử lý form liên hệ.
	 */
	public function handle_contact(): void {
		$input = $this->guard( 'contact' );

		// Form công khai chỉ được tạo lead với nguồn "phía khách" — giống REST /contact.
		$source          = sanitize_key( (string) ( $input['source'] ?? 'contact' ) );
		$input['source'] = in_array( $source, array( 'contact', 'website', 'product', 'brand', 'landing_page' ), true ) ? $source : 'contact';

		$result = Lead::validate( $input );

		if ( $result['errors'] ) {
			$this->redirect( 'contact_invalid', $input );
		}

		$created = Lead::create( $result['data'] );

		$this->redirect( $created['id'] > 0 ? 'contact_sent' : 'contact_error', $input );
	}

	/**
	 * Rate limit + nonce + honeypot. Dừng request nếu không hợp lệ.
	 *
	 * @param string $scope quote|contact.
	 * @return array<string, mixed> Payload đã unslash.
	 */
	private function guard( string $scope ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify ngay dưới.
		$input = isset( $_POST ) && is_array( $_POST ) ? (array) wp_unslash( $_POST ) : array();

		if ( ! Security::check_rate_limit( $scope, 5, 10 * MINUTE_IN_SECONDS ) ) {
			$this->redirect( $scope . '_limited', $input );
		}

		if ( ! Security::verify_nonce( (string) ( $input['saha_nonce'] ?? '' ) ) ) {
			$this->redirect( $scope . '_expired', $input );
		}

		if ( ! Security::honeypot_passed( $input ) ) {
			Logger::info( 'Chặn submit do honeypot (no-JS).', $scope );
			// Giả thành công để bot không học được.
			$this->redirect( $scope . '_sent', $input );
		}

		return $input;
	}

	/**
	 * Redirect về trang gốc (chỉ cùng domain) kèm cờ kết quả.
	 *
	 * @param string               $result Mã kết quả.
	 * @param array<string, mixed> $input  Payload.
	 * @return never
	 */
	private function redirect( string $result, array $input ): void {
		$target = Quote::sanitize_source_url( (string) ( $input['source_url'] ?? '' ) );

		if ( '' === $target ) {
			$target = home_url( '/' );
		}

		$target = remove_query_arg( self::RESULT_VAR, $target );

		wp_safe_redirect( add_query_arg( self::RESULT_VAR, rawurlencode( $result ), $target ) . '#saha-form-result', 303 );
		exit;
	}

	/**
	 * Thông báo tương ứng mã kết quả — dùng ở theme.
	 *
	 * @param string $code Mã kết quả.
	 * @return array{type: string, message: string}|null
	 */
	public static function message_for( string $code ): ?array {
		$map = array(
			'quote_sent'      => array( 'success', __( 'Đã gửi yêu cầu báo giá. Chúng tôi sẽ liên hệ sớm nhất.', 'saha-core' ) ),
			'contact_sent'    => array( 'success', __( 'Đã gửi liên hệ. Chúng tôi sẽ phản hồi sớm nhất.', 'saha-core' ) ),
			'quote_invalid'   => array( 'error', __( 'Thông tin chưa hợp lệ. Vui lòng kiểm tra họ tên và số điện thoại.', 'saha-core' ) ),
			'contact_invalid' => array( 'error', __( 'Thông tin chưa hợp lệ. Vui lòng kiểm tra họ tên, số điện thoại và nội dung.', 'saha-core' ) ),
			'quote_expired'   => array( 'error', __( 'Phiên làm việc đã hết hạn, vui lòng thử lại.', 'saha-core' ) ),
			'contact_expired' => array( 'error', __( 'Phiên làm việc đã hết hạn, vui lòng thử lại.', 'saha-core' ) ),
			'quote_limited'   => array( 'error', __( 'Bạn gửi quá nhanh, vui lòng thử lại sau ít phút.', 'saha-core' ) ),
			'contact_limited' => array( 'error', __( 'Bạn gửi quá nhanh, vui lòng thử lại sau ít phút.', 'saha-core' ) ),
			'quote_error'     => array( 'error', __( 'Không gửi được yêu cầu. Vui lòng gọi hotline để được hỗ trợ ngay.', 'saha-core' ) ),
			'contact_error'   => array( 'error', __( 'Không gửi được liên hệ. Vui lòng gọi hotline để được hỗ trợ ngay.', 'saha-core' ) ),
		);

		if ( ! isset( $map[ $code ] ) ) {
			return null;
		}

		return array(
			'type'    => $map[ $code ][0],
			'message' => $map[ $code ][1],
		);
	}
}
