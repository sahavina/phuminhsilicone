<?php
/**
 * Element: một slide của Slider.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\Controls\Link;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * Slide — ảnh (Media Library, srcset), chú thích tuỳ chọn, liên kết tuỳ chọn.
 */
final class Slide extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'slide',
			'name'           => __( 'Slide', 'saha-core' ),
			'icon'           => 'image',
			'category'       => 'marketing',
			'allowedParents' => array( 'slider' ),
			'controls'       => array(
				'image'    => array(
					'type'        => 'media',
					'label'       => __( 'Ảnh', 'saha-core' ),
					'section'     => 'content',
					'defaultSize' => 'large',
				),
				'alt'      => array(
					'type'      => 'text',
					'label'     => __( 'Mô tả ảnh (alt)', 'saha-core' ),
					'section'   => 'content',
					'maxLength' => 200,
				),
				'caption'  => array(
					'type'      => 'text',
					'label'     => __( 'Chú thích trên ảnh', 'saha-core' ),
					'section'   => 'content',
					'maxLength' => 160,
				),
				'link'     => array(
					'type'    => 'link',
					'label'   => __( 'Liên kết', 'saha-core' ),
					'section' => 'content',
				),
				'priority' => array(
					'type'    => 'toggle',
					'label'   => __( 'Ưu tiên tải (slide đầu của banner đầu trang)', 'saha-core' ),
					'section' => 'content',
					'default' => false,
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
		$attrs = array(
			'class'   => 'saha-slide__img',
			'alt'     => (string) $node->prop( 'alt', '' ),
			'loading' => 'lazy',
		);

		if ( $this->prop( $node, 'priority' ) ) {
			$attrs['loading']       = 'eager';
			$attrs['fetchpriority'] = 'high';
		}

		$img = $id > 0 ? (string) wp_get_attachment_image( $id, (string) ( $image['size'] ?? 'large' ), false, $attrs ) : '';

		if ( '' === $img ) {
			$img = $ctx->editor ? '<span class="saha-slide__empty">' . esc_html__( 'Chọn ảnh cho slide', 'saha-core' ) . '</span>' : '';
		}

		$caption = (string) $node->prop( 'caption', '' );
		$inner   = $img . ( '' !== $caption ? '<span class="saha-slide__caption">' . esc_html( $caption ) . '</span>' : '' );
		$link    = $node->prop( 'link' );

		if ( is_array( $link ) && ! empty( $link['url'] ) ) {
			$inner = '<a class="saha-slide__link"' . Link::attributes( $link ) . '>' . $inner . '</a>';
		}

		return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-slide' ), array( 'role' => 'group', 'aria-roledescription' => __( 'slide', 'saha-core' ) ) ) . '>' . $inner . '</div>';
	}
}
