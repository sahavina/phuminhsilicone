<?php
/**
 * Flatsome Child — SAHA.
 *
 * File này CHỈ bootstrap. Không đặt business logic ở đây (spec §3).
 *
 * @package Flatsome_Child_Saha
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'SAHA_THEME_VERSION', '1.0.0' );
define( 'SAHA_THEME_PATH', get_stylesheet_directory() );
define( 'SAHA_THEME_URI', get_stylesheet_directory_uri() );

/**
 * Nạp các module của child theme.
 */
$saha_theme_modules = array(
	'setup',
	'helpers',
	'enqueue',
	'hooks',
	'woocommerce',
	'shortcodes',
	'ux-elements',
);

foreach ( $saha_theme_modules as $saha_theme_module ) {
	$saha_theme_file = SAHA_THEME_PATH . '/inc/' . $saha_theme_module . '.php';

	if ( is_readable( $saha_theme_file ) ) {
		require_once $saha_theme_file;
	}
}

unset( $saha_theme_modules, $saha_theme_module, $saha_theme_file );
