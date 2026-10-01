<?php
/**
 * Nền cho element động của trang sản phẩm.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * ProductElement — mỗi element gọi đúng hàm template của WooCommerce (`woocommerce_template_single_*`)
 * với `global $product` là sản phẩm đang xem → hook, plugin, chế độ catalogue (saha-core) vẫn chạy
 * như trang sản phẩm mặc định.
 */
abstract class ProductElement extends DynamicElement {

	/**
	 * Định nghĩa chung.
	 *
	 * @param array<string, mixed> $def Định nghĩa riêng.
	 * @return array<string, mixed>
	 */
	protected static function productDef( array $def ): array {
		return self::dynamicDef( $def + array( 'category' => 'product-template' ) );
	}

	/**
	 * In phần của trang sản phẩm (global $product đã đặt).
	 *
	 * @param Node        $node    Node.
	 * @param \WC_Product $product Sản phẩm.
	 */
	abstract protected function output( Node $node, \WC_Product $product ): void;

	/**
	 * Class riêng của thẻ gốc.
	 */
	protected function cssClass(): string {
		return 'saha-product-part';
	}

	/**
	 * Render.
	 *
	 * @param Node          $node    Node.
	 * @param RenderContext $ctx     Ngữ cảnh.
	 * @param string        $content HTML con (không dùng).
	 */
	public function render( Node $node, RenderContext $ctx, string $content ): string {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return $this->placeholder( $node, $ctx, __( 'Cần bật WooCommerce.', 'saha-core' ) );
		}

		$html = self::withSubject(
			$ctx,
			function ( \WP_Post $post ) use ( $node ): string {
				$product = $GLOBALS['product'] ?? null;

				if ( 'product' !== $post->post_type || ! $product instanceof \WC_Product ) {
					return '';
				}

				return self::capture( fn() => $this->output( $node, $product ) );
			}
		);

		if ( null === $html || '' === trim( $html ) ) {
			return $this->placeholder(
				$node,
				$ctx,
				/* translators: %s: tên element */
				sprintf( __( '%s — chỉ có dữ liệu trong template Trang sản phẩm.', 'saha-core' ), (string) $this->def()['name'] )
			);
		}

		return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-dynamic', $this->cssClass() ) ) . '>' . $html . '</div>';
	}
}
