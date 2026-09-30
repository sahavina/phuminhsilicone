<?php
/**
 * Trang (page). Trang dựng bằng SAHA Builder sẽ được render thay nội dung ở mốc 1.2.
 *
 * @package Saha\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) {
	the_post();
	?>
	<div class="saha-container saha-section">
		<?php saha_theme_breadcrumb(); ?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'saha-page' ); ?>>
			<?php the_title( '<h1 class="saha-page-title">', '</h1>' ); ?>
			<div class="saha-prose">
				<?php the_content(); ?>
			</div>
		</article>
	</div>
	<?php
}

get_footer();
