<?php
/**
 * Phân trang danh sách bài.
 *
 * @package Saha\Theme
 */

defined( 'ABSPATH' ) || exit;

the_posts_pagination(
	array(
		'class'              => 'saha-pagination',
		'mid_size'           => 1,
		'prev_text'          => __( 'Trước', 'saha' ),
		'next_text'          => __( 'Sau', 'saha' ),
		'screen_reader_text' => __( 'Phân trang', 'saha' ),
	)
);
