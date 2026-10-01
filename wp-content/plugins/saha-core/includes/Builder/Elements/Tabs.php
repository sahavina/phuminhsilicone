<?php
/**
 * Element: Tabs — nhóm tab, mỗi tab chứa element bất kỳ.
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
 * Tabs — ARIA tabs (`role=tablist/tab/tabpanel`, phím ←/→/Home/End) dùng chung JS với tab của
 * element Sản phẩm (`elements.js`, `[data-saha-tabs]`). Tiêu đề tab lấy từ prop `title` của từng Tab;
 * tab đầu mở sẵn, tab khác có `hidden` (HTML tĩnh, cache được).
 */
final class Tabs extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'            => 'tabs',
			'name'            => __( 'Tabs', 'saha-core' ),
			'icon'            => 'index-card',
			'category'        => 'layout',
			'allowedParents'  => self::CONTENT_PARENTS,
			'allowedChildren' => array( 'tab' ),
			'initialChildren' => array( 'tab', 'tab' ),
			'controls'        => array(
				'style'  => array(
					'type'    => 'select',
					'label'   => __( 'Kiểu', 'saha-core' ),
					'section' => 'content',
					'default' => 'line',
					'options' => array(
						'line' => __( 'Gạch chân', 'saha-core' ),
						'pill' => __( 'Nút bo tròn', 'saha-core' ),
						'box'  => __( 'Thẻ có khung', 'saha-core' ),
					),
				),
				'align'  => array(
					'type'    => 'select',
					'label'   => __( 'Căn hàng tab', 'saha-core' ),
					'section' => 'content',
					'default' => 'left',
					'options' => array(
						'left'    => __( 'Trái', 'saha-core' ),
						'center'  => __( 'Giữa', 'saha-core' ),
						'stretch' => __( 'Giãn đều', 'saha-core' ),
					),
				),
				'label'  => array(
					'type'      => 'text',
					'label'     => __( 'Tên nhóm tab (cho trình đọc màn hình)', 'saha-core' ),
					'section'   => 'content',
					'maxLength' => 80,
				),
				'accent' => array(
					'type'    => 'color',
					'label'   => __( 'Màu tab đang chọn', 'saha-core' ),
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
	 * @param string        $content HTML các tab (panel).
	 */
	public function render( Node $node, RenderContext $ctx, string $content ): string {
		$list  = '';
		$first = true;
		$i     = 0;

		foreach ( $node->children as $child ) {
			if ( 'tab' !== $child->type ) {
				continue;
			}

			++$i;
			$title = trim( (string) $child->prop( 'title', '' ) );
			$title = '' !== $title ? $title : sprintf( /* translators: %d: số thứ tự */ __( 'Tab %d', 'saha-core' ), $i );

			$list .= '<button type="button" role="tab" class="saha-tabs__tab" id="saha-tt-' . esc_attr( $child->id ) . '" aria-controls="saha-tp-' . esc_attr( $child->id ) . '" aria-selected="' . ( $first ? 'true' : 'false' ) . '"' . ( $first ? '' : ' tabindex="-1"' ) . '>' . esc_html( $title ) . '</button>';

			// Panel do Tab in ra (theo ID) — tab không phải đầu tiên ẩn sẵn. Trong editor hiện mọi tab
			// (xếp chồng, có nhãn) để sửa được nội dung từng tab ngay trên canvas.
			if ( ! $first && ! $ctx->editor ) {
				$content = str_replace( 'id="saha-tp-' . $child->id . '"', 'id="saha-tp-' . $child->id . '" hidden', $content );
			}

			$first = false;
		}

		$label = trim( (string) $this->prop( $node, 'label' ) );
		$cls   = array( 'saha-tabs', 'saha-tabs--' . sanitize_html_class( (string) $this->prop( $node, 'style' ) ), 'saha-tabs--align-' . sanitize_html_class( (string) $this->prop( $node, 'align' ) ) );

		return '<div' . $this->rootAttributes( $node, $ctx, $cls, array( 'data-saha-tabs' => '1' ) ) . '>'
			. '<div class="saha-tabs__list" role="tablist"' . ( '' !== $label ? ' aria-label="' . esc_attr( $label ) . '"' : '' ) . '>' . $list . '</div>'
			. '<div class="saha-tabs__panels">' . $content . '</div></div>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', '--saha-tabs-accent', $node->prop( 'accent' ) );
	}
}
