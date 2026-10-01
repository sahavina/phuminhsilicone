<?php
/**
 * CTA trên trang sản phẩm: hotline + yêu cầu báo giá (spec §17).
 *
 * @package Saha\Theme
 *
 * @var array<string, mixed> $args product_id.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$saha_product_id = absint( $args['product_id'] ?? 0 );

if ( $saha_product_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
	return;
}

$saha_product = wc_get_product( $saha_product_id );

if ( ! $saha_product ) {
	return;
}

$saha_cta = saha_theme_has_core()
	? saha_get_product_cta( $saha_product_id )
	: array(
		'mode'  => 'quote',
		'label' => saha_theme_cta_label(),
	);

if ( 'hidden' === $saha_cta['mode'] ) {
	return;
}

$saha_hotlines = saha_theme_hotlines();
$saha_zalo     = saha_theme_zalo_url();
?>
<div class="saha-product-cta">
	<?php if ( 'quote' === $saha_cta['mode'] ) : ?>
		<button
			type="button"
			class="button primary saha-product-cta__quote"
			data-saha-open-quote="1"
			data-saha-product-id="<?php echo esc_attr( (string) $saha_product_id ); ?>"
			data-saha-product-name="<?php echo esc_attr( $saha_product->get_name() ); ?>"
			data-saha-sku="<?php echo esc_attr( (string) $saha_product->get_sku() ); ?>"
			aria-haspopup="dialog"
		>
			<?php echo esc_html( (string) $saha_cta['label'] ); ?>
		</button>
	<?php endif; ?>

	<?php if ( $saha_hotlines ) : ?>
		<div class="saha-product-cta__hotlines">
			<span class="saha-product-cta__hotline-title">
				<?php esc_html_e( 'Tư vấn kỹ thuật & báo giá nhanh:', 'saha' ); ?>
			</span>

			<?php foreach ( $saha_hotlines as $saha_hotline ) : ?>
				<?php if ( '' === $saha_hotline['href'] ) { continue; } ?>
				<a
					class="saha-hotline"
					href="<?php echo esc_url( $saha_hotline['href'] ); ?>"
					data-saha-event="click_phone"
					data-saha-region="<?php echo esc_attr( $saha_hotline['region'] ); ?>"
				>
					<span class="saha-hotline__label"><?php echo esc_html( $saha_hotline['label'] ); ?></span>
					<span class="saha-hotline__number"><?php echo esc_html( $saha_hotline['number'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( '' !== $saha_zalo ) : ?>
		<a
			class="saha-product-cta__zalo"
			href="<?php echo esc_url( $saha_zalo ); ?>"
			target="_blank"
			rel="noopener nofollow"
			data-saha-event="click_zalo"
		>
			<?php esc_html_e( 'Chat Zalo', 'saha' ); ?>
		</a>
	<?php endif; ?>
</div>
