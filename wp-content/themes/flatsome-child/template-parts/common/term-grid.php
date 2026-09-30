<?php
/**
 * Grid danh mục / ứng dụng (Category Card — spec §52).
 *
 * @package Flatsome_Child_Saha
 *
 * @var array<string, mixed> $args terms, columns, title, subtitle, view_all_url, show_count, style (image|chip).
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$saha_terms = isset( $args['terms'] ) && is_array( $args['terms'] ) ? $args['terms'] : array();

if ( ! $saha_terms ) {
	return;
}

wp_enqueue_style( 'saha-sections' );

$saha_columns    = max( 2, min( 8, (int) ( $args['columns'] ?? 4 ) ) );
$saha_show_count = ! empty( $args['show_count'] );
$saha_style      = 'chip' === ( $args['style'] ?? '' ) ? 'chip' : 'image';
?>
<section class="saha-section saha-term-block">
	<?php
	saha_theme_part(
		'common/section-heading',
		array(
			'title'        => $args['title'] ?? '',
			'subtitle'     => $args['subtitle'] ?? '',
			'view_all_url' => $args['view_all_url'] ?? '',
		)
	);
	?>

	<ul class="saha-term-grid saha-term-grid--<?php echo esc_attr( $saha_style ); ?> saha-cols-<?php echo esc_attr( (string) $saha_columns ); ?>">
		<?php foreach ( $saha_terms as $saha_term ) : ?>
			<?php
			if ( empty( $saha_term['url'] ) ) {
				continue;
			}

			$saha_thumb = absint( $saha_term['thumbnail_id'] ?? 0 );
			?>
			<li class="saha-term-card">
				<a class="saha-term-card__link" href="<?php echo esc_url( (string) $saha_term['url'] ); ?>">
					<?php if ( 'image' === $saha_style ) : ?>
						<span class="saha-term-card__media">
							<?php
							if ( $saha_thumb > 0 ) {
								echo wp_get_attachment_image(
									$saha_thumb,
									'woocommerce_thumbnail',
									false,
									array(
										'alt'     => esc_attr( (string) $saha_term['name'] ),
										'loading' => 'lazy',
										'sizes'   => '(max-width: 575px) 45vw, (max-width: 991px) 30vw, 220px',
									)
								);
							} else {
								echo '<span class="saha-term-card__placeholder" aria-hidden="true">' . esc_html( mb_substr( (string) $saha_term['name'], 0, 1 ) ) . '</span>';
							}
							?>
						</span>
					<?php endif; ?>

					<span class="saha-term-card__name"><?php echo esc_html( (string) $saha_term['name'] ); ?></span>

					<?php if ( $saha_show_count && ! empty( $saha_term['count'] ) ) : ?>
						<span class="saha-term-card__count">
							<?php
							printf(
								/* translators: %d: số sản phẩm */
								esc_html( _n( '%d sản phẩm', '%d sản phẩm', (int) $saha_term['count'], 'flatsome-child' ) ),
								(int) $saha_term['count']
							);
							?>
						</span>
					<?php endif; ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
