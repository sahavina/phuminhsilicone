<?php
/**
 * Element: vùng trong hàng header.
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
 * HeaderZone — vùng trái / giữa / phải (theo thứ tự trong hàng). Vùng giữa luôn
 * ở giữa header kể cả khi hai bên dài ngắn khác nhau.
 */
final class HeaderZone extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'            => 'header-zone',
			'name'            => __( 'Vùng header', 'saha-core' ),
			'icon'            => 'editor-table',
			'category'        => 'header',
			'allowedParents'  => array( 'header-row' ),
			'allowedChildren' => array( '*' ),
			'controls'        => array(
				'gap'   => array(
					'type'       => 'size',
					'label'      => __( 'Khoảng cách giữa các mục', 'saha-core' ),
					'section'    => 'content',
					'responsive' => true,
					'units'      => array( 'px' ),
					'min'        => 0,
					'max'        => 100,
				),
				'grow'  => array(
					'type'    => 'toggle',
					'label'   => __( 'Chiếm phần còn lại (ví dụ ô tìm kiếm dài)', 'saha-core' ),
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
	 * @param string        $content HTML con.
	 */
	public function render( Node $node, RenderContext $ctx, string $content ): string {
		$classes = array( 'saha-hb-zone' );

		if ( $this->prop( $node, 'grow' ) ) {
			$classes[] = 'saha-hb-zone--grow';
		}

		return '<div' . $this->rootAttributes( $node, $ctx, $classes ) . '>' . $content . '</div>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', 'gap', $node->prop( 'gap' ) );
	}
}
