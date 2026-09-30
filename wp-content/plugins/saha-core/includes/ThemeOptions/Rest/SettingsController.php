<?php
/**
 * REST: /saha/v1/settings — Theme Options.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\ThemeOptions\Rest;

use Saha\Core\Api;
use Saha\Core\Performance\CssFileStore;
use Saha\Core\ThemeOptions\Module;
use Saha\Core\ThemeOptions\Repository;
use Saha\Core\ThemeOptions\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * SettingsController.
 *
 * GET  → schema + giá trị (ứng dụng admin tự dựng form từ schema).
 * POST → sanitize theo schema, lưu, sinh lại CSS.
 *
 * Quyền: `edit_theme_options` — cùng quyền với Appearance → Customize.
 * Nonce `wp_rest` do cookie auth của REST kiểm; thiếu nonce thì user = 0 →
 * permission_callback trả false (nonce không phải cơ chế phân quyền).
 */
final class SettingsController {

	/**
	 * Đăng ký route.
	 */
	public function registerRoutes(): void {
		register_rest_route(
			Api::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'permission_callback' => array( $this, 'canManage' ),
					'callback'            => array( $this, 'show' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'permission_callback' => array( $this, 'canManage' ),
					'callback'            => array( $this, 'update' ),
					'args'                => array(
						'values' => array(
							'type'     => 'object',
							'required' => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Quyền.
	 */
	public function canManage(): bool {
		return current_user_can( 'edit_theme_options' );
	}

	/**
	 * GET /settings.
	 */
	public function show(): \WP_REST_Response {
		return Api::success(
			array(
				'schema' => Schema::forClient(),
				'values' => Repository::all(),
			)
		);
	}

	/**
	 * POST /settings.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function update( \WP_REST_Request $request ): \WP_REST_Response {
		$values = $request->get_param( 'values' );

		if ( ! is_array( $values ) ) {
			return Api::error( __( 'Dữ liệu không hợp lệ.', 'saha-core' ), array(), 400 );
		}

		$result = Repository::save( $values );

		if ( $result['errors'] ) {
			// Field hợp lệ ĐÃ được lưu; chỉ field lỗi giữ giá trị cũ.
			return new \WP_REST_Response(
				array(
					'success' => false,
					'code'    => 'validation_failed',
					'message' => __( 'Một số giá trị không hợp lệ và chưa được lưu.', 'saha-core' ),
					'errors'  => $result['errors'],
					'data'    => array( 'values' => $result['values'] ),
				),
				422
			);
		}

		$state = get_option( Module::CSS_OPTION, array() );

		return Api::success(
			array(
				'values' => $result['values'],
				'cssUrl' => is_array( $state ) && ! empty( $state['file'] ) ? CssFileStore::url( (string) $state['file'] ) : '',
			),
			$result['changed'] ? __( 'Đã lưu Theme Options.', 'saha-core' ) : __( 'Không có thay đổi.', 'saha-core' )
		);
	}
}
