<?php
/**
 * Element: Giỏ hàng.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\CssRules;
use Saha\Core\Builder\Icons;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;
use Saha\Core\WooCommerce\MiniCart;

defined( 'ABSPATH' ) || exit;

/**
 * Cart — icon giỏ + số lượng, link trang giỏ.
 *
 * Tự ẩn ở chế độ catalogue. Số lượng cập nhật sau "Thêm vào giỏ" qua fragment của
 * WooCommerce (`span.saha-cart-count`, Templates\Module). `dynamic`: theo phiên khách.
 */
final class Cart extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'cart',
			'name'           => __( 'Giỏ hàng', 'saha-core' ),
			'icon'           => 'cart',
			'category'       => 'header',
			'allowedParents' => self::CONTENT_PARENTS,
			'dynamic'        => true,
			'controls'       => array(
				'label'   => array(
					'type'      => 'text',
					'label'     => __( 'Chữ cạnh icon', 'saha-core' ),
					'section'   => 'content',
					'maxLength' => 40,
				),
				'size'    => array(
					'type'    => 'size',
					'label'   => __( 'Cỡ icon', 'saha-core' ),
					'section' => 'style',
					'units'   => array( 'px' ),
					'min'     => 12,
					'max'     => 64,
				),
				'color'   => array(
					'type'    => 'color',
					'label'   => __( 'Màu', 'saha-core' ),
					'section' => 'style',
				),
				'badgeBg' => array(
					'type'    => 'color',
					'label'   => __( 'Màu nền số lượng', 'saha-core' ),
					'section' => 'style',
				),
			),
		);
	}

	/**
	 * Số sản phẩm trong giỏ.
	 */
	public static function count(): int {
		return function_exists( 'WC' ) && WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0;
	}

	/**
	 * Render.
	 *
	 * @param Node          $node    Node.
	 * @param RenderContext $ctx     Ngữ cảnh.
	 * @param string        $content HTML con (không dùng).
	 */
	public function render( Node $node, RenderContext $ctx, string $content ): string {
		if ( ! function_exists( 'wc_get_cart_url' ) || ( function_exists( 'saha_is_catalogue_mode' ) && saha_is_catalogue_mode() ) ) {
			return $ctx->editor
				? '<span' . $this->rootAttributes( $node, $ctx, array( 'saha-hb-icon', 'saha-image--empty' ) ) . '>' . esc_html__( 'Giỏ hàng (ẩn ở chế độ catalogue)', 'saha-core' ) . '</span>'
				: '';
		}

		$count = self::count();
		$label = (string) $node->prop( 'label', '' );

		$drawer = ! $ctx->editor && MiniCart::enabled();

		return '<a href="' . esc_url( wc_get_cart_url() ) . '"' . $this->rootAttributes( $node, $ctx, array( 'saha-hb-icon', 'saha-cart-el' ) ) . ( $drawer ? ' data-saha-mini-cart aria-haspopup="dialog"' : '' ) . '>'
			. Icons::svg( 'cart' )
			. ( '' !== $label ? '<span class="saha-hb-icon__label">' . esc_html( $label ) . '</span>' : '' )
			. '<span class="saha-cart-count" aria-hidden="true">' . $count . '</span>'
			. '<span class="screen-reader-text saha-cart-sr">' . esc_html(
				/* translators: %d: số sản phẩm */
				sprintf( _n( 'Giỏ hàng: %d sản phẩm', 'Giỏ hàng: %d sản phẩm', $count, 'saha-core' ), $count )
			) . '</span></a>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', 'font-size', $node->prop( 'size' ) );
		$css->set( '', 'color', $node->prop( 'color' ) );
		$css->set( ' .saha-cart-count', 'background-color', $node->prop( 'badgeBg' ) );
	}
}
