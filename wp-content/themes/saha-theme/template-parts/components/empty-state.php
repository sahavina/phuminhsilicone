<?php
/**
 * Trạng thái rỗng khi không có bài nào.
 *
 * @package Saha\Theme
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="saha-empty">
	<p><?php esc_html_e( 'Chưa có nội dung phù hợp.', 'saha' ); ?></p>
	<?php get_search_form(); ?>
</div>
