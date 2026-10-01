<?php
/**
 * Empty state dùng chung: không có sản phẩm, không có kết quả, 404 (spec §82).
 *
 * @package Saha\Theme
 *
 * @var array<string, mixed> $args Tham số truyền từ saha_theme_part().
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$saha_args = wp_parse_args(
	isset( $args ) && is_array( $args ) ? $args : array(),
	array(
		'title'       => __( 'Chưa có nội dung', 'saha' ),
		'description' => '',
		'show_search' => false,
		'show_cats'   => false,
		'cat_limit'   => 8,
	)
);
?>
<div class="saha-empty-state">
	<h2 class="saha-empty-state__title"><?php echo esc_html( (string) $saha_args['title'] ); ?></h2>

	<?php if ( '' !== (string) $saha_args['description'] ) : ?>
		<p class="saha-empty-state__desc"><?php echo esc_html( (string) $saha_args['description'] ); ?></p>
	<?php endif; ?>

	<?php if ( ! empty( $saha_args['show_search'] ) ) : ?>
		<div class="saha-empty-state__search">
			<?php get_search_form(); ?>
		</div>
	<?php endif; ?>

	<?php
	if ( ! empty( $saha_args['show_cats'] ) && taxonomy_exists( 'product_cat' ) ) :
		$saha_cats = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'parent'     => 0,
				'number'     => (int) $saha_args['cat_limit'],
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);

		if ( $saha_cats && ! is_wp_error( $saha_cats ) ) :
			?>
			<ul class="saha-empty-state__cats">
				<?php foreach ( $saha_cats as $saha_cat ) : ?>
					<li>
						<a href="<?php echo esc_url( (string) get_term_link( $saha_cat ) ); ?>">
							<?php echo esc_html( $saha_cat->name ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php
		endif;
	endif;
	?>

	<p class="saha-empty-state__home">
		<a class="button" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php esc_html_e( 'Về trang chủ', 'saha' ); ?>
		</a>
	</p>
</div>
