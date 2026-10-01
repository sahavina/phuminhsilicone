<?php
/**
 * Element: Tìm kiếm.
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
 * Search — form tìm sản phẩm của WooCommerce (tìm theo SKU/thương hiệu nhờ Search
 * của saha-core); không có WooCommerce thì form tìm bài viết.
 * `dynamic`: ô tìm kiếm điền sẵn từ khoá đang tìm.
 */
final class Search extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'search',
			'name'           => __( 'Tìm kiếm', 'saha-core' ),
			'icon'           => 'search',
			'category'       => 'header',
			'allowedParents' => self::CONTENT_PARENTS,
			'dynamic'        => true,
			'controls'       => array(
				'products' => array(
					'type'    => 'toggle',
					'label'   => __( 'Tìm sản phẩm (WooCommerce)', 'saha-core' ),
					'section' => 'content',
					'default' => true,
				),
				'width'    => array(
					'type'       => 'size',
					'label'      => __( 'Độ rộng', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'units'      => array( 'px', '%' ),
					'min'        => 80,
					'max'        => 1200,
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
		$form = $this->prop( $node, 'products' ) && function_exists( 'get_product_search_form' )
			? (string) get_product_search_form( false )
			: (string) get_search_form( array( 'echo' => false ) );

		return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-search-el' ) ) . '>' . $form . '</div>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', 'width', $node->prop( 'width' ) );
	}
}
