<?php
/**
 * Element: Product Categories.
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
 * Product Categories — lưới danh mục / ứng dụng (ảnh, tên, số sản phẩm).
 *
 * Dữ liệu qua `Catalog::terms()` (có cache). Danh sách ít đổi nhưng số sản phẩm
 * đổi → element tĩnh: cache HTML theo thế hệ cache SAHA (bị xoá khi lưu sản phẩm/term).
 */
final class ProductCategories extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'product-categories',
			'name'           => __( 'Danh mục sản phẩm', 'saha-core' ),
			'icon'           => 'category',
			'category'       => 'woocommerce',
			'allowedParents' => self::CONTENT_PARENTS,
			'controls'       => array(
				'taxonomy'  => array(
					'type'    => 'select',
					'label'   => __( 'Loại', 'saha-core' ),
					'section' => 'content',
					'default' => 'product_cat',
					'options' => array(
						'product_cat'           => __( 'Danh mục sản phẩm', 'saha-core' ),
						Taxonomies::APPLICATION => __( 'Ứng dụng', 'saha-core' ),
					),
				),
				'parent'    => array(
					'type'     => 'term',
					'taxonomy' => 'product_cat',
					'label'    => __( 'Danh mục cha', 'saha-core' ),
					'section'  => 'content',
					'help'     => __( 'Để trống = danh mục cấp cao nhất. Chọn một danh mục để hiện các danh mục con của nó.', 'saha-core' ),
				),
				'limit'     => array(
					'type'    => 'number',
					'label'   => __( 'Số mục', 'saha-core' ),
					'section' => 'content',
					'default' => 8,
					'min'     => 1,
					'max'     => 48,
				),
				'orderby'   => array(
					'type'    => 'select',
					'label'   => __( 'Sắp xếp', 'saha-core' ),
					'section' => 'content',
					'default' => 'menu_order',
					'options' => array(
						'menu_order' => __( 'Thứ tự tuỳ chỉnh', 'saha-core' ),
						'name'       => __( 'Tên A–Z', 'saha-core' ),
						'count'      => __( 'Nhiều sản phẩm nhất', 'saha-core' ),
					),
				),
				'showCount' => array(
					'type'    => 'toggle',
					'label'   => __( 'Hiện số sản phẩm', 'saha-core' ),
					'section' => 'content',
					'default' => true,
				),
				'columns'   => array(
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
					'max'        => 8,
				),
				'gap'       => array(
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
		$taxonomy = (string) $this->prop( $node, 'taxonomy' );
		$parent   = 0;
		$slug     = (string) $node->prop( 'parent', '' );

		if ( 'product_cat' === $taxonomy && '' !== $slug ) {
			$term   = get_term_by( 'slug', $slug, 'product_cat' );
			$parent = $term instanceof \WP_Term ? $term->term_id : 0;
		}

		$terms = Catalog::terms(
			$taxonomy,
			array(
				'parent'  => $parent,
				'limit'   => (int) $this->prop( $node, 'limit' ),
				'orderby' => (string) $this->prop( $node, 'orderby' ),
			)
		);

		if ( ! $terms ) {
			return $ctx->editor
				? '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-terms', 'saha-image--empty' ) ) . '>' . esc_html__( 'Không có danh mục nào.', 'saha-core' ) . '</div>'
				: '';
		}

		$count = (bool) $this->prop( $node, 'showCount' );
		$items = '';

		foreach ( $terms as $term ) {
			$image = ! empty( $term['thumbnail_id'] )
				? (string) wp_get_attachment_image( (int) $term['thumbnail_id'], 'medium', false, array( 'class' => 'saha-terms__img', 'loading' => 'lazy', 'alt' => '' ) )
				: '';

			$items .= '<li class="saha-terms__item"><a class="saha-terms__link" href="' . esc_url( (string) $term['url'] ) . '">'
				. ( '' !== $image ? '<span class="saha-terms__media">' . $image . '</span>' : '' )
				. '<span class="saha-terms__name">' . esc_html( (string) $term['name'] ) . '</span>'
				. ( $count ? '<span class="saha-terms__count">' . esc_html(
					/* translators: %d: số sản phẩm */
					sprintf( _n( '%d sản phẩm', '%d sản phẩm', (int) $term['count'], 'saha-core' ), (int) $term['count'] )
				) . '</span>' : '' )
				. '</a></li>';
		}

		return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-terms' ) ) . '><ul class="saha-terms__grid">' . $items . '</ul></div>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( ' .saha-terms__grid', '--saha-cols', $this->prop( $node, 'columns' ) );
		$css->set( ' .saha-terms__grid', 'gap', $node->prop( 'gap' ) );
	}
}
