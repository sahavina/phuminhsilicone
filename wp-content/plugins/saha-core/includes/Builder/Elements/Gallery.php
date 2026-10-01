<?php
/**
 * Element: thư viện ảnh (lưới / masonry + phóng to).
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
 * Gallery — chứa các element Ảnh (mỗi ảnh có alt / chú thích / link riêng như Ảnh thường).
 * "Phóng to khi bấm": ảnh không gắn link mở trong `<dialog>` (elements.js, `[data-saha-gallery]`),
 * ←/→ chuyển ảnh, Esc đóng. Không JS: ảnh hiển thị bình thường.
 */
final class Gallery extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'            => 'gallery',
			'name'            => __( 'Thư viện ảnh', 'saha-core' ),
			'icon'            => 'format-gallery',
			'category'        => 'content',
			'allowedParents'  => self::CONTENT_PARENTS,
			'allowedChildren' => array( 'image' ),
			'initialChildren' => array( 'image', 'image', 'image' ),
			'controls'        => array(
				'layout'   => array(
					'type'    => 'select',
					'label'   => __( 'Bố cục', 'saha-core' ),
					'section' => 'content',
					'default' => 'grid',
					'options' => array(
						'grid'    => __( 'Lưới đều', 'saha-core' ),
						'masonry' => __( 'So le (giữ tỉ lệ ảnh)', 'saha-core' ),
					),
				),
				'ratio'    => array(
					'type'    => 'select',
					'label'   => __( 'Tỉ lệ ô (lưới đều)', 'saha-core' ),
					'section' => 'content',
					'default' => '1/1',
					'options' => array(
						'1/1'  => '1:1',
						'4/3'  => '4:3',
						'3/4'  => '3:4',
						'16/9' => '16:9',
					),
				),
				'lightbox' => array(
					'type'    => 'toggle',
					'label'   => __( 'Bấm ảnh để phóng to', 'saha-core' ),
					'section' => 'content',
					'default' => true,
				),
				'columns'  => array(
					'type'       => 'number',
					'label'      => __( 'Số cột', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'default'    => array(
						'desktop' => 4,
						'tablet'  => 3,
						'mobile'  => 2,
					),
					'min'        => 1,
					'max'        => 8,
				),
				'gap'      => array(
					'type'       => 'size',
					'label'      => __( 'Khoảng cách', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'units'      => array( 'px' ),
					'min'        => 0,
					'max'        => 80,
				),
				'radius'   => array(
					'type'    => 'size',
					'label'   => __( 'Bo góc ảnh', 'saha-core' ),
					'section' => 'style',
					'units'   => array( 'px' ),
					'min'     => 0,
					'max'     => 40,
				),
			),
		);
	}

	/**
	 * Render.
	 *
	 * @param Node          $node    Node.
	 * @param RenderContext $ctx     Ngữ cảnh.
	 * @param string        $content HTML các ảnh.
	 */
	public function render( Node $node, RenderContext $ctx, string $content ): string {
		if ( '' === trim( $content ) ) {
			return $ctx->editor ? '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-gallery', 'saha-image--empty' ) ) . '>' . esc_html__( 'Thêm ảnh vào thư viện (chọn ảnh ở từng ô).', 'saha-core' ) . '</div>' : '';
		}

		$layout = 'masonry' === $this->prop( $node, 'layout' ) ? 'masonry' : 'grid';
		$attrs  = $this->prop( $node, 'lightbox' ) ? array(
			'data-saha-gallery' => '1',
			'data-label'        => __( 'Xem ảnh', 'saha-core' ),
			'data-close'        => __( 'Đóng', 'saha-core' ),
			'data-prev'         => __( 'Ảnh trước', 'saha-core' ),
			'data-next'         => __( 'Ảnh sau', 'saha-core' ),
		) : array();

		if ( 'grid' === $layout ) {
			$attrs['style'] = '--saha-gallery-ratio:' . (string) $this->prop( $node, 'ratio' );
		}

		return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-gallery', 'saha-gallery--' . $layout ), $attrs ) . '>' . $content . '</div>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', '--saha-gallery-cols', $this->prop( $node, 'columns' ) );
		$css->set( '', '--saha-gallery-gap', $node->prop( 'gap' ) );
		$css->set( '', '--saha-gallery-radius', $node->prop( 'radius' ) );
	}
}
