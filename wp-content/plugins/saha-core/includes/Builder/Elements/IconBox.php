<?php
/**
 * Element: Icon Box.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\Controls\Link;
use Saha\Core\Builder\CssRules;
use Saha\Core\Builder\Icons;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * Icon Box — icon + tiêu đề + mô tả (khối USP, lợi ích, dịch vụ).
 *
 * Có link → tiêu đề là liên kết (một link duy nhất, không lồng link).
 */
final class IconBox extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'iconbox',
			'name'           => __( 'Icon Box', 'saha-core' ),
			'icon'           => 'id-alt',
			'category'       => 'content',
			'allowedParents' => self::CONTENT_PARENTS,
			'controls'       => array(
				'icon'        => array(
					'type'    => 'icon',
					'label'   => __( 'Icon', 'saha-core' ),
					'section' => 'content',
					'default' => 'check-circle',
				),
				'title'       => array(
					'type'      => 'text',
					'label'     => __( 'Tiêu đề', 'saha-core' ),
					'section'   => 'content',
					'default'   => __( 'Hàng chính hãng', 'saha-core' ),
					'maxLength' => 200,
				),
				'titleTag'    => array(
					'type'    => 'select',
					'label'   => __( 'Thẻ tiêu đề', 'saha-core' ),
					'section' => 'content',
					'default' => 'h3',
					'options' => array(
						'h2'  => 'H2',
						'h3'  => 'H3',
						'h4'  => 'H4',
						'p'   => 'P',
						'div' => 'DIV',
					),
				),
				'description' => array(
					'type'      => 'textarea',
					'label'     => __( 'Mô tả', 'saha-core' ),
					'section'   => 'content',
					'maxLength' => 1000,
				),
				'link'        => array(
					'type'    => 'link',
					'label'   => __( 'Liên kết', 'saha-core' ),
					'section' => 'content',
				),
				'layout'      => array(
					'type'       => 'select',
					'label'      => __( 'Vị trí icon', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'default'    => 'top',
					'options'    => array(
						'top'  => __( 'Trên', 'saha-core' ),
						'left' => __( 'Trái', 'saha-core' ),
					),
				),
				'align'       => array(
					'type'       => 'align',
					'label'      => __( 'Căn lề', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'options'    => array( 'left', 'center', 'right' ),
				),
				'iconSize'    => array(
					'type'       => 'size',
					'label'      => __( 'Cỡ icon', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'units'      => array( 'px', 'rem' ),
					'min'        => 8,
					'max'        => 300,
				),
				'iconColor'   => array(
					'type'    => 'color',
					'label'   => __( 'Màu icon', 'saha-core' ),
					'section' => 'style',
				),
				'titleTypo'   => array(
					'type'    => 'typography',
					'label'   => __( 'Kiểu chữ tiêu đề', 'saha-core' ),
					'section' => 'style',
				),
				'titleColor'  => array(
					'type'    => 'color',
					'label'   => __( 'Màu tiêu đề', 'saha-core' ),
					'section' => 'style',
				),
				'textColor'   => array(
					'type'    => 'color',
					'label'   => __( 'Màu mô tả', 'saha-core' ),
					'section' => 'style',
				),
				'boxStyle'    => array(
					'type'    => 'select',
					'label'   => __( 'Kiểu khối', 'saha-core' ),
					'section' => 'style',
					'default' => 'plain',
					'options' => array(
						'plain' => __( 'Không khung', 'saha-core' ),
						'card'  => __( 'Thẻ có viền', 'saha-core' ),
						'shadow' => __( 'Thẻ có bóng', 'saha-core' ),
					),
				),
				'background'  => array(
					'type'    => 'color',
					'label'   => __( 'Màu nền khối', 'saha-core' ),
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
		$tag   = in_array( $this->prop( $node, 'titleTag' ), array( 'h2', 'h3', 'h4', 'p', 'div' ), true ) ? (string) $this->prop( $node, 'titleTag' ) : 'h3';
		$title = esc_html( (string) $this->prop( $node, 'title' ) );
		$link  = $node->prop( 'link' );

		if ( '' !== $title && is_array( $link ) && ! empty( $link['url'] ) ) {
			$title = '<a' . Link::attributes( $link ) . '>' . $title . '</a>';
		}

		$html = '';
		$svg  = Icons::svg( (string) $this->prop( $node, 'icon' ) );

		if ( '' !== $svg ) {
			$html .= '<div class="saha-iconbox__icon">' . $svg . '</div>';
		}

		$body = '' !== $title ? '<' . $tag . ' class="saha-iconbox__title">' . $title . '</' . $tag . '>' : '';
		$desc = (string) $node->prop( 'description', '' );

		if ( '' !== $desc ) {
			$body .= '<p class="saha-iconbox__text">' . nl2br( esc_html( $desc ) ) . '</p>';
		}

		$html .= '<div class="saha-iconbox__body">' . $body . '</div>';

		$box     = (string) $this->prop( $node, 'boxStyle' );
		$classes = in_array( $box, array( 'card', 'shadow' ), true ) ? array( 'saha-iconbox', 'saha-iconbox--' . $box ) : array( 'saha-iconbox' );

		return '<div' . $this->rootAttributes( $node, $ctx, $classes ) . '>' . $html . '</div>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', 'flex-direction', $this->prop( $node, 'layout' ), static fn( $v ) => 'left' === $v ? 'row' : 'column' );
		$css->set( '', 'text-align', $node->prop( 'align' ) );
		$css->set(
			'',
			'align-items',
			$node->prop( 'align' ),
			static fn( $v ) => array(
				'left'   => 'flex-start',
				'center' => 'center',
				'right'  => 'flex-end',
			)[ $v ] ?? null
		);
		$css->set( '', 'background-color', $node->prop( 'background' ) );

		if ( null !== $node->prop( 'background' ) ) {
			$css->put( 'desktop', '', 'padding', '24px' );
			$css->put( 'desktop', '', 'border-radius', 'var(--saha-radius, 6px)' );
		}

		$css->set( ' .saha-iconbox__icon', 'font-size', $node->prop( 'iconSize' ) );
		$css->set( ' .saha-iconbox__icon', 'color', $node->prop( 'iconColor' ) );
		$css->typography( ' .saha-iconbox__title', $node->prop( 'titleTypo' ) );
		$css->set( ' .saha-iconbox__title', 'color', $node->prop( 'titleColor' ) );
		$css->set( ' .saha-iconbox__title a', 'color', $node->prop( 'titleColor' ) );
		$css->set( ' .saha-iconbox__text', 'color', $node->prop( 'textColor' ) );
	}
}
