<?php
/**
 * Element: HTML.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * HTML — nhúng mã (bản đồ Google, video, form bên thứ ba…).
 *
 * Nội dung đã được lọc lúc lưu theo quyền người lưu (Controls\Html): chỉ người có
 * `unfiltered_html` mới lưu được script/iframe. Render in nguyên văn — giống khối
 * "HTML tuỳ chỉnh" của WordPress.
 */
final class Html extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'html',
			'name'           => __( 'HTML', 'saha-core' ),
			'icon'           => 'editor-code',
			'category'       => 'content',
			'allowedParents' => self::CONTENT_PARENTS,
			'controls'       => array(
				'html' => array(
					'type'    => 'html',
					'label'   => __( 'Mã HTML', 'saha-core' ),
					'section' => 'content',
					'help'    => __( 'Dùng để nhúng bản đồ, video, mã theo dõi… Mã script chỉ chạy ngoài website, không chạy trong trình soạn thảo.', 'saha-core' ),
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
		$html = (string) $node->prop( 'html', '' );

		if ( '' === $html ) {
			return $ctx->editor ? '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-html', 'saha-image--empty' ) ) . '>' . esc_html__( 'Nhập mã HTML', 'saha-core' ) . '</div>' : '';
		}

		// Đã lọc theo quyền người lưu — in nguyên văn (phpcs: xem mô tả lớp).
		return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-html' ) ) . '>' . $html . '</div>';
	}
}
