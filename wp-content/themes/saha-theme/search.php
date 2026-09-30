<?php
/**
 * Kết quả tìm kiếm (bài viết/trang). Tìm sản phẩm do WooCommerce + saha-core Search xử lý.
 *
 * @package Saha\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="saha-container saha-section">
	<header class="saha-archive-header">
		<h1 class="saha-page-title">
			<?php
			/* translators: %s: từ khoá */
			printf( esc_html__( 'Kết quả cho: %s', 'saha' ), '<span>' . esc_html( get_search_query() ) . '</span>' );
			?>
		</h1>
		<?php get_search_form(); ?>
	</header>

	<?php saha_theme_posts_loop(); ?>
</div>
<?php
get_footer();
