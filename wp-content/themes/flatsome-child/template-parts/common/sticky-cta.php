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

	<button
		type="button"
		class="saha-sticky-cta__item saha-sticky-cta__item--quote"
		data-saha-open-quote="1"
		aria-haspopup="dialog"
	>
		<span class="saha-sticky-cta__icon" aria-hidden="true">&#9998;</span>
		<span class="saha-sticky-cta__text"><?php echo esc_html( saha_theme_cta_label() ); ?></span>
	</button>
</nav>
