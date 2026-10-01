<?php
/**
 * Element: CTA.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\CssRules;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * CTA — khối kêu gọi hành động: tiêu đề, mô tả, tối đa hai nút.
 *
 * Nút chính có thể mở form báo giá tại chỗ (modal của theme) thay vì đi tới một
 * liên kết: render `data-saha-open-quote` và báo cho theme qua action
 * `saha_quote_modal_needed` để in modal ở footer.
 */
final class Cta extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'cta',
			'name'           => __( 'Kêu gọi hành động (CTA)', 'saha-core' ),
			'icon'           => 'megaphone',
			'category'       => 'marketing',
			'allowedParents' => self::CONTENT_PARENTS,
			'controls'       => array(
				'title'       => array(
					'type'      => 'text',
					'label'     => __( 'Tiêu đề', 'saha-core' ),
					'section'   => 'content',
					'default'   => __( 'Cần báo giá keo cho công trình?', 'saha-core' ),
					'maxLength' => 200,
				),
				'text'        => array(
					'type'      => 'textarea',
					'label'     => __( 'Mô tả', 'saha-core' ),
					'section'   => 'content',
					'maxLength' => 600,
				),
				'button1'     => array(
					'type'      => 'text',
					'label'     => __( 'Nút chính', 'saha-core' ),
					'section'   => 'content',
					'default'   => __( 'Yêu cầu báo giá', 'saha-core' ),
					'maxLength' => 80,
				),
				'action1'     => array(
					'type'    => 'select',
					'label'   => __( 'Nút chính làm gì', 'saha-core' ),
					'section' => 'content',
					'default' => 'link',
					'options' => array(
						'link'  => __( 'Đi tới liên kết', 'saha-core' ),
						'quote' => __( 'Mở form báo giá', 'saha-core' ),
					),
				),
				'link1'       => array(
					'type'    => 'link',
					'label'   => __( 'Liên kết nút chính', 'saha-core' ),
					'section' => 'content',
				),
				'button2'     => array(
					'type'      => 'text',
					'label'     => __( 'Nút phụ', 'saha-core' ),
					'section'   => 'content',
					'maxLength' => 80,
					'help'      => __( 'Ví dụ: "Gọi 0966.75.3382" với liên kết tel:0966753382.', 'saha-core' ),
				),
				'link2'       => array(
					'type'    => 'link',
					'label'   => __( 'Liên kết nút phụ', 'saha-core' ),
					'section' => 'content',
				),
				'layout'      => array(
					'type'       => 'select',
					'label'      => __( 'Bố cục', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'default'    => 'row',
					'options'    => array(
						'row'    => __( 'Chữ trái — nút phải', 'saha-core' ),
						'column' => __( 'Xếp dọc', 'saha-core' ),
					),
				),
				'align'       => array(
					'type'       => 'align',
					'label'      => __( 'Căn lề (khi xếp dọc)', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'options'    => array( 'left', 'center', 'right' ),
				),
				'background'  => array(
					'type'    => 'color',
					'label'   => __( 'Màu nền', 'saha-core' ),
					'section' => 'style',
					'default' => 'var(--saha-secondary)',
				),
				'textColor'   => array(
					'type'    => 'color',
					'label'   => __( 'Màu chữ', 'saha-core' ),
					'section' => 'style',
					'default' => '#ffffff',
				),
				'titleTypo'   => array(
					'type'    => 'typography',
					'label'   => __( 'Kiểu chữ tiêu đề', 'saha-core' ),
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
		$title = (string) $this->prop( $node, 'title' );
		$text  = (string) $node->prop( 'text', '' );
		$body  = '' !== $title ? '<h2 class="saha-cta__title">' . esc_html( $title ) . '</h2>' : '';

		if ( '' !== $text ) {
			$body .= '<p class="saha-cta__text">' . nl2br( esc_html( $text ) ) . '</p>';
		}

		$label   = (string) $this->prop( $node, 'button1' );
		$buttons = 'quote' === $this->prop( $node, 'action1' ) && '' !== trim( $label )
			? $this->quoteButton( $label, $ctx )
			: Button::markup( $node->prop( 'link1' ), $label, 'primary', 'lg' );

		$buttons .= Button::markup( $node->prop( 'link2' ), (string) $node->prop( 'button2', '' ), 'outline', 'lg' );

		$html = '<div class="saha-cta__body">' . $body . '</div>';

		if ( '' !== $buttons ) {
			$html .= '<div class="saha-cta__actions">' . $buttons . '</div>';
		}

		return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-cta' ) ) . '>' . $html . '</div>';
	}

	/**
	 * Nút báo giá báo cho theme in modal mỗi lần render → không cache HTML.
	 *
	 * @param Node $node Node.
	 */
	public function isDynamic( Node $node ): bool {
		return 'quote' === $node->prop( 'action1', 'link' );
	}

	/**
	 * Nút mở form báo giá.
	 *
	 * @param string        $label Nhãn.
	 * @param RenderContext $ctx   Ngữ cảnh.
	 */
	private function quoteButton( string $label, RenderContext $ctx ): string {
		if ( ! $ctx->editor ) {
			/**
			 * Trang cần modal báo giá (theme in modal + nạp JS ở footer).
			 */
			do_action( 'saha_quote_modal_needed' );
		}

		return '<button type="button" class="saha-btn saha-btn--primary saha-btn--lg" data-saha-open-quote="1">' . esc_html( $label ) . '</button>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', 'flex-direction', $this->prop( $node, 'layout' ) );
		$css->set( '', 'background-color', $this->prop( $node, 'background' ) );
		$css->set( '', 'color', $this->prop( $node, 'textColor' ) );
		$css->set( ' .saha-cta__title', 'color', $this->prop( $node, 'textColor' ) );
		$css->set( ' .saha-btn--outline', 'color', $this->prop( $node, 'textColor' ) );
		$css->set( ' .saha-btn--outline', 'border-color', $this->prop( $node, 'textColor' ) );
		$css->set( '', 'text-align', $node->prop( 'align' ) );
		$css->typography( ' .saha-cta__title', $node->prop( 'titleTypo' ) );
	}
}
