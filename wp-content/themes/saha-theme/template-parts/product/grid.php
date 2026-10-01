<?php
/**
 * Grid sản phẩm cho khối trang chủ / landing page.
 *
 * Render bằng loop chuẩn của WooCommerce (content-product.php) để product card
 * đồng nhất với archive: ảnh, brand, tên, SKU, giá nếu bật, CTA (spec §53).
 *
 * Dữ liệu (danh sách ID) do Catalog service trong plugin cung cấp — template
 * này không tự query (spec §36).
 *
 * @package Saha\Theme
 *
 * @var array<string, mixed> $args ids, columns, title, subtitle, view_all_url, view_all_label, empty_message.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wc_get_template_part' ) ) {
	return;
}

$saha_ids     = array_values( array_filter( array_map( 'absint', (array) ( $args['ids'] ?? array() ) ) ) );
$saha_columns = max( 2, min( 6, (int) ( $args['columns'] ?? 4 ) ) );

wp_enqueue_style( 'saha-sections' );
?>
<section class="saha-section saha-product-block">
	<?php
	saha_theme_part(
		'common/section-heading',
		array(
			'title'          => $args['title'] ?? '',
			'subtitle'       => $args['subtitle'] ?? '',
			'view_all_url'   => $saha_ids ? ( $args['view_all_url'] ?? '' ) : '',
			'view_all_label' => $args['view_all_label'] ?? '',
		)
	);

	if ( ! $saha_ids ) {
		// Ẩn hẳn khối rỗng ngoài trang, chỉ hiện gợi ý cho người đang soạn trang.
		if ( current_user_can( 'edit_pages' ) ) {
			saha_theme_part(
				'common/empty-state',
				array(
					'title'       => (string) ( $args['empty_message'] ?? __( 'Khối này chưa có sản phẩm', 'saha' ) ),
					'description' => __( 'Chỉ quản trị viên thấy thông báo này. Kiểm tra lại danh mục/thương hiệu đã chọn, hoặc đánh dấu sản phẩm nổi bật.', 'saha' ),
				)
			);
		}
		?>
		</section>
		<?php
		return;
	}

	// Nạp sẵn post + meta + term cho toàn bộ ID, tránh N+1 khi render card (spec §28).
	_prime_post_caches( $saha_ids, true, true );
	update_object_term_cache( $saha_ids, 'product' );

	wc_set_loop_prop( 'columns', $saha_columns );
	wc_set_loop_prop( 'name', 'saha_product_block' );
	wc_set_loop_prop( 'is_shortcode', true );

	woocommerce_product_loop_start();

	foreach ( $saha_ids as $saha_id ) {
		$GLOBALS['post'] = get_post( $saha_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- chuẩn loop của WooCommerce.
		setup_postdata( $GLOBALS['post'] );
		wc_get_template_part( 'content', 'product' );
	}

	woocommerce_product_loop_end();

	wp_reset_postdata();
	wc_reset_loop();
	?>
</section>
