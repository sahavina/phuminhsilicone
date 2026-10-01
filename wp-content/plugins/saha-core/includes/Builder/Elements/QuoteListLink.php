<?php
/**
 * Element header: icon "Danh sách báo giá" + số sản phẩm đã chọn.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\CssRules;
use Saha\Core\Builder\Icons;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;
use Saha\Core\WooCommerce\QuoteList as QuoteListService;

defined( 'ABSPATH' ) || exit;

/**
 * QuoteListLink — link tới trang danh sách. Số sản phẩm nằm ở trình duyệt khách (localStorage) nên
 * HTML luôn in 0 và `quote-list.js` điền số thật (HTML tĩnh, cache được). Ẩn khi tuỳ chọn tắt.
 */
final class QuoteListLink extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'quote-list-link',
			'name'           => __( 'Icon danh sách báo giá', 'saha-core' ),
			'icon'           => 'clipboard',
			'category'       => 'header',
			'allowedParents' => self::CONTENT_PARENTS,
			'dynamic'        => true,
			'controls'       => array(
				'label'   => array(
					'type'      => 'text',
					'label'     => __( 'Chữ cạnh icon', 'saha-core' ),
					'section'   => 'content',
					'default'   => __( 'Báo giá', 'saha-core' ),
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
	 * Render.
	 *
	 * @param Node          $node    Node.
	 * @param RenderContext $ctx     Ngữ cảnh.
	 * @param string        $content HTML con (không dùng).
	 */
	public function render( Node $node, RenderContext $ctx, string $content ): string {
		$url = QuoteListService::pageUrl();

		if ( ! $ctx->editor && ( ! QuoteListService::enabled() || '' === $url ) ) {
			return '';
		}

		$label = trim( (string) $this->prop( $node, 'label' ) );

		return '<a href="' . esc_url( '' !== $url ? $url : '#' ) . '"' . $this->rootAttributes( $node, $ctx, array( 'saha-hb-icon', 'saha-ql-link' ) ) . ' data-saha-ql-link>'
			. Icons::svg( 'file-text' )
			. ( '' !== $label ? '<span class="saha-hb-icon__label">' . esc_html( $label ) . '</span>' : '' )
			. '<span class="saha-ql-count" aria-hidden="true" hidden>0</span>'
			. '<span class="screen-reader-text saha-ql-sr">' . esc_html__( 'Danh sách báo giá', 'saha-core' ) . '</span></a>';
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
		$css->set( ' .saha-ql-count', 'background-color', $node->prop( 'badgeBg' ) );
	}
}
