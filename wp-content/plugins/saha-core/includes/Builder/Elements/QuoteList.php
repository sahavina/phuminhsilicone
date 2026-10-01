<?php
/**
 * Element: danh sách báo giá nhiều sản phẩm (trang "Danh sách báo giá").
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\CssRules;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;
use Saha\Core\WooCommerce\QuoteList as QuoteListService;

defined( 'ABSPATH' ) || exit;

/**
 * QuoteList — khung trống; `quote-list.js` dựng bảng sản phẩm (từ localStorage của khách) và form
 * liên hệ, gửi `POST /saha/v1/quote/list`. HTML tĩnh → cache được. Không JS: thông báo + hotline
 * (form báo giá một sản phẩm vẫn dùng được ở trang sản phẩm).
 */
final class QuoteList extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'quote-list',
			'name'           => __( 'Danh sách báo giá', 'saha-core' ),
			'icon'           => 'clipboard',
			'category'       => 'woocommerce',
			'allowedParents' => self::CONTENT_PARENTS,
			'controls'       => array(
				'intro'       => array(
					'type'      => 'textarea',
					'label'     => __( 'Lời dẫn phía trên', 'saha-core' ),
					'section'   => 'content',
					'default'   => __( 'Kiểm tra sản phẩm, số lượng rồi gửi một lần — chúng tôi báo giá trọn gói cho cả danh sách.', 'saha-core' ),
					'maxLength' => 300,
				),
				'accent'      => array(
					'type'    => 'color',
					'label'   => __( 'Màu nút gửi', 'saha-core' ),
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
		$intro = trim( (string) $this->prop( $node, 'intro' ) );
		$body  = $ctx->editor
			? '<div class="saha-image--empty">' . esc_html__( 'Bảng sản phẩm + form liên hệ hiện ở trang thật (lấy từ danh sách khách đã chọn).', 'saha-core' ) . '</div>'
			: '<noscript><p>' . esc_html__( 'Cần bật JavaScript để xem danh sách báo giá. Bạn vẫn có thể gửi yêu cầu báo giá ở từng trang sản phẩm.', 'saha-core' ) . '</p></noscript>';

		if ( ! $ctx->editor && ! QuoteListService::enabled() ) {
			return '';
		}

		return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-ql' ) ) . ' data-saha-quote-list>'
			. ( '' !== $intro ? '<p class="saha-ql__intro">' . esc_html( $intro ) . '</p>' : '' )
			. $body
			. '</div>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( ' .saha-ql__submit', 'background-color', $node->prop( 'accent' ) );
		$css->set( ' .saha-ql__submit', 'border-color', $node->prop( 'accent' ) );
	}
}
