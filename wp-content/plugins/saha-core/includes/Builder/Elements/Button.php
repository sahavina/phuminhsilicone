<?php
/**
 * Element: Button.
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
 * Button — liên kết dạng nút. Không có link → <span> (không giả làm nút bấm được).
 */
final class Button extends Element {

	public const VARIANTS = array( 'primary', 'secondary', 'accent', 'outline', 'link' );
	public const SIZES    = array( 'sm', 'md', 'lg' );

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'button',
			'name'           => __( 'Nút', 'saha-core' ),
			'icon'           => 'button',
			'category'       => 'content',
			'allowedParents' => self::CONTENT_PARENTS,
			'controls'       => array(
				'text'         => array(
					'type'      => 'text',
					'label'     => __( 'Chữ trên nút', 'saha-core' ),
					'section'   => 'content',
					'default'   => __( 'Xem thêm', 'saha-core' ),
					'maxLength' => 120,
				),
				'link'         => array(
					'type'    => 'link',
					'label'   => __( 'Liên kết', 'saha-core' ),
					'section' => 'content',
				),
				'variant'      => array(
					'type'    => 'select',
					'label'   => __( 'Kiểu', 'saha-core' ),
					'section' => 'content',
					'default' => 'primary',
					'options' => array(
						'primary'   => __( 'Chính', 'saha-core' ),
						'secondary' => __( 'Phụ', 'saha-core' ),
						'accent'    => __( 'Nhấn (màu nhấn)', 'saha-core' ),
						'outline'   => __( 'Viền', 'saha-core' ),
						'link'      => __( 'Dạng liên kết', 'saha-core' ),
					),
				),
				'size'         => array(
					'type'    => 'select',
					'label'   => __( 'Cỡ', 'saha-core' ),
					'section' => 'content',
					'default' => 'md',
					'options' => array(
						'sm' => __( 'Nhỏ', 'saha-core' ),
						'md' => __( 'Vừa', 'saha-core' ),
						'lg' => __( 'Lớn', 'saha-core' ),
					),
				),
				'fullWidth'    => array(
					'type'    => 'toggle',
					'label'   => __( 'Rộng hết khung', 'saha-core' ),
					'section' => 'content',
					'default' => false,
				),
				'align'        => array(
					'type'       => 'align',
					'label'      => __( 'Căn lề', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'options'    => array( 'left', 'center', 'right' ),
				),
				'typography'   => array(
					'type'    => 'typography',
					'label'   => __( 'Kiểu chữ', 'saha-core' ),
					'section' => 'style',
				),
				'textColor'    => array(
					'type'    => 'color',
					'label'   => __( 'Màu chữ', 'saha-core' ),
					'section' => 'style',
				),
				'bgColor'      => array(
					'type'    => 'color',
					'label'   => __( 'Màu nền', 'saha-core' ),
					'section' => 'style',
				),
				'hoverText'    => array(
					'type'    => 'color',
					'label'   => __( 'Màu chữ khi rê chuột', 'saha-core' ),
					'section' => 'style',
				),
				'hoverBg'      => array(
					'type'    => 'color',
					'label'   => __( 'Màu nền khi rê chuột', 'saha-core' ),
					'section' => 'style',
				),
				'borderRadius' => array(
					'type'    => 'size',
					'label'   => __( 'Bo góc', 'saha-core' ),
					'section' => 'style',
					'units'   => array( 'px', '%', 'rem' ),
					'min'     => 0,
					'max'     => 200,
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
		$variant = in_array( $this->prop( $node, 'variant' ), self::VARIANTS, true ) ? (string) $this->prop( $node, 'variant' ) : 'primary';
		$size    = in_array( $this->prop( $node, 'size' ), self::SIZES, true ) ? (string) $this->prop( $node, 'size' ) : 'md';
		$classes = 'saha-btn saha-btn--' . $variant . ' saha-btn--' . $size;
		$text    = esc_html( (string) $this->prop( $node, 'text' ) );
		$link    = $node->prop( 'link' );
		$wrap    = array( 'saha-button-wrap' );

		if ( $this->prop( $node, 'fullWidth' ) ) {
			$wrap[] = 'saha-button-wrap--full';
		}

		$inner = is_array( $link ) && ! empty( $link['url'] )
			? '<a class="' . esc_attr( $classes ) . '"' . Link::attributes( $link ) . '>' . $text . '</a>'
			: '<span class="' . esc_attr( $classes ) . '">' . $text . '</span>';

		return '<div' . $this->rootAttributes( $node, $ctx, $wrap ) . '>' . $inner . '</div>';
	}

	/**
	 * HTML một nút (dùng lại trong Banner, CTA, Icon Box).
	 *
	 * @param mixed  $link    Giá trị control link.
	 * @param string $text    Chữ.
	 * @param string $variant primary | secondary | outline | link.
	 * @param string $size    sm | md | lg.
	 * @return string '' nếu không có chữ.
	 */
	public static function markup( $link, string $text, string $variant = 'primary', string $size = 'md' ): string {
		if ( '' === trim( $text ) ) {
			return '';
		}

		$variant = in_array( $variant, self::VARIANTS, true ) ? $variant : 'primary';
		$size    = in_array( $size, self::SIZES, true ) ? $size : 'md';
		$classes = esc_attr( 'saha-btn saha-btn--' . $variant . ' saha-btn--' . $size );

		return is_array( $link ) && ! empty( $link['url'] )
			? '<a class="' . $classes . '"' . Link::attributes( $link ) . '>' . esc_html( $text ) . '</a>'
			: '<span class="' . $classes . '">' . esc_html( $text ) . '</span>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', 'text-align', $node->prop( 'align' ) );
		$css->typography( ' .saha-btn', $node->prop( 'typography' ) );
		$css->set( ' .saha-btn', 'color', $node->prop( 'textColor' ) );
		$css->set( ' .saha-btn', 'background-color', $node->prop( 'bgColor' ) );
		$css->set( ' .saha-btn', 'border-color', $node->prop( 'bgColor' ) );
		$css->set( ' .saha-btn', 'border-radius', $node->prop( 'borderRadius' ) );
		$css->set( ' .saha-btn:hover', 'color', $node->prop( 'hoverText' ) );
		$css->set( ' .saha-btn:hover', 'background-color', $node->prop( 'hoverBg' ) );
		$css->set( ' .saha-btn:hover', 'border-color', $node->prop( 'hoverBg' ) );
	}
}
