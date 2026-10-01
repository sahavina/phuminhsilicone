<?php
/**
 * Element: nút mở menu di động.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\CssRules;
use Saha\Core\Builder\Icons;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * MenuToggle — nút ☰ mở Menu di động của cùng template (aria-controls, aria-expanded).
 */
final class MenuToggle extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'menu-toggle',
			'name'           => __( 'Nút menu (☰)', 'saha-core' ),
			'icon'           => 'menu',
			'category'       => 'header',
			'allowedParents' => array( 'header-zone' ),
			'controls'       => array(
				'label'     => array(
					'type'      => 'text',
					'label'     => __( 'Chữ cạnh icon', 'saha-core' ),
					'section'   => 'content',
					'maxLength' => 30,
					'help'      => __( 'Để trống = chỉ icon (trình đọc màn hình vẫn đọc "Mở menu").', 'saha-core' ),
				),
				'size'      => array(
					'type'    => 'size',
					'label'   => __( 'Cỡ icon', 'saha-core' ),
					'section' => 'style',
					'units'   => array( 'px' ),
					'min'     => 12,
					'max'     => 64,
				),
				'color'     => array(
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
		$label = (string) $node->prop( 'label', '' );
		$text  = '' !== $label
			? '<span class="saha-menu-toggle__label">' . esc_html( $label ) . '</span>'
			: '<span class="screen-reader-text">' . esc_html__( 'Mở menu', 'saha-core' ) . '</span>';

		return '<button type="button"' . $this->rootAttributes(
			$node,
			$ctx,
			array( 'saha-menu-toggle' ),
			array(
				'aria-controls'          => HeaderOffcanvas::panelId( $ctx->postId ),
				'aria-expanded'          => 'false',
				'data-saha-offcanvas'    => HeaderOffcanvas::panelId( $ctx->postId ),
			)
		) . '>' . Icons::svg( 'menu' ) . $text . '</button>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', 'font-size', $node->prop( 'size' ) );
		$css->set( '', 'color', $node->prop( 'color' ) );
	}
}
