<?php
/**
 * Route: GET /saha/v1/nonce
 *
 * Trả nonce `wp_rest` mới. Cần thiết vì trang có thể được phục vụ từ page
 * cache (LiteSpeed/Cloudflare — spec §27) với nonce đã hết hạn; JS sẽ gọi
 * endpoint này rồi thử lại request một lần.
 *
 * Response gửi kèm header no-cache để chính endpoint này không bị cache.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use Saha\Core\Api;

return static function ( string $namespace ): void {
	register_rest_route(
		$namespace,
		'/nonce',
		array(
			'methods'             => 'GET',
			'permission_callback' => Api::public_permission( 'nonce', 20, MINUTE_IN_SECONDS ),
			'callback'            => static function (): WP_REST_Response {
				$response = Api::success( array( 'nonce' => wp_create_nonce( 'wp_rest' ) ) );

				foreach ( wp_get_nocache_headers() as $name => $value ) {
					$response->header( $name, (string) $value );
				}

				return $response;
			},
		)
	);
};
