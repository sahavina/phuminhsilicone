<?php
/**
 * Element: Logo.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\CssRules;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;
use Saha\Core\ThemeOptions\Repository as ThemeOptions;

defined( 'ABSPATH' ) || exit;

/**
 * Logo — link về trang chủ. Ảnh: chọn riêng → logo trong Theme Options →
 * Custom Logo của WordPress → tên website. Có logo riêng cho mobile.
 * Ảnh không lazy-load (nằm trên màn hình đầu), có width/height (không CLS).
 */
final class Logo extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'logo',
			'name'           => __( 'Logo', 'saha-core' ),
			'icon'           => 'admin-site-alt3',
			'category'       => 'header',
			'allowedParents' => self::CONTENT_PARENTS,
			'controls'       => array(
				'image'       => array(
					'type'        => 'media',
					'label'       => __( 'Ảnh logo', 'saha-core' ),
					'section'     => 'content',
					'defaultSize' => 'medium',
					'help'        => __( 'Để trống = logo trong Theme Options.', 'saha-core' ),
				),
				'imageMobile' => array(
					'type'        => 'media',
					'label'       => __( 'Logo trên mobile', 'saha-core' ),
					'section'     => 'content',
					'defaultSize' => 'medium',
				),
				'height'      => array(
					'type'       => 'size',
					'label'      => __( 'Chiều cao', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'units'      => array( 'px' ),
					'min'        => 16,
					'max'        => 200,
				),
				'textTypo'    => array(
					'type'    => 'typography',
					'label'   => __( 'Kiểu chữ (khi chưa có ảnh logo)', 'saha-core' ),
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
		$name   = get_bloginfo( 'name' );
		$image  = $node->prop( 'image' );
		$mobile = $node->prop( 'imageMobile' );
		$id     = is_array( $image ) ? (int) ( $image['id'] ?? 0 ) : 0;

		if ( 0 === $id && class_exists( ThemeOptions::class ) ) {
			$id = (int) ThemeOptions::get( 'general.logo', 0 );
		}

		if ( 0 === $id ) {
			$id = (int) get_theme_mod( 'custom_logo' );
		}

		// Tải ngay (đầu trang) nhưng không `fetchpriority="high"`: header thường in logo 2–3 lần
		// (desktop, mobile, menu trượt) và logo nhỏ hiếm khi là LCP — ưu tiên dành cho ảnh hero
		// (Banner / Slide bật "Ưu tiên tải"). QA 2.8.
		$attrs = array(
			'class'   => 'saha-logo-el__img',
			'alt'     => $name,
			'loading' => 'eager',
		);

		$html = $id > 0 ? (string) wp_get_attachment_image( $id, is_array( $image ) ? (string) ( $image['size'] ?? 'medium' ) : 'medium', false, $attrs ) : '';
		$mid  = is_array( $mobile ) ? (int) ( $mobile['id'] ?? 0 ) : 0;
		$cls  = array( 'saha-logo-el' );

		if ( '' !== $html && $mid > 0 && $mid !== $id ) {
			$attrs['class'] = 'saha-logo-el__img saha-logo-el__img--mobile';
			$html  = str_replace( 'class="saha-logo-el__img"', 'class="saha-logo-el__img saha-logo-el__img--desktop"', $html );
			$html .= (string) wp_get_attachment_image( $mid, (string) ( $mobile['size'] ?? 'medium' ), false, $attrs );
			$cls[] = 'has-mobile';
		}

		if ( '' === $html ) {
			$html  = '<span class="saha-logo-el__text">' . esc_html( $name ) . '</span>';
			$cls[] = 'saha-logo-el--text';
		}

		return '<a href="' . esc_url( home_url( '/' ) ) . '" rel="home"' . $this->rootAttributes( $node, $ctx, $cls ) . '>' . $html . '</a>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( ' img', 'height', $node->prop( 'height' ) );
		$css->typography( ' .saha-logo-el__text', $node->prop( 'textTypo' ) );
	}
}
