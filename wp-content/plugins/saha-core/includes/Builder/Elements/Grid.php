<?php
/**
 * Element: lưới (CSS grid) — xếp element con thành nhiều cột đều nhau.
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
 * Grid — khác Hàng / Cột: không cần tạo từng cột; mỗi element con là một ô, tự xuống dòng theo
 * số cột (từng thiết bị). Hợp cho lưới thẻ Icon Box, Banner, Hộp… "Stack" (xếp chồng / hàng ngang)
 * đã có ở element Hộp (Container: hướng dọc / ngang, khoảng cách, xuống dòng).
 */
final class Grid extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'            => 'grid',
			'name'            => __( 'Lưới (Grid)', 'saha-core' ),
			'icon'            => 'grid-view',
			'category'        => 'layout',
			'allowedParents'  => self::CONTENT_PARENTS,
			'allowedChildren' => array( '*' ),
			'initialChildren' => array( 'container', 'container', 'container' ),
			'controls'        => array(
				'columns' => array(
					'type'       => 'number',
					'label'      => __( 'Số cột', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'default'    => array(
						'desktop' => 3,
						'tablet'  => 2,
						'mobile'  => 1,
					),
					'min'        => 1,
					'max'        => 12,
				),
				'gap'     => array(
					'type'       => 'size',
					'label'      => __( 'Khoảng cách', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'units'      => array( 'px', 'rem' ),
					'min'        => 0,
					'max'        => 120,
				),
				'align'   => array(
					'type'    => 'select',
					'label'   => __( 'Căn dọc trong ô', 'saha-core' ),
					'section' => 'style',
					'default' => 'stretch',
					'options' => array(
						'stretch' => __( 'Kéo cao bằng nhau', 'saha-core' ),
						'start'   => __( 'Trên', 'saha-core' ),
						'center'  => __( 'Giữa', 'saha-core' ),
						'end'     => __( 'Dưới', 'saha-core' ),
					),
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
		if ( '' === trim( $content ) && $ctx->editor ) {
			$content = '<div class="saha-image--empty">' . esc_html__( 'Thêm element vào lưới', 'saha-core' ) . '</div>';
		}

		return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-grid' ) ) . '>' . $content . '</div>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', '--saha-grid-cols', $this->prop( $node, 'columns' ) );
		$css->set( '', 'gap', $node->prop( 'gap' ) );
		$css->set( '', 'align-items', $this->prop( $node, 'align' ) );
	}
}
