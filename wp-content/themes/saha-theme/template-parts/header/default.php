<?php
/**
 * Header mặc định (khi chưa có header dựng bằng builder — mốc 1.5).
 *
 * @package Saha\Theme
 */

defined( 'ABSPATH' ) || exit;

$saha_catalog   = saha_theme_is_catalog();
$saha_hotline   = saha_theme_option( 'header.show_hotline', true ) ? saha_theme_hotline() : null;
$saha_has_wc    = function_exists( 'wc_get_cart_url' );
$saha_show_cart = $saha_has_wc && ! $saha_catalog && saha_theme_option( 'header.show_cart', true );
$saha_show_acc  = $saha_has_wc && saha_theme_option( 'header.show_account', false );
?>
<header id="saha-header" class="<?php echo esc_attr( saha_theme_header_classes() ); ?>">
	<div class="saha-container saha-header__inner">
		<button class="saha-header__burger" type="button" aria-controls="saha-offcanvas" aria-expanded="false">
			<?php echo saha_theme_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput -- SVG tĩnh. ?>
			<span class="screen-reader-text"><?php esc_html_e( 'Mở menu', 'saha' ); ?></span>
		</button>

		<div class="saha-header__brand">
			<?php echo saha_theme_logo(); // phpcs:ignore WordPress.Security.EscapeOutput -- đã escape trong hàm. ?>
		</div>

		<?php if ( has_nav_menu( 'primary' ) ) : ?>
			<nav class="saha-header__nav" aria-label="<?php esc_attr_e( 'Menu chính', 'saha' ); ?>">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'saha-menu',
						'depth'          => 2,
						'fallback_cb'    => false,
					)
				);
				?>
			</nav>
		<?php endif; ?>

		<div class="saha-header__tools">
			<?php if ( saha_theme_option( 'header.show_search', true ) ) : ?>
				<div class="saha-header__search">
					<?php
					if ( function_exists( 'get_product_search_form' ) ) {
						get_product_search_form();
					} else {
						get_search_form();
					}
					?>
				</div>
			<?php endif; ?>

			<?php if ( $saha_hotline ) : ?>
				<a class="saha-header__hotline" href="<?php echo esc_url( $saha_hotline['href'] ); ?>">
					<?php echo saha_theme_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<span><?php echo esc_html( $saha_hotline['number'] ); ?></span>
				</a>
			<?php endif; ?>

			<?php if ( $saha_show_acc ) : ?>
				<a class="saha-header__icon" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">
					<?php echo saha_theme_icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<span class="screen-reader-text"><?php esc_html_e( 'Tài khoản', 'saha' ); ?></span>
				</a>
			<?php endif; ?>

			<?php if ( $saha_show_cart ) : ?>
				<a class="saha-header__icon saha-header__cart" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
					<?php echo saha_theme_icon( 'cart' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<span class="screen-reader-text"><?php esc_html_e( 'Giỏ hàng', 'saha' ); ?></span>
				</a>
			<?php endif; ?>
		</div>
	</div>
</header>

<div id="saha-offcanvas" class="saha-offcanvas" hidden>
	<div class="saha-offcanvas__backdrop" data-saha-close></div>
	<div class="saha-offcanvas__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Menu', 'saha' ); ?>">
		<button class="saha-offcanvas__close" type="button" data-saha-close>
			<?php echo saha_theme_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<span class="screen-reader-text"><?php esc_html_e( 'Đóng menu', 'saha' ); ?></span>
		</button>
		<?php
		$saha_mobile_loc = has_nav_menu( 'mobile' ) ? 'mobile' : ( has_nav_menu( 'primary' ) ? 'primary' : '' );

		if ( $saha_mobile_loc ) {
			wp_nav_menu(
				array(
					'theme_location'       => $saha_mobile_loc,
					'container'            => 'nav',
					'container_class'      => 'saha-offcanvas__nav',
					'container_aria_label' => __( 'Menu di động', 'saha' ),
					'menu_class'           => 'saha-menu saha-menu--vertical',
					'depth'                => 2,
					'fallback_cb'          => false,
				)
			);
		}

		if ( $saha_hotline ) {
			printf(
				'<a class="saha-button saha-offcanvas__hotline" href="%1$s">%2$s %3$s</a>',
				esc_url( $saha_hotline['href'] ),
				saha_theme_icon( 'phone' ), // phpcs:ignore WordPress.Security.EscapeOutput
				esc_html( $saha_hotline['number'] )
			);
		}
		?>
	</div>
</div>
