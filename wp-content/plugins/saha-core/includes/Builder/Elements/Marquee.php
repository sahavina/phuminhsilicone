<?php
/**
 * Element: chữ chạy (thanh thông báo ở header / dải ưu đãi).
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
 * Marquee — chạy bằng CSS (không JS): dải nội dung nhân đôi, bản thứ hai `aria-hidden`
 * để trình đọc màn hình chỉ đọc một lần. Dừng khi rê chuột/focus; người dùng chọn
 * "giảm chuyển động" (prefers-reduced-motion) → đứng yên, cuộn ngang được.
 */
final class Marquee extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'marquee',
			'name'           => __( 'Chữ chạy', 'saha-core' ),
			'icon'           => 'zap',
			'category'       => 'marketing',
			'allowedParents' => self::CONTENT_PARENTS,
			'controls'       => array(
				'items'      => array(
					'type'      => 'textarea',
					'label'     => __( 'Nội dung (mỗi dòng một mục)', 'saha-core' ),
					'section'   => 'content',
					'default'   => __( "Miễn phí vận chuyển cho đơn từ 2.000.000đ\nTư vấn kỹ thuật miễn phí\nHàng chính hãng, đủ chứng từ", 'saha-core' ),
					'maxLength' => 1200,
				),
				'separator'  => array(
					'type'    => 'select',
					'label'   => __( 'Dấu phân cách', 'saha-core' ),
					'section' => 'content',
					'default' => 'dot',
					'options' => array(
						'dot'  => '•',
						'star' => '★',
						'none' => __( 'Không', 'saha-core' ),
					),
				),
				'duration'   => array(
					'type'    => 'number',
					'label'   => __( 'Thời gian một vòng (giây)', 'saha-core' ),
					'section' => 'content',
					'default' => 30,
					'min'     => 5,
					'max'     => 120,
				),
				'uppercase'  => array(
					'type'    => 'toggle',
					'label'   => __( 'Chữ in hoa', 'saha-core' ),
					'section' => 'style',
					'default' => true,
				),
				'color'      => array(
					'type'    => 'color',
					'label'   => __( 'Màu chữ', 'saha-core' ),
					'section' => 'style',
				),
				'sepColor'   => array(
					'type'    => 'color',
					'label'   => __( 'Màu dấu phân cách', 'saha-core' ),
					'section' => 'style',
				),
				'typography' => array(
					'type'    => 'typography',
					'label'   => __( 'Kiểu chữ', 'saha-core' ),
					'section' => 'style',
				),
			),
		);
	}

	/**
	 * Các mục (dòng không rỗng).
	 *
	 * @param Node $node Node.
	 * @return string[]
	 */
	private function items( Node $node ): array {
		return array_values( array_filter( array_map( 'trim', preg_split( '/\R/u',(string) $this->prop( $node, 'items' ) ) ?: array() ), 'strlen' ) );
	}

	/**
	 * Render.
	 *
	 * @param Node          $node    Node.
	 * @param RenderContext $ctx     Ngữ cảnh.
	 * @param string        $content HTML con (không dùng).
	 */
	public function render( Node $node, RenderContext $ctx, string $content ): string {
		$items = $this->items( $node );

		if ( ! $items ) {
			return $ctx->editor ? '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-marquee', 'saha-image--empty' ) ) . '>' . esc_html__( 'Nhập nội dung chữ chạy', 'saha-core' ) . '</div>' : '';
		}

		$sep  = (string) $this->prop( $node, 'separator' );
		$mark = 'none' === $sep ? '' : '<span class="saha-marquee__sep" aria-hidden="true">' . ( 'star' === $sep ? '★' : '•' ) . '</span>';
		$list = '';

		foreach ( $items as $item ) {
			$list .= '<li class="saha-marquee__item">' . esc_html( $item ) . '</li>' . ( '' !== $mark ? '<li class="saha-marquee__item" aria-hidden="true">' . $mark . '</li>' : '' );
		}

		$classes = array( 'saha-marquee' );

		if ( $this->prop( $node, 'uppercase' ) ) {
			$classes[] = 'saha-marquee--upper';
		}

		$duration = max( 5, min( 120, (int) $this->prop( $node, 'duration' ) ) );

		// Hai bản giống nhau → dịch -50% là liền mạch.
		return '<div' . $this->rootAttributes( $node, $ctx, $classes, array( 'style' => '--saha-marquee-duration:' . $duration . 's' ) ) . '>'
			. '<div class="saha-marquee__track"><ul class="saha-marquee__list">' . $list . '</ul>'
			. '<ul class="saha-marquee__list" aria-hidden="true">' . $list . '</ul></div></div>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', 'color', $node->prop( 'color' ) );
		$css->set( ' .saha-marquee__sep', 'color', $node->prop( 'sepColor' ) );
		$css->typography( '', $node->prop( 'typography' ) );
	}
}
