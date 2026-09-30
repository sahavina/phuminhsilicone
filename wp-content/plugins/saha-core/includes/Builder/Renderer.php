<?php
/**
 * Render tài liệu builder thành HTML.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder;

use Saha\Core\Builder\Schema\Document;
use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * Renderer — JSON là nguồn chính, HTML luôn sinh ra từ đây (spec §61).
 *
 * Node type không đăng ký bị bỏ qua (dữ liệu vẫn còn trong JSON). Subtree tĩnh ở
 * cấp gốc được cache (RenderCache).
 */
final class Renderer {

	/**
	 * Tạo renderer.
	 *
	 * @param ElementRegistry|null $elements Registry.
	 * @param RenderCache|null     $cache    Cache.
	 */
	public function __construct(
		private ?ElementRegistry $elements = null,
		private ?RenderCache $cache = null
	) {
		$this->elements ??= ElementRegistry::instance();
		$this->cache    ??= new RenderCache( $this->elements );
	}

	/**
	 * Render cả tài liệu.
	 *
	 * @param Document      $document Tài liệu.
	 * @param RenderContext $ctx      Ngữ cảnh.
	 */
	public function document( Document $document, RenderContext $ctx ): string {
		$html = '';

		foreach ( $document->elements as $node ) {
			$html .= $this->node( $node, $ctx );
		}

		return $html;
	}

	/**
	 * Render một node.
	 *
	 * @param Node          $node Node.
	 * @param RenderContext $ctx  Ngữ cảnh.
	 */
	public function node( Node $node, RenderContext $ctx ): string {
		$element = $this->elements->get( $node->type );

		if ( null === $element || ! $node->isKnown() ) {
			return $ctx->editor ? '<!-- saha: element "' . esc_html( $node->type ) . '" chưa được cài -->' : '';
		}

		$ctx->addAssets( (array) ( $element->def()['assets'] ?? array() ) );

		if ( 0 === $ctx->depth && $this->cache->allows( $node, $ctx ) ) {
			return $this->cache->remember( $node, $ctx, fn(): string => $this->build( $node, $ctx ) );
		}

		return $this->build( $node, $ctx );
	}

	/**
	 * Render thật (không cache).
	 *
	 * @param Node          $node Node.
	 * @param RenderContext $ctx  Ngữ cảnh.
	 */
	private function build( Node $node, RenderContext $ctx ): string {
		$element = $this->elements->get( $node->type );

		if ( null === $element ) {
			return '';
		}

		/**
		 * Trước khi render một element.
		 *
		 * @param Node          $node Node.
		 * @param RenderContext $ctx  Ngữ cảnh.
		 */
		do_action( 'saha_builder_render_before', $node, $ctx );

		++$ctx->depth;
		$content = '';

		foreach ( $node->children as $child ) {
			$content .= $this->node( $child, $ctx );
		}

		--$ctx->depth;

		$html = $element->render( $node, $ctx, $content );

		/**
		 * Sửa HTML của một element.
		 *
		 * @param string        $html HTML.
		 * @param Node          $node Node.
		 * @param RenderContext $ctx  Ngữ cảnh.
		 */
		$html = (string) apply_filters( 'saha_builder_render_element', $html, $node, $ctx );

		/**
		 * Sau khi render một element.
		 *
		 * @param Node          $node Node.
		 * @param RenderContext $ctx  Ngữ cảnh.
		 */
		do_action( 'saha_builder_render_after', $node, $ctx );

		return $html;
	}
}
