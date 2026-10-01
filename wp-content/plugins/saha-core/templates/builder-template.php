<?php
/**
 * Khung trang khi nội dung do Template Builder dựng (Templates\Loader).
 *
 * Header/footer của theme (hoặc header/footer builder) giữ nguyên; giữa là layout
 * của template khớp điều kiện. Trang đơn: chạy vòng lặp chính để global $post,
 * $product đúng như template PHP của theme.
 *
 * @package Saha\Core
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( is_singular() ) {
	while ( have_posts() ) {
		the_post();
		Saha\Core\Templates\Loader::render();
	}
} else {
	Saha\Core\Templates\Loader::render();
}

get_footer();
