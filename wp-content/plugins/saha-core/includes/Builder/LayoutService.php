<?php
/**
 * Nghiệp vụ lưu / render layout builder.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder;

use Saha\Core\Builder\Schema\Document;
use Saha\Core\Performance\CssFileStore;

defined( 'ABSPATH' ) || exit;

/**
 * LayoutService.
 *
 * Lưu: khoá chỉnh sửa → so baseHash (chống ghi đè) → sanitize → ghi meta →
 * sinh CSS → cập nhật post_content dự phòng (tạo revision) → `saha_builder_saved`.
 */
final class LayoutService {

	/**
	 * Tài liệu rỗng.
	 */
	public static function emptyDocument(): Document {
		return new Document( array() );
	}

	/**
	 * Lưu layout.
	 *
	 * @param int    $post_id   Post ID.
	 * @param mixed  $data      Tài liệu thô từ client.
	 * @param string $base_hash Hash client đang sửa ('' nếu tài liệu mới).
	 * @param bool   $enabled   Bật builder cho post.
	 * @return array{status: string, errors?: array<string, string>, hash?: string, document?: Document, lockedBy?: string}
	 *         status: saved | invalid | conflict | locked | unsupported
	 */
	public static function save( int $post_id, $data, string $base_hash, bool $enabled = true ): array {
		if ( ! LayoutRepository::supports( $post_id ) ) {
			return array( 'status' => 'unsupported' );
		}

		$locker = self::lockedBy( $post_id );

		if ( '' !== $locker ) {
			return array(
				'status'   => 'locked',
				'lockedBy' => $locker,
			);
		}

		$current = LayoutRepository::hash( $post_id );

		if ( '' !== $current && ! hash_equals( $current, $base_hash ) ) {
			return array(
				'status' => 'conflict',
				'hash'   => $current,
			);
		}

		$result = ( new Sanitizer() )->document( $data );

		if ( null === $result['document'] ) {
			return array(
				'status' => 'invalid',
				'errors' => $result['errors'],
			);
		}

		$document = $result['document'];
		$hash     = LayoutRepository::save( $post_id, $document, $enabled );

		self::regenerateCss( $post_id, $document );
		self::writeFallbackContent( $post_id, $document );

		/**
		 * Layout vừa được lưu — điểm cắm để purge page cache/CDN.
		 *
		 * @param int      $post_id  Post ID.
		 * @param Document $document Tài liệu.
		 */
		do_action( 'saha_builder_saved', $post_id, $document );

		return array(
			'status'   => 'saved',
			'hash'     => $hash,
			'document' => $document,
		);
	}

	/**
	 * Render layout của post cho frontend.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function renderPost( int $post_id ): string {
		$document = LayoutRepository::get( $post_id );

		if ( null === $document ) {
			return '';
		}

		$html = ( new Renderer() )->document( $document, new RenderContext( $post_id ) );

		return '<div class="saha-builder-content saha-builder-' . $post_id . '">' . $html . '</div>';
	}

	/**
	 * Sinh lại file CSS của post.
	 *
	 * @param int           $post_id  Post ID.
	 * @param Document|null $document Tài liệu (null = đọc từ DB).
	 * @return array{file: string, inline: bool, layout: string, env: string}
	 */
	public static function regenerateCss( int $post_id, ?Document $document = null ): array {
		$document ??= LayoutRepository::get( $post_id ) ?? self::emptyDocument();
		$css        = ( new CssGenerator() )->document( $document );
		$written    = '' === $css
			? array(
				'file'   => '',
				'inline' => false,
			)
			: CssFileStore::write( 'post-' . $post_id, $css );

		$state = array(
			'file'   => (string) $written['file'],
			'inline' => (bool) $written['inline'],
			'layout' => LayoutRepository::hash( $post_id ),
			'env'    => self::env(),
		);

		LayoutRepository::setCssState( $post_id, $state );

		return $state;
	}

	/**
	 * Trạng thái CSS còn đúng không; sai thì sinh lại.
	 *
	 * Sai khi: layout đổi mà CSS chưa (khôi phục revision), plugin nâng cấp,
	 * đổi domain (URL ảnh nền), hoặc file bị xoá.
	 *
	 * @param int $post_id Post ID.
	 * @return array{file: string, inline: bool, layout: string, env: string}
	 */
	public static function ensureCss( int $post_id ): array {
		$state = LayoutRepository::cssState( $post_id );

		$stale = ( $state['layout'] ?? null ) !== LayoutRepository::hash( $post_id )
			|| ( $state['env'] ?? null ) !== self::env()
			|| ( ! empty( $state['file'] ) && ! CssFileStore::exists( (string) $state['file'] ) );

		if ( $stale ) {
			return self::regenerateCss( $post_id );
		}

		return array(
			'file'   => (string) ( $state['file'] ?? '' ),
			'inline' => (bool) ( $state['inline'] ?? false ),
			'layout' => (string) ( $state['layout'] ?? '' ),
			'env'    => (string) ( $state['env'] ?? '' ),
		);
	}

	/**
	 * CSS dạng chuỗi (khi không ghi được file).
	 *
	 * @param int $post_id Post ID.
	 */
	public static function inlineCss( int $post_id ): string {
		$document = LayoutRepository::get( $post_id );

		return null === $document ? '' : ( new CssGenerator() )->document( $document );
	}

	/**
	 * Người đang giữ khoá chỉnh sửa (không phải user hiện tại), '' nếu không có.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function lockedBy( int $post_id ): string {
		self::loadPostFunctions();

		$user_id = wp_check_post_lock( $post_id );

		if ( ! $user_id ) {
			return '';
		}

		$user = get_userdata( (int) $user_id );

		return $user ? (string) $user->display_name : __( 'Người dùng khác', 'saha-core' );
	}

	/**
	 * Giữ khoá chỉnh sửa (cơ chế post lock của WordPress, hết hạn sau 150 giây không gia hạn).
	 *
	 * @param int $post_id Post ID.
	 * @return string '' nếu giữ được; tên người đang giữ nếu không.
	 */
	public static function lock( int $post_id ): string {
		$locker = self::lockedBy( $post_id );

		if ( '' !== $locker ) {
			return $locker;
		}

		wp_set_post_lock( $post_id );

		return '';
	}

	/**
	 * post_content dự phòng: HTML tĩnh của layout.
	 *
	 * Để tìm kiếm WordPress, excerpt, plugin SEO đọc được nội dung, và trang vẫn
	 * có nội dung nếu saha-core bị tắt. KHÔNG phải nguồn dữ liệu: frontend luôn
	 * render từ JSON. Cập nhật post_content cũng tạo revision (kèm meta builder).
	 *
	 * @param int      $post_id  Post ID.
	 * @param Document $document Tài liệu.
	 */
	private static function writeFallbackContent( int $post_id, Document $document ): void {
		$html = ( new Renderer() )->document( $document, new RenderContext( $post_id, false, false ) );

		wp_update_post(
			wp_slash(
				array(
					'ID'           => $post_id,
					'post_content' => "<!-- saha-builder: nội dung dự phòng, sửa trong SAHA Builder -->\n" . $html,
				)
			)
		);
	}

	/**
	 * Môi trường sinh CSS: phiên bản plugin + domain.
	 */
	private static function env(): string {
		return SAHA_CORE_VERSION . '|' . substr( md5( home_url() ), 0, 8 );
	}

	/**
	 * Hàm post lock nằm trong wp-admin/includes/post.php — REST không tự nạp.
	 */
	private static function loadPostFunctions(): void {
		if ( ! function_exists( 'wp_check_post_lock' ) ) {
			require_once ABSPATH . 'wp-admin/includes/post.php';
		}
	}
}
