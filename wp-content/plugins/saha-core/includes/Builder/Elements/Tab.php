<?php
/**
 * Element: một tab của Tabs.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * Tab — panel (`role=tabpanel`) chứa element bất kỳ; nút tab do Tabs in từ `title`.
 */
final class Tab extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'            => 'tab',
			'name'            => __( 'Tab', 'saha-core' ),
			'icon'            => 'index-card',
			'category'        => 'layout',
			'allowedParents'  => array( 'tabs' ),
			'allowedChildren' => array( '*' ),
			'controls'        => array(
				'title' => array(
					'type'      => 'text',
					'label'     => __( 'Tiêu đề tab', 'saha-core' ),
					'section'   => 'content',
					'default'   => __( 'Tab mới', 'saha-core' ),
					'maxLength' => 80,
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
		// Panel ARIA ở thẻ trong: thẻ ngoài có thể mang ID tự đặt (Nâng cao → ID) mà không trùng.
		$label = $ctx->editor
			? '<div class="saha-tabs__edit-label">' . esc_html( sprintf( /* translators: %s: tiêu đề tab */ __( 'Tab: %s', 'saha-core' ), (string) $this->prop( $node, 'title' ) ) ) . '</div>'
			: '';

		return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-tabs__item' ) ) . '>' . $label
			. '<div class="saha-tabs__panel" id="saha-tp-' . esc_attr( $node->id ) . '" role="tabpanel" aria-labelledby="saha-tt-' . esc_attr( $node->id ) . '" tabindex="0">'
			. $content . '</div></div>';
	}
}
