<?php
/**
 * Element: Tài khoản.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\CssRules;
use Saha\Core\Builder\Icons;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * Account — link trang Tài khoản của WooCommerce (hoặc trang đăng nhập WordPress).
 */
final class Account extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'account',
			'name'           => __( 'Tài khoản', 'saha-core' ),
			'icon'           => 'admin-users',
			'category'       => 'header',
			'allowedParents' => self::CONTENT_PARENTS,
			'controls'       => array(
				'label' => array(
					'type'      => 'text',
					'label'     => __( 'Chữ cạnh icon', 'saha-core' ),
					'section'   => 'content',
					'maxLength' => 40,
				),
				'size'  => array(
					'type'    => 'size',
					'label'   => __( 'Cỡ icon', 'saha-core' ),
					'section' => 'style',
					'units'   => array( 'px' ),
					'min'     => 12,
					'max'     => 64,
				),
				'color' => array(
					'type'    => 'color',
					'label'   => __( 'Màu', 'saha-core' ),
					'section' => 'style',
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
		$url   = function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'myaccount' ) : wp_login_url();
		$label = (string) $node->prop( 'label', '' );
		$text  = '' !== $label
			? '<span class="saha-hb-icon__label">' . esc_html( $label ) . '</span>'
			: '<span class="screen-reader-text">' . esc_html__( 'Tài khoản', 'saha-core' ) . '</span>';

		return '<a href="' . esc_url( $url ) . '"' . $this->rootAttributes( $node, $ctx, array( 'saha-hb-icon', 'saha-account-el' ) ) . '>' . Icons::svg( 'user' ) . $text . '</a>';
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
	}
}
