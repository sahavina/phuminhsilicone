<?php
/**
 * Element động: Phần dưới trang sản phẩm (hook).
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * ProductAfterSummary — chạy hook woocommerce_after_single_product_summary (tab, sản phẩm bán kèm, liên quan, phần theme/plugin gắn thêm).
 */
final class ProductAfterSummary extends ProductElement {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return self::productDef(
			array(
				'type'     => 'product-after-summary',
				'name'     => __( 'Phần dưới trang sản phẩm (hook)', 'saha-core' ),
				'icon'     => 'layers',
				'controls' => array(),
			)
		);
	}

	/**
	 * Class riêng.
	 */
	protected function cssClass(): string {
		return 'saha-product-after';
	}

	/**
	 * In.
	 *
	 * @param Node        $node    Node.
	 * @param \WC_Product $product Sản phẩm.
	 */
	protected function output( Node $node, \WC_Product $product ): void {
		do_action( 'woocommerce_after_single_product_summary' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- hook của WooCommerce.
	}
}
