<?php
/**
 * Khối meta sản phẩm: SKU, thương hiệu, tình trạng, đơn vị.
 *
 * Field trống thì không hiển thị (spec §5).
 *
 * @package Flatsome_Child_Saha
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

$saha_rows = array();

$saha_sku = (string) $saha_product->get_sku();

if ( '' !== $saha_sku ) {
	$saha_rows[] = array(
		'label' => __( 'Mã sản phẩm', 'flatsome-child' ),
		'html'  => '<span class="saha-product-meta__sku">' . esc_html( $saha_sku ) . '</span>',
	);
}

if ( saha_theme_has_core() ) {
	$saha_brand = saha_get_product_brand( $saha_product_id );

	if ( ! empty( $saha_brand['name'] ) ) {
		$saha_rows[] = array(
			'label' => __( 'Thương hiệu', 'flatsome-child' ),
			'html'  => ! empty( $saha_brand['url'] )
				? '<a href="' . esc_url( (string) $saha_brand['url'] ) . '">' . esc_html( (string) $saha_brand['name'] ) . '</a>'
				: esc_html( (string) $saha_brand['name'] ),
		);
	}

	$saha_availability = saha_get_availability_label( $saha_product_id );

	if ( '' !== $saha_availability ) {
		$saha_rows[] = array(
			'label' => __( 'Tình trạng', 'flatsome-child' ),
			'html'  => '<span class="saha-product-meta__availability">' . esc_html( $saha_availability ) . '</span>',
		);
	}

	$saha_unit = (string) saha_get_product_meta( $saha_product_id, 'unit' );

	if ( '' !== $saha_unit ) {
		$saha_rows[] = array(
			'label' => __( 'Đơn vị tính', 'flatsome-child' ),
			'html'  => esc_html( $saha_unit ),
		);
	}

	$saha_line = (string) saha_get_product_meta( $saha_product_id, 'product_line' );

	if ( '' !== $saha_line ) {
		$saha_rows[] = array(
			'label' => __( 'Dòng sản phẩm', 'flatsome-child' ),
			'html'  => esc_html( $saha_line ),
		);
	}
}

if ( ! $saha_rows ) {
	return;
}
?>
<ul class="saha-product-meta">
	<?php foreach ( $saha_rows as $saha_row ) : ?>
		<li class="saha-product-meta__row">
			<span class="saha-product-meta__label"><?php echo esc_html( (string) $saha_row['label'] ); ?>:</span>
			<span class="saha-product-meta__value">
				<?php echo wp_kses_post( (string) $saha_row['html'] ); ?>
			</span>
		</li>
	<?php endforeach; ?>
</ul>
