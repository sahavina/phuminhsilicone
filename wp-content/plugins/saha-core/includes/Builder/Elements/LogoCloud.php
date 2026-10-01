<?php
/**
 * Element: logo thương hiệu / đối tác (lưới hoặc băng chuyền).
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Brand;
use Saha\Core\Builder\CssRules;
use Saha\Core\Builder\Icons;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * LogoCloud — nguồn:
 * - "Thương hiệu sản phẩm": logo + link trang thương hiệu (taxonomy product_brand; thương hiệu chưa
 *   có logo hiện tên), cache theo thế hệ SAHA như Brand::get_all;
 * - "Tự chọn": các element Ảnh đặt bên trong (đối tác, chứng nhận…).
 *
 * Băng chuyền dùng chung JS / CSS của Slider (`[data-saha-slider]`, cuộn ngang có snap, nút ‹ ›).
 */
final class LogoCloud extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'            => 'logo-cloud',
			'name'            => __( 'Logo thương hiệu / đối tác', 'saha-core' ),
			'icon'            => 'awards',
			'category'        => 'marketing',
			'allowedParents'  => self::CONTENT_PARENTS,
			'allowedChildren' => array( 'image' ),
			'controls'        => array(
				'source'    => array(
					'type'    => 'select',
					'label'   => __( 'Nguồn', 'saha-core' ),
					'section' => 'content',
					'default' => 'brands',
					'options' => array(
						'brands' => __( 'Thương hiệu sản phẩm (tự động)', 'saha-core' ),
						'manual' => __( 'Tự chọn ảnh (thêm Ảnh vào bên trong)', 'saha-core' ),
					),
				),
				'limit'     => array(
					'type'    => 'number',
					'label'   => __( 'Số thương hiệu tối đa', 'saha-core' ),
					'section' => 'content',
					'default' => 12,
					'min'     => 1,
					'max'     => 48,
				),
				'display'   => array(
					'type'    => 'select',
					'label'   => __( 'Hiển thị', 'saha-core' ),
					'section' => 'content',
					'default' => 'grid',
					'options' => array(
						'grid'     => __( 'Lưới', 'saha-core' ),
						'carousel' => __( 'Băng chuyền (cuộn ngang)', 'saha-core' ),
					),
				),
				'autoplay'  => array(
					'type'    => 'toggle',
					'label'   => __( 'Băng chuyền: tự chạy', 'saha-core' ),
					'section' => 'content',
					'default' => false,
				),
				'grayscale' => array(
					'type'    => 'toggle',
					'label'   => __( 'Logo xám, có màu khi rê chuột', 'saha-core' ),
					'section' => 'style',
					'default' => false,
				),
				'columns'   => array(
					'type'       => 'number',
					'label'      => __( 'Số logo mỗi hàng', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'default'    => array(
						'desktop' => 6,
						'tablet'  => 4,
						'mobile'  => 3,
					),
					'min'        => 1,
					'max'        => 10,
				),
				'height'    => array(
					'type'    => 'size',
					'label'   => __( 'Chiều cao logo', 'saha-core' ),
					'section' => 'style',
					'units'   => array( 'px' ),
					'min'     => 20,
					'max'     => 200,
				),
				'gap'       => array(
					'type'       => 'size',
					'label'      => __( 'Khoảng cách', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'units'      => array( 'px' ),
					'min'        => 0,
					'max'        => 80,
				),
			),
		);
	}

	/**
	 * Ô logo từ thương hiệu.
	 *
	 * @param int $limit Số tối đa.
	 */
	private static function brandItems( int $limit ): string {
		if ( ! class_exists( Brand::class ) ) {
			return '';
		}

		$items = '';

		foreach ( array_slice( Brand::get_all(), 0, max( 1, $limit ) ) as $brand ) {
			$name = (string) ( $brand['name'] ?? '' );
			$logo = (int) ( $brand['logo_id'] ?? 0 ) > 0
				? (string) wp_get_attachment_image(
					(int) $brand['logo_id'],
					'medium',
					false,
					array(
						'class'   => 'saha-logos__img',
						'alt'     => $name,
						'loading' => 'lazy',
					)
				)
				: '';
			$body = '' !== $logo ? $logo : '<span class="saha-logos__name">' . esc_html( $name ) . '</span>';
			$url  = (string) ( $brand['url'] ?? '' );

			$items .= '<li class="saha-logos__item">' . ( '' !== $url ? '<a class="saha-logos__link" href="' . esc_url( $url ) . '">' . $body . '</a>' : $body ) . '</li>';
		}

		return $items;
	}

	/**
	 * Render.
	 *
	 * @param Node          $node    Node.
	 * @param RenderContext $ctx     Ngữ cảnh.
	 * @param string        $content HTML các Ảnh (nguồn tự chọn).
	 */
	public function render( Node $node, RenderContext $ctx, string $content ): string {
		$manual = 'manual' === $this->prop( $node, 'source' );
		$items  = $manual ? $content : self::brandItems( (int) $this->prop( $node, 'limit' ) );

		if ( '' === trim( $items ) ) {
			return $ctx->editor
				? '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-logos', 'saha-image--empty' ) ) . '>' . esc_html( $manual ? __( 'Thêm Ảnh vào bên trong (Cấu trúc → + Thêm vào).', 'saha-core' ) : __( 'Chưa có thương hiệu nào (Sản phẩm → Thương hiệu).', 'saha-core' ) ) . '</div>'
				: '';
		}

		$classes = array( 'saha-logos', $manual ? 'saha-logos--manual' : 'saha-logos--brands' );

		if ( $this->prop( $node, 'grayscale' ) ) {
			$classes[] = 'saha-logos--gray';
		}

		$list_tag = $manual ? 'div' : 'ul';

		if ( 'carousel' !== $this->prop( $node, 'display' ) ) {
			return '<div' . $this->rootAttributes( $node, $ctx, $classes ) . '><' . $list_tag . ' class="saha-logos__list">' . $items . '</' . $list_tag . '></div>';
		}

		$classes[] = 'saha-slider';
		$classes[] = 'saha-logos--carousel';
		$attrs     = array( 'data-saha-slider' => '1' );

		if ( $this->prop( $node, 'autoplay' ) && ! $ctx->editor ) {
			$attrs['data-autoplay'] = '3000';
		}

		return '<div' . $this->rootAttributes( $node, $ctx, $classes, $attrs ) . '>'
			. '<' . $list_tag . ' class="saha-logos__list saha-slider__track" role="group" aria-roledescription="' . esc_attr__( 'băng chuyền', 'saha-core' ) . '" aria-label="' . esc_attr__( 'Logo thương hiệu', 'saha-core' ) . '" tabindex="0">' . $items . '</' . $list_tag . '>'
			. '<button type="button" class="saha-slider__arrow saha-slider__arrow--prev" data-saha-slider-prev aria-label="' . esc_attr__( 'Logo trước', 'saha-core' ) . '">' . Icons::svg( 'chevron-right' ) . '</button>'
			. '<button type="button" class="saha-slider__arrow saha-slider__arrow--next" data-saha-slider-next aria-label="' . esc_attr__( 'Logo sau', 'saha-core' ) . '">' . Icons::svg( 'chevron-right' ) . '</button>'
			. '</div>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', '--saha-logos-cols', $this->prop( $node, 'columns' ) );
		$css->set( '', '--saha-slider-per-view', $this->prop( $node, 'columns' ) );
		$css->set( '', '--saha-logos-gap', $node->prop( 'gap' ) );
		$css->set( '', '--saha-slider-gap', $node->prop( 'gap' ) );
		$css->set( '', '--saha-logos-h', $node->prop( 'height' ) );
	}
}
