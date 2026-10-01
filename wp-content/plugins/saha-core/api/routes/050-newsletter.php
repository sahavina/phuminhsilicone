<?php
/**
 * Route: POST /saha/v1/newsletter — đăng ký nhận tin (element Newsletter, SCC 2.7).
 *
 * Cùng lớp bảo vệ với form liên hệ (040-forms.php): rate limit, nonce, honeypot.
 * Lưu thành lead nguồn "newsletter" (một bản ghi mỗi email).
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use Saha\Core\Api;
use Saha\Core\Lead;

return static function ( string $namespace ): void {
	register_rest_route(
		$namespace,
		'/newsletter',
		array(
			'methods'             => 'POST',
			'permission_callback' => Api::public_permission( 'newsletter', 5, 10 * MINUTE_IN_SECONDS ),
			'args'                => saha_api_form_args( array( 'email', 'source_url' ) ),
			'callback'            => static function ( WP_REST_Request $request ): WP_REST_Response {
				$guard = saha_api_form_guard( $request, 'newsletter' );

				if ( $guard ) {
					return $guard;
				}

				$result = Lead::subscribe( (string) $request->get_param( 'email' ), (string) $request->get_param( 'source_url' ) );

				if ( '' !== $result['error'] ) {
					$invalid = ! is_email( sanitize_email( (string) $request->get_param( 'email' ) ) );

					return Api::error( $result['error'], $invalid ? array( 'email' => $result['error'] ) : array(), $invalid ? 422 : 500 );
				}

				return Api::success(
					array( 'duplicate' => $result['duplicate'] ),
					$result['duplicate']
						? __( 'Email này đã đăng ký nhận tin rồi. Cảm ơn bạn!', 'saha-core' )
						: __( 'Đăng ký thành công. Cảm ơn bạn!', 'saha-core' ),
					$result['duplicate'] ? 200 : 201
				);
			},
		)
	);
};
