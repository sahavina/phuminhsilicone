<?php
/**
 * Element động: danh sách bài của trang blog / chuyên mục / tìm kiếm + phân trang.
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
 * ArchivePosts — dùng main query (không query lại); thẻ bài cùng markup với element Bài viết.
 */
final class ArchivePosts extends DynamicElement {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return self::dynamicDef(
			array(
				'type'     => 'archive-posts',
				'name'     => __( 'Danh sách bài (động)', 'saha-core' ),
				'icon'     => 'posts',
				'controls' => array(
					'showImage'   => array(
						'type'    => 'toggle',
						'label'   => __( 'Ảnh', 'saha-core' ),
						'section' => 'content',
						'default' => true,
					),
					'showExcerpt' => array(
						'type'    => 'toggle',
						'label'   => __( 'Tóm tắt', 'saha-core' ),
						'section' => 'content',
						'default' => true,
					),
					'showDate'    => array(
						'type'    => 'toggle',
						'label'   => __( 'Ngày', 'saha-core' ),
						'section' => 'content',
						'default' => true,
					),
					'titleTag'    => array(
						'type'    => 'select',
						'label'   => __( 'Thẻ tiêu đề bài', 'saha-core' ),
						'section' => 'content',
						'default' => 'h2',
						'options' => array(
							'h2' => 'H2',
							'h3' => 'H3',
						),
					),
					'columns'     => array(
						'type'       => 'number',
						'label'      => __( 'Số cột', 'saha-core' ),
						'section'    => 'style',
						'responsive' => true,
						'default'    => array(
							'desktop' => 3,
							'tablet'  => 2,
							'mobile'  => 1,
						),
						'min'        => 1,
						'max'        => 4,
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
		$image   = (bool) $this->prop( $node, 'showImage' );
		$excerpt = (bool) $this->prop( $node, 'showExcerpt' );
		$date    = (bool) $this->prop( $node, 'showDate' );
		$tag     = 'h3' === $this->prop( $node, 'titleTag' ) ? 'h3' : 'h2';

		$html = self::withArchive(
			$ctx,
			static function () use ( $image, $excerpt, $date, $tag ): string {
				global $wp_query;

				if ( ! $wp_query instanceof \WP_Query || ! $wp_query->posts ) {
					return '';
				}

				if ( $image ) {
					update_post_thumbnail_cache( $wp_query );
				}

				$items = '';

				foreach ( $wp_query->posts as $post ) {
					$url   = (string) get_permalink( $post );
					$thumb = $image && has_post_thumbnail( $post )
						? '<a class="saha-post-card__media" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . get_the_post_thumbnail( $post, 'medium_large', array( 'loading' => 'lazy' ) ) . '</a>'
						: '';

					$items .= '<li class="saha-post-card">' . $thumb . '<div class="saha-post-card__body">'
						. '<' . $tag . ' class="saha-post-card__title"><a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $post ) ) . '</a></' . $tag . '>'
						. ( $date ? '<time class="saha-post-card__date" datetime="' . esc_attr( (string) get_the_date( 'c', $post ) ) . '">' . esc_html( (string) get_the_date( '', $post ) ) . '</time>' : '' )
						. ( $excerpt ? '<p class="saha-post-card__excerpt">' . esc_html( wp_trim_words( (string) get_the_excerpt( $post ), 24 ) ) . '</p>' : '' )
						. '</div></li>';
				}

				$pagination = (string) get_the_posts_pagination(
					array(
						'mid_size'  => 1,
						'prev_text' => __( '← Trước', 'saha-core' ),
						'next_text' => __( 'Sau →', 'saha-core' ),
						'class'     => 'saha-pagination',
					)
				);

				return '<ul class="saha-posts-el__grid">' . $items . '</ul>' . $pagination;
			}
		);

		return '' === $html
			? $this->placeholder( $node, $ctx, __( 'Danh sách bài (chưa có bài nào)', 'saha-core' ) )
			: '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-dynamic', 'saha-posts-el' ) ) . '>' . $html . '</div>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( ' .saha-posts-el__grid', '--saha-cols', $this->prop( $node, 'columns' ) );
	}
}
