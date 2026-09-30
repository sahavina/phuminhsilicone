<?php
/**
 * Bài viết đơn (spec SCC §49).
 *
 * @package Saha\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="saha-container saha-container--content saha-section">
	<?php saha_theme_breadcrumb(); ?>

	<?php
	while ( have_posts() ) {
		the_post();
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'saha-article' ); ?>>
			<header class="saha-article__header">
				<?php the_title( '<h1 class="saha-page-title">', '</h1>' ); ?>
				<p class="saha-article__meta">
					<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
					<?php if ( '' !== get_the_author() ) : ?>
						<span aria-hidden="true">·</span>
						<?php the_author(); ?>
					<?php endif; ?>
				</p>
			</header>

			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="saha-article__thumb">
					<?php the_post_thumbnail( 'large', array( 'loading' => 'eager', 'fetchpriority' => 'high' ) ); ?>
				</figure>
			<?php endif; ?>

			<div class="saha-prose">
				<?php
				the_content();
				wp_link_pages(
					array(
						'before' => '<nav class="saha-page-links" aria-label="' . esc_attr__( 'Trang', 'saha' ) . '">',
						'after'  => '</nav>',
					)
				);
				?>
			</div>

			<footer class="saha-article__footer">
				<?php the_tags( '<p class="saha-article__tags">', ' ', '</p>' ); ?>
			</footer>
		</article>
		<?php
		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
	}
	?>
</div>
<?php
get_footer();
