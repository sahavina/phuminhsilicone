<?php
/**
 * Mọi trang WooCommerce (shop, danh mục, sản phẩm…) đi qua khung này.
 *
 * @package Saha\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="saha-container saha-section saha-woocommerce">
	<?php woocommerce_breadcrumb(); ?>
	<?php
	// Wrapper mặc định của WooCommerce đã gỡ (inc/woocommerce.php); hook vẫn chạy
	// để plugin/phần catalogue gắn nội dung trước/sau danh sách.
	do_action( 'woocommerce_before_main_content' );
	woocommerce_content();
	do_action( 'woocommerce_after_main_content' );
	?>
</div>
<?php
get_footer();
