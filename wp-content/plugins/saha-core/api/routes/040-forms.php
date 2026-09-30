<?php
/**
 * Route: POST /saha/v1/quote, POST /saha/v1/contact
 *
 * Bảo vệ nhiều lớp (spec §11, §29, §30, §84, §92):
 *   1. permission_callback: rate limit theo hash(IP + salt)
 *   2. nonce trong header X-WP-Nonce (wp_rest) HOẶC field saha_nonce
 *   3. honeypot
 *   4. validate + sanitize ở Quote/Lead
 *   5. chống trùng ở tầng repository
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use Saha\Core\Api;
use Saha\Core\Lead;
use Saha\Core\Logger;
use Saha\Core\Quote;
use Saha\Core\Security;

if ( ! function_exists( 'saha_api_form_guard' ) ) {
	/**
	 * Kiểm tra nonce + honeypot cho form công khai.
	 *
	 * Nonce ở đây chống CSRF, KHÔNG phải authorization (spec §93) — endpoint
	 * này công khai có chủ đích.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $scope   quote|contact (dùng cho log).
	 * @return WP_REST_Response|null Response lỗi, hoặc null nếu hợp lệ.
	 */
	function saha_api_form_guard( WP_REST_Request $request, string $scope ): ?WP_REST_Response {
		$header_nonce = (string) $request->get_header( 'X-WP-Nonce' );
		$field_nonce  = (string) $request->get_param( 'saha_nonce' );

		$valid_nonce = ( '' !== $header_nonce && wp_verify_nonce( $header_nonce, 'wp_rest' ) )
			|| Security::verify_nonce( $field_nonce );

		if ( ! $valid_nonce ) {
			return Api::error(
				__( 'Phiên làm việc đã hết hạn, vui lòng tải lại trang và thử lại.', 'saha-core' ),
				array(),
				403
			);
		}

		$params = (array) $request->get_params();

		if ( ! Security::honeypot_passed( $params ) ) {
			Logger::info( 'Chặn submit do honeypot.', $scope );

			// Trả về thành công giả để bot không học được cách vượt qua.
			return Api::success( array(), __( 'Đã gửi yêu cầu.', 'saha-core' ), 200 );
		}

		return null;
	}
}

if ( ! function_exists( 'saha_api_form_args' ) ) {
	/**
	 * Khai báo args chung. Sanitize thật sự nằm ở Quote::validate / Lead::validate.
	 *
	 * @param string[] $fields Tên field.
	 * @return array<string, array<string, mixed>>
	 */
	function saha_api_form_args( array $fields ): array {
		$args = array();

		foreach ( $fields as $field ) {
			$args[ $field ] = array(
				'type'     => 'product_id' === $field ? 'integer' : 'string',
				'required' => false,
			);
		}

		return $args;
	}
}

return static function ( string $namespace ): void {
	register_rest_route(
		$namespace,
		'/quote',
		array(
			'methods'             => 'POST',
			'permission_callback' => Api::public_permission( 'quote', 5, 10 * MINUTE_IN_SECONDS ),
			'args'                => saha_api_form_args(
				array( 'name', 'phone', 'email', 'company', 'product_id', 'quantity', 'message', 'source_url' )
			),
			'callback'            => static function ( WP_REST_Request $request ): WP_REST_Response {
				$guard = saha_api_form_guard( $request, 'quote' );

				if ( $guard ) {
					return $guard;
				}

				$result = Quote::validate( (array) $request->get_params() );

				if ( $result['errors'] ) {
					return Api::error(
						__( 'Vui lòng kiểm tra lại thông tin.', 'saha-core' ),
						$result['errors'],
						422
					);
				}

				$created = Quote::create( $result['data'] );

				if ( $created['id'] <= 0 ) {
					return Api::error(
						__( 'Không gửi được yêu cầu lúc này. Vui lòng gọi hotline để được hỗ trợ ngay.', 'saha-core' ),
						array(),
						500
					);
				}

				return Api::success(
					array( 'duplicate' => $created['duplicate'] ),
					$created['duplicate']
						? __( 'Chúng tôi đã nhận yêu cầu của bạn trước đó và sẽ liên hệ sớm.', 'saha-core' )
						: __( 'Đã gửi yêu cầu báo giá. Chúng tôi sẽ liên hệ sớm nhất.', 'saha-core' ),
					$created['duplicate'] ? 200 : 201
				);
			},
		)
	);

	register_rest_route(
		$namespace,
		'/contact',
		array(
			'methods'             => 'POST',
			'permission_callback' => Api::public_permission( 'contact', 5, 10 * MINUTE_IN_SECONDS ),
			'args'                => saha_api_form_args(
				array( 'name', 'phone', 'email', 'company', 'message', 'source', 'source_url' )
			),
			'callback'            => static function ( WP_REST_Request $request ): WP_REST_Response {
				$guard = saha_api_form_guard( $request, 'contact' );

				if ( $guard ) {
					return $guard;
				}

				$params = (array) $request->get_params();

				// Form liên hệ công khai chỉ được tạo lead với nguồn "phía khách".
				$source = sanitize_key( (string) ( $params['source'] ?? 'contact' ) );

				if ( ! in_array( $source, array( 'contact', 'website', 'product', 'brand', 'landing_page' ), true ) ) {
					$params['source'] = 'contact';
				}

				$result = Lead::validate( $params );

				if ( $result['errors'] ) {
					return Api::error(
						__( 'Vui lòng kiểm tra lại thông tin.', 'saha-core' ),
						$result['errors'],
						422
					);
				}

				$created = Lead::create( $result['data'] );

				if ( $created['id'] <= 0 ) {
					return Api::error(
						__( 'Không gửi được liên hệ lúc này. Vui lòng gọi hotline để được hỗ trợ ngay.', 'saha-core' ),
						array(),
						500
					);
				}

				return Api::success(
					array( 'duplicate' => $created['duplicate'] ),
					__( 'Đã gửi liên hệ. Chúng tôi sẽ phản hồi sớm nhất.', 'saha-core' ),
					$created['duplicate'] ? 200 : 201
				);
			},
		)
	);
};
