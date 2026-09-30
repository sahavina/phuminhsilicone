<?php
/**
 * Brand grid.
 *
 * @package Flatsome_Child_Saha
 *
 * @var array<string, mixed> $args brands, show_count, empty_message.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$saha_brands = isset( $args['brands'] ) && is_array( $args['brands'] ) ? $args['brands'] : array();

if ( ! $saha_brands ) {
	saha_theme_part(
		'common/empty-state',
		array(
			'title'       => (string) ( $args['empty_message'] ?? __( 'Chưa có thương hiệu nào', 'flatsome-child' ) ),
			'description' => __( 'Nội dung đang được cập nhật.', 'flatsome-child' ),
		)
	);

	return;
}

$saha_show_count = ! empty( $args['show_count'] );
?>
<ul class="saha-brand-grid">
	<?php
	foreach ( $saha_brands as $saha_brand ) {
		saha_theme_part(
			'brand/card',
			array(
				'brand'      => $saha_brand,
				'show_count' => $saha_show_count,
			)
		);
	}
	?>
</ul>
