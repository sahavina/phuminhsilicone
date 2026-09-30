<?php
/**
 * Element: Divider.
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
 * Divider — đường kẻ ngang (<hr>).
 */
final class Divider extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'divider',
			'name'           => __( 'Đường kẻ', 'saha-core' ),
			'icon'           => 'minus',
			'category'       => 'layout',
			'allowedParents' => self::CONTENT_PARENTS,
			'controls'       => array(
				'style'  => array(
					'type'    => 'select',
					'label'   => __( 'Kiểu nét', 'saha-core' ),
					'section' => 'style',
					'default' => 'solid',
					'options' => array(
						'solid'  => __( 'Liền', 'saha-core' ),
						'dashed' => __( 'Gạch', 'saha-core' ),
						'dotted' => __( 'Chấm', 'saha-core' ),
					),
				),
				'weight' => array(
					'type'    => 'size',
					'label'   => __( 'Độ dày', 'saha-core' ),
					'section' => 'style',
					'default' => '1px',
					'units'   => array( 'px' ),
					'min'     => 1,
					'max'     => 20,
				),
				'width'  => array(
					'type'       => 'size',
					'label'      => __( 'Độ dài', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'units'      => array( '%', 'px' ),
					'min'        => 1,
					'max'        => 3000,
				),
				'align'  => array(
					'type'    => 'align',
					'label'   => __( 'Căn lề', 'saha-core' ),
					'section' => 'style',
					'options' => array( 'left', 'center', 'right' ),
				),
				'color'  => array(
					'type'    => 'color',
					'label'   => __( 'Màu', 'saha-core' ),
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
		return '<hr' . $this->rootAttributes( $node, $ctx, array( 'saha-divider' ) ) . '>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', 'border-top-style', $node->prop( 'style' ) );
		$css->set( '', 'border-top-width', $node->prop( 'weight' ) );
		$css->set( '', 'border-top-color', $node->prop( 'color' ) );
		$css->set( '', 'width', $node->prop( 'width' ) );
		$css->set(
			'',
			'margin-inline',
			$node->prop( 'align' ),
			static fn( $v ) => array(
				'left'   => '0 auto',
				'center' => 'auto',
				'right'  => 'auto 0',
			)[ $v ] ?? null
		);
	}
}
