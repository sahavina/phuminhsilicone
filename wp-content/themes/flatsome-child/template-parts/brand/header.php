<?php
/**
 * Header trang thương hiệu: banner, logo, H1, mô tả ngắn (spec §7, §23).
 *
 * H1 = "Tên thương hiệu + nhóm sản phẩm".
 *
 * @package Flatsome_Child_Saha
 *
 * @var array<string, mixed> $args brand.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$saha_brand = isset( $args['brand'] ) && is_array( $args['brand'] ) ? $args['brand'] : array();

if ( empty( $saha_brand['name'] ) ) {
	return;
}

$saha_banner_id = absint( $saha_brand['banner_id'] ?? 0 );
$saha_logo_id   = absint( $saha_brand['logo_id'] ?? 0 );

/**
 * Hậu tố cho H1 trang thương hiệu.
 *
 * @param string               $suffix Hậu tố.
 * @param array<string, mixed> $brand  Dữ liệu thương hiệu.
 */
$saha_suffix = (string) apply_filters(
	'saha_theme_brand_heading_suffix',
	__( 'chính hãng', 'flatsome-child' ),
	$saha_brand
);
?>
<header class="saha-brand-header">
	<?php if ( $saha_banner_id > 0 ) : ?>
		<div class="saha-brand-header__banner">
			<?php
			echo wp_get_attachment_image(
				$saha_banner_id,
				'full',
				false,
				array(
					'alt'           => esc_attr( (string) $saha_brand['name'] ),
					'fetchpriority' => 'high',
				)
			);
			?>
		</div>
	<?php endif; ?>

	<div class="saha-brand-header__main">
		<?php if ( $saha_logo_id > 0 ) : ?>
			<div class="saha-brand-header__logo">
				<?php
				echo wp_get_attachment_image(
					$saha_logo_id,
					'saha-brand-logo',
					false,
					array( 'alt' => esc_attr( (string) $saha_brand['name'] ) )
				);
				?>
			</div>
		<?php endif; ?>

		<div class="saha-brand-header__text">
			<h1 class="saha-brand-header__title">
				<?php
				echo esc_html(
					trim( (string) $saha_brand['name'] . ' ' . $saha_suffix )
				);
				?>
			</h1>

			<?php if ( ! empty( $saha_brand['country'] ) ) : ?>
				<p class="saha-brand-header__country">
					<?php
					printf(
						/* translators: %s: quốc gia */
						esc_html__( 'Xuất xứ: %s', 'flatsome-child' ),
						esc_html( (string) $saha_brand['country'] )
					);
					?>
				</p>
			<?php endif; ?>

			<?php if ( ! empty( $saha_brand['short_description'] ) ) : ?>
				<div class="saha-brand-header__excerpt">
					<?php echo wp_kses_post( wpautop( (string) $saha_brand['short_description'] ) ); ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $saha_brand['website'] ) ) : ?>
				<p class="saha-brand-header__website">
					<a href="<?php echo esc_url( (string) $saha_brand['website'] ); ?>" target="_blank" rel="noopener nofollow">
						<?php esc_html_e( 'Website thương hiệu', 'flatsome-child' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
	</div>
</header>
