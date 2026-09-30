<?php
/**
 * Element: Section.
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
 * Section — khối cấp gốc: nền, chiều cao, độ rộng nội dung.
 */
final class Section extends Element {

	public const TAGS = array( 'section', 'div', 'header', 'footer', 'aside', 'article' );

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'            => 'section',
			'name'            => __( 'Section', 'saha-core' ),
			'icon'            => 'table-row-before',
			'category'        => 'layout',
			'allowedParents'  => array( 'root' ),
			'allowedChildren' => array( 'row', 'heading', 'text', 'button', 'image' ),
			'controls'        => array(
				'layout'        => array(
					'type'    => 'select',
					'label'   => __( 'Độ rộng nội dung', 'saha-core' ),
					'section' => 'content',
					'default' => 'container',
					'options' => array(
						'container' => __( 'Theo khung (Theme Options)', 'saha-core' ),
						'full'      => __( 'Tràn màn hình', 'saha-core' ),
					),
				),
				'contentWidth'  => array(
					'type'    => 'size',
					'label'   => __( 'Độ rộng khung tuỳ chỉnh', 'saha-core' ),
					'section' => 'content',
					'units'   => array( 'px', '%' ),
					'min'     => 1,
					'max'     => 2560,
					'help'    => __( 'Để trống = độ rộng khung trong Theme Options.', 'saha-core' ),
				),
				'minHeight'     => array(
					'type'       => 'size',
					'label'      => __( 'Chiều cao tối thiểu', 'saha-core' ),
					'section'    => 'content',
					'responsive' => true,
					'units'      => array( 'px', 'vh' ),
					'min'        => 0,
					'max'        => 2000,
				),
				'verticalAlign' => array(
					'type'    => 'select',
					'label'   => __( 'Căn dọc nội dung', 'saha-core' ),
					'section' => 'content',
					'default' => 'top',
					'options' => array(
						'top'    => __( 'Trên', 'saha-core' ),
						'center' => __( 'Giữa', 'saha-core' ),
						'bottom' => __( 'Dưới', 'saha-core' ),
					),
				),
				'tag'           => array(
					'type'    => 'select',
					'label'   => __( 'Thẻ HTML', 'saha-core' ),
					'section' => 'content',
					'default' => 'section',
					'options' => array_combine( self::TAGS, self::TAGS ),
				),
				'background'    => array(
					'type'    => 'background',
					'label'   => __( 'Nền', 'saha-core' ),
					'section' => 'style',
				),
				'textColor'     => array(
					'type'    => 'color',
					'label'   => __( 'Màu chữ', 'saha-core' ),
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
	 * @param string        $content HTML con.
	 */
	public function render( Node $node, RenderContext $ctx, string $content ): string {
		$tag     = in_array( $this->prop( $node, 'tag' ), self::TAGS, true ) ? (string) $this->prop( $node, 'tag' ) : 'section';
		$classes = array( 'saha-section' );

		if ( 'full' === $this->prop( $node, 'layout' ) ) {
			$classes[] = 'saha-section--full';
		}

		$background = $node->prop( 'background' );

		if ( is_array( $background ) && ! empty( $background['overlay'] ) ) {
			$classes[] = 'saha-section--overlay';
		}

		return '<' . $tag . $this->rootAttributes( $node, $ctx, $classes ) . '><div class="saha-section__inner">' . $content . '</div></' . $tag . '>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', 'min-height', $node->prop( 'minHeight' ) );
		$css->set( '', '--saha-section-width', $node->prop( 'contentWidth' ) );
		$css->set(
			'',
			'justify-content',
			$node->prop( 'verticalAlign' ),
			static fn( $v ) => array(
				'top'    => 'flex-start',
				'center' => 'center',
				'bottom' => 'flex-end',
			)[ $v ] ?? null
		);
		$css->set( '', 'color', $node->prop( 'textColor' ) );
		// Theme thường đặt màu riêng cho h1–h6: áp màu chữ cho tiêu đề bên trong, specificity
		// (0,1,0) như style riêng của Heading — CSS của con sinh sau nên màu tự đặt vẫn thắng.
		$css->set( ' :where(h1, h2, h3, h4, h5, h6)', 'color', $node->prop( 'textColor' ) );

		self::background( $css, '', $node->prop( 'background' ) );
	}

	/**
	 * Khai báo nền (dùng lại cho element khác có nền).
	 *
	 * @param CssRules $css      Luật.
	 * @param string   $selector Hậu tố selector.
	 * @param mixed    $bg       Giá trị control background.
	 */
	public static function background( CssRules $css, string $selector, $bg ): void {
		if ( ! is_array( $bg ) ) {
			return;
		}

		$css->set( $selector, 'background-color', $bg['color'] ?? null );

		if ( ! empty( $bg['image']['id'] ) ) {
			$url = wp_get_attachment_image_url( (int) $bg['image']['id'], (string) ( $bg['image']['size'] ?? 'full' ) );

			if ( $url ) {
				$css->set( $selector, 'background-image', 'url("' . $url . '")' );
				$css->set( $selector, 'background-position', $bg['position'] ?? 'center center' );
				$css->set( $selector, 'background-size', $bg['size'] ?? 'cover' );
				$css->set( $selector, 'background-repeat', $bg['repeat'] ?? 'no-repeat' );
				$css->set( $selector, 'background-attachment', $bg['attachment'] ?? null );
			}
		}

		if ( ! empty( $bg['overlay']['color'] ) ) {
			$css->set( $selector . '::before', 'background-color', $bg['overlay']['color'] );
			$css->set( $selector . '::before', 'opacity', (string) ( $bg['overlay']['opacity'] ?? 0.5 ) );
		}
	}
}
