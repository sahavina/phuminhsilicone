<?php
/**
 * Element: Dòng bản quyền.
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
 * Copyright — "© {year} {site}" (năm tự đổi).
 */
final class Copyright extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'copyright',
			'name'           => __( 'Bản quyền', 'saha-core' ),
			'icon'           => 'info-outline',
			'category'       => 'header',
			'allowedParents' => self::CONTENT_PARENTS,
			'controls'       => array(
				'text'       => array(
					'type'      => 'text',
					'label'     => __( 'Nội dung', 'saha-core' ),
					'section'   => 'content',
					'default'   => '© {year} {site}. ' . __( 'Bảo lưu mọi quyền.', 'saha-core' ),
					'maxLength' => 300,
					'help'      => __( '{year} = năm hiện tại, {site} = tên website.', 'saha-core' ),
				),
				'align'      => array(
					'type'       => 'align',
					'label'      => __( 'Căn lề', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'options'    => array( 'left', 'center', 'right' ),
				),
				'color'      => array(
					'type'    => 'color',
					'label'   => __( 'Màu', 'saha-core' ),
					'section' => 'style',
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
		$text = strtr(
			(string) $this->prop( $node, 'text' ),
			array(
				'{year}' => wp_date( 'Y' ),
				'{site}' => get_bloginfo( 'name' ),
			)
		);

		return '<p' . $this->rootAttributes( $node, $ctx, array( 'saha-copyright' ) ) . '>' . esc_html( $text ) . '</p>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', 'text-align', $node->prop( 'align' ) );
		$css->set( '', 'color', $node->prop( 'color' ) );
		$css->typography( '', $node->prop( 'typography' ) );
	}
}
