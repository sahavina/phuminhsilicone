<?php
/**
 * Brand card.
 *
 * @package Flatsome_Child_Saha
 *
 * @var array<string, mixed> $args brand (mảng dữ liệu từ saha_get_brand), show_count.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$saha_brand = isset( $args['brand'] ) && is_array( $args['brand'] ) ? $args['brand'] : array();

if ( empty( $saha_brand['name'] ) ) {
	return;
}

$saha_show_count = ! empty( $args['show_count'] );
$saha_logo_id    = absint( $saha_brand['logo_id'] ?? 0 );
$saha_url        = (string) ( $saha_brand['url'] ?? '' );
?>
<li class="saha-brand-card">
	<?php if ( '' !== $saha_url ) : ?>
		<a class="saha-brand-card__link" href="<?php echo esc_url( $saha_url ); ?>">
	<?php endif; ?>

	<span class="saha-brand-card__logo">
		<?php
		if ( $saha_logo_id > 0 ) {
			echo wp_get_attachment_image(
				$saha_logo_id,
				'saha-brand-logo',
				false,
				array(
					'alt'     => esc_attr( (string) $saha_brand['name'] ),
					'loading' => 'lazy',
				)
			);
		} else {
			echo '<span class="saha-brand-card__placeholder" aria-hidden="true">' . esc_html( mb_substr( (string) $saha_brand['name'], 0, 1 ) ) . '</span>';
		}
		?>
	</span>

	<span class="saha-brand-card__name"><?php echo esc_html( (string) $saha_brand['name'] ); ?></span>

	<?php if ( $saha_show_count && ! empty( $saha_brand['count'] ) ) : ?>
		<span class="saha-brand-card__count">
			<?php
			printf(
				/* translators: %d: số sản phẩm */
				esc_html( _n( '%d sản phẩm', '%d sản phẩm', (int) $saha_brand['count'], 'flatsome-child' ) ),
				(int) $saha_brand['count']
			);
			?>
		</span>
	<?php endif; ?>

	<?php if ( '' !== $saha_url ) : ?>
		</a>
	<?php endif; ?>
</li>
