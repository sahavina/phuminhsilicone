<?php
/**
 * REST: /saha/v1/builder/*.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Rest;

use Saha\Core\Api;
use Saha\Core\Builder\CssGenerator;
use Saha\Core\Builder\ElementRegistry;
use Saha\Core\Builder\LayoutRepository;
use Saha\Core\Builder\LayoutService;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Renderer;
use Saha\Core\Builder\Sanitizer;
use Saha\Core\Performance\CssFileStore;
use Saha\Core\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * BuilderController (TECHNICAL-DESIGN §7).
 *
 * | GET  /builder/elements   | định nghĩa element + control                     |
 * | GET  /builder/{id}       | layout, hash, khoá chỉnh sửa                     |
 * | POST /builder/save       | {postId, data, baseHash, enabled} → lưu           |
 * | POST /builder/render     | {postId?, node} → HTML + CSS một node (canvas)   |
 * | POST /builder/lock/{id}  | giữ khoá chỉnh sửa                               |
 *
 * Quyền: `edit_saha_builder`, thêm `edit_post` khi thao tác trên một post.
 */
final class BuilderController {

	/**
	 * Đăng ký route.
	 */
	public function registerRoutes(): void {
		$id_arg = array(
			'type'     => 'integer',
			'minimum'  => 1,
			'required' => true,
		);

		register_rest_route(
			Api::NAMESPACE,
			'/builder/elements',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => array( $this, 'canUseBuilder' ),
				'callback'            => array( $this, 'elements' ),
			)
		);

		register_rest_route(
			Api::NAMESPACE,
			'/builder/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => array( $this, 'canEditPost' ),
				'callback'            => array( $this, 'show' ),
				'args'                => array( 'id' => $id_arg ),
			)
		);

		register_rest_route(
			Api::NAMESPACE,
			'/builder/save',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'permission_callback' => array( $this, 'canEditPost' ),
				'callback'            => array( $this, 'save' ),
				'args'                => array(
					'postId'   => $id_arg,
					'data'     => array(
						'type'     => 'object',
						'required' => true,
					),
					'baseHash' => array(
						'type'    => 'string',
						'default' => '',
					),
					'enabled'  => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
			)
		);

		register_rest_route(
			Api::NAMESPACE,
			'/builder/render',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'permission_callback' => array( $this, 'canRender' ),
				'callback'            => array( $this, 'render' ),
				'args'                => array(
					'postId' => array(
						'type'    => 'integer',
						'minimum' => 0,
						'default' => 0,
					),
					'node'   => array(
						'type'     => 'object',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			Api::NAMESPACE,
			'/builder/lock/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'permission_callback' => array( $this, 'canEditPost' ),
				'callback'            => array( $this, 'lock' ),
				'args'                => array( 'id' => $id_arg ),
			)
		);
	}

	/**
	 * Quyền dùng builder.
	 */
	public function canUseBuilder(): bool {
		return current_user_can( Roles::CAP_BUILDER );
	}

	/**
	 * Quyền dùng builder + sửa post cụ thể.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function canEditPost( \WP_REST_Request $request ): bool {
		$post_id = self::postId( $request );

		return $this->canUseBuilder() && $post_id > 0 && current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Quyền render: có postId thì cần quyền sửa post đó.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function canRender( \WP_REST_Request $request ): bool {
		$post_id = self::postId( $request );

		return $this->canUseBuilder() && ( 0 === $post_id || current_user_can( 'edit_post', $post_id ) );
	}

	/**
	 * GET /builder/elements.
	 */
	public function elements(): \WP_REST_Response {
		return Api::success( ElementRegistry::instance()->forClient() );
	}

	/**
	 * GET /builder/{id}.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function show( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = self::postId( $request );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return Api::error( __( 'Không tìm thấy trang.', 'saha-core' ), array(), 404 );
		}

		if ( ! LayoutRepository::supports( $post_id ) ) {
			return Api::error( __( 'Loại nội dung này chưa dùng được builder.', 'saha-core' ), array(), 400, 'unsupported' );
		}

		$locker = LayoutService::lockedBy( $post_id );

		return Api::success(
			array(
				'postId'     => $post_id,
				'postType'   => $post->post_type,
				'title'      => get_the_title( $post ),
				'status'     => $post->post_status,
				'enabled'    => LayoutRepository::isEnabled( $post_id ),
				'document'   => LayoutRepository::raw( $post_id ) ?? LayoutService::emptyDocument()->toArray(),
				'hash'       => LayoutRepository::hash( $post_id ),
				'lockedBy'   => '' !== $locker ? $locker : null,
				// Block không có trang riêng để xem.
				'permalink'  => is_post_type_viewable( $post->post_type ) ? get_permalink( $post ) : '',
				'previewUrl' => is_post_type_viewable( $post->post_type ) ? get_preview_post_link( $post ) : '',
			)
		);
	}

	/**
	 * POST /builder/save.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function save( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = self::postId( $request );

		if ( ! get_post( $post_id ) ) {
			return Api::error( __( 'Không tìm thấy trang.', 'saha-core' ), array(), 404 );
		}

		$result = LayoutService::save(
			$post_id,
			$request->get_param( 'data' ),
			(string) $request->get_param( 'baseHash' ),
			(bool) $request->get_param( 'enabled' )
		);

		switch ( $result['status'] ) {
			case 'unsupported':
				return Api::error( __( 'Loại nội dung này chưa dùng được builder.', 'saha-core' ), array(), 400, 'unsupported' );

			case 'locked':
				return self::response(
					409,
					'locked',
					/* translators: %s: tên người dùng */
					sprintf( __( '%s đang chỉnh sửa trang này.', 'saha-core' ), (string) $result['lockedBy'] ),
					array( 'lockedBy' => $result['lockedBy'] )
				);

			case 'conflict':
				return self::response(
					409,
					'conflict',
					__( 'Trang vừa được người khác lưu. Tải lại để xem bản mới trước khi lưu.', 'saha-core' ),
					array( 'hash' => $result['hash'] )
				);

			case 'invalid':
				return self::response( 422, 'validation_failed', __( 'Layout có lỗi, chưa được lưu.', 'saha-core' ), array(), (array) $result['errors'] );
		}

		$state = LayoutRepository::cssState( $post_id );

		return Api::success(
			array(
				'hash'     => $result['hash'],
				'document' => $result['document']->toArray(),
				'cssUrl'   => ! empty( $state['file'] ) ? CssFileStore::url( (string) $state['file'] ) : '',
			),
			__( 'Đã lưu.', 'saha-core' )
		);
	}

	/**
	 * POST /builder/render — HTML + CSS một node cho canvas (element động).
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function render( \WP_REST_Request $request ): \WP_REST_Response {
		$result = ( new Sanitizer() )->standalone( $request->get_param( 'node' ) );

		if ( null === $result['node'] ) {
			return self::response( 422, 'validation_failed', __( 'Element có lỗi.', 'saha-core' ), array(), $result['errors'] );
		}

		$ctx = new RenderContext( self::postId( $request ), true, false );
		$css = ( new CssGenerator() )->node( $result['node'] );

		// Block dùng chung trong node: kèm CSS của block (frontend nạp từ file riêng của block).
		foreach ( LayoutService::referencedBlocks( new \Saha\Core\Builder\Schema\Document( array( $result['node'] ) ) ) as $block_id ) {
			$css .= "\n" . LayoutService::inlineCss( $block_id );
		}

		return Api::success(
			array(
				'html'   => ( new Renderer() )->node( $result['node'], $ctx ),
				'css'    => $css,
				'assets' => $ctx->assets,
			)
		);
	}

	/**
	 * POST /builder/lock/{id}.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function lock( \WP_REST_Request $request ): \WP_REST_Response {
		$locker = LayoutService::lock( self::postId( $request ) );

		if ( '' !== $locker ) {
			return self::response(
				409,
				'locked',
				/* translators: %s: tên người dùng */
				sprintf( __( '%s đang chỉnh sửa trang này.', 'saha-core' ), $locker ),
				array( 'lockedBy' => $locker )
			);
		}

		return Api::success( array( 'locked' => true ) );
	}

	/**
	 * Post ID từ URL (`id`) hoặc body (`postId`).
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	private static function postId( \WP_REST_Request $request ): int {
		$id = $request->get_param( 'id' ) ?? $request->get_param( 'postId' );

		return is_numeric( $id ) ? max( 0, (int) $id ) : 0;
	}

	/**
	 * Response lỗi có kèm data (envelope D10).
	 *
	 * @param int                   $status  HTTP status.
	 * @param string                $code    Mã lỗi.
	 * @param string                $message Thông báo.
	 * @param array<string, mixed>  $data    Dữ liệu kèm.
	 * @param array<string, string> $errors  Lỗi theo đường dẫn.
	 */
	private static function response( int $status, string $code, string $message, array $data = array(), array $errors = array() ): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'success' => false,
				'code'    => $code,
				'message' => $message,
				'errors'  => $errors,
				'data'    => $data,
			),
			$status
		);
	}
}
