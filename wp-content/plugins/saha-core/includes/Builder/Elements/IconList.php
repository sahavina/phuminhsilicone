<?php
/**
 * Element: danh sách có icon (✓ cam kết, ưu điểm…).
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
 * IconList — `<ul>` thật (trình đọc màn hình đọc là danh sách), icon `aria-hidden`.
 */
final class IconList extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'icon-list',
			'name'           => __( 'Danh sách icon', 'saha-core' ),
			'icon'           => 'check-circle',
			'category'       => 'content',
			'allowedParents' => self::CONTENT_PARENTS,
			'controls'       => array(
				'items'      => array(
					'type'      => 'textarea',
					'label'     => __( 'Các dòng (mỗi dòng một mục)', 'saha-core' ),
					'section'   => 'content',
					'default'   => __( "Hàng chính hãng, đủ chứng từ\nTư vấn kỹ thuật miễn phí\nGiao hàng toàn quốc", 'saha-core' ),
					'maxLength' => 2000,
				),
				'icon'       => array(
					'type'    => 'icon',
					'label'   => __( 'Icon', 'saha-core' ),
					'section' => 'content',
					'default' => 'check',
				),
				'iconStyle'  => array(
					'type'    => 'select',
					'label'   => __( 'Kiểu icon', 'saha-core' ),
					'section' => 'style',
					'default' => 'circle',
					'options' => array(
						'circle' => __( 'Trong vòng tròn tô màu', 'saha-core' ),
						'plain'  => __( 'Chỉ icon', 'saha-core' ),
					),
				),
				'layout'     => array(
					'type'    => 'select',
					'label'   => __( 'Bố cục', 'saha-core' ),
					'section' => 'style',
					'default' => 'vertical',
					'options' => array(
						'vertical'   => __( 'Dọc', 'saha-core' ),
						'horizontal' => __( 'Ngang', 'saha-core' ),
					),
				),
				'iconColor'  => array(
					'type'    => 'color',
					'label'   => __( 'Màu icon / vòng tròn', 'saha-core' ),
					'section' => 'style',
				),
				'color'      => array(
					'type'    => 'color',
					'label'   => __( 'Màu chữ', 'saha-core' ),
					'section' => 'style',
				),
				'gap'        => array(
					'type'       => 'size',
					'label'      => __( 'Khoảng cách giữa dòng', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'units'      => array( 'px' ),
					'min'        => 0,
					'max'        => 60,
				),
				'typography' => array(
					'type'    => 'typography',
					'label'   => __( 'Kiểu chữ', 'saha-core' ),
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
		$items = array_values( array_filter( array_map( 'trim', preg_split( '/\R/u',(string) $this->prop( $node, 'items' ) ) ?: array() ), 'strlen' ) );

		if ( ! $items ) {
			return $ctx->editor ? '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-icon-list', 'saha-image--empty' ) ) . '>' . esc_html__( 'Nhập các dòng', 'saha-core' ) . '</div>' : '';
		}

		$icon = Icons::svg( (string) $this->prop( $node, 'icon' ), 'saha-icon-list__icon' );
		$html = '';

		foreach ( $items as $item ) {
			$html .= '<li class="saha-icon-list__item"><span class="saha-icon-list__mark" aria-hidden="true">' . $icon . '</span><span class="saha-icon-list__text">' . esc_html( $item ) . '</span></li>';
		}

		$classes = array(
			'saha-icon-list',
			'saha-icon-list--' . ( 'plain' === $this->prop( $node, 'iconStyle' ) ? 'plain' : 'circle' ),
			'saha-icon-list--' . ( 'horizontal' === $this->prop( $node, 'layout' ) ? 'horizontal' : 'vertical' ),
		);

		return '<ul' . $this->rootAttributes( $node, $ctx, $classes ) . '>' . $html . '</ul>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', '--saha-icon-list-color', $node->prop( 'iconColor' ) );
		$css->set( '', 'color', $node->prop( 'color' ) );
		$css->set( '', 'gap', $node->prop( 'gap' ) );
		$css->typography( '', $node->prop( 'typography' ) );
	}
}
