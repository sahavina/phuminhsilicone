<?php
/**
 * Hiển thị layout builder ở frontend.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder;

use Saha\Core\Performance\CssFileStore;

defined( 'ABSPATH' ) || exit;

/**
 * Frontend.
 *
 * Chạy với MỌI theme (không phụ thuộc saha-theme, không cần saha-builder):
 * `the_content` của post bật builder được thay bằng HTML render từ JSON.
 */
final class Frontend {

	public const BASE_STYLE = 'saha-builder-frontend';

	/**
	 * Post đang render (chống đệ quy nếu element gọi the_content).
	 *
	 * @var array<int, true>
	 */
	private array $rendering = array();

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		// Sau wpautop/do_shortcode/wp_filter_content_tags: HTML builder không bị chèn <p> hay xử lý lại.
		add_filter( 'the_content', array( $this, 'content' ), 999 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'body_class', array( $this, 'bodyClass' ) );
	}

	/**
	 * Thay nội dung bằng layout builder.
	 *
	 * @param string $content Nội dung gốc (post_content dự phòng).
	 */
	public function content( $content ) {
		$post = get_post();

		if ( ! $post || ! LayoutRepository::isEnabled( (int) $post->ID ) || post_password_required( $post ) || isset( $this->rendering[ $post->ID ] ) ) {
			return $content;
		}

		$this->rendering[ $post->ID ] = true;

		// the_content ngoài trang đơn (ví dụ archive in toàn văn): CSS chưa được nạp ở wp_enqueue_scripts.
		if ( ! wp_style_is( self::BASE_STYLE, 'enqueued' ) ) {
			$this->enqueueFor( (int) $post->ID );
		}

		$html = LayoutService::renderPost( (int) $post->ID );

		unset( $this->rendering[ $post->ID ] );

		return $html;
	}

	/**
	 * Nạp CSS ở trang đơn dùng builder.
	 */
	public function enqueue(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post_id = (int) get_queried_object_id();

		if ( LayoutRepository::isEnabled( $post_id ) ) {
			$this->enqueueFor( $post_id );
		}
	}

	/**
	 * Nạp CSS nền của builder + CSS riêng của layout.
	 *
	 * @param int $post_id Post ID.
	 */
	private function enqueueFor( int $post_id ): void {
		wp_enqueue_style( self::BASE_STYLE, SAHA_CORE_URL . 'public/assets/css/builder.css', array(), SAHA_CORE_VERSION );

		$state = LayoutService::ensureCss( $post_id );

		if ( '' !== $state['file'] ) {
			// Hash nằm trong tên file → không cần ?ver.
			wp_enqueue_style( 'saha-layout-' . $post_id, CssFileStore::url( $state['file'] ), array( self::BASE_STYLE ), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			return;
		}

		if ( $state['inline'] ) {
			wp_add_inline_style( self::BASE_STYLE, LayoutService::inlineCss( $post_id ) );
		}
	}

	/**
	 * Class body cho trang dùng builder (theme dựa vào đây để bỏ khung nội dung).
	 *
	 * @param string[] $classes Class.
	 * @return string[]
	 */
	public function bodyClass( array $classes ): array {
		if ( is_singular() && LayoutRepository::isEnabled( (int) get_queried_object_id() ) ) {
			$classes[] = 'saha-builder-page';
		}

		return $classes;
	}
}
