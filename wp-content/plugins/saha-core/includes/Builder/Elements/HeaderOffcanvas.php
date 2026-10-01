<?php
/**
 * Element: menu di động (off-canvas).
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
 * HeaderOffcanvas — bảng trượt mở bằng element "Nút menu".
 *
 * Frontend: `hidden` cho tới khi mở; role=dialog, aria-modal, Esc để đóng, giữ
 * focus trong bảng (public/assets/js/header.js). Editor: hiện luôn bên dưới
 * header để kéo thả nội dung vào.
 */
final class HeaderOffcanvas extends Element {

	/**
	 * ID của bảng (nút menu trỏ tới bằng aria-controls).
	 *
	 * @param int $template_id Template header.
	 */
	public static function panelId( int $template_id ): string {
		return 'saha-offcanvas-' . $template_id;
	}

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'            => 'header-offcanvas',
			'name'            => __( 'Menu di động (off-canvas)', 'saha-core' ),
			'icon'            => 'menu-alt3',
			'category'        => 'header',
			'allowedParents'  => array( 'site-header' ),
			'allowedChildren' => array( '*' ),
			'controls'        => array(
				'side'       => array(
					'type'    => 'select',
					'label'   => __( 'Trượt từ', 'saha-core' ),
					'section' => 'content',
					'default' => 'left',
					'options' => array(
						'left'  => __( 'Bên trái', 'saha-core' ),
						'right' => __( 'Bên phải', 'saha-core' ),
					),
				),
				'label'      => array(
					'type'      => 'text',
					'label'     => __( 'Tên cho trình đọc màn hình', 'saha-core' ),
					'section'   => 'content',
					'default'   => __( 'Menu', 'saha-core' ),
					'maxLength' => 60,
				),
				'width'      => array(
					'type'    => 'size',
					'label'   => __( 'Độ rộng', 'saha-core' ),
					'section' => 'style',
					'units'   => array( 'px', 'vw' ),
					'min'     => 200,
					'max'     => 600,
				),
				'background' => array(
					'type'    => 'color',
					'label'   => __( 'Màu nền', 'saha-core' ),
					'section' => 'style',
				),
				'textColor'  => array(
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
		$side  = 'right' === $this->prop( $node, 'side' ) ? 'right' : 'left';
		$label = (string) $this->prop( $node, 'label' );

		if ( $ctx->editor ) {
			return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-hb-offcanvas', 'is-editor' ) ) . '><p class="saha-hb-offcanvas__hint">' . esc_html__( 'Menu di động — hiện khi bấm nút menu trên điện thoại', 'saha-core' ) . '</p>' . $content . '</div>';
		}

		$close = '<button type="button" class="saha-hb-offcanvas__close" data-saha-close>' . Icons::svg( 'close' ) . '<span class="screen-reader-text">' . esc_html__( 'Đóng menu', 'saha-core' ) . '</span></button>';

		return '<div' . $this->rootAttributes(
			$node,
			$ctx,
			array( 'saha-hb-offcanvas', 'saha-hb-offcanvas--' . $side ),
			array( 'id' => self::panelId( $ctx->postId ) )
		) . ' hidden><div class="saha-hb-offcanvas__backdrop" data-saha-close></div><div class="saha-hb-offcanvas__panel" role="dialog" aria-modal="true" aria-label="' . esc_attr( $label ) . '" tabindex="-1">' . $close . $content . '</div></div>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( ' .saha-hb-offcanvas__panel', 'width', $node->prop( 'width' ) );
		$css->set( ' .saha-hb-offcanvas__panel', 'background-color', $node->prop( 'background' ) );
		$css->set( ' .saha-hb-offcanvas__panel', 'color', $node->prop( 'textColor' ) );
	}
}
