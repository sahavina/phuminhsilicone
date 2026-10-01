<?php
/**
 * Element: slider (băng chuyền ảnh / nội dung).
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
 * Slider — các Slide con nằm trong một dải cuộn ngang CSS `scroll-snap`: không JS vẫn vuốt/
 * cuộn được, không nhảy bố cục. `public/assets/js/elements.js` thêm nút trước/sau, chấm,
 * tự chạy (dừng khi rê chuột, focus, tab ẩn, hoặc người dùng chọn giảm chuyển động).
 */
final class Slider extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'            => 'slider',
			'name'            => __( 'Slider', 'saha-core' ),
			'icon'            => 'layers',
			'category'        => 'marketing',
			'allowedParents'  => self::CONTENT_PARENTS,
			'allowedChildren' => array( 'slide' ),
			'initialChildren' => array( 'slide', 'slide', 'slide' ),
			'controls'        => array(
				'label'     => array(
					'type'      => 'text',
					'label'     => __( 'Tên cho trình đọc màn hình', 'saha-core' ),
					'section'   => 'content',
					'default'   => __( 'Hình ảnh nổi bật', 'saha-core' ),
					'maxLength' => 80,
				),
				'perView'   => array(
					'type'       => 'number',
					'label'      => __( 'Số slide hiện cùng lúc', 'saha-core' ),
					'section'    => 'content',
					'responsive' => true,
					'default'    => array( 'desktop' => 1 ),
					'min'        => 1,
					'max'        => 6,
				),
				'autoplay'  => array(
					'type'    => 'toggle',
					'label'   => __( 'Tự chạy', 'saha-core' ),
					'section' => 'content',
					'default' => true,
				),
				'interval'  => array(
					'type'    => 'number',
					'label'   => __( 'Thời gian mỗi slide (giây)', 'saha-core' ),
					'section' => 'content',
					'default' => 5,
					'min'     => 2,
					'max'     => 20,
				),
				'arrows'    => array(
					'type'    => 'toggle',
					'label'   => __( 'Nút trước / sau', 'saha-core' ),
					'section' => 'content',
					'default' => true,
				),
				'dots'      => array(
					'type'    => 'toggle',
					'label'   => __( 'Chấm điều hướng', 'saha-core' ),
					'section' => 'content',
					'default' => true,
				),
				'ratio'     => array(
					'type'    => 'select',
					'label'   => __( 'Tỉ lệ khung', 'saha-core' ),
					'section' => 'style',
					'default' => '4/3',
					'options' => array(
						'auto' => __( 'Theo ảnh', 'saha-core' ),
						'16/9' => '16:9',
						'4/3'  => '4:3',
						'1/1'  => '1:1',
						'21/9' => '21:9',
					),
				),
				'gap'       => array(
					'type'       => 'size',
					'label'      => __( 'Khoảng cách giữa slide', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'units'      => array( 'px' ),
					'min'        => 0,
					'max'        => 60,
				),
				'radius'    => array(
					'type'    => 'size',
					'label'   => __( 'Bo góc', 'saha-core' ),
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
	 * @param string        $content HTML các Slide.
	 */
	public function render( Node $node, RenderContext $ctx, string $content ): string {
		$ratio    = (string) $this->prop( $node, 'ratio' );
		$classes  = array( 'saha-slider' );
		$interval = max( 2, min( 20, (int) $this->prop( $node, 'interval' ) ) );

		if ( 'auto' !== $ratio ) {
			$classes[] = 'saha-slider--ratio';
		}

		$attrs = array(
			'data-saha-slider' => '1',
			'style'            => 'auto' === $ratio ? '' : '--saha-slider-ratio:' . $ratio,
		);

		if ( $this->prop( $node, 'autoplay' ) && ! $ctx->editor ) {
			$attrs['data-autoplay'] = (string) ( $interval * 1000 );
		}

		$controls = '';

		if ( $this->prop( $node, 'arrows' ) ) {
			$controls .= '<button type="button" class="saha-slider__arrow saha-slider__arrow--prev" data-saha-slider-prev aria-label="' . esc_attr__( 'Slide trước', 'saha-core' ) . '">' . Icons::svg( 'chevron-right' ) . '</button>'
				. '<button type="button" class="saha-slider__arrow saha-slider__arrow--next" data-saha-slider-next aria-label="' . esc_attr__( 'Slide sau', 'saha-core' ) . '">' . Icons::svg( 'chevron-right' ) . '</button>';
		}

		if ( $this->prop( $node, 'dots' ) ) {
			$controls .= '<div class="saha-slider__dots" data-saha-slider-dots></div>';
		}

		return '<div' . $this->rootAttributes( $node, $ctx, $classes, $attrs ) . '>'
			. '<div class="saha-slider__track" role="group" aria-roledescription="' . esc_attr__( 'băng chuyền', 'saha-core' ) . '" aria-label="' . esc_attr( (string) $this->prop( $node, 'label' ) ) . '" tabindex="0">' . $content . '</div>'
			. $controls . '</div>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', '--saha-slider-per-view', $this->prop( $node, 'perView' ) );
		$css->set( '', '--saha-slider-gap', $node->prop( 'gap' ) );
		$css->set( '', '--saha-slider-radius', $node->prop( 'radius' ) );
	}
}
