<?php
/**
 * Template dự phòng cuối cùng: blog, archive không có template riêng.
 *
 * @package Saha\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="saha-container saha-section">
	<?php saha_theme_breadcrumb(); ?>

	<?php if ( is_home() && ! is_front_page() ) : ?>
		<h1 class="saha-page-title"><?php single_post_title(); ?></h1>
	<?php elseif ( is_front_page() ) : ?>
		<?php // Trang chủ = danh sách bài: vẫn cần đúng 1 H1 cho SEO. ?>
		<h1 class="screen-reader-text"><?php bloginfo( 'name' ); ?></h1>
	<?php else : ?>
		<h1 class="saha-page-title"><?php esc_html_e( 'Bài viết', 'saha' ); ?></h1>
	<?php endif; ?>

	<?php saha_theme_posts_loop(); ?>
</div>
<?php
get_footer();
