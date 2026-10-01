<?php
/**
 * Element: tiêu đề khối (tiêu đề + gạch trang trí + mô tả + link "Xem tất cả").
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\Controls\Link;
use Saha\Core\Builder\CssRules;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;
use Saha\Core\Builder\Icons;

defined( 'ABSPATH' ) || exit;

/**
 * SectionTitle — đầu mỗi khối trang chủ: tiêu đề (H2 mặc định) với gạch ngắn màu nhấn
 * bên dưới, mô tả tuỳ chọn, link "Xem tất cả →" bên phải (căn trái) hoặc bên dưới (căn giữa).
 */
final class SectionTitle extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'section-title',
			'name'           => __( 'Tiêu đề khối', 'saha-core' ),
			'icon'           => 'heading',
			'category'       => 'content',
			'allowedParents' => self::CONTENT_PARENTS,
			'controls'       => array(
				'title'      => array(
					'type'      => 'text',
					'label'     => __( 'Tiêu đề', 'saha-core' ),
					'section'   => 'content',
					'default'   => __( 'Sản phẩm nổi bật', 'saha-core' ),
					'maxLength' => 200,
				),
				'tag'        => array(
					'type'    => 'select',
					'label'   => __( 'Thẻ', 'saha-core' ),
					'section' => 'content',
					'default' => 'h2',
					'options' => array(
						'h1' => 'H1',
						'h2' => 'H2',
						'h3' => 'H3',
						'p'  => __( 'Đoạn văn', 'saha-core' ),
					),
				),
				'subtitle'   => array(
					'type'      => 'textarea',
					'label'     => __( 'Mô tả', 'saha-core' ),
					'section'   => 'content',
					'maxLength' => 400,
				),
				'linkText'   => array(
					'type'      => 'text',
					'label'     => __( 'Chữ link bên phải', 'saha-core' ),
					'section'   => 'content',
					'default'   => __( 'Xem tất cả', 'saha-core' ),
					'maxLength' => 60,
				),
				'link'       => array(
					'type'    => 'link',
					'label'   => __( 'Link "Xem tất cả"', 'saha-core' ),
					'section' => 'content',
					'help'    => __( 'Để trống = không hiện link.', 'saha-core' ),
				),
				'decoration' => array(
					'type'    => 'select',
					'label'   => __( 'Trang trí', 'saha-core' ),
					'section' => 'style',
					'default' => 'bar',
					'options' => array(
						'bar'  => __( 'Gạch ngắn dưới tiêu đề', 'saha-core' ),
						'none' => __( 'Không', 'saha-core' ),
					),
				),
				'align'      => array(
					'type'    => 'select',
					'label'   => __( 'Căn', 'saha-core' ),
					'section' => 'style',
					'default' => 'left',
					'options' => array(
						'left'   => __( 'Trái (link bên phải)', 'saha-core' ),
						'center' => __( 'Giữa', 'saha-core' ),
					),
				),
				'barColor'   => array(
					'type'    => 'color',
					'label'   => __( 'Màu gạch', 'saha-core' ),
					'section' => 'style',
				),
				'color'      => array(
					'type'    => 'color',
					'label'   => __( 'Màu tiêu đề', 'saha-core' ),
					'section' => 'style',
				),
				'typography' => array(
					'type'    => 'typography',
					'label'   => __( 'Kiểu chữ tiêu đề', 'saha-core' ),
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
		$title = (string) $this->prop( $node, 'title' );

		if ( '' === trim( $title ) ) {
			return '';
		}

		$tag      = in_array( $this->prop( $node, 'tag' ), array( 'h1', 'h2', 'h3', 'p' ), true ) ? (string) $this->prop( $node, 'tag' ) : 'h2';
		$subtitle = (string) $node->prop( 'subtitle', '' );
		$link     = $node->prop( 'link' );
		$more     = '';

		if ( is_array( $link ) && ! empty( $link['url'] ) && '' !== trim( (string) $this->prop( $node, 'linkText' ) ) ) {
			$more = '<a class="saha-stitle__more"' . Link::attributes( $link ) . '>' . esc_html( (string) $this->prop( $node, 'linkText' ) ) . Icons::svg( 'arrow-right', 'saha-stitle__arrow' ) . '</a>';
		}

		$classes = array(
			'saha-stitle',
			'saha-stitle--' . ( 'center' === $this->prop( $node, 'align' ) ? 'center' : 'left' ),
		);

		if ( 'bar' === $this->prop( $node, 'decoration' ) ) {
			$classes[] = 'saha-stitle--bar';
		}

		return '<div' . $this->rootAttributes( $node, $ctx, $classes ) . '>'
			. '<div class="saha-stitle__text"><' . $tag . ' class="saha-stitle__title">' . esc_html( $title ) . '</' . $tag . '>'
			. ( '' !== $subtitle ? '<p class="saha-stitle__sub">' . esc_html( $subtitle ) . '</p>' : '' )
			. '</div>' . $more . '</div>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( ' .saha-stitle__title', 'color', $node->prop( 'color' ) );
		$css->set( ' .saha-stitle__title::after', 'background-color', $node->prop( 'barColor' ) );
		$css->typography( ' .saha-stitle__title', $node->prop( 'typography' ) );
	}
}
