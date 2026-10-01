<?php
/**
 * Element: khối đánh giá khách hàng (chứa các Testimonial).
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
 * Testimonials — lưới N cột (1 cột = danh sách dọc như cột bên phải trang mẫu).
 */
final class Testimonials extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'            => 'testimonials',
			'name'            => __( 'Đánh giá khách hàng', 'saha-core' ),
			'icon'            => 'users',
			'category'        => 'marketing',
			'allowedParents'  => self::CONTENT_PARENTS,
			'allowedChildren' => array( 'testimonial' ),
			'initialChildren' => array( 'testimonial', 'testimonial', 'testimonial' ),
			'controls'        => array(
				'columns'    => array(
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
					'max'        => 4,
				),
				'gap'        => array(
					'type'       => 'size',
					'label'      => __( 'Khoảng cách', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'units'      => array( 'px' ),
					'min'        => 0,
					'max'        => 60,
				),
				'accent'     => array(
					'type'    => 'color',
					'label'   => __( 'Màu viền nhấn / sao', 'saha-core' ),
					'section' => 'style',
				),
				'background' => array(
					'type'    => 'color',
					'label'   => __( 'Nền thẻ', 'saha-core' ),
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
	 * @param string        $content HTML các mục.
	 */
	public function render( Node $node, RenderContext $ctx, string $content ): string {
		return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-testimonials' ) ) . '>' . $content . '</div>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', '--saha-cols', $this->prop( $node, 'columns' ) );
		$css->set( '', 'gap', $node->prop( 'gap' ) );
		$css->set( '', '--saha-testimonial-accent', $node->prop( 'accent' ) );
		$css->set( '', '--saha-testimonial-bg', $node->prop( 'background' ) );
	}
}
