<?php
/**
 * Element: Image.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\Controls\Link;
use Saha\Core\Builder\CssRules;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * Image — ảnh Media Library qua `wp_get_attachment_image` (srcset/sizes, width/height chống CLS).
 */
final class Image extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'image',
			'name'           => __( 'Ảnh', 'saha-core' ),
			'icon'           => 'format-image',
			'category'       => 'content',
			'allowedParents' => array( 'column', 'section' ),
			'controls'       => array(
				'image'        => array(
					'type'        => 'media',
					'label'       => __( 'Ảnh', 'saha-core' ),
					'section'     => 'content',
					'defaultSize' => 'large',
				),
				'alt'          => array(
					'type'      => 'text',
					'label'     => __( 'Văn bản thay thế (alt)', 'saha-core' ),
					'section'   => 'content',
					'maxLength' => 300,
					'help'      => __( 'Để trống = dùng alt trong Media Library.', 'saha-core' ),
				),
				'caption'      => array(
					'type'      => 'text',
					'label'     => __( 'Chú thích', 'saha-core' ),
					'section'   => 'content',
					'maxLength' => 300,
				),
				'link'         => array(
					'type'    => 'link',
					'label'   => __( 'Liên kết', 'saha-core' ),
					'section' => 'content',
				),
				'loading'      => array(
					'type'    => 'select',
					'label'   => __( 'Tải ảnh', 'saha-core' ),
					'section' => 'content',
					'default' => 'auto',
					'options' => array(
						'auto'  => __( 'Tự động', 'saha-core' ),
						'lazy'  => __( 'Tải chậm (lazy)', 'saha-core' ),
						'eager' => __( 'Tải ngay — ảnh đầu trang (LCP)', 'saha-core' ),
					),
				),
				'width'        => array(
					'type'       => 'size',
					'label'      => __( 'Độ rộng', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'units'      => array( 'px', '%' ),
					'min'        => 1,
					'max'        => 3000,
				),
				'align'        => array(
					'type'       => 'align',
					'label'      => __( 'Căn lề', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'options'    => array( 'left', 'center', 'right' ),
				),
				'borderRadius' => array(
					'type'    => 'size',
					'label'   => __( 'Bo góc', 'saha-core' ),
					'section' => 'style',
					'units'   => array( 'px', '%' ),
					'min'     => 0,
					'max'     => 500,
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
		$image = $node->prop( 'image' );
		$id    = is_array( $image ) ? (int) ( $image['id'] ?? 0 ) : 0;
		$img   = '';

		if ( $id > 0 ) {
			$attrs = array( 'class' => 'saha-image__img' );
			$alt   = (string) $node->prop( 'alt', '' );

			if ( '' !== $alt ) {
				$attrs['alt'] = $alt;
			}

			$loading = (string) $this->prop( $node, 'loading' );

			if ( 'lazy' === $loading ) {
				$attrs['loading'] = 'lazy';
			} elseif ( 'eager' === $loading ) {
				$attrs['loading']       = 'eager';
				$attrs['fetchpriority'] = 'high';
			}

			$img = (string) wp_get_attachment_image( $id, (string) ( $image['size'] ?? 'large' ), false, $attrs );
		}

		if ( '' === $img ) {
			// Ảnh chưa chọn hoặc đã bị xoá: frontend không in gì, editor hiện chỗ trống.
			return $ctx->editor
				? '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-image', 'saha-image--empty' ) ) . '>' . esc_html__( 'Chọn ảnh', 'saha-core' ) . '</div>'
				: '';
		}

		$link = $node->prop( 'link' );

		if ( is_array( $link ) && ! empty( $link['url'] ) ) {
			$img = '<a' . Link::attributes( $link ) . '>' . $img . '</a>';
		}

		$caption = (string) $node->prop( 'caption', '' );

		if ( '' !== $caption ) {
			$img .= '<figcaption class="saha-image__caption">' . esc_html( $caption ) . '</figcaption>';
		}

		return '<figure' . $this->rootAttributes( $node, $ctx, array( 'saha-image' ) ) . '>' . $img . '</figure>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', 'text-align', $node->prop( 'align' ) );
		$css->set( ' img', 'width', $node->prop( 'width' ) );
		$css->set( ' img', 'border-radius', $node->prop( 'borderRadius' ) );
	}
}
