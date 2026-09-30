<?php
/**
 * Sticky CTA mobile: Gọi điện · Zalo · Báo giá.
 *
 * @package Flatsome_Child_Saha
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$saha_hotlines = saha_theme_hotlines();
$saha_primary  = $saha_hotlines[0] ?? null;
$saha_zalo     = saha_theme_zalo_url();

if ( ! $saha_primary && '' === $saha_zalo ) {
	return;
}
?>
<nav class="saha-sticky-cta" aria-label="<?php esc_attr_e( 'Liên hệ nhanh', 'flatsome-child' ); ?>">
	<?php if ( $saha_primary && '' !== $saha_primary['href'] ) : ?>
		<a
			class="saha-sticky-cta__item saha-sticky-cta__item--call"
			href="<?php echo esc_url( $saha_primary['href'] ); ?>"
			data-saha-event="click_phone"
		>
			<span class="saha-sticky-cta__icon" aria-hidden="true">&#9742;</span>
			<span class="saha-sticky-cta__text"><?php esc_html_e( 'Gọi điện', 'flatsome-child' ); ?></span>
		</a>
	<?php endif; ?>

	<?php if ( '' !== $saha_zalo ) : ?>
		<a
			class="saha-sticky-cta__item saha-sticky-cta__item--zalo"
			href="<?php echo esc_url( $saha_zalo ); ?>"
			target="_blank"
			rel="noopener nofollow"
			data-saha-event="click_zalo"
		>
			<span class="saha-sticky-cta__icon" aria-hidden="true">&#128172;</span>
			<span class="saha-sticky-cta__text"><?php esc_html_e( 'Zalo', 'flatsome-child' ); ?></span>
		</a>
	<?php endif; ?>

	<?php
	// Có trang báo giá: dùng <a> để vẫn đi được khi JS lỗi hoặc trang không có modal.
	$saha_quote_url = function_exists( 'saha_quote_page_url' ) ? saha_quote_page_url() : '';
	$saha_product   = is_singular( 'product' ) && function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null;
	$saha_tag       = '' !== $saha_quote_url ? 'a' : 'button';
	?>
	<<?php echo esc_html( $saha_tag ); ?>
		<?php if ( 'a' === $saha_tag ) : ?>
			href="<?php echo esc_url( $saha_quote_url ); ?>"
		<?php else : ?>
			type="button"
		<?php endif; ?>
		class="saha-sticky-cta__item saha-sticky-cta__item--quote"
		data-saha-open-quote="1"
		<?php if ( $saha_product ) : ?>
			data-saha-product-id="<?php echo esc_attr( (string) $saha_product->get_id() ); ?>"
			data-saha-product-name="<?php echo esc_attr( $saha_product->get_name() ); ?>"
			data-saha-sku="<?php echo esc_attr( (string) $saha_product->get_sku() ); ?>"
		<?php endif; ?>
		aria-haspopup="dialog"
	>
		<span class="saha-sticky-cta__icon" aria-hidden="true">&#9998;</span>
		<span class="saha-sticky-cta__text"><?php echo esc_html( saha_theme_cta_label() ); ?></span>
	</<?php echo esc_html( $saha_tag ); ?>>
</nav>
