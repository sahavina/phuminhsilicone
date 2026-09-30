<?php
/**
 * Màn hình SAHA Builder (toàn trang).
 *
 * @package Saha\Builder
 */

declare( strict_types=1 );

namespace Saha\Builder\Admin;

use Saha\Builder\Assets;
use Saha\Builder\Canvas;
use Saha\Core\Builder\LayoutRepository;
use Saha\Core\ThemeOptions\Repository as ThemeOptions;
use Saha\Core\ThemeOptions\Schema as ThemeSchema;

defined( 'ABSPATH' ) || exit;

/**
 * BuilderScreen — `admin.php?page=saha-builder&post={id}`.
 *
 * Trang ẩn khỏi menu (vào từ danh sách Trang/Bài viết, admin bar, block editor).
 * Ứng dụng React chiếm toàn màn hình; mọi dữ liệu đi qua REST của saha-core.
 */
final class BuilderScreen {

	public const SLUG = 'saha-builder';

	/**
	 * Hook suffix của trang.
	 *
	 * @var string
	 */
	private string $hook = '';

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'addPage' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'admin_body_class', array( $this, 'bodyClass' ) );
	}

	/**
	 * URL mở builder cho một post.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function url( int $post_id ): string {
		return add_query_arg(
			array(
				'page' => self::SLUG,
				'post' => $post_id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Người dùng hiện tại mở được builder cho post không.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function canEdit( int $post_id ): bool {
		return $post_id > 0
			&& current_user_can( 'edit_saha_builder' )
			&& current_user_can( 'edit_post', $post_id )
			&& LayoutRepository::supports( $post_id );
	}

	/**
	 * Đăng ký trang ẩn (parent `options.php` = không hiện trong menu).
	 */
	public function addPage(): void {
		$hook = add_submenu_page(
			'options.php',
			__( 'SAHA Builder', 'saha-builder' ),
			__( 'SAHA Builder', 'saha-builder' ),
			'edit_saha_builder',
			self::SLUG,
			array( $this, 'render' )
		);

		$this->hook = is_string( $hook ) ? $hook : '';

		if ( '' !== $this->hook ) {
			add_action( 'load-' . $this->hook, array( $this, 'guard' ) );
		}
	}

	/**
	 * Kiểm tra quyền trước khi in bất cứ gì.
	 */
	public function guard(): void {
		$post_id = self::requestedPost();

		if ( ! self::canEdit( $post_id ) ) {
			wp_die( esc_html__( 'Bạn không có quyền sửa trang này bằng SAHA Builder, hoặc loại nội dung này chưa hỗ trợ builder.', 'saha-builder' ), 403 );
		}

		// Màn hình toàn trang: bỏ thông báo của plugin khác.
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
	}

	/**
	 * Class body để CSS ẩn khung wp-admin.
	 *
	 * @param string $classes Class.
	 */
	public function bodyClass( $classes ) {
		$screen = get_current_screen();

		if ( $screen && '' !== $this->hook && $screen->id === $this->hook ) {
			$classes .= ' saha-builder-fullscreen';
		}

		return $classes;
	}

	/**
	 * Nạp ứng dụng.
	 *
	 * @param string $hook_suffix Trang hiện tại.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( '' === $this->hook || $hook_suffix !== $this->hook ) {
			return;
		}

		$post_id = self::requestedPost();
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return;
		}

		wp_enqueue_media( array( 'post' => $post_id ) );
		// TinyMCE cho control rich text (quen thuộc với người dùng WordPress).
		wp_enqueue_editor();

		$handle = Assets::enqueue( 'builder' );

		if ( '' === $handle ) {
			return;
		}

		wp_add_inline_script(
			$handle,
			'window.sahaBuilder = ' . wp_json_encode( $this->config( $post ), JSON_UNESCAPED_UNICODE ) . ';',
			'before'
		);
	}

	/**
	 * Cấu hình cho ứng dụng.
	 *
	 * @param \WP_Post $post Post.
	 * @return array<string, mixed>
	 */
	private function config( \WP_Post $post ): array {
		$type = get_post_type_object( $post->post_type );

		return array(
			'postId'        => $post->ID,
			'postType'      => $post->post_type,
			'postTypeLabel' => $type ? (string) $type->labels->singular_name : $post->post_type,
			'canvasUrl'     => Canvas::url( $post->ID ),
			'exitUrl'       => admin_url( 'edit.php?post_type=' . $post->post_type ),
			'editUrl'       => (string) get_edit_post_link( $post->ID, 'raw' ),
			'legacyContent' => LayoutRepository::isEnabled( $post->ID ) || null !== LayoutRepository::raw( $post->ID ) ? '' : self::legacyContent( $post ),
			'palette'       => self::palette(),
			'canUpload'     => current_user_can( 'upload_files' ),
			'lockInterval'  => 60,
			'builderUrl'    => add_query_arg( 'page', self::SLUG, admin_url( 'admin.php' ) ) . '&post=',
			'blocksUrl'     => admin_url( 'edit.php?post_type=saha_block' ),
			'canCreateBlock' => current_user_can( 'publish_pages' ),
		);
	}

	/**
	 * Nội dung cũ của trang (lần đầu mở builder) → đưa vào một khối Văn bản để không mất.
	 *
	 * @param \WP_Post $post Post.
	 */
	private static function legacyContent( \WP_Post $post ): string {
		$content = trim( (string) $post->post_content );

		if ( '' === $content ) {
			return '';
		}

		// Render block (không chạy the_content: tránh shortcode/plugin chèn HTML động).
		return trim( wpautop( do_blocks( $content ) ) );
	}

	/**
	 * Bảng màu Theme Options → biến CSS (đổi màu toàn cục là element đổi theo).
	 *
	 * @return array<int, array{name: string, value: string, color: string}>
	 */
	private static function palette(): array {
		if ( ! class_exists( ThemeSchema::class ) ) {
			return array();
		}

		$groups = ThemeSchema::groups();
		$fields = (array) ( $groups['colors']['fields'] ?? array() );
		$out    = array();

		foreach ( $fields as $key => $field ) {
			if ( empty( $field['cssVar'] ) ) {
				continue;
			}

			$out[] = array(
				'name'  => (string) ( $field['label'] ?? $key ),
				'value' => 'var(' . $field['cssVar'] . ')',
				'color' => (string) ThemeOptions::get( 'colors.' . $key, $field['default'] ?? '' ),
			);
		}

		return $out;
	}

	/**
	 * Post đang mở.
	 */
	private static function requestedPost(): int {
		return isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- chỉ đọc ID để kiểm quyền.
	}

	/**
	 * Khung của ứng dụng.
	 */
	public function render(): void {
		$built = is_readable( SAHA_BUILDER_PATH . 'build/builder.asset.php' );
		?>
		<div id="saha-builder-root" class="saha-builder-root">
			<?php if ( ! $built ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Chưa có bản build của ứng dụng. Chạy "npm run build" ở gốc repo.', 'saha-builder' ); ?></p></div>
			<?php else : ?>
				<p class="saha-builder-loading"><?php esc_html_e( 'Đang tải SAHA Builder…', 'saha-builder' ); ?></p>
			<?php endif; ?>
			<noscript><p><?php esc_html_e( 'SAHA Builder cần JavaScript.', 'saha-builder' ); ?></p></noscript>
		</div>
		<?php
	}
}
