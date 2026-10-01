<?php
/**
 * Element động: Ảnh sản phẩm (động).
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * ProductGallery — ảnh chính + thumbnail, zoom, lightbox, slider của WooCommerce (theme bật hỗ trợ).
 */
final class ProductGallery extends ProductElement {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return self::productDef(
			array(
				'type'     => 'product-gallery',
				'name'     => __( 'Ảnh sản phẩm (động)', 'saha-core' ),
				'icon'     => 'image',
				'controls' => array(),
			)
		);
	}

	/**
	 * Class riêng.
	 */
	protected function cssClass(): string {
		return 'saha-product-gallery';
	}

	/**
	 * In.
	 *
	 * @param Node        $node    Node.
	 * @param \WC_Product $product Sản phẩm.
	 */
	protected function output( Node $node, \WC_Product $product ): void {
		woocommerce_show_product_images();
	}
}
