<?php
/**
 * Đóng trang: footer.
 *
 * @package Saha\Theme
 */

defined( 'ABSPATH' ) || exit;
?>
<?php do_action( 'saha_after_content' ); ?>
</main>

<?php do_action( 'saha_before_footer' ); ?>

<?php saha_theme_render_footer(); ?>

<?php do_action( 'saha_after_footer' ); ?>

<?php wp_footer(); ?>
</body>
</html>
