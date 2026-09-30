<?php
/**
 * Hàm dựng khung trang.
 *
 * @package Saha\Theme
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'saha_theme_render_header' ) ) {
	/**
	 * Header: template dựng bằng SAHA Builder nếu có (mốc 1.5), ngược lại header PHP mặc định.
	 */
	function saha_theme_render_header(): void {
		/**
		 * Cho phép builder/plugin render header thay thế. Trả về true nếu đã render.
		 *
		 * @param bool $rendered Đã render chưa.
		 */
		if ( apply_filters( 'saha_render_header', false ) ) {
			return;
		}

		get_template_part( 'template-parts/header/default' );
	}
}

if ( ! function_exists( 'saha_theme_render_footer' ) ) {
	/**
	 * Footer: template builder nếu có, ngược lại footer PHP mặc định.
	 */
	function saha_theme_render_footer(): void {
		/**
		 * Cho phép builder/plugin render footer thay thế. Trả về true nếu đã render.
		 *
		 * @param bool $rendered Đã render chưa.
		 */
		if ( apply_filters( 'saha_render_footer', false ) ) {
			return;
		}

		get_template_part( 'template-parts/footer/default' );
	}
}

if ( ! function_exists( 'saha_theme_posts_loop' ) ) {
	/**
	 * Danh sách bài viết cho archive / blog / search (spec SCC §49–50).
	 */
	function saha_theme_posts_loop(): void {
		if ( ! have_posts() ) {
			get_template_part( 'template-parts/components/empty-state' );
			return;
		}

		$layout  = 'list' === saha_theme_option( 'blog.layout', 'grid' ) ? 'list' : 'grid';
		$columns = max( 1, min( 4, (int) saha_theme_option( 'blog.columns', 3 ) ) );

		printf( '<div class="saha-posts saha-posts--%1$s saha-posts--cols-%2$d">', esc_attr( $layout ), (int) $columns );

		while ( have_posts() ) {
			the_post();
			get_template_part( 'template-parts/blog/card' );
		}

		echo '</div>';

		get_template_part( 'template-parts/components/pagination' );
	}
}
