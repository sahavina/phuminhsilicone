<?php
/**
 * Element: Banner.
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
 * Banner — ảnh nền + tiêu đề + mô tả + nút (hero đầu trang, banner khuyến mại).
 *
 * Ảnh nền là thẻ <img> (object-fit: cover), không phải CSS background: có
 * srcset/sizes theo màn hình và `fetchpriority="high"` khi bật "ảnh đầu trang"
 * → trình duyệt tải sớm, tốt cho LCP (spec §25, §96).
 */
final class Banner extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'banner',
			'name'           => __( 'Banner', 'saha-core' ),
			'icon'           => 'cover-image',
			'category'       => 'marketing',
			'allowedParents' => array( 'root', 'section', 'column', 'container' ),
			'controls'       => array(
				'image'       => array(
					'type'        => 'media',
					'label'       => __( 'Ảnh nền', 'saha-core' ),
					'section'     => 'content',
					'defaultSize' => 'full',
				),
				'priority'    => array(
					'type'    => 'toggle',
					'label'   => __( 'Ảnh đầu trang (ưu tiên tải — chỉ bật cho banner đầu tiên)', 'saha-core' ),
					'section' => 'content',
					'default' => false,
				),
				'title'       => array(
					'type'      => 'text',
					'label'     => __( 'Tiêu đề', 'saha-core' ),
					'section'   => 'content',
					'default'   => __( 'Tổng kho keo dán chính hãng', 'saha-core' ),
					'maxLength' => 200,
				),
				'titleTag'    => array(
					'type'    => 'select',
					'label'   => __( 'Thẻ tiêu đề', 'saha-core' ),
					'section' => 'content',
					'default' => 'h2',
					'options' => array(
						'h1' => 'H1',
						'h2' => 'H2',
						'h3' => 'H3',
						'p'  => 'P',
					),
				),
				'text'        => array(
					'type'      => 'textarea',
					'label'     => __( 'Mô tả', 'saha-core' ),
					'section'   => 'content',
					'maxLength' => 600,
				),
				'buttonText'  => array(
					'type'      => 'text',
					'label'     => __( 'Chữ trên nút', 'saha-core' ),
					'section'   => 'content',
					'maxLength' => 80,
				),
				'buttonLink'  => array(
					'type'    => 'link',
					'label'   => __( 'Liên kết nút', 'saha-core' ),
					'section' => 'content',
				),
				'minHeight'   => array(
					'type'       => 'size',
					'label'      => __( 'Chiều cao tối thiểu', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'default'    => '420px',
					'units'      => array( 'px', 'vh' ),
					'min'        => 80,
					'max'        => 2000,
				),
				'overlay'     => array(
					'type'    => 'color',
					'label'   => __( 'Màu lớp phủ', 'saha-core' ),
					'section' => 'style',
					'default' => 'rgba(0,0,0,.45)',
				),
				'textColor'   => array(
					'type'    => 'color',
					'label'   => __( 'Màu chữ', 'saha-core' ),
					'section' => 'style',
					'default' => '#ffffff',
				),
				'contentWidth' => array(
					'type'    => 'size',
					'label'   => __( 'Độ rộng phần chữ', 'saha-core' ),
					'section' => 'style',
					'units'   => array( 'px', '%' ),
					'min'     => 100,
					'max'     => 2000,
					'default' => '640px',
				),
				'hAlign'      => array(
					'type'       => 'align',
					'label'      => __( 'Căn ngang', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'options'    => array( 'left', 'center', 'right' ),
				),
				'vAlign'      => array(
					'type'    => 'select',
					'label'   => __( 'Căn dọc', 'saha-core' ),
					'section' => 'style',
					'default' => 'center',
					'options' => array(
						'top'    => __( 'Trên', 'saha-core' ),
						'center' => __( 'Giữa', 'saha-core' ),
						'bottom' => __( 'Dưới', 'saha-core' ),
					),
				),
				'titleTypo'   => array(
					'type'    => 'typography',
					'label'   => __( 'Kiểu chữ tiêu đề', 'saha-core' ),
					'section' => 'style',
				),
				'buttonStyle' => array(
					'type'    => 'select',
					'label'   => __( 'Kiểu nút', 'saha-core' ),
					'section' => 'style',
					'default' => 'primary',
					'options' => array(
						'primary'   => __( 'Chính', 'saha-core' ),
						'secondary' => __( 'Phụ', 'saha-core' ),
						'accent'    => __( 'Nhấn (màu nhấn)', 'saha-core' ),
						'outline'   => __( 'Viền', 'saha-core' ),
					),
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
		$html  = '';
		$image = $node->prop( 'image' );

		if ( is_array( $image ) && ! empty( $image['id'] ) ) {
			$attrs = array(
				'class' => 'saha-banner__bg',
				'alt'   => '',
				'sizes' => '100vw',
			);

			if ( $this->prop( $node, 'priority' ) ) {
				$attrs['loading']       = 'eager';
				$attrs['fetchpriority'] = 'high';
			} else {
				$attrs['loading'] = 'lazy';
			}

			// Ảnh nền trang trí → alt rỗng; nội dung nằm ở tiêu đề/mô tả.
			$html .= (string) wp_get_attachment_image( (int) $image['id'], (string) ( $image['size'] ?? 'full' ), false, $attrs );
		}

		$tag   = in_array( $this->prop( $node, 'titleTag' ), array( 'h1', 'h2', 'h3', 'p' ), true ) ? (string) $this->prop( $node, 'titleTag' ) : 'h2';
		$title = (string) $this->prop( $node, 'title' );
		$text  = (string) $node->prop( 'text', '' );
		$body  = '' !== $title ? '<' . $tag . ' class="saha-banner__title">' . esc_html( $title ) . '</' . $tag . '>' : '';

		if ( '' !== $text ) {
			$body .= '<p class="saha-banner__text">' . nl2br( esc_html( $text ) ) . '</p>';
		}

		$button = Button::markup( $node->prop( 'buttonLink' ), (string) $node->prop( 'buttonText', '' ), (string) $this->prop( $node, 'buttonStyle' ), 'lg' );

		if ( '' !== $button ) {
			$body .= '<div class="saha-banner__actions">' . $button . '</div>';
		}

		$html .= '<div class="saha-banner__content">' . $body . '</div>';

		return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-banner' ) ) . '>' . $html . '</div>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', 'min-height', $this->prop( $node, 'minHeight' ) );
		$css->set( '', 'color', $this->prop( $node, 'textColor' ) );
		$css->set( ' :where(h1, h2, h3, p)', 'color', $this->prop( $node, 'textColor' ) );
		$css->set( '::before', 'background-color', $this->prop( $node, 'overlay' ) );
		$css->set( ' .saha-banner__content', 'max-width', $this->prop( $node, 'contentWidth' ) );
		$css->set( '', 'text-align', $node->prop( 'hAlign' ) );
		$css->set(
			'',
			'align-items',
			$this->prop( $node, 'vAlign' ),
			static fn( $v ) => array(
				'top'    => 'flex-start',
				'center' => 'center',
				'bottom' => 'flex-end',
			)[ $v ] ?? null
		);
		$css->set(
			'',
			'justify-content',
			$node->prop( 'hAlign' ),
			static fn( $v ) => array(
				'left'   => 'flex-start',
				'center' => 'center',
				'right'  => 'flex-end',
			)[ $v ] ?? null
		);
		$css->typography( ' .saha-banner__title', $node->prop( 'titleTypo' ) );
	}
}
