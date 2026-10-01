<?php
/**
 * Element động: nội dung bài / trang / mô tả dài sản phẩm.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * PostContent — chạy filter `the_content` (shortcode, embed; trang dựng bằng builder
 * thì ra layout builder của trang đó).
 */
final class PostContent extends DynamicElement {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return self::dynamicDef(
			array(
				'type'     => 'post-content',
				'name'     => __( 'Nội dung (động)', 'saha-core' ),
				'icon'     => 'text',
				'controls' => array(),
			)
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
		$html = self::withSubject(
			$ctx,
			static function ( \WP_Post $post ): string {
				if ( post_password_required( $post ) ) {
					return (string) get_the_password_form( $post );
				}

				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- hook lõi.
				return (string) apply_filters( 'the_content', str_replace( ']]>', ']]&gt;', (string) $post->post_content ) );
			}
		);

		return null === $html
			? $this->placeholder( $node, $ctx, __( 'Nội dung bài viết / trang', 'saha-core' ) )
			: $this->wrap( $node, $ctx, 'saha-prose saha-post-content', $html );
	}
}
