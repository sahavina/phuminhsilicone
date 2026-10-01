<?php
/**
 * Sản phẩm khác cùng thương hiệu.
 *
 * Dùng WP_Query có giới hạn, `fields => ids` + `update_post_term_cache` để
 * tránh N+1 khi render card (spec §28).
 *
 * @package Saha\Theme
 *
 * @var array<string, mixed> $args product_id.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$saha_product_id = absint( $args['product_id'] ?? 0 );

if ( $saha_product_id <= 0 || ! saha_theme_has_core() || ! function_exists( 'wc_get_product' ) ) {
	return;
}

$saha_brand = saha_get_product_brand( $saha_product_id );

if ( empty( $saha_brand['slug'] ) ) {
	return;
}

/**
 * Số sản phẩm cùng thương hiệu hiển thị.
 *
 * @param int $limit Số lượng.
 */
$saha_limit = (int) apply_filters( 'saha_theme_brand_products_limit', 8 );

$saha_query = new WP_Query(
	array(
		'post_type'              => 'product',
		'post_status'            => 'publish',
		'posts_per_page'         => $saha_limit,
		'post__not_in'           => array( $saha_product_id ),
		'ignore_sticky_posts'    => true,
		'no_found_rows'          => true,
		'orderby'                => 'date',
		'order'                  => 'DESC',
		'update_post_meta_cache' => true,
		'update_post_term_cache' => true,
		'tax_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- giới hạn nhỏ, có cache object.
			array(
				'taxonomy' => 'product_brand',
				'field'    => 'slug',
				'terms'    => (string) $saha_brand['slug'],
			),
		),
	)
);

if ( ! $saha_query->have_posts() ) {
	wp_reset_postdata();
	return;
}
?>
<section class="saha-product-section saha-brand-products">
	<h2 class="saha-product-section__title">
		<?php
		printf(
			/* translators: %s: brand name */
			esc_html__( 'Sản phẩm khác của %s', 'saha' ),
			esc_html( (string) $saha_brand['name'] )
		);
		?>
	</h2>

	<ul class="products saha-product-grid">
		<?php
		while ( $saha_query->have_posts() ) :
			$saha_query->the_post();
			wc_get_template_part( 'content', 'product' );
		endwhile;
		?>
	</ul>

	<?php if ( ! empty( $saha_brand['url'] ) ) : ?>
		<p class="saha-brand-products__more">
			<a class="button" href="<?php echo esc_url( (string) $saha_brand['url'] ); ?>">
				<?php
				printf(
					/* translators: %s: brand name */
					esc_html__( 'Xem tất cả sản phẩm %s', 'saha' ),
					esc_html( (string) $saha_brand['name'] )
				);
				?>
			</a>
		</p>
	<?php endif; ?>
</section>
<?php
wp_reset_postdata();
