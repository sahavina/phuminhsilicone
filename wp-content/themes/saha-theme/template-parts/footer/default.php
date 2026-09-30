<?php
/**
 * Footer mặc định (khi chưa có footer dựng bằng builder — mốc 1.5).
 *
 * @package Saha\Theme
 */

defined( 'ABSPATH' ) || exit;

$saha_widget_areas = array_values( array_filter( array( 'footer-1', 'footer-2', 'footer-3', 'footer-4' ), 'is_active_sidebar' ) );
?>
<footer id="saha-footer" class="saha-footer">
	<?php if ( $saha_widget_areas ) : ?>
		<div class="saha-container saha-footer__widgets saha-footer__widgets--<?php echo (int) count( $saha_widget_areas ); ?>">
			<?php foreach ( $saha_widget_areas as $saha_area ) : ?>
				<div class="saha-footer__col">
					<?php dynamic_sidebar( $saha_area ); ?>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="saha-footer__bottom">
		<div class="saha-container saha-footer__bottom-inner">
			<p class="saha-footer__copyright"><?php echo esc_html( saha_theme_copyright() ); ?></p>
			<?php
			if ( has_nav_menu( 'footer' ) ) {
				wp_nav_menu(
					array(
						'theme_location'       => 'footer',
						'container'            => 'nav',
						'container_class'      => 'saha-footer__nav',
						'container_aria_label' => __( 'Menu chân trang', 'saha' ),
						'menu_class'           => 'saha-menu saha-menu--inline',
						'depth'                => 1,
						'fallback_cb'          => false,
					)
				);
			}
			?>
		</div>
	</div>
</footer>
