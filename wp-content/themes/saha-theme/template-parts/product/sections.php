<?php
/**
 * Khối nội dung kỹ thuật của sản phẩm: ứng dụng, thông số, hướng dẫn, lưu ý, tài liệu.
 *
 * Heading dùng h2 để giữ cấu trúc semantic (h1 là tên sản phẩm — spec §24).
 * Field trống thì không render.
 *
 * @package Saha\Theme
 *
 * @var array<string, mixed> $args product_id.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$saha_product_id = absint( $args['product_id'] ?? 0 );

if ( $saha_product_id <= 0 || ! saha_theme_has_core() ) {
	return;
}

$saha_specs = saha_get_product_specs( $saha_product_id );
$saha_docs  = saha_get_product_documents( $saha_product_id );

$saha_html_sections = array(
	'application_text' => __( 'Ứng dụng', 'saha' ),
	'usage'            => __( 'Hướng dẫn sử dụng', 'saha' ),
	'warning'          => __( 'Lưu ý', 'saha' ),
);

$saha_has_content = (bool) $saha_specs || (bool) $saha_docs;

foreach ( $saha_html_sections as $saha_key => $saha_label ) {
	if ( '' !== trim( (string) saha_get_product_meta( $saha_product_id, $saha_key ) ) ) {
		$saha_has_content = true;
		break;
	}
}

if ( ! $saha_has_content ) {
	return;
}
?>
<div class="saha-product-sections">

	<?php foreach ( $saha_html_sections as $saha_key => $saha_label ) : ?>
		<?php
		$saha_content = (string) saha_get_product_meta( $saha_product_id, $saha_key );

		if ( '' === trim( $saha_content ) ) {
			continue;
		}
		?>
		<section class="saha-product-section saha-product-section--<?php echo esc_attr( str_replace( '_', '-', $saha_key ) ); ?>">
			<h2 class="saha-product-section__title"><?php echo esc_html( $saha_label ); ?></h2>
			<div class="saha-product-section__body">
				<?php echo wp_kses_post( wpautop( $saha_content ) ); ?>
			</div>
		</section>
	<?php endforeach; ?>

	<?php if ( $saha_specs ) : ?>
		<section class="saha-product-section saha-product-section--specs">
			<h2 class="saha-product-section__title"><?php esc_html_e( 'Thông số kỹ thuật', 'saha' ); ?></h2>
			<table class="saha-specs-table">
				<tbody>
					<?php foreach ( $saha_specs as $saha_spec ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $saha_spec['label'] ); ?></th>
							<td><?php echo esc_html( $saha_spec['value'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</section>
	<?php endif; ?>

	<?php if ( $saha_docs ) : ?>
		<section class="saha-product-section saha-product-section--docs">
			<h2 class="saha-product-section__title"><?php esc_html_e( 'Tài liệu kỹ thuật', 'saha' ); ?></h2>
			<ul class="saha-docs-list">
				<?php foreach ( $saha_docs as $saha_doc ) : ?>
					<li>
						<a
							href="<?php echo esc_url( (string) $saha_doc['url'] ); ?>"
							target="_blank"
							rel="noopener"
							download
						>
							<span class="saha-docs-list__type"><?php echo esc_html( (string) $saha_doc['type_label'] ); ?></span>
							<span class="saha-docs-list__title">
								<?php echo esc_html( '' !== (string) $saha_doc['title'] ? (string) $saha_doc['title'] : (string) $saha_doc['type_label'] ); ?>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

</div>
