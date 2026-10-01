<?php
/**
 * Nền cho element động của Template Builder.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;
use Saha\Core\Templates\Preview;
use Saha\Core\Templates\Repository as Templates;

defined( 'ABSPATH' ) || exit;

/**
 * DynamicElement — element lấy dữ liệu từ trang đang xem (tiêu đề, nội dung, giá…).
 *
 * "Đối tượng" (subject):
 * - frontend: bài/sản phẩm/trang đang xem (`get_queried_object_id()`), danh sách = main query;
 * - editor, đang dựng template: đối tượng xem trước (Templates\Preview);
 * - editor, đang dựng một trang thường: chính trang đó.
 *
 * Luôn `dynamic` (không render cache). Không có dữ liệu: editor hiện ô gợi ý, frontend không in gì.
 */
abstract class DynamicElement extends Element {

	/**
	 * Định nghĩa chung: động, nhóm "Template (động)".
	 *
	 * @param array<string, mixed> $def Định nghĩa riêng.
	 * @return array<string, mixed>
	 */
	protected static function dynamicDef( array $def ): array {
		return $def + array(
			'category'       => 'dynamic',
			'allowedParents' => self::CONTENT_PARENTS,
			'dynamic'        => true,
		);
	}

	/**
	 * Template đang dựng trong editor (0 nếu không phải).
	 *
	 * @param RenderContext $ctx Ngữ cảnh.
	 */
	protected static function editingTemplate( RenderContext $ctx ): int {
		return $ctx->editor && '' !== Templates::typeOf( $ctx->postId ) ? $ctx->postId : 0;
	}

	/**
	 * Bài / sản phẩm / trang đang hiển thị (0 nếu là trang danh sách).
	 *
	 * @param RenderContext $ctx Ngữ cảnh.
	 */
	protected static function subject( RenderContext $ctx ): int {
		$template = self::editingTemplate( $ctx );

		if ( $template > 0 ) {
			return Preview::subject( $template );
		}

		if ( $ctx->editor ) {
			return $ctx->postId;
		}

		return is_singular() ? (int) get_queried_object_id() : 0;
	}

	/**
	 * Chạy $render với global $post (và $product) là đối tượng; khôi phục sau đó.
	 *
	 * @param RenderContext $ctx    Ngữ cảnh.
	 * @param callable      $render fn( \WP_Post $post ): string.
	 */
	protected static function withSubject( RenderContext $ctx, callable $render ): ?string {
		$id   = self::subject( $ctx );
		$post = $id > 0 ? get_post( $id ) : null;

		if ( ! $post instanceof \WP_Post ) {
			return null;
		}

		$old_post    = $GLOBALS['post'] ?? null;
		$old_product = $GLOBALS['product'] ?? null;

		$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- đặt tạm, khôi phục ngay dưới.
		setup_postdata( $post );

		if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
			$GLOBALS['product'] = wc_get_product( $post ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		try {
			return (string) $render( $post );
		} finally {
			$GLOBALS['post']    = $old_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$GLOBALS['product'] = $old_product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

			if ( $old_post instanceof \WP_Post ) {
				setup_postdata( $old_post );
			}
		}
	}

	/**
	 * Chạy $render với main query là danh sách đang xem (editor: danh sách xem trước).
	 *
	 * @param RenderContext $ctx    Ngữ cảnh.
	 * @param callable      $render fn(): string.
	 */
	protected static function withArchive( RenderContext $ctx, callable $render ): string {
		$template = self::editingTemplate( $ctx );

		if ( 0 === $template ) {
			return (string) $render();
		}

		$query = Preview::archiveQuery( $template );

		if ( null === $query ) {
			return '';
		}

		$old       = $GLOBALS['wp_query'] ?? null;
		$old_the   = $GLOBALS['wp_the_query'] ?? null;
		$old_post  = $GLOBALS['post'] ?? null;

		$GLOBALS['wp_query']     = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- đặt tạm trong editor, khôi phục ngay dưới.
		$GLOBALS['wp_the_query'] = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		try {
			return (string) $render();
		} finally {
			$GLOBALS['wp_query']     = $old; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$GLOBALS['wp_the_query'] = $old_the; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$GLOBALS['post']         = $old_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}
	}

	/**
	 * Ô gợi ý trong editor khi chưa có dữ liệu (frontend: rỗng).
	 *
	 * @param Node          $node    Node.
	 * @param RenderContext $ctx     Ngữ cảnh.
	 * @param string        $message Thông báo.
	 */
	protected function placeholder( Node $node, RenderContext $ctx, string $message ): string {
		return $ctx->editor
			? '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-dynamic', 'saha-image--empty' ) ) . '>' . esc_html( $message ) . '</div>'
			: '';
	}

	/**
	 * Bọc HTML trong thẻ gốc của element.
	 *
	 * @param Node          $node    Node.
	 * @param RenderContext $ctx     Ngữ cảnh.
	 * @param string        $class   Class riêng.
	 * @param string        $html    Nội dung.
	 */
	protected function wrap( Node $node, RenderContext $ctx, string $class, string $html ): string {
		return '' === trim( $html ) ? $this->placeholder( $node, $ctx, (string) $this->def()['name'] ) : '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-dynamic', $class ) ) . '>' . $html . '</div>';
	}

	/**
	 * Lấy output của hàm in ra màn hình.
	 *
	 * @param callable $print Hàm in.
	 */
	protected static function capture( callable $print ): string {
		ob_start();
		$print();

		return (string) ob_get_clean();
	}
}
