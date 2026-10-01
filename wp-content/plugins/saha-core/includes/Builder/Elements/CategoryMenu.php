<?php
/**
 * Element: nút "Danh mục sản phẩm" mở danh sách danh mục dọc.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\CssRules;
use Saha\Core\Builder\Icons;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;
use Saha\Core\Catalog;

defined( 'ABSPATH' ) || exit;

/**
 * CategoryMenu — nút (màu nhấn) + bảng `<nav>` danh mục dọc; danh mục con bật ra bên phải
 * khi rê chuột/focus. Nguồn: danh mục sản phẩm cấp 1 (tự động, thứ tự kéo-thả của
 * WooCommerce) hoặc một menu WordPress.
 *
 * Mở/đóng bằng `elements.js`: aria-expanded, Esc, bấm ra ngoài. Không JS: link tới shop.
 */
final class CategoryMenu extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'category-menu',
			'name'           => __( 'Nút danh mục sản phẩm', 'saha-core' ),
			'icon'           => 'menu',
			'category'       => 'header',
			'allowedParents' => self::CONTENT_PARENTS,
			'controls'       => array(
				'label'       => array(
					'type'      => 'text',
					'label'     => __( 'Chữ trên nút', 'saha-core' ),
					'section'   => 'content',
					'default'   => __( 'Danh mục sản phẩm', 'saha-core' ),
					'maxLength' => 60,
				),
				'source'      => array(
					'type'    => 'select',
					'label'   => __( 'Nguồn', 'saha-core' ),
					'section' => 'content',
					'default' => 'categories',
					'options' => array(
						'categories' => __( 'Danh mục sản phẩm (tự động)', 'saha-core' ),
						'menu'       => __( 'Một menu WordPress', 'saha-core' ),
					),
				),
				'menu'        => array(
					'type'     => 'term',
					'taxonomy' => 'nav_menu',
					'label'    => __( 'Menu (khi nguồn là menu)', 'saha-core' ),
					'section'  => 'content',
				),
				'limit'       => array(
					'type'    => 'number',
					'label'   => __( 'Số danh mục cấp 1', 'saha-core' ),
					'section' => 'content',
					'default' => 12,
					'min'     => 1,
					'max'     => 30,
				),
				'children'    => array(
					'type'    => 'toggle',
					'label'   => __( 'Hiện danh mục con (bật ra bên phải)', 'saha-core' ),
					'section' => 'content',
					'default' => true,
				),
				'buttonBg'    => array(
					'type'    => 'color',
					'label'   => __( 'Màu nút', 'saha-core' ),
					'section' => 'style',
				),
				'buttonColor' => array(
					'type'    => 'color',
					'label'   => __( 'Màu chữ nút', 'saha-core' ),
					'section' => 'style',
				),
				'panelWidth'  => array(
					'type'    => 'size',
					'label'   => __( 'Độ rộng danh sách', 'saha-core' ),
					'section' => 'style',
					'units'   => array( 'px' ),
					'min'     => 200,
					'max'     => 480,
				),
			),
		);
	}

	/**
	 * Danh mục cấp 1 (+ con) dạng `<li>`.
	 *
	 * @param int  $limit    Số mục.
	 * @param bool $children Có danh mục con.
	 */
	private static function categories( int $limit, bool $children ): string {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return '';
		}

		$skip  = (int) get_option( 'default_product_cat', 0 );
		$items = '';

		foreach ( Catalog::terms( 'product_cat', array( 'limit' => $limit + 1 ) ) as $term ) {
			if ( (int) $term['id'] === $skip ) {
				continue;
			}

			$sub = '';

			if ( $children ) {
				foreach ( Catalog::terms( 'product_cat', array( 'parent' => (int) $term['id'], 'limit' => 24 ) ) as $child ) {
					$sub .= '<li><a href="' . esc_url( (string) $child['url'] ) . '">' . esc_html( (string) $child['name'] ) . '</a></li>';
				}
			}

			$items .= '<li class="saha-catmenu__item' . ( '' !== $sub ? ' has-children' : '' ) . '"><a href="' . esc_url( (string) $term['url'] ) . '">' . esc_html( (string) $term['name'] ) . ( '' !== $sub ? Icons::svg( 'chevron-right', 'saha-catmenu__caret' ) : '' ) . '</a>'
				. ( '' !== $sub ? '<ul class="saha-catmenu__sub">' . $sub . '</ul>' : '' ) . '</li>';

			if ( substr_count( $items, 'class="saha-catmenu__item' ) >= $limit ) {
				break;
			}
		}

		return $items;
	}

	/**
	 * Render.
	 *
	 * @param Node          $node    Node.
	 * @param RenderContext $ctx     Ngữ cảnh.
	 * @param string        $content HTML con (không dùng).
	 */
	public function render( Node $node, RenderContext $ctx, string $content ): string {
		$label = (string) $this->prop( $node, 'label' );
		$menu  = (string) $node->prop( 'menu', '' );
		$list  = '';

		if ( 'menu' === $this->prop( $node, 'source' ) && '' !== $menu ) {
			$list = (string) wp_nav_menu(
				array(
					'menu'        => $menu,
					'container'   => false,
					'menu_class'  => 'saha-catmenu__list',
					'depth'       => $this->prop( $node, 'children' ) ? 2 : 1,
					'fallback_cb' => false,
					'echo'        => false,
				)
			);
		} else {
			$items = self::categories( max( 1, min( 30, (int) $this->prop( $node, 'limit' ) ) ), (bool) $this->prop( $node, 'children' ) );
			$list  = '' !== $items ? '<ul class="saha-catmenu__list">' . $items . '</ul>' : '';
		}

		$panel  = 'saha-catmenu-' . $node->id;
		$shop   = function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'shop' ) : home_url( '/' );
		$toggle = '<a class="saha-catmenu__toggle" href="' . esc_url( $shop ) . '" role="button" aria-expanded="false" aria-controls="' . esc_attr( $panel ) . '" data-saha-catmenu-toggle>'
			. Icons::svg( 'menu', 'saha-catmenu__icon' ) . '<span>' . esc_html( $label ) . '</span>' . Icons::svg( 'arrow-right', 'saha-catmenu__arrow' ) . '</a>';

		if ( '' === $list ) {
			return $ctx->editor
				? '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-catmenu' ) ) . '>' . $toggle . '</div>'
				: '';
		}

		return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-catmenu' ), array( 'data-saha-catmenu' => '1' ) ) . '>' . $toggle
			. '<nav class="saha-catmenu__panel" id="' . esc_attr( $panel ) . '" aria-label="' . esc_attr( $label ) . '" hidden>' . $list . '</nav></div>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( ' .saha-catmenu__toggle', 'background-color', $node->prop( 'buttonBg' ) );
		$css->set( ' .saha-catmenu__toggle', 'color', $node->prop( 'buttonColor' ) );
		$css->set( ' .saha-catmenu__panel', 'width', $node->prop( 'panelWidth' ) );
	}
}
