<?php
/**
 * Element: Heading.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\Controls\Link;
use Saha\Core\Builder\CssRules;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * Heading — tiêu đề (text thuần, escape khi render).
 */
final class Heading extends Element {

	public const TAGS = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div' );

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'heading',
			'name'           => __( 'Tiêu đề', 'saha-core' ),
			'icon'           => 'heading',
			'category'       => 'content',
			'allowedParents' => self::CONTENT_PARENTS,
			'controls'       => array(
				'text'       => array(
					'type'      => 'text',
					'label'     => __( 'Nội dung', 'saha-core' ),
					'section'   => 'content',
					'default'   => __( 'Tiêu đề', 'saha-core' ),
					'maxLength' => 300,
				),
				'tag'        => array(
					'type'    => 'select',
					'label'   => __( 'Thẻ HTML', 'saha-core' ),
					'section' => 'content',
					'default' => 'h2',
					'options' => array_combine( self::TAGS, array_map( 'strtoupper', self::TAGS ) ),
					'help'    => __( 'Mỗi trang chỉ nên có một H1.', 'saha-core' ),
				),
				'link'       => array(
					'type'    => 'link',
					'label'   => __( 'Liên kết', 'saha-core' ),
					'section' => 'content',
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
		$tag  = in_array( $this->prop( $node, 'tag' ), self::TAGS, true ) ? (string) $this->prop( $node, 'tag' ) : 'h2';
		$text = esc_html( (string) $this->prop( $node, 'text' ) );
		$link = $node->prop( 'link' );

		if ( is_array( $link ) && ! empty( $link['url'] ) ) {
			$text = '<a' . Link::attributes( $link ) . '>' . $text . '</a>';
		}

		return '<' . $tag . $this->rootAttributes( $node, $ctx, array( 'saha-heading' ) ) . '>' . $text . '</' . $tag . '>';
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

		// Link trong heading mang màu của heading.
		if ( null !== $node->prop( 'color' ) ) {
			$css->set( ' a', 'color', 'inherit' );
		}
	}
}
