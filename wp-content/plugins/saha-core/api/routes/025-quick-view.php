<?php
/**
 * Route: GET /saha/v1/products/{id}/quick-view — HTML hộp Xem nhanh.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use Saha\Core\Api;
use Saha\Core\WooCommerce\QuickView;

return static function ( string $namespace ): void {
	register_rest_route(
		$namespace,
		'/products/(?P<id>\d+)/quick-view',
		array(
			'methods'             => 'GET',
			'permission_callback' => Api::public_permission( 'quick_view', 60, MINUTE_IN_SECONDS ),
			'args'                => array(
				'id' => array(
					'type'              => 'integer',
					'required'          => true,
					'sanitize_callback' => 'absint',
				),
			),
			'callback'            => static function ( WP_REST_Request $request ): WP_REST_Response {
				if ( ! function_exists( 'wc_get_product' ) || ! QuickView::enabled() ) {
					return Api::error( __( 'Xem nhanh chưa được bật.', 'saha-core' ), array(), 404 );
				}

				$html = QuickView::html( absint( $request->get_param( 'id' ) ) );

				if ( null === $html ) {
					return Api::error( __( 'Không tìm thấy sản phẩm.', 'saha-core' ), array(), 404 );
				}

				$response = Api::success( array( 'html' => $html ) );
				$response->header( 'Cache-Control', 'no-store' );

				return $response;
			},
		)
	);
};
