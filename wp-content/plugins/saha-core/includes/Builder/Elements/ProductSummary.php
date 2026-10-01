<?php
/**
 * Element động: Khối thông tin sản phẩm (hook).
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * ProductSummary — chạy toàn bộ hook woocommerce_single_product_summary (tiêu đề, giá, mô tả ngắn, thêm vào giỏ, mã, cùng mọi phần do theme/plugin gắn — ví dụ CTA báo giá của saha-theme). Dùng khi muốn giữ nguyên bố cục WooCommerce cho cột phải.
 */
final class ProductSummary extends ProductElement {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return self::productDef(
			array(
				'type'     => 'product-summary',
				'name'     => __( 'Khối thông tin sản phẩm (hook)', 'saha-core' ),
				'icon'     => 'layers',
				'controls' => array(),
			)
		);
	}

	/**
	 * Class riêng.
	 */
	protected function cssClass(): string {
		return 'saha-product-summary';
	}

	/**
	 * In.
	 *
	 * @param Node        $node    Node.
	 * @param \WC_Product $product Sản phẩm.
	 */
	protected function output( Node $node, \WC_Product $product ): void {
		echo '<div class="summary entry-summary">';
		do_action( 'woocommerce_single_product_summary' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- hook của WooCommerce.
		echo '</div>';
	}
}
