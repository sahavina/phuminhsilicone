<?php
/**
 * Element động: tiêu đề bài / sản phẩm / trang.
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
 * PostTitle — mặc định H1 (template đơn chỉ có một H1).
 */
final class PostTitle extends DynamicElement {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return self::dynamicDef(
			array(
				'type'     => 'post-title',
				'name'     => __( 'Tiêu đề (động)', 'saha-core' ),
				'icon'     => 'heading',
				'controls' => array(
					'tag'        => array(
						'type'    => 'select',
						'label'   => __( 'Thẻ', 'saha-core' ),
						'section' => 'content',
						'default' => 'h1',
						'options' => array(
							'h1' => 'H1',
							'h2' => 'H2',
							'h3' => 'H3',
							'p'  => __( 'Đoạn văn', 'saha-core' ),
						),
					),
					'link'       => array(
						'type'    => 'toggle',
						'label'   => __( 'Liên kết tới bài', 'saha-core' ),
						'section' => 'content',
						'default' => false,
					),
					'align'      => array(
						'type'       => 'align',
						'label'      => __( 'Căn lề', 'saha-core' ),
						'section'    => 'style',
						'responsive' => true,
						'options'    => array( 'left', 'center', 'right' ),
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
				),
			)
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
		$tag  = in_array( $this->prop( $node, 'tag' ), array( 'h1', 'h2', 'h3', 'p' ), true ) ? (string) $this->prop( $node, 'tag' ) : 'h1';
		$html = self::withSubject(
			$ctx,
			function ( \WP_Post $post ) use ( $node, $ctx, $tag ): string {
				$title = esc_html( get_the_title( $post ) );

				if ( $this->prop( $node, 'link' ) ) {
					$title = '<a href="' . esc_url( (string) get_permalink( $post ) ) . '">' . $title . '</a>';
				}

				return '<' . $tag . $this->rootAttributes( $node, $ctx, array( 'saha-heading', 'saha-post-title' ) ) . '>' . $title . '</' . $tag . '>';
			}
		);

		return $html ?? $this->placeholder( $node, $ctx, __( 'Tiêu đề bài viết / sản phẩm', 'saha-core' ) );
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', 'text-align', $node->prop( 'align' ) );
		$css->set( '', 'color', $node->prop( 'color' ) );
		$css->typography( '', $node->prop( 'typography' ) );
	}
}
