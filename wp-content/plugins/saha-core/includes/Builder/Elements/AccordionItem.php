<?php
/**
 * Element: một mục accordion / một câu hỏi.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\Icons;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * AccordionItem — tiêu đề (câu hỏi) + nội dung soạn thảo (câu trả lời).
 * Trong editor luôn mở để sửa thấy nội dung.
 */
final class AccordionItem extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'accordion-item',
			'name'           => __( 'Một mục hỏi đáp', 'saha-core' ),
			'icon'           => 'info',
			'category'       => 'content',
			'allowedParents' => array( 'accordion' ),
			'controls'       => array(
				'title'   => array(
					'type'      => 'text',
					'label'     => __( 'Câu hỏi / tiêu đề', 'saha-core' ),
					'section'   => 'content',
					'default'   => __( 'Câu hỏi thường gặp?', 'saha-core' ),
					'maxLength' => 300,
				),
				'content' => array(
					'type'    => 'richtext',
					'label'   => __( 'Trả lời / nội dung', 'saha-core' ),
					'section' => 'content',
					'default' => '<p>' . __( 'Nội dung trả lời.', 'saha-core' ) . '</p>',
				),
				'open'    => array(
					'type'    => 'toggle',
					'label'   => __( 'Mở sẵn', 'saha-core' ),
					'section' => 'content',
					'default' => false,
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
		$attrs = $ctx->editor || $this->prop( $node, 'open' ) ? array( 'open' => 'open' ) : array();

		return '<details' . $this->rootAttributes( $node, $ctx, array( 'saha-acc-item' ), $attrs ) . '>'
			. '<summary class="saha-acc-item__title"><span>' . esc_html( (string) $this->prop( $node, 'title' ) ) . '</span>' . Icons::svg( 'chevron-right', 'saha-acc-item__icon' ) . '</summary>'
			. '<div class="saha-acc-item__body saha-prose">' . wp_kses_post( (string) $this->prop( $node, 'content' ) ) . '</div></details>';
	}
}
