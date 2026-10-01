<?php
/**
 * Element: hàng của header.
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
 * HeaderRow — một hàng (thanh trên / hàng chính / hàng dưới), 3 vùng trái–giữa–phải.
 *
 * Header khác nhau theo thiết bị = nhiều hàng, mỗi hàng bật "Ẩn trên …" ở tab Nâng
 * cao (ví dụ hàng chính desktop ẩn ở tablet/mobile, hàng chính mobile ẩn ở desktop).
 */
final class HeaderRow extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'            => 'header-row',
			'name'            => __( 'Hàng header', 'saha-core' ),
			'icon'            => 'table-row-after',
			'category'        => 'header',
			'allowedParents'  => array( 'site-header' ),
			'allowedChildren' => array( 'header-zone' ),
			'initialChildren' => array( 'header-zone', 'header-zone', 'header-zone' ),
			'controls'        => array(
				'height'     => array(
					'type'       => 'size',
					'label'      => __( 'Chiều cao', 'saha-core' ),
					'section'    => 'content',
					'responsive' => true,
					'units'      => array( 'px' ),
					'min'        => 24,
					'max'        => 300,
				),
				'fullWidth'  => array(
					'type'    => 'toggle',
					'label'   => __( 'Tràn màn hình', 'saha-core' ),
					'section' => 'content',
					'default' => false,
				),
				'hideSticky' => array(
					'type'    => 'toggle',
					'label'   => __( 'Ẩn khi header đang dính (thanh trên)', 'saha-core' ),
					'section' => 'content',
					'default' => false,
				),
				'background' => array(
					'type'    => 'color',
					'label'   => __( 'Màu nền', 'saha-core' ),
					'section' => 'style',
				),
				'textColor'  => array(
					'type'    => 'color',
					'label'   => __( 'Màu chữ', 'saha-core' ),
					'section' => 'style',
				),
				'border'     => array(
					'type'    => 'color',
					'label'   => __( 'Màu viền dưới', 'saha-core' ),
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
		$classes = array( 'saha-hb-row' );

		if ( $this->prop( $node, 'fullWidth' ) ) {
			$classes[] = 'saha-hb-row--full';
		}

		if ( $this->prop( $node, 'hideSticky' ) ) {
			$classes[] = 'saha-hb-row--hide-sticky';
		}

		return '<div' . $this->rootAttributes( $node, $ctx, $classes ) . '><div class="saha-hb-row__inner">' . $content . '</div></div>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( ' .saha-hb-row__inner', 'min-height', $node->prop( 'height' ) );
		$css->set( '', 'background-color', $node->prop( 'background' ) );
		$css->set( '', 'color', $node->prop( 'textColor' ) );
		// Không tô link trong bảng thả xuống (menu con, mega menu, danh mục): bảng có nền riêng.
		$css->set( ' a:not(.saha-btn):not(:where(.sub-menu a, .saha-mega a, .saha-catmenu__panel a, .saha-ls a))', 'color', $node->prop( 'textColor' ) );

		if ( null !== $node->prop( 'border' ) ) {
			$css->set( '', 'border-bottom', '1px solid ' . (string) $node->prop( 'border' ) );
		}
	}
}
