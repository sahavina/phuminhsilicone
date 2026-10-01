<?php
/**
 * Element động: danh sách sản phẩm của shop / danh mục / thương hiệu.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * ProductArchive — vòng lặp WooCommerce trên main query: hook `woocommerce_before_shop_loop`
 * (số kết quả, sắp xếp, bộ lọc của saha-theme), thẻ sản phẩm, phân trang, trạng thái rỗng.
 * Số cột/số sản phẩm theo Theme Options → WooCommerce (như shop mặc định).
 */
final class ProductArchive extends DynamicElement {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return self::dynamicDef(
			array(
				'type'     => 'product-archive',
				'name'     => __( 'Danh sách sản phẩm (động)', 'saha-core' ),
				'icon'     => 'products',
				'category' => 'product-template',
				'controls' => array(
					'toolbar' => array(
						'type'    => 'toggle',
						'label'   => __( 'Thanh trên danh sách (số kết quả, sắp xếp, bộ lọc)', 'saha-core' ),
						'section' => 'content',
						'default' => true,
					),
				),
			)
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
		if ( ! function_exists( 'woocommerce_product_loop' ) ) {
			return $this->placeholder( $node, $ctx, __( 'Cần bật WooCommerce.', 'saha-core' ) );
		}

		$toolbar = (bool) $this->prop( $node, 'toolbar' );
		$html    = self::withArchive(
			$ctx,
			static function () use ( $toolbar ): string {
				return self::capture(
					static function () use ( $toolbar ): void {
						if ( ! woocommerce_product_loop() ) {
							do_action( 'woocommerce_no_products_found' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- hook của WooCommerce.
							return;
						}

						if ( $toolbar ) {
							do_action( 'woocommerce_before_shop_loop' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
						}

						woocommerce_product_loop_start();

						while ( have_posts() ) {
							the_post();
							do_action( 'woocommerce_shop_loop' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
							wc_get_template_part( 'content', 'product' );
						}

						woocommerce_product_loop_end();
						do_action( 'woocommerce_after_shop_loop' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
					}
				);
			}
		);

		return '' === trim( $html )
			? $this->placeholder( $node, $ctx, __( 'Danh sách sản phẩm', 'saha-core' ) )
			: '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-dynamic', 'saha-product-archive', 'woocommerce' ) ) . '>' . $html . '</div>';
	}
}
