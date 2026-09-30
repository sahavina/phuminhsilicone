<?php
/**
 * Element: Column.
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
 * Column — chỉ nằm trong Row. Độ rộng theo % (xem Row).
 */
final class Column extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'            => 'column',
			'name'            => __( 'Cột', 'saha-core' ),
			'icon'            => 'align-pull-left',
			'category'        => 'layout',
			'allowedParents'  => array( 'row' ),
			'allowedChildren' => array( 'row', 'heading', 'text', 'button', 'image' ),
			'controls'        => array(
				'width'         => array(
					'type'       => 'size',
					'label'      => __( 'Độ rộng', 'saha-core' ),
					'section'    => 'content',
					'responsive' => true,
					'units'      => array( '%' ),
					'min'        => 5,
					'max'        => 100,
					'help'       => __( 'Để trống = chia đều phần còn lại của hàng.', 'saha-core' ),
				),
				'verticalAlign' => array(
					'type'       => 'select',
					'label'      => __( 'Căn dọc nội dung', 'saha-core' ),
					'section'    => 'content',
					'responsive' => true,
					'options'    => array(
						'top'           => __( 'Trên', 'saha-core' ),
						'center'        => __( 'Giữa', 'saha-core' ),
						'bottom'        => __( 'Dưới', 'saha-core' ),
						'space-between' => __( 'Giãn đều', 'saha-core' ),
					),
				),
				'background'    => array(
					'type'    => 'background',
					'label'   => __( 'Nền', 'saha-core' ),
					'section' => 'style',
				),
				'textColor'     => array(
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
		$classes = array( 'saha-column' );
		$bg      = $node->prop( 'background' );

		if ( is_array( $bg ) && ! empty( $bg['overlay'] ) ) {
			$classes[] = 'saha-column--overlay';
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
		$css->set( '', '--saha-col', $node->prop( 'width' ), static fn( $v ) => null === Row::fraction( $v ) ? null : Row::format( (float) Row::fraction( $v ) ) );
		$css->set(
			'',
			'justify-content',
			$node->prop( 'verticalAlign' ),
			static fn( $v ) => array(
				'top'           => 'flex-start',
				'center'        => 'center',
				'bottom'        => 'flex-end',
				'space-between' => 'space-between',
			)[ $v ] ?? null
		);
		$css->set( '', 'color', $node->prop( 'textColor' ) );
		// Theme thường đặt màu riêng cho h1–h6: áp màu chữ cho tiêu đề bên trong, specificity
		// (0,1,0) như style riêng của Heading — CSS của con sinh sau nên màu tự đặt vẫn thắng.
		$css->set( ' :where(h1, h2, h3, h4, h5, h6)', 'color', $node->prop( 'textColor' ) );

		Section::background( $css, '', $node->prop( 'background' ) );
	}
}
