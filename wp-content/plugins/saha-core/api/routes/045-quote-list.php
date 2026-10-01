<?php
/**
 * Route: POST /saha/v1/quote/list — gửi danh sách báo giá nhiều sản phẩm (SCC 2.5, spec §102).
 *
 * Cùng các lớp bảo vệ với POST /quote (040-forms.php): rate limit, nonce, honeypot, validate ở
 * Quote, chống gửi trùng. Danh sách nằm ở trình duyệt khách (localStorage) cho tới lúc gửi;
 * server chỉ tin product_id / variation_id / số lượng / ghi chú — tên, SKU đọc lại từ database.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use Saha\Core\Api;
use Saha\Core\Quote;

return static function ( string $namespace ): void {
	register_rest_route(
		$namespace,
		'/quote/list',
		array(
			'methods'             => 'POST',
			'permission_callback' => Api::public_permission( 'quote_list', 5, 10 * MINUTE_IN_SECONDS ),
			'args'                => array_merge(
				saha_api_form_args( array( 'name', 'phone', 'email', 'company', 'message', 'source_url' ) ),
				array(
					'items' => array(
						'type'     => 'array',
						'required' => true,
						'maxItems' => Quote::MAX_ITEMS,
						'items'    => array(
							'type'       => 'object',
							'properties' => array(
								'product_id'   => array( 'type' => 'integer' ),
								'variation_id' => array( 'type' => 'integer' ),
								'quantity'     => array( 'type' => 'integer' ),
								'note'         => array( 'type' => 'string' ),
							),
						),
					),
				)
			),
			'callback'            => static function ( WP_REST_Request $request ): WP_REST_Response {
				$guard = saha_api_form_guard( $request, 'quote' );

				if ( $guard ) {
					return $guard;
				}

				$params = (array) $request->get_params();

				// Sản phẩm nằm trong danh sách — bỏ product_id lẻ nếu client gửi kèm.
				unset( $params['product_id'], $params['quantity'] );

				$customer = Quote::validate( $params );
				$list     = Quote::validate_items( $request->get_param( 'items' ) );
				$errors   = array_merge( $customer['errors'], $list['errors'] );

				if ( $errors ) {
					$response = Api::error( __( 'Vui lòng kiểm tra lại thông tin.', 'saha-core' ), $errors, 422 );
					$data     = (array) $response->get_data();

					// Client đánh dấu / gợi ý xoá các sản phẩm không còn bán.
					$data['data']            = (array) ( $data['data'] ?? array() );
					$data['data']['invalid'] = $list['invalid'];
					$response->set_data( $data );

					return $response;
				}

				$created = Quote::create( $customer['data'], $list['items'] );

				if ( $created['id'] <= 0 ) {
					return Api::error(
						__( 'Không gửi được yêu cầu lúc này. Vui lòng gọi hotline để được hỗ trợ ngay.', 'saha-core' ),
						array(),
						500
					);
				}

				return Api::success(
					array(
						'duplicate' => $created['duplicate'],
						'count'     => count( $list['items'] ),
					),
					$created['duplicate']
						? __( 'Chúng tôi đã nhận danh sách này trước đó và sẽ liên hệ sớm.', 'saha-core' )
						: __( 'Đã gửi danh sách báo giá. Chúng tôi sẽ liên hệ sớm nhất.', 'saha-core' ),
					$created['duplicate'] ? 200 : 201
				);
			},
		)
	);
};
