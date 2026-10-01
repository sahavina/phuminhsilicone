<?php
/**
 * Element động: ngày, tác giả, chuyên mục của bài.
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
 * PostMeta.
 */
final class PostMeta extends DynamicElement {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return self::dynamicDef(
			array(
				'type'     => 'post-meta',
				'name'     => __( 'Ngày, tác giả, chuyên mục (động)', 'saha-core' ),
				'icon'     => 'info',
				'controls' => array(
					'showDate'       => array(
						'type'    => 'toggle',
						'label'   => __( 'Ngày đăng', 'saha-core' ),
						'section' => 'content',
						'default' => true,
					),
					'showAuthor'     => array(
						'type'    => 'toggle',
						'label'   => __( 'Tác giả', 'saha-core' ),
						'section' => 'content',
						'default' => true,
					),
					'showCategories' => array(
						'type'    => 'toggle',
						'label'   => __( 'Chuyên mục', 'saha-core' ),
						'section' => 'content',
						'default' => true,
					),
					'color'          => array(
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
		$html = self::withSubject(
			$ctx,
			function ( \WP_Post $post ) use ( $node ): string {
				$parts = array();

				if ( $this->prop( $node, 'showDate' ) ) {
					$parts[] = '<time datetime="' . esc_attr( (string) get_the_date( 'c', $post ) ) . '">' . esc_html( (string) get_the_date( '', $post ) ) . '</time>';
				}

				if ( $this->prop( $node, 'showAuthor' ) ) {
					$parts[] = '<span class="saha-post-meta__author">' . esc_html( (string) get_the_author_meta( 'display_name', (int) $post->post_author ) ) . '</span>';
				}

				if ( $this->prop( $node, 'showCategories' ) && 'post' === $post->post_type ) {
					$list = get_the_category_list( ', ', '', $post->ID );

					if ( is_string( $list ) && '' !== $list ) {
						$parts[] = '<span class="saha-post-meta__cats">' . wp_kses_post( $list ) . '</span>';
					}
				}

				return implode( '<span class="saha-post-meta__sep" aria-hidden="true"> · </span>', $parts );
			}
		);

		return null === $html
			? $this->placeholder( $node, $ctx, __( 'Ngày · tác giả · chuyên mục', 'saha-core' ) )
			: $this->wrap( $node, $ctx, 'saha-post-meta', $html );
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
