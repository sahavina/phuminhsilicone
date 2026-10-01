<?php
/**
 * Element động: sản phẩm liên quan / bán kèm.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * ProductRelated — hàm của WooCommerce (thẻ sản phẩm giống shop, có thương hiệu + mã).
 */
final class ProductRelated extends ProductElement {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return self::productDef(
			array(
				'type'     => 'product-related',
				'name'     => __( 'Sản phẩm liên quan (động)', 'saha-core' ),
				'icon'     => 'products',
				'controls' => array(
					'source'  => array(
						'type'    => 'select',
						'label'   => __( 'Nguồn', 'saha-core' ),
						'section' => 'content',
						'default' => 'related',
						'options' => array(
							'related' => __( 'Liên quan (cùng danh mục/thẻ)', 'saha-core' ),
							'upsells' => __( 'Bán kèm (đặt trong sản phẩm)', 'saha-core' ),
						),
					),
					'limit'   => array(
						'type'    => 'number',
						'label'   => __( 'Số sản phẩm', 'saha-core' ),
						'section' => 'content',
						'default' => 4,
						'min'     => 1,
						'max'     => 12,
					),
					'columns' => array(
						'type'    => 'number',
						'label'   => __( 'Số cột', 'saha-core' ),
						'section' => 'content',
						'default' => 4,
						'min'     => 1,
						'max'     => 6,
					),
				),
			)
		);
	}

	/**
	 * Class riêng.
	 */
	protected function cssClass(): string {
		return 'saha-product-related woocommerce';
	}

	/**
	 * In.
	 *
	 * @param Node        $node    Node.
	 * @param \WC_Product $product Sản phẩm.
	 */
	protected function output( Node $node, \WC_Product $product ): void {
		$limit   = max( 1, min( 12, (int) $this->prop( $node, 'limit' ) ) );
		$columns = max( 1, min( 6, (int) $this->prop( $node, 'columns' ) ) );

		if ( 'upsells' === $this->prop( $node, 'source' ) ) {
			woocommerce_upsell_display( $limit, $columns );
			return;
		}

		woocommerce_related_products(
			array(
				'posts_per_page' => $limit,
				'columns'        => $columns,
			)
		);
	}
}
