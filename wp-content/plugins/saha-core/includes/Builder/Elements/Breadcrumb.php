<?php
/**
 * Element động: breadcrumb.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * Breadcrumb — dùng breadcrumb của theme (`saha_theme_breadcrumb`, có schema BreadcrumbList
 * theo plugin SEO) hoặc của WooCommerce. Editor: bản mẫu tĩnh.
 */
final class Breadcrumb extends DynamicElement {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return self::dynamicDef(
			array(
				'type'     => 'breadcrumb',
				'name'     => __( 'Breadcrumb (động)', 'saha-core' ),
				'icon'     => 'chevron-right',
				'controls' => array(),
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
		if ( $ctx->editor ) {
			return '<nav' . $this->rootAttributes( $node, $ctx, array( 'saha-dynamic', 'saha-breadcrumb' ) ) . '><a href="#">' . esc_html__( 'Trang chủ', 'saha-core' ) . '</a> <span class="saha-breadcrumb__sep">/</span> <a href="#">' . esc_html__( 'Danh mục', 'saha-core' ) . '</a> <span class="saha-breadcrumb__sep">/</span> <span>' . esc_html__( 'Trang hiện tại', 'saha-core' ) . '</span></nav>';
		}

		$html = self::capture(
			static function (): void {
				if ( function_exists( 'saha_theme_breadcrumb' ) ) {
					saha_theme_breadcrumb();
				} elseif ( function_exists( 'woocommerce_breadcrumb' ) ) {
					woocommerce_breadcrumb();
				}
			}
		);

		return '' === trim( $html ) ? '' : '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-dynamic' ) ) . '>' . $html . '</div>';
	}
}
