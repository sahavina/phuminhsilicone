<?php
/**
 * Element động: Tab mô tả / thông số / đánh giá (động).
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * ProductTabs — tab của WooCommerce + tab do plugin thêm.
 */
final class ProductTabs extends ProductElement {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return self::productDef(
			array(
				'type'     => 'product-tabs',
				'name'     => __( 'Tab mô tả / thông số / đánh giá (động)', 'saha-core' ),
				'icon'     => 'layers',
				'controls' => array(),
			)
		);
	}

	/**
	 * Class riêng.
	 */
	protected function cssClass(): string {
		return 'saha-product-tabs';
	}

	/**
	 * In.
	 *
	 * @param Node        $node    Node.
	 * @param \WC_Product $product Sản phẩm.
	 */
	protected function output( Node $node, \WC_Product $product ): void {
		woocommerce_output_product_data_tabs();
	}
}
