<?php
/**
 * Element: Text.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\CssRules;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * Text — đoạn văn có định dạng (rich text qua wp_kses_post).
 */
final class Text extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'text',
			'name'           => __( 'Văn bản', 'saha-core' ),
			'icon'           => 'editor-paragraph',
			'category'       => 'content',
			'allowedParents' => self::CONTENT_PARENTS,
			'controls'       => array(
				'content'    => array(
					'type'    => 'richtext',
					'label'   => __( 'Nội dung', 'saha-core' ),
					'section' => 'content',
					'default' => '<p>' . __( 'Nhập nội dung tại đây.', 'saha-core' ) . '</p>',
				),
				'align'      => array(
					'type'       => 'align',
					'label'      => __( 'Căn lề', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
				),
				'typography' => array(
					'type'    => 'typography',
					'label'   => __( 'Kiểu chữ', 'saha-core' ),
					'section' => 'style',
				),
				'color'      => array(
					'type'    => 'color',
					'label'   => __( 'Màu chữ', 'saha-core' ),
					'section' => 'style',
				),
				'linkColor'  => array(
					'type'    => 'color',
					'label'   => __( 'Màu liên kết', 'saha-core' ),
					'section' => 'style',
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
		// Đã kses khi lưu; kses lại khi render phòng dữ liệu sửa tay trong DB.
		$html = wp_kses_post( (string) $this->prop( $node, 'content' ) );

		return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-text' ) ) . '>' . $html . '</div>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', 'text-align', $node->prop( 'align' ) );
		$css->typography( '', $node->prop( 'typography' ) );
		$css->set( '', 'color', $node->prop( 'color' ) );
		$css->set( ' a', 'color', $node->prop( 'linkColor' ) );
	}
}
