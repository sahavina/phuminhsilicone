<?php
/**
 * Element: Icon.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\Controls\Link;
use Saha\Core\Builder\CssRules;
use Saha\Core\Builder\Icons;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * Icon — SVG nội tuyến. Icon có link cần "tên cho trình đọc màn hình".
 */
final class Icon extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'icon',
			'name'           => __( 'Icon', 'saha-core' ),
			'icon'           => 'star-filled',
			'category'       => 'content',
			'allowedParents' => self::CONTENT_PARENTS,
			'controls'       => array(
				'icon'       => array(
					'type'    => 'icon',
					'label'   => __( 'Icon', 'saha-core' ),
					'section' => 'content',
					'default' => 'star',
				),
				'link'       => array(
					'type'    => 'link',
					'label'   => __( 'Liên kết', 'saha-core' ),
					'section' => 'content',
				),
				'label'      => array(
					'type'      => 'text',
					'label'     => __( 'Tên cho trình đọc màn hình', 'saha-core' ),
					'section'   => 'content',
					'maxLength' => 120,
					'help'      => __( 'Bắt buộc nếu icon có liên kết (ví dụ: "Gọi hotline").', 'saha-core' ),
				),
				'size'       => array(
					'type'       => 'size',
					'label'      => __( 'Cỡ', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'units'      => array( 'px', 'rem' ),
					'min'        => 8,
					'max'        => 400,
				),
				'color'      => array(
					'type'    => 'color',
					'label'   => __( 'Màu', 'saha-core' ),
					'section' => 'style',
				),
				'background' => array(
					'type'    => 'color',
					'label'   => __( 'Màu nền (hình tròn)', 'saha-core' ),
					'section' => 'style',
				),
				'align'      => array(
					'type'       => 'align',
					'label'      => __( 'Căn lề', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'options'    => array( 'left', 'center', 'right' ),
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
		$svg = Icons::svg( (string) $this->prop( $node, 'icon' ), 'saha-icon-el__svg' );

		if ( '' === $svg ) {
			return '';
		}

		$label = (string) $node->prop( 'label', '' );
		$inner = '<span class="saha-icon-el__shape">' . $svg . '</span>';

		if ( '' !== $label ) {
			$inner .= '<span class="screen-reader-text">' . esc_html( $label ) . '</span>';
		}

		$link = $node->prop( 'link' );

		if ( is_array( $link ) && ! empty( $link['url'] ) ) {
			$inner = '<a' . Link::attributes( $link ) . '>' . $inner . '</a>';
		}

		return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-icon-el' ) ) . '>' . $inner . '</div>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', 'text-align', $node->prop( 'align' ) );
		$css->set( ' .saha-icon-el__shape', 'font-size', $node->prop( 'size' ) );
		$css->set( ' .saha-icon-el__shape', 'color', $node->prop( 'color' ) );
		$css->set( ' .saha-icon-el__shape', 'background-color', $node->prop( 'background' ) );

		if ( null !== $node->prop( 'background' ) ) {
			$css->put( 'desktop', ' .saha-icon-el__shape', 'padding', '0.5em' );
			$css->put( 'desktop', ' .saha-icon-el__shape', 'border-radius', '50%' );
		}
	}
}
