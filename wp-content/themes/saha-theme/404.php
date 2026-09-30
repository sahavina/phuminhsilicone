<?php
/**
 * 404: không chuyển hướng về trang chủ — hiện ô tìm kiếm và danh mục (spec cũ §81).
 *
 * @package Saha\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="saha-container saha-section saha-404">
	<h1 class="saha-page-title"><?php esc_html_e( 'Không tìm thấy trang bạn cần', 'saha' ); ?></h1>
	<p><?php esc_html_e( 'Thử tìm theo tên hoặc mã sản phẩm:', 'saha' ); ?></p>

	<?php
	if ( function_exists( 'get_product_search_form' ) ) {
		get_product_search_form();
	} else {
		get_search_form();
	}

	if ( taxonomy_exists( 'product_cat' ) ) {
		$saha_cats = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => 0,
				'hide_empty' => true,
				'number'     => 8,
			)
		);

		if ( ! is_wp_error( $saha_cats ) && $saha_cats ) {
			echo '<h2 class="saha-404__heading">' . esc_html__( 'Danh mục sản phẩm', 'saha' ) . '</h2><ul class="saha-chips">';

			foreach ( $saha_cats as $saha_cat ) {
				printf( '<li><a href="%1$s">%2$s</a></li>', esc_url( (string) get_term_link( $saha_cat ) ), esc_html( $saha_cat->name ) );
			}

			echo '</ul>';
		}
	}
	?>

	<p><a class="saha-button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Về trang chủ', 'saha' ); ?></a></p>
</div>
<?php
get_footer();
