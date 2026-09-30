<?php
/**
 * Element: Products.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\CssRules;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;
use Saha\Core\Catalog;
use Saha\Core\Taxonomies;

defined( 'ABSPATH' ) || exit;

/**
 * Products — lưới sản phẩm (mới, nổi bật, khuyến mại, bán chạy, theo danh mục/thương hiệu/ứng dụng).
 *
 * ID lấy qua `Catalog::product_ids()` (cache theo thế hệ, chỉ lấy ID). Thẻ sản phẩm
 * dùng template `content-product.php` của WooCommerce → theme/plugin tuỳ biến thẻ
 * sản phẩm một chỗ, áp cho cả shop lẫn builder; chế độ catalogue (ẩn giá) giữ nguyên.
 * `dynamic`: giá, tồn kho đổi liên tục → không cache HTML.
 */
final class Products extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		$sources = array_diff_key( Catalog::sources(), array( 'ids' => true ) );

		return array(
			'type'           => 'products',
			'name'           => __( 'Sản phẩm', 'saha-core' ),
			'icon'           => 'products',
			'category'       => 'woocommerce',
			'allowedParents' => self::CONTENT_PARENTS,
			'dynamic'        => true,
			'controls'       => array(
				'source'      => array(
					'type'    => 'select',
					'label'   => __( 'Nguồn', 'saha-core' ),
					'section' => 'content',
					'default' => 'latest',
					'options' => $sources,
				),
				'category'    => array(
					'type'     => 'term',
					'taxonomy' => 'product_cat',
					'label'    => __( 'Danh mục', 'saha-core' ),
					'section'  => 'content',
					'help'     => __( 'Dùng khi nguồn là "Theo danh mục"; với nguồn khác thì lọc thêm.', 'saha-core' ),
				),
				'brand'       => array(
					'type'     => 'term',
					'taxonomy' => Taxonomies::BRAND,
					'label'    => __( 'Thương hiệu', 'saha-core' ),
					'section'  => 'content',
				),
				'application' => array(
					'type'     => 'term',
					'taxonomy' => Taxonomies::APPLICATION,
					'label'    => __( 'Ứng dụng', 'saha-core' ),
					'section'  => 'content',
				),
				'orderby'     => array(
					'type'    => 'select',
					'label'   => __( 'Sắp xếp', 'saha-core' ),
					'section' => 'content',
					'default' => 'date',
					'options' => array(
						'date'       => __( 'Mới nhất', 'saha-core' ),
						'popularity' => __( 'Bán chạy', 'saha-core' ),
						'title'      => __( 'Tên A–Z', 'saha-core' ),
						'menu_order' => __( 'Thứ tự tuỳ chỉnh', 'saha-core' ),
						'rand'       => __( 'Ngẫu nhiên', 'saha-core' ),
					),
				),
				'limit'       => array(
					'type'    => 'number',
					'label'   => __( 'Số sản phẩm', 'saha-core' ),
					'section' => 'content',
					'default' => 8,
					'min'     => 1,
					'max'     => Catalog::MAX_LIMIT,
				),
				'hideOutOfStock' => array(
					'type'    => 'toggle',
					'label'   => __( 'Ẩn sản phẩm hết hàng', 'saha-core' ),
					'section' => 'content',
					'default' => false,
				),
				'columns'     => array(
					'type'       => 'number',
					'label'      => __( 'Số cột', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'default'    => array(
						'desktop' => 4,
						'tablet'  => 3,
						'mobile'  => 2,
					),
					'min'        => 1,
					'max'        => 6,
				),
				'gap'         => array(
					'type'       => 'size',
					'label'      => __( 'Khoảng cách', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'units'      => array( 'px', 'rem' ),
					'min'        => 0,
					'max'        => 100,
				),
			),
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
		if ( ! function_exists( 'wc_get_template_part' ) ) {
			return $ctx->editor ? $this->notice( $node, $ctx, __( 'Cần bật WooCommerce.', 'saha-core' ) ) : '';
		}

		$ids = Catalog::product_ids(
			array(
				'source'            => (string) $this->prop( $node, 'source' ),
				'category'          => (string) $node->prop( 'category', '' ),
				'brand'             => (string) $node->prop( 'brand', '' ),
				'application'       => (string) $node->prop( 'application', '' ),
				'orderby'           => (string) $this->prop( $node, 'orderby' ),
				'limit'             => (int) $this->prop( $node, 'limit' ),
				'hide_out_of_stock' => (bool) $this->prop( $node, 'hideOutOfStock' ),
			)
		);

		if ( ! $ids ) {
			return $ctx->editor ? $this->notice( $node, $ctx, __( 'Không có sản phẩm phù hợp.', 'saha-core' ) ) : '';
		}

		// Nạp trước post + meta một lần (tránh N+1 khi template đọc giá, ảnh…).
		_prime_post_caches( $ids, true, true );

		global $post;
		$previous = $post;

		ob_start();

		echo '<ul class="products saha-products-grid">';

		foreach ( $ids as $id ) {
			$post = get_post( $id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- vòng lặp WooCommerce chuẩn, khôi phục ngay sau.

			if ( ! $post ) {
				continue;
			}

			setup_postdata( $post );
			wc_get_template_part( 'content', 'product' );
		}

		echo '</ul>';

		$post = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		wp_reset_postdata();

		// Bọc .woocommerce như shortcode [products] để CSS WooCommerce/theme áp dụng.
		return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-products', 'woocommerce' ) ) . '>' . (string) ob_get_clean() . '</div>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( ' .saha-products-grid', '--saha-cols', $this->prop( $node, 'columns' ) );
		$css->set( ' .saha-products-grid', 'gap', $node->prop( 'gap' ) );
	}

	/**
	 * Thông báo trong editor.
	 *
	 * @param Node          $node    Node.
	 * @param RenderContext $ctx     Ngữ cảnh.
	 * @param string        $message Nội dung.
	 */
	private function notice( Node $node, RenderContext $ctx, string $message ): string {
		return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-products', 'saha-image--empty' ) ) . '>' . esc_html( $message ) . '</div>';
	}
}
