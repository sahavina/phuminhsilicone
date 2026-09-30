<?php
/**
 * Archive: chuyên mục, thẻ, tác giả, ngày (spec SCC §49).
 *
 * @package Saha\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="saha-container saha-section">
	<?php saha_theme_breadcrumb(); ?>

	<header class="saha-archive-header">
		<?php the_archive_title( '<h1 class="saha-page-title">', '</h1>' ); ?>
		<?php the_archive_description( '<div class="saha-archive-description">', '</div>' ); ?>
	</header>

	<?php saha_theme_posts_loop(); ?>
</div>
<?php
get_footer();
