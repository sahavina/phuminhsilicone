<?php
/**
 * Element động: tóm tắt bài / mô tả ngắn sản phẩm.
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
 * PostExcerpt — sản phẩm: mô tả ngắn (giữ định dạng); bài viết: tóm tắt (cắt theo số từ).
 */
final class PostExcerpt extends DynamicElement {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return self::dynamicDef(
			array(
				'type'     => 'post-excerpt',
				'name'     => __( 'Tóm tắt / mô tả ngắn (động)', 'saha-core' ),
				'icon'     => 'text',
				'controls' => array(
					'words' => array(
						'type'    => 'number',
						'label'   => __( 'Số từ tối đa (bài viết)', 'saha-core' ),
						'section' => 'content',
						'default' => 40,
						'min'     => 5,
						'max'     => 200,
					),
					'color' => array(
						'type'    => 'color',
						'label'   => __( 'Màu chữ', 'saha-core' ),
						'section' => 'style',
					),
				),
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
		$words = (int) $this->prop( $node, 'words' );
		$html  = self::withSubject(
			$ctx,
			static function ( \WP_Post $post ) use ( $words ): string {
				if ( 'product' === $post->post_type ) {
					// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- hook của WooCommerce.
					return (string) apply_filters( 'woocommerce_short_description', $post->post_excerpt );
				}

				$text = (string) get_the_excerpt( $post );

				return '' === $text ? '' : '<p>' . esc_html( wp_trim_words( $text, $words ) ) . '</p>';
			}
		);

		return null === $html
			? $this->placeholder( $node, $ctx, __( 'Tóm tắt / mô tả ngắn', 'saha-core' ) )
			: $this->wrap( $node, $ctx, 'saha-text saha-post-excerpt', $html );
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', 'color', $node->prop( 'color' ) );
	}
}
