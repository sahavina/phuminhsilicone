<?php
/**
 * Element: Block (tham chiếu block tái sử dụng).
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\LayoutService;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * Block — hiển thị nội dung MỚI NHẤT của một `saha_block` (spec §20): sửa block
 * một chỗ, mọi trang dùng nó đổi theo.
 *
 * `dynamic`: không cache ở cấp trang (nội dung block đổi mà JSON trang không đổi).
 * Bản thân block vẫn được cache theo nội dung của nó (LayoutService::renderBlock).
 */
final class Block extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'block',
			'name'           => __( 'Block dùng chung', 'saha-core' ),
			'icon'           => 'block-default',
			'category'       => 'marketing',
			'allowedParents' => array_merge( array( 'root' ), self::CONTENT_PARENTS ),
			'dynamic'        => true,
			'controls'       => array(
				'blockId' => array(
					'type'    => 'blockRef',
					'label'   => __( 'Block', 'saha-core' ),
					'section' => 'content',
					'help'    => __( 'Sửa block ở SAHA → Blocks: mọi trang dùng block đổi theo.', 'saha-core' ),
				),
			),
		);
	}

	/**
	 * Render.
	 *
	 * @param Node          $node    Node.
	 * @param RenderContext $ctx     Ngữ cảnh.
	 * @param string        $content HTML con (không dùng).
	 */
	public function render( Node $node, RenderContext $ctx, string $content ): string {
		$block_id = (int) $node->prop( 'blockId', 0 );
		$result   = $block_id > 0 ? LayoutService::renderBlock( $block_id, $ctx ) : array(
			'html'  => '',
			'error' => __( 'Chưa chọn block.', 'saha-core' ),
		);

		if ( '' === $result['html'] ) {
			return $ctx->editor
				? '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-block-ref', 'saha-image--empty' ) ) . '>' . esc_html( $result['error'] ?: __( 'Block trống.', 'saha-core' ) ) . '</div>'
				: '';
		}

		return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-block-ref', 'saha-block-' . $block_id ) ) . '>' . $result['html'] . '</div>';
	}
}
