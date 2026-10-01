<?php
/**
 * Element: một đánh giá khách hàng.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\Icons;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * Testimonial — `<figure><blockquote>` + `<figcaption>` (tên, nơi ở), sao tuỳ chọn, ảnh đại diện tuỳ chọn.
 *
 * Không in dữ liệu cấu trúc Review: Google không cho phép đánh giá tự viết về chính doanh nghiệp.
 */
final class Testimonial extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'testimonial',
			'name'           => __( 'Một đánh giá', 'saha-core' ),
			'icon'           => 'message',
			'category'       => 'marketing',
			'allowedParents' => array( 'testimonials' ),
			'controls'       => array(
				'quote'  => array(
					'type'      => 'textarea',
					'label'     => __( 'Nội dung đánh giá', 'saha-core' ),
					'section'   => 'content',
					'default'   => __( 'Được tư vấn đúng loại keo cho công trình, giao hàng nhanh, giá rõ ràng.', 'saha-core' ),
					'maxLength' => 800,
				),
				'name'   => array(
					'type'      => 'text',
					'label'     => __( 'Tên khách hàng', 'saha-core' ),
					'section'   => 'content',
					'default'   => __( 'Anh Minh', 'saha-core' ),
					'maxLength' => 80,
				),
				'meta'   => array(
					'type'      => 'text',
					'label'     => __( 'Nơi ở / công ty', 'saha-core' ),
					'section'   => 'content',
					'maxLength' => 120,
				),
				'rating' => array(
					'type'    => 'select',
					'label'   => __( 'Số sao', 'saha-core' ),
					'section' => 'content',
					'default' => 'off',
					'options' => array(
						'off' => __( 'Không hiện', 'saha-core' ),
						's5'  => '★★★★★',
						's4'  => '★★★★',
					),
				),
				'avatar' => array(
					'type'        => 'media',
					'label'       => __( 'Ảnh đại diện', 'saha-core' ),
					'section'     => 'content',
					'defaultSize' => 'thumbnail',
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
		$quote = trim( (string) $this->prop( $node, 'quote' ) );

		if ( '' === $quote ) {
			return $ctx->editor ? '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-testimonial', 'saha-image--empty' ) ) . '>' . esc_html__( 'Nhập nội dung đánh giá', 'saha-core' ) . '</div>' : '';
		}

		$stars  = array( 's5' => 5, 's4' => 4 )[ (string) $this->prop( $node, 'rating' ) ] ?? 0;
		$avatar = $node->prop( 'avatar' );
		$img    = is_array( $avatar ) && ! empty( $avatar['id'] )
			? (string) wp_get_attachment_image(
				(int) $avatar['id'],
				'thumbnail',
				false,
				array(
					'class'   => 'saha-testimonial__avatar',
					'alt'     => '',
					'loading' => 'lazy',
				)
			)
			: '';
		$meta   = (string) $node->prop( 'meta', '' );

		$rating = $stars > 0
			/* translators: %d: số sao */
			? '<div class="saha-testimonial__stars" role="img" aria-label="' . esc_attr( sprintf( __( '%d trên 5 sao', 'saha-core' ), $stars ) ) . '">' . str_repeat( Icons::svg( 'star' ), $stars ) . '</div>'
			: '';

		return '<figure' . $this->rootAttributes( $node, $ctx, array( 'saha-testimonial' ) ) . '>' . $rating
			. '<blockquote class="saha-testimonial__quote"><p>' . esc_html( $quote ) . '</p></blockquote>'
			. '<figcaption class="saha-testimonial__by">' . $img . '<span><strong>' . esc_html( (string) $this->prop( $node, 'name' ) ) . '</strong>'
			. ( '' !== $meta ? '<span class="saha-testimonial__meta"> · ' . esc_html( $meta ) . '</span>' : '' ) . '</span></figcaption></figure>';
	}
}
