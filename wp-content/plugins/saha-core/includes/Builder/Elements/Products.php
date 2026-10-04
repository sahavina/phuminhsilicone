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
use Saha\Core\Performance\CardImageSizes;
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
				'tabs'        => array(
					'type'    => 'select',
					'label'   => __( 'Tab lọc', 'saha-core' ),
					'section' => 'content',
					'default' => 'none',
					'options' => array(
						'none'       => __( 'Không', 'saha-core' ),
						'categories' => __( 'Theo danh mục ("Tất cả" + danh mục con)', 'saha-core' ),
					),
					'help'    => __( 'Danh mục con của "Danh mục" ở trên; để trống = danh mục cấp 1.', 'saha-core' ),
				),
				'tabsLimit'   => array(
					'type'    => 'number',
					'label'   => __( 'Số tab danh mục', 'saha-core' ),
					'section' => 'content',
					'default' => 6,
					'min'     => 2,
					'max'     => 8,
				),
				'tabAll'      => array(
					'type'      => 'text',
					'label'     => __( 'Chữ tab đầu', 'saha-core' ),
					'section'   => 'content',
					'default'   => __( 'Tất cả', 'saha-core' ),
					'maxLength' => 40,
				),
				'display'     => self::displayControl(),
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
		// Ảnh thẻ lấy `sizes` theo số cột của element (không phải 400px mặc định của WooCommerce).
		return CardImageSizes::run( $this->prop( $node, 'columns' ), fn (): string => $this->renderProducts( $node, $ctx ) );
	}

	/**
	 * Render lưới / băng chuyền / tab.
	 *
	 * @param Node          $node Node.
	 * @param RenderContext $ctx  Ngữ cảnh.
	 */
	private function renderProducts( Node $node, RenderContext $ctx ): string {
		if ( ! function_exists( 'wc_get_template_part' ) ) {
			return $ctx->editor ? $this->notice( $node, $ctx, __( 'Cần bật WooCommerce.', 'saha-core' ) ) : '';
		}

		$args = $this->queryArgs( $node );
		$ids  = Catalog::product_ids( $args );

		if ( 'categories' === $this->prop( $node, 'tabs' ) ) {
			$tabs = $this->tabs( $node, $ctx, $args, $ids );

			if ( '' !== $tabs ) {
				return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-products', 'saha-products--tabs', 'woocommerce' ) ) . '>' . $tabs . '</div>';
			}
		}

		if ( ! $ids ) {
			return $ctx->editor ? $this->notice( $node, $ctx, __( 'Không có sản phẩm phù hợp.', 'saha-core' ) ) : '';
		}

		// Bọc .woocommerce như shortcode [products] để CSS WooCommerce/theme áp dụng.
		if ( 'carousel' === $this->prop( $node, 'display' ) ) {
			return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-products', 'woocommerce', 'saha-slider', 'saha-pcarousel' ), array( 'data-saha-slider' => '1' ) ) . '>'
				. self::grid( $ids, true ) . self::carouselArrows( __( 'sản phẩm', 'saha-core' ) ) . '</div>';
		}

		return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-products', 'woocommerce' ) ) . '>' . self::grid( $ids ) . '</div>';
	}

	/**
	 * Tham số truy vấn từ thiết lập.
	 *
	 * @param Node $node Node.
	 * @return array<string, mixed>
	 */
	private function queryArgs( Node $node ): array {
		return array(
			'source'            => (string) $this->prop( $node, 'source' ),
			'category'          => (string) $node->prop( 'category', '' ),
			'brand'             => (string) $node->prop( 'brand', '' ),
			'application'       => (string) $node->prop( 'application', '' ),
			'orderby'           => (string) $this->prop( $node, 'orderby' ),
			'limit'             => (int) $this->prop( $node, 'limit' ),
			'hide_out_of_stock' => (bool) $this->prop( $node, 'hideOutOfStock' ),
		);
	}

	/**
	 * Lưới thẻ sản phẩm (template content-product của WooCommerce).
	 *
	 * @param int[] $ids      Sản phẩm.
	 * @param bool  $carousel Làm track của băng chuyền.
	 */
	private static function grid( array $ids, bool $carousel = false ): string {
		// Nạp trước post + meta một lần (tránh N+1 khi template đọc giá, ảnh…).
		_prime_post_caches( $ids, true, true );

		global $post;
		$previous = $post;

		ob_start();

		echo $carousel
			? '<ul class="products saha-products-grid saha-slider__track" tabindex="0" aria-label="' . esc_attr__( 'Danh sách sản phẩm (cuộn ngang)', 'saha-core' ) . '">'
			: '<ul class="products saha-products-grid">';

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

		return (string) ob_get_clean();
	}

	/**
	 * Tab "Tất cả" + danh mục (ARIA tabs). Mỗi tab một lưới dựng sẵn (ẩn), không AJAX:
	 * HTML lấy từ cache Catalog; ảnh trong tab ẩn tải lười. Danh mục không có sản phẩm khớp → bỏ tab.
	 *
	 * @param Node                 $node Node.
	 * @param RenderContext        $ctx  Ngữ cảnh.
	 * @param array<string, mixed> $args Truy vấn gốc.
	 * @param int[]                $all  Sản phẩm tab đầu.
	 */
	private function tabs( Node $node, RenderContext $ctx, array $args, array $all ): string {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return '';
		}

		$parent = 0;

		if ( '' !== (string) $args['category'] ) {
			$term   = get_term_by( 'slug', (string) $args['category'], 'product_cat' );
			$parent = $term instanceof \WP_Term ? (int) $term->term_id : 0;
		}

		$skip  = (int) get_option( 'default_product_cat', 0 );
		$limit = max( 2, min( 8, (int) $this->prop( $node, 'tabsLimit' ) ) );
		$panes = array();

		if ( $all ) {
			$panes[] = array( (string) $this->prop( $node, 'tabAll' ), $all );
		}

		foreach ( Catalog::terms( 'product_cat', array( 'parent' => $parent, 'limit' => $limit + 1 ) ) as $term ) {
			if ( (int) $term['id'] === $skip || count( $panes ) > $limit ) {
				continue;
			}

			$ids = Catalog::product_ids(
				array_merge(
					$args,
					array(
						'source'   => 'featured' === $args['source'] || 'sale' === $args['source'] ? $args['source'] : 'category',
						'category' => (string) $term['slug'],
					)
				)
			);

			if ( $ids ) {
				$panes[] = array( (string) $term['name'], $ids );
			}
		}

		if ( count( $panes ) < 2 ) {
			return '';
		}

		$base = 'saha-pt-' . $node->id;
		$list = '';
		$body = '';

		foreach ( $panes as $i => list( $label, $ids ) ) {
			$first = 0 === $i;
			$list .= '<button type="button" role="tab" class="saha-ptabs__tab" id="' . esc_attr( $base . '-t' . $i ) . '" aria-controls="' . esc_attr( $base . '-p' . $i ) . '" aria-selected="' . ( $first ? 'true' : 'false' ) . '"' . ( $first ? '' : ' tabindex="-1"' ) . '>' . esc_html( $label ) . '</button>';
			$body .= '<div role="tabpanel" class="saha-ptabs__panel" id="' . esc_attr( $base . '-p' . $i ) . '" aria-labelledby="' . esc_attr( $base . '-t' . $i ) . '" tabindex="0"' . ( $first ? '' : ' hidden' ) . '>' . self::grid( $ids ) . '</div>';
		}

		return '<div class="saha-ptabs" data-saha-tabs><div class="saha-ptabs__list" role="tablist" aria-label="' . esc_attr__( 'Lọc theo danh mục', 'saha-core' ) . '">' . $list . '</div>' . $body . '</div>';
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
		$css->set( '', '--saha-slider-per-view', $this->prop( $node, 'columns' ) );
		$css->set( '', '--saha-slider-gap', $node->prop( 'gap' ) );
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
