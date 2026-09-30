<?php
/**
 * REST API bootstrap.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Api: đăng ký namespace saha/v1 và nạp route từ api/routes/.
 *
 * Mọi route phải có permission_callback (spec §29). Response dùng envelope
 * thống nhất và HTTP status đúng (spec §31).
 */
final class Api {

	/**
	 * Namespace REST.
	 */
	public const NAMESPACE = 'saha/v1';

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Nạp toàn bộ file route.
	 */
	public function register_routes(): void {
		foreach ( $this->route_files() as $file ) {
			$route = require $file;

			if ( is_callable( $route ) ) {
				$route( self::NAMESPACE );
			}
		}

		/**
		 * Điểm cắm để add-on đăng ký thêm route.
		 *
		 * @param string $namespace Namespace REST.
		 */
		do_action( 'saha_core_register_routes', self::NAMESPACE );
	}

	/**
	 * Danh sách file route.
	 *
	 * @return string[]
	 */
	private function route_files(): array {
		$files = glob( SAHA_CORE_PATH . 'api/routes/*.php' );

		if ( ! $files ) {
			return array();
		}

		sort( $files, SORT_NATURAL );

		return $files;
	}

	/*
	 * ---------------------------------------------------------------------
	 * Response helper
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Response thành công.
	 *
	 * @param mixed  $data    Payload.
	 * @param string $message Thông báo.
	 * @param int    $status  HTTP status.
	 */
	public static function success( $data = array(), string $message = '', int $status = 200 ): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => $message,
				'data'    => $data,
			),
			$status
		);
	}

	/**
	 * Response lỗi.
	 *
	 * @param string               $message Thông báo.
	 * @param array<string, mixed> $errors  Lỗi theo field.
	 * @param int                  $status  HTTP status.
	 */
	public static function error( string $message, array $errors = array(), int $status = 400 ): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'success' => false,
				'message' => $message,
				'errors'  => $errors,
			),
			$status
		);
	}

	/**
	 * Response vượt rate limit.
	 */
	public static function too_many_requests(): \WP_REST_Response {
		return self::error(
			__( 'Bạn thao tác quá nhanh, vui lòng thử lại sau ít phút.', 'saha-core' ),
			array(),
			429
		);
	}

	/**
	 * permission_callback cho endpoint công khai, có rate limit.
	 *
	 * Trả về true/WP_Error — WP_Error sẽ thành response 429.
	 *
	 * @param string $scope  Phạm vi rate limit.
	 * @param int    $limit  Số request.
	 * @param int    $window Cửa sổ (giây).
	 * @return callable
	 */
	public static function public_permission( string $scope, int $limit = 60, int $window = MINUTE_IN_SECONDS ): callable {
		return static function () use ( $scope, $limit, $window ) {
			if ( Security::check_rate_limit( $scope, $limit, $window ) ) {
				return true;
			}

			return new \WP_Error(
				'saha_rate_limited',
				__( 'Bạn thao tác quá nhanh, vui lòng thử lại sau ít phút.', 'saha-core' ),
				array( 'status' => 429 )
			);
		};
	}

	/**
	 * Tham số phân trang dùng chung (spec §79).
	 *
	 * @param int $max_per_page Giới hạn trên.
	 * @return array<string, array<string, mixed>>
	 */
	public static function pagination_args( int $max_per_page = 50 ): array {
		return array(
			'page'     => array(
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page' => array(
				'type'              => 'integer',
				'default'           => 12,
				'minimum'           => 1,
				'maximum'           => $max_per_page,
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Chuẩn hoá page/per_page từ request.
	 *
	 * @param \WP_REST_Request $request      Request.
	 * @param int              $max_per_page Giới hạn trên.
	 * @return array{page: int, per_page: int}
	 */
	public static function pagination( \WP_REST_Request $request, int $max_per_page = 50 ): array {
		return array(
			'page'     => max( 1, (int) $request->get_param( 'page' ) ),
			'per_page' => max( 1, min( $max_per_page, (int) $request->get_param( 'per_page' ) ) ),
		);
	}
}
