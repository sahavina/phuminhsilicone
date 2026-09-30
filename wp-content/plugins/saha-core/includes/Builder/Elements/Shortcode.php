<?php
/**
 * Element: Shortcode.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode — chạy shortcode của plugin khác (form liên hệ, bảng giá…).
 *
 * `dynamic`: kết quả shortcode có thể đổi theo request (nonce, người dùng) → không cache.
 */
final class Shortcode extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'shortcode',
			'name'           => __( 'Shortcode', 'saha-core' ),
			'icon'           => 'shortcode',
			'category'       => 'content',
			'allowedParents' => self::CONTENT_PARENTS,
			'dynamic'        => true,
			'controls'       => array(
				'shortcode' => array(
					'type'      => 'textarea',
					'label'     => __( 'Shortcode', 'saha-core' ),
					'section'   => 'content',
					'maxLength' => 2000,
					'help'      => __( 'Ví dụ: [contact-form-7 id="12"]', 'saha-core' ),
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
		$code = trim( (string) $node->prop( 'shortcode', '' ) );

		if ( '' === $code ) {
			return $ctx->editor ? '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-shortcode', 'saha-image--empty' ) ) . '>' . esc_html__( 'Nhập shortcode', 'saha-core' ) . '</div>' : '';
		}

		// Shortcode được lưu qua sanitize_textarea_field (không HTML); kết quả do plugin sở hữu shortcode escape.
		return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-shortcode' ) ) . '>' . do_shortcode( $code ) . '</div>';
	}
}
