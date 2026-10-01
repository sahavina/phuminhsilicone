<?php
/**
 * Element: form đăng ký nhận tin (email).
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
 * Newsletter — ô email + nút; gửi `POST /saha/v1/newsletter` bằng elements.js
 * (`[data-saha-newsletter]`: lấy nonce mới, báo kết quả ở vùng `role=status`). Lưu thành lead nguồn
 * "Đăng ký nhận tin" (SAHA → Khách hàng tiềm năng). HTML tĩnh (cache được); không JS → báo cần bật JS.
 */
final class Newsletter extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'newsletter',
			'name'           => __( 'Đăng ký nhận tin', 'saha-core' ),
			'icon'           => 'email',
			'category'       => 'marketing',
			'allowedParents' => self::CONTENT_PARENTS,
			'controls'       => array(
				'placeholder' => array(
					'type'      => 'text',
					'label'     => __( 'Chữ gợi ý trong ô', 'saha-core' ),
					'section'   => 'content',
					'default'   => __( 'Email của bạn', 'saha-core' ),
					'maxLength' => 80,
				),
				'button'      => array(
					'type'      => 'text',
					'label'     => __( 'Chữ trên nút', 'saha-core' ),
					'section'   => 'content',
					'default'   => __( 'Đăng ký', 'saha-core' ),
					'maxLength' => 40,
				),
				'note'        => array(
					'type'      => 'text',
					'label'     => __( 'Ghi chú nhỏ bên dưới', 'saha-core' ),
					'section'   => 'content',
					'default'   => __( 'Nhận bảng giá và khuyến mãi mới. Huỷ bất cứ lúc nào.', 'saha-core' ),
					'maxLength' => 200,
				),
				'layout'      => array(
					'type'    => 'select',
					'label'   => __( 'Bố cục', 'saha-core' ),
					'section' => 'content',
					'default' => 'inline',
					'options' => array(
						'inline'  => __( 'Ô và nút cùng hàng', 'saha-core' ),
						'stacked' => __( 'Nút dưới ô', 'saha-core' ),
					),
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
				'maxWidth'    => array(
					'type'    => 'size',
					'label'   => __( 'Độ rộng tối đa', 'saha-core' ),
					'section' => 'style',
					'units'   => array( 'px', '%' ),
					'min'     => 200,
					'max'     => 1200,
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
		$id     = 'saha-nl-' . $node->id;
		$note   = trim( (string) $this->prop( $node, 'note' ) );
		$layout = 'stacked' === $this->prop( $node, 'layout' ) ? 'stacked' : 'inline';

		return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-nl', 'saha-nl--' . $layout ) ) . '>'
			. '<form class="saha-nl__form" data-saha-newsletter data-endpoint="' . esc_url( rest_url( 'saha/v1/newsletter' ) ) . '" data-nonce-url="' . esc_url( rest_url( 'saha/v1/nonce' ) ) . '" data-error="' . esc_attr__( 'Không gửi được. Vui lòng thử lại sau.', 'saha-core' ) . '" novalidate>'
			. '<label class="screen-reader-text" for="' . esc_attr( $id ) . '">' . esc_html__( 'Email', 'saha-core' ) . '</label>'
			. '<input class="saha-nl__input" type="email" id="' . esc_attr( $id ) . '" name="email" required autocomplete="email" maxlength="191" placeholder="' . esc_attr( (string) $this->prop( $node, 'placeholder' ) ) . '"' . ( '' !== $note ? ' aria-describedby="' . esc_attr( $id ) . '-note"' : '' ) . '>'
			. '<span class="saha-nl__trap" aria-hidden="true"><input type="text" name="saha_hp_email" tabindex="-1" autocomplete="off"></span>'
			. '<button class="saha-btn saha-btn--primary saha-nl__button" type="submit">' . esc_html( (string) $this->prop( $node, 'button' ) ) . '</button>'
			. '</form>'
			. ( '' !== $note ? '<p class="saha-nl__note" id="' . esc_attr( $id ) . '-note">' . esc_html( $note ) . '</p>' : '' )
			. '<p class="saha-nl__status" role="status"></p>'
			. '<noscript><p class="saha-nl__note">' . esc_html__( 'Cần bật JavaScript để đăng ký.', 'saha-core' ) . '</p></noscript>'
			. '</div>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( ' .saha-nl__button', 'background-color', $node->prop( 'buttonBg' ) );
		$css->set( ' .saha-nl__button', 'border-color', $node->prop( 'buttonBg' ) );
		$css->set( ' .saha-nl__button', 'color', $node->prop( 'buttonColor' ) );
		$css->set( '', 'max-width', $node->prop( 'maxWidth' ) );
	}
}
