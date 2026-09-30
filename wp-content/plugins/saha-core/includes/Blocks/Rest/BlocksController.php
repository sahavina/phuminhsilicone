<?php
/**
 * REST: /saha/v1/blocks.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Blocks\Rest;

use Saha\Core\Api;
use Saha\Core\Blocks\PostType;
use Saha\Core\Builder\LayoutService;
use Saha\Core\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * BlocksController.
 *
 * | GET  /blocks | danh sách block (chọn trong element Block)             |
 * | POST /blocks | {title, node} → tạo block đã xuất bản ("Lưu thành block") |
 */
final class BlocksController {

	/**
	 * Đăng ký route.
	 */
	public function registerRoutes(): void {
		register_rest_route(
			Api::NAMESPACE,
			'/blocks',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'permission_callback' => array( $this, 'canUse' ),
					'callback'            => array( $this, 'index' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'permission_callback' => array( $this, 'canCreate' ),
					'callback'            => array( $this, 'create' ),
					'args'                => array(
						'title' => array(
							'type'      => 'string',
							'required'  => true,
							'minLength' => 1,
							'maxLength' => 200,
						),
						'node'  => array(
							'type'     => 'object',
							'required' => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Quyền xem danh sách.
	 */
	public function canUse(): bool {
		return current_user_can( Roles::CAP_BUILDER );
	}

	/**
	 * Quyền tạo block (cần quyền đăng bài của post type).
	 */
	public function canCreate(): bool {
		$type = get_post_type_object( PostType::NAME );

		return $this->canUse() && $type && current_user_can( $type->cap->publish_posts );
	}

	/**
	 * GET /blocks.
	 */
	public function index(): \WP_REST_Response {
		$posts = get_posts(
			array(
				'post_type'      => PostType::NAME,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		return Api::success(
			array_map(
				static fn( \WP_Post $post ): array => array(
					'id'     => $post->ID,
					'title'  => get_the_title( $post ),
					'status' => $post->post_status,
				),
				$posts
			)
		);
	}

	/**
	 * POST /blocks — tạo block từ một element (thường là Section).
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function create( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = wp_insert_post(
			array(
				'post_type'   => PostType::NAME,
				'post_status' => 'publish',
				'post_title'  => sanitize_text_field( (string) $request->get_param( 'title' ) ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return Api::error( $post_id->get_error_message(), array(), 500 );
		}

		$result = LayoutService::save(
			(int) $post_id,
			array(
				'version'  => 1,
				'elements' => array( $request->get_param( 'node' ) ),
			),
			''
		);

		if ( 'saved' !== $result['status'] ) {
			wp_delete_post( (int) $post_id, true );

			return new \WP_REST_Response(
				array(
					'success' => false,
					'code'    => 'validation_failed',
					'message' => __( 'Không tạo được block từ element này.', 'saha-core' ),
					'errors'  => $result['errors'] ?? array(),
				),
				422
			);
		}

		return Api::success(
			array(
				'id'    => (int) $post_id,
				'title' => get_the_title( (int) $post_id ),
			),
			__( 'Đã tạo block.', 'saha-core' ),
			201
		);
	}
}
