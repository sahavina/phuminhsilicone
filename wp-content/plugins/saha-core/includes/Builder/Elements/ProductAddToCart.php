<?php
/**
 * Element động: thêm vào giỏ / Mua ngay / Yêu cầu báo giá.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\Schema\Node;
use Saha\Core\WooCommerce\CatalogMode;
use Saha\Core\WooCommerce\QuoteList;

defined( 'ABSPATH' ) || exit;

/**
 * ProductAddToCart — form thêm vào giỏ của WooCommerce (số lượng, biến thể, nút Mua ngay
 * của saha-core). Chế độ catalogue: sản phẩm không mua được → nút "Yêu cầu báo giá" mở
 * modal báo giá của theme (có sẵn tên + mã sản phẩm).
 */
final class ProductAddToCart extends ProductElement {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return self::productDef(
			array(
				'type'     => 'product-add-to-cart',
				'name'     => __( 'Thêm vào giỏ / Báo giá (động)', 'saha-core' ),
				'icon'     => 'cart',
				'controls' => array(
					'quoteButton' => array(
						'type'    => 'toggle',
						'label'   => __( 'Nút "Yêu cầu báo giá" khi không bán (chế độ catalogue)', 'saha-core' ),
						'section' => 'content',
						'default' => true,
					),
					'quoteLabel'  => array(
						'type'      => 'text',
						'label'     => __( 'Chữ trên nút báo giá', 'saha-core' ),
						'section'   => 'content',
						'default'   => __( 'Yêu cầu báo giá', 'saha-core' ),
						'maxLength' => 60,
					),
				),
			)
		);
	}

	/**
	 * Class riêng.
	 */
	protected function cssClass(): string {
		return 'saha-product-cart';
	}

	/**
	 * In.
	 *
	 * @param Node        $node    Node.
	 * @param \WC_Product $product Sản phẩm.
	 */
	protected function output( Node $node, \WC_Product $product ): void {
		if ( $product->is_purchasable() || $product->is_type( 'external' ) ) {
			woocommerce_template_single_add_to_cart();
			return;
		}

		if ( ! $this->prop( $node, 'quoteButton' ) || ( ! CatalogMode::enabled() && $product->is_in_stock() ) ) {
			return;
		}

		do_action( 'saha_quote_modal_needed' );

		printf(
			'<button type="button" class="saha-btn saha-btn--primary saha-btn--lg" data-saha-open-quote="1" data-saha-product-id="%1$d" data-saha-product-name="%2$s" data-saha-sku="%3$s">%4$s</button>',
			(int) $product->get_id(),
			esc_attr( $product->get_name() ),
			esc_attr( (string) $product->get_sku() ),
			esc_html( (string) $this->prop( $node, 'quoteLabel' ) )
		);

		echo QuoteList::button( $product ); // phpcs:ignore WordPress.Security.EscapeOutput -- button() đã escape.
	}
}
