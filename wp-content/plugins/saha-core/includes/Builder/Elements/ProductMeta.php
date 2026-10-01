<?php
/**
 * Element động: Mã, danh mục sản phẩm (động).
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * ProductMeta — SKU, danh mục, thẻ (woocommerce_template_single_meta).
 */
final class ProductMeta extends ProductElement {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return self::productDef(
			array(
				'type'     => 'product-meta',
				'name'     => __( 'Mã, danh mục sản phẩm (động)', 'saha-core' ),
				'icon'     => 'info',
				'controls' => array(),
			)
		);
	}

	/**
	 * Class riêng.
	 */
	protected function cssClass(): string {
		return 'saha-product-meta-el';
	}

	/**
	 * In.
	 *
	 * @param Node        $node    Node.
	 * @param \WC_Product $product Sản phẩm.
	 */
	protected function output( Node $node, \WC_Product $product ): void {
		woocommerce_template_single_meta();
	}
}
