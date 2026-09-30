<?php
/**
 * Element: Spacer.
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
 * Spacer — khoảng trống theo chiều dọc.
 */
final class Spacer extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'spacer',
			'name'           => __( 'Khoảng trống', 'saha-core' ),
			'icon'           => 'image-flip-vertical',
			'category'       => 'layout',
			'allowedParents' => self::CONTENT_PARENTS,
			'controls'       => array(
				'height' => array(
					'type'       => 'size',
					'label'      => __( 'Chiều cao', 'saha-core' ),
					'section'    => 'content',
					'responsive' => true,
					'default'    => '40px',
					'units'      => array( 'px', 'rem', 'vh' ),
					'min'        => 0,
					'max'        => 1000,
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
		return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-spacer' ), array( 'aria-hidden' => 'true' ) ) . '></div>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', 'height', $this->prop( $node, 'height' ) );
	}
}
