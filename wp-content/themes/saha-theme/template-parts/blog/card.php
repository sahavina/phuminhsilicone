<?php
/**
 * Thẻ bài viết trong danh sách.
 *
 * @package Saha\Theme
 */

defined( 'ABSPATH' ) || exit;

$saha_cats = get_the_category();
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'saha-card' ); ?>>
	<?php if ( has_post_thumbnail() ) : ?>
		<a class="saha-card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
			<?php the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy' ) ); ?>
		</a>
	<?php endif; ?>

	<div class="saha-card__body">
		<?php if ( $saha_cats ) : ?>
			<a class="saha-card__cat" href="<?php echo esc_url( get_category_link( $saha_cats[0] ) ); ?>"><?php echo esc_html( $saha_cats[0]->name ); ?></a>
		<?php endif; ?>

		<?php the_title( '<h2 class="saha-card__title"><a href="' . esc_url( get_permalink() ) . '">', '</a></h2>' ); ?>

		<div class="saha-card__excerpt"><?php the_excerpt(); ?></div>

		<p class="saha-card__meta">
			<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
			<?php if ( '' !== get_the_author() ) : ?>
				<span aria-hidden="true">·</span>
				<?php the_author(); ?>
			<?php endif; ?>
		</p>
	</div>
</article>
