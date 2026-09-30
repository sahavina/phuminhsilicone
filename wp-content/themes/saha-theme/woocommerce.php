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
	<?php woocommerce_content(); ?>
</div>
<?php
get_footer();
