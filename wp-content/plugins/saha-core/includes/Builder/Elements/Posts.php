<?php
/**
 * Element: Posts.
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
 * Posts — lưới bài viết mới nhất / theo chuyên mục.
 *
 * `dynamic`: bài mới xuất bản phải hiện ngay → không cache HTML; truy vấn nhẹ
 * (không đếm tổng, không sticky, nạp ảnh đại diện một lượt).
 */
final class Posts extends Element {

	public const MAX = 12;

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'posts',
			'name'           => __( 'Bài viết', 'saha-core' ),
			'icon'           => 'admin-post',
			'category'       => 'blog',
			'allowedParents' => self::CONTENT_PARENTS,
			'dynamic'        => true,
			'controls'       => array(
				'category'    => array(
					'type'     => 'term',
					'taxonomy' => 'category',
					'label'    => __( 'Chuyên mục', 'saha-core' ),
					'section'  => 'content',
					'help'     => __( 'Để trống = mọi chuyên mục (bài mới nhất).', 'saha-core' ),
				),
				'limit'       => array(
					'type'    => 'number',
					'label'   => __( 'Số bài', 'saha-core' ),
					'section' => 'content',
					'default' => 3,
					'min'     => 1,
					'max'     => self::MAX,
				),
				'showImage'   => array(
					'type'    => 'toggle',
					'label'   => __( 'Hiện ảnh đại diện', 'saha-core' ),
					'section' => 'content',
					'default' => true,
				),
				'showExcerpt' => array(
					'type'    => 'toggle',
					'label'   => __( 'Hiện tóm tắt', 'saha-core' ),
					'section' => 'content',
					'default' => true,
				),
				'showDate'    => array(
					'type'    => 'toggle',
					'label'   => __( 'Hiện ngày đăng', 'saha-core' ),
					'section' => 'content',
					'default' => true,
				),
				'titleTag'    => array(
					'type'    => 'select',
					'label'   => __( 'Thẻ tiêu đề bài', 'saha-core' ),
					'section' => 'content',
					'default' => 'h3',
					'options' => array(
						'h2' => 'H2',
						'h3' => 'H3',
						'h4' => 'H4',
					),
				),
				'display'     => self::displayControl(),
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
				'gap'         => array(
					'type'       => 'size',
					'label'      => __( 'Khoảng cách', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'units'      => array( 'px', 'rem' ),
					'min'        => 0,
					'max'        => 100,
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
		$args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, min( self::MAX, (int) $this->prop( $node, 'limit' ) ) ),
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
		);

		$category = (string) $node->prop( 'category', '' );

		if ( '' !== $category ) {
			$args['category_name'] = $category;
		}

		// Không hiện chính bài đang xem trong danh sách của nó.
		if ( $ctx->postId > 0 && 'post' === get_post_type( $ctx->postId ) ) {
			$args['post__not_in'] = array( $ctx->postId );
		}

		$query = new \WP_Query( $args );

		if ( ! $query->posts ) {
			return $ctx->editor
				? '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-posts-el', 'saha-image--empty' ) ) . '>' . esc_html__( 'Chưa có bài viết.', 'saha-core' ) . '</div>'
				: '';
		}

		$image   = (bool) $this->prop( $node, 'showImage' );
		$excerpt = (bool) $this->prop( $node, 'showExcerpt' );
		$date    = (bool) $this->prop( $node, 'showDate' );
		$tag     = in_array( $this->prop( $node, 'titleTag' ), array( 'h2', 'h3', 'h4' ), true ) ? (string) $this->prop( $node, 'titleTag' ) : 'h3';

		if ( $image ) {
			update_post_thumbnail_cache( $query );
		}

		$items = '';

		foreach ( $query->posts as $post ) {
			$url   = (string) get_permalink( $post );
			$thumb = $image && has_post_thumbnail( $post )
				? '<a class="saha-post-card__media" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . get_the_post_thumbnail( $post, 'medium_large', array( 'loading' => 'lazy' ) ) . '</a>'
				: '';

			$items .= '<li class="saha-post-card">' . $thumb . '<div class="saha-post-card__body">'
				. '<' . $tag . ' class="saha-post-card__title"><a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $post ) ) . '</a></' . $tag . '>'
				. ( $date ? '<time class="saha-post-card__date" datetime="' . esc_attr( get_the_date( 'c', $post ) ) . '">' . esc_html( get_the_date( '', $post ) ) . '</time>' : '' )
				. ( $excerpt ? '<p class="saha-post-card__excerpt">' . esc_html( wp_trim_words( get_the_excerpt( $post ), 24 ) ) . '</p>' : '' )
				. '</div></li>';
		}

		if ( 'carousel' === $this->prop( $node, 'display' ) ) {
			return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-posts-el', 'saha-slider', 'saha-posts-el--carousel' ), array( 'data-saha-slider' => '1' ) ) . '>'
				. '<ul class="saha-posts-el__grid saha-slider__track" tabindex="0" aria-label="' . esc_attr__( 'Danh sách bài viết (cuộn ngang)', 'saha-core' ) . '">' . $items . '</ul>'
				. self::carouselArrows( __( 'bài viết', 'saha-core' ) ) . '</div>';
		}

		return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-posts-el' ) ) . '><ul class="saha-posts-el__grid">' . $items . '</ul></div>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( ' .saha-posts-el__grid', '--saha-cols', $this->prop( $node, 'columns' ) );
		$css->set( ' .saha-posts-el__grid', 'gap', $node->prop( 'gap' ) );
		$css->set( '', '--saha-slider-per-view', $this->prop( $node, 'columns' ) );
		$css->set( '', '--saha-slider-gap', $node->prop( 'gap' ) );
	}
}
