<?php
/**
 * Bài viết liên quan cuối bài blog (spec §21) — tăng liên kết nội bộ cho SEO.
 *
 * Danh sách ID do Catalog service cung cấp (có cache); template chỉ render.
 *
 * @package Flatsome_Child_Saha
 *
 * @var array<string, mixed> $args ids.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$saha_ids = array_values( array_filter( array_map( 'absint', (array) ( $args['ids'] ?? array() ) ) ) );

if ( ! $saha_ids ) {
	return;
}

wp_enqueue_style( 'saha-sections' );

// Nạp sẵn bài + meta, rồi nạp sẵn ảnh đại diện: tổng 2 query thay vì 2 query mỗi bài.
_prime_post_caches( $saha_ids, false, true );

$saha_thumb_ids = array_values( array_filter( array_map( 'get_post_thumbnail_id', $saha_ids ) ) );

if ( $saha_thumb_ids ) {
	_prime_post_caches( array_map( 'absint', $saha_thumb_ids ), false, true );
}
?>
<aside class="saha-related-posts" aria-labelledby="saha-related-posts-title">
	<h2 class="saha-related-posts__title" id="saha-related-posts-title"><?php esc_html_e( 'Bài viết liên quan', 'flatsome-child' ); ?></h2>

	<ul class="saha-related-posts__list">
		<?php foreach ( $saha_ids as $saha_id ) : ?>
			<?php
			$saha_url   = (string) get_permalink( $saha_id );
			$saha_title = get_the_title( $saha_id );
			?>
			<li class="saha-related-posts__item">
				<a class="saha-related-posts__link" href="<?php echo esc_url( $saha_url ); ?>">
					<span class="saha-related-posts__media">
						<?php
						if ( has_post_thumbnail( $saha_id ) ) {
							echo get_the_post_thumbnail(
								$saha_id,
								'medium',
								array(
									'loading' => 'lazy',
									'alt'     => esc_attr( wp_strip_all_tags( $saha_title ) ),
								)
							);
						}
						?>
					</span>
					<span class="saha-related-posts__name"><?php echo esc_html( wp_strip_all_tags( $saha_title ) ); ?></span>
					<time class="saha-related-posts__date" datetime="<?php echo esc_attr( (string) get_the_date( 'c', $saha_id ) ); ?>">
						<?php echo esc_html( (string) get_the_date( '', $saha_id ) ); ?>
					</time>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</aside>
