<?php
/**
 * SAHA Theme — bootstrap.
 *
 * File này chỉ nạp module trong inc/. Không đặt logic ở đây.
 *
 * @package Saha\Theme
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'SAHA_THEME_VERSION', '0.2.1' );
define( 'SAHA_THEME_DIR', get_template_directory() );
define( 'SAHA_THEME_URI', get_template_directory_uri() );

foreach ( array( 'helpers', 'setup', 'assets', 'hooks', 'template-functions', 'woocommerce', 'performance', 'seo', 'shortcodes', 'catalog' ) as $saha_theme_module ) {
	require_once SAHA_THEME_DIR . '/inc/' . $saha_theme_module . '.php';
}

unset( $saha_theme_module );
