<?php
/**
 * Cache HTML của subtree tĩnh.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder;

use Saha\Core\Builder\Schema\Node;
use Saha\Core\Cache;

defined( 'ABSPATH' ) || exit;

/**
 * RenderCache.
 *
 * Chỉ cache subtree mà MỌI element đều tĩnh (`dynamic: false`) — nội dung theo
 * user/giỏ hàng/giá không bao giờ vào cache chung (rủi ro R6). Khoá gồm hash
 * nội dung node → sửa layout là khoá mới; dùng thế hệ của `Saha\Core\Cache`
 * nên "xoá cache" của SAHA cũng xoá luôn render cache.
 */
final class RenderCache {

	public const GROUP = 'builder';
	public const TTL   = DAY_IN_SECONDS;

	/**
	 * Tạo cache.
	 *
	 * @param ElementRegistry|null $elements Registry.
	 */
	public function __construct( private ?ElementRegistry $elements = null ) {
		$this->elements ??= ElementRegistry::instance();
	}

	/**
	 * Có được cache node này không.
	 *
	 * @param Node          $node Node.
	 * @param RenderContext $ctx  Ngữ cảnh.
	 */
	public function allows( Node $node, RenderContext $ctx ): bool {
		if ( ! $ctx->useCache || $ctx->editor || ! $this->isStatic( $node ) ) {
			return false;
		}

		/**
		 * Bật/tắt render cache.
		 *
		 * @param bool $enabled Mặc định true.
		 * @param Node $node    Node.
		 */
		return (bool) apply_filters( 'saha_builder_render_cache', true, $node );
	}

	/**
	 * Lấy từ cache, chưa có thì render và ghi.
	 *
	 * @param Node          $node   Node.
	 * @param RenderContext $ctx    Ngữ cảnh.
	 * @param callable      $render fn(): string.
	 */
	public function remember( Node $node, RenderContext $ctx, callable $render ): string {
		$args = array( $ctx->postId, $node->hash(), SAHA_CORE_VERSION );

		return (string) Cache::remember( self::GROUP, $args, $render, self::TTL );
	}

	/**
	 * Mọi element trong subtree đều tĩnh.
	 *
	 * @param Node $node Node.
	 */
	public function isStatic( Node $node ): bool {
		$element = $this->elements->get( $node->type );

		if ( null === $element || ! empty( $element->def()['dynamic'] ) ) {
			return false;
		}

		foreach ( $node->children as $child ) {
			if ( ! $this->isStatic( $child ) ) {
				return false;
			}
		}

		return true;
	}
}
