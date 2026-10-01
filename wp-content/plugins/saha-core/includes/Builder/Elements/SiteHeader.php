<?php
/**
 * Element: khung Header.
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
 * SiteHeader — gốc của template header: thẻ <header>, chế độ dính khi cuộn.
 *
 * Cấu trúc: SiteHeader › Hàng header (trên / chính / dưới — mỗi hàng ẩn/hiện theo
 * thiết bị) › 3 vùng trái/giữa/phải › element; thêm một Menu di động (off-canvas).
 * Thiết lập dính nằm trên element này (thay cho meta `_saha_header_settings` của
 * thiết kế: một nguồn dữ liệu, sửa ngay trong bảng thiết lập, có undo/revision).
 */
final class SiteHeader extends Element {

	public const MODES = array( 'none', 'always', 'up' );

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'            => 'site-header',
			'name'            => __( 'Header', 'saha-core' ),
			'icon'            => 'align-wide',
			'category'        => 'header',
			'allowedParents'  => array( 'header-root' ),
			'allowedChildren' => array( 'header-row', 'header-offcanvas' ),
			'controls'        => array(
				'sticky'     => array(
					'type'    => 'select',
					'label'   => __( 'Dính khi cuộn', 'saha-core' ),
					'section' => 'content',
					'default' => 'always',
					'options' => array(
						'none'   => __( 'Không', 'saha-core' ),
						'always' => __( 'Luôn hiện', 'saha-core' ),
						'up'     => __( 'Chỉ hiện khi cuộn lên', 'saha-core' ),
					),
				),
				'shadow'     => array(
					'type'    => 'toggle',
					'label'   => __( 'Đổ bóng khi đang dính', 'saha-core' ),
					'section' => 'content',
					'default' => true,
				),
				'background' => array(
					'type'    => 'color',
					'label'   => __( 'Màu nền', 'saha-core' ),
					'section' => 'style',
					'default' => 'var(--saha-background)',
				),
				'stickyBg'   => array(
					'type'    => 'color',
					'label'   => __( 'Màu nền khi đang dính', 'saha-core' ),
					'section' => 'style',
				),
				'textColor'  => array(
					'type'    => 'color',
					'label'   => __( 'Màu chữ', 'saha-core' ),
					'section' => 'style',
				),
				'border'     => array(
					'type'    => 'color',
					'label'   => __( 'Màu viền dưới', 'saha-core' ),
					'section' => 'style',
					'default' => 'var(--saha-border)',
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
		$mode    = in_array( $this->prop( $node, 'sticky' ), self::MODES, true ) ? (string) $this->prop( $node, 'sticky' ) : 'none';
		$classes = array( 'saha-hb', 'saha-builder-content', 'saha-hb--sticky-' . $mode );

		if ( $this->prop( $node, 'shadow' ) ) {
			$classes[] = 'saha-hb--shadow';
		}

		$ctx->addAssets( array( 'script' => array( 'saha-builder-header' ) ) );

		return '<header' . $this->rootAttributes( $node, $ctx, $classes, array( 'data-saha-sticky' => $ctx->editor ? 'none' : $mode ) ) . '>' . $content . '</header>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', 'background-color', $this->prop( $node, 'background' ) );
		$css->set( '', 'color', $node->prop( 'textColor' ) );
		$css->set( '', 'border-bottom-color', $this->prop( $node, 'border' ) );
		$css->set( '.is-stuck', 'background-color', $node->prop( 'stickyBg' ) );
	}
}
