<?php
/**
 * Element: Container.
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
 * Container — hộp flex một lớp (dọc hoặc ngang).
 *
 * Thay cho Row › Column khi chỉ cần xếp vài element cạnh nhau: ít thẻ HTML hơn
 * (rủi ro R11 — DOM sâu làm chậm).
 */
final class Container extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'            => 'container',
			'name'            => __( 'Hộp (Container)', 'saha-core' ),
			'icon'            => 'screenoptions',
			'category'        => 'layout',
			'allowedParents'  => self::CONTENT_PARENTS,
			'allowedChildren' => array( '*' ),
			'controls'        => array(
				'direction'  => array(
					'type'       => 'select',
					'label'      => __( 'Hướng xếp', 'saha-core' ),
					'section'    => 'content',
					'responsive' => true,
					'default'    => 'column',
					'options'    => array(
						'column' => __( 'Dọc', 'saha-core' ),
						'row'    => __( 'Ngang', 'saha-core' ),
					),
				),
				'gap'        => array(
					'type'       => 'size',
					'label'      => __( 'Khoảng cách', 'saha-core' ),
					'section'    => 'content',
					'responsive' => true,
					'units'      => array( 'px', 'rem' ),
					'min'        => 0,
					'max'        => 200,
				),
				'justify'    => array(
					'type'       => 'select',
					'label'      => __( 'Căn theo hướng xếp', 'saha-core' ),
					'section'    => 'content',
					'responsive' => true,
					'options'    => array(
						'flex-start'    => __( 'Đầu', 'saha-core' ),
						'center'        => __( 'Giữa', 'saha-core' ),
						'flex-end'      => __( 'Cuối', 'saha-core' ),
						'space-between' => __( 'Giãn đều', 'saha-core' ),
					),
				),
				'align'      => array(
					'type'       => 'select',
					'label'      => __( 'Căn vuông góc', 'saha-core' ),
					'section'    => 'content',
					'responsive' => true,
					'options'    => array(
						'stretch'    => __( 'Kéo dài', 'saha-core' ),
						'flex-start' => __( 'Đầu', 'saha-core' ),
						'center'     => __( 'Giữa', 'saha-core' ),
						'flex-end'   => __( 'Cuối', 'saha-core' ),
					),
				),
				'wrap'       => array(
					'type'    => 'toggle',
					'label'   => __( 'Tự xuống dòng khi hết chỗ', 'saha-core' ),
					'section' => 'content',
					'default' => true,
				),
				// Kích thước (cũng chỉnh được bằng tay kéo trên canvas — saha-builder Canvas).
				'width'      => array(
					'type'       => 'size',
					'label'      => __( 'Độ rộng', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'units'      => array( '%', 'px', 'vw' ),
					'min'        => 1,
					'max'        => 3000,
				),
				'minHeight'  => array(
					'type'       => 'size',
					'label'      => __( 'Chiều cao tối thiểu', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'units'      => array( 'px', 'vh' ),
					'min'        => 0,
					'max'        => 3000,
				),
				'height'     => array(
					'type'       => 'size',
					'label'      => __( 'Chiều cao cố định', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'units'      => array( 'px', 'vh' ),
					'min'        => 0,
					'max'        => 3000,
					'help'       => __( 'Thường để trống, dùng "Chiều cao tối thiểu". Nội dung dài hơn → xem "Phần tràn".', 'saha-core' ),
				),
				'overflow'   => array(
					'type'       => 'select',
					'label'      => __( 'Phần tràn (khi đặt chiều cao)', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'options'    => array(
						'visible' => __( 'Hiện ra ngoài', 'saha-core' ),
						'hidden'  => __( 'Cắt bỏ', 'saha-core' ),
						'auto'    => __( 'Cuộn', 'saha-core' ),
					),
				),
				'maxWidth'   => array(
					'type'       => 'size',
					'label'      => __( 'Độ rộng tối đa', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'units'      => array( 'px', '%' ),
					'min'        => 1,
					'max'        => 3000,
				),
				'background' => array(
					'type'    => 'background',
					'label'   => __( 'Nền', 'saha-core' ),
					'section' => 'style',
				),
				'radius'     => array(
					'type'    => 'size',
					'label'   => __( 'Bo góc', 'saha-core' ),
					'section' => 'style',
					'units'   => array( 'px', '%' ),
					'min'     => 0,
					'max'     => 200,
				),
				'textColor'  => array(
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
	 * @param string        $content HTML con.
	 */
	public function render( Node $node, RenderContext $ctx, string $content ): string {
		$classes = array( 'saha-container-el' );
		$bg      = $node->prop( 'background' );

		if ( is_array( $bg ) && ! empty( $bg['overlay'] ) ) {
			$classes[] = 'saha-column--overlay';
		}

		if ( false === $this->prop( $node, 'wrap' ) ) {
			$classes[] = 'saha-container-el--nowrap';
		}

		return '<div' . $this->rootAttributes( $node, $ctx, $classes ) . '>' . $content . '</div>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', 'flex-direction', $node->prop( 'direction' ) );
		$css->set( '', 'gap', $node->prop( 'gap' ) );
		$css->set( '', 'justify-content', $node->prop( 'justify' ) );
		$css->set( '', 'align-items', $node->prop( 'align' ) );
		$css->set( '', 'max-width', $node->prop( 'maxWidth' ) );
		$css->set( '', 'width', $node->prop( 'width' ) );
		$css->set( '', 'min-height', $node->prop( 'minHeight' ) );
		$css->set( '', 'height', $node->prop( 'height' ) );
		$css->set( '', 'overflow', $node->prop( 'overflow' ) );
		$css->set( '', 'border-radius', $node->prop( 'radius' ) );
		$css->set( '', 'color', $node->prop( 'textColor' ) );
		$css->set( ' :where(h1, h2, h3, h4, h5, h6)', 'color', $node->prop( 'textColor' ) );

		Section::background( $css, '', $node->prop( 'background' ) );
	}
}
