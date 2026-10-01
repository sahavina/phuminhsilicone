<?php
/**
 * Element động: Giá sản phẩm (động).
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * ProductPrice — chế độ catalogue: "Liên hệ báo giá" (saha-core).
 */
final class ProductPrice extends ProductElement {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return self::productDef(
			array(
				'type'     => 'product-price',
				'name'     => __( 'Giá sản phẩm (động)', 'saha-core' ),
				'icon'     => 'tag',
				'controls' => array(),
			)
		);
	}

	/**
	 * Class riêng.
	 */
	protected function cssClass(): string {
		return 'saha-product-price';
	}

	/**
	 * In.
	 *
	 * @param Node        $node    Node.
	 * @param \WC_Product $product Sản phẩm.
	 */
	protected function output( Node $node, \WC_Product $product ): void {
		woocommerce_template_single_price();
	}
}
