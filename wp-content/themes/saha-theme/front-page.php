<?php
/**
 * Trang chủ.
 *
 * Trang chủ tĩnh: hiển thị nội dung trang (sau này là layout builder, mốc 1.2),
 * không lặp tiêu đề. Trang chủ là danh sách bài: dùng index.php.
 *
 * @package Saha\Theme
 */

defined( 'ABSPATH' ) || exit;

if ( 'page' !== get_option( 'show_on_front' ) ) {
	require SAHA_THEME_DIR . '/index.php';
	return;
}

get_header();

while ( have_posts() ) {
	the_post();
	?>
	<div class="saha-front">
		<h1 class="screen-reader-text"><?php bloginfo( 'name' ); ?></h1>
		<div class="saha-container saha-prose">
			<?php the_content(); ?>
		</div>
	</div>
	<?php
}

get_footer();
