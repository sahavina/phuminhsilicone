<?php
/**
 * Nạp CSS/JS frontend.
 *
 * @package Saha\Theme
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		$asset_file = SAHA_THEME_DIR . '/assets/build/frontend.asset.php';
		$asset      = is_readable( $asset_file ) ? require $asset_file : array(
			'dependencies' => array(),
			'version'      => SAHA_THEME_VERSION,
		);
		$version    = (string) ( $asset['version'] ?? SAHA_THEME_VERSION );

		// Biến --saha-* (saha-core) nạp trước để CSS theme dùng được.
		$style_deps = wp_style_is( 'saha-global', 'registered' ) ? array( 'saha-global' ) : array();

		wp_enqueue_style( 'saha-theme', SAHA_THEME_URI . '/assets/build/frontend.css', $style_deps, $version );
		wp_style_add_data( 'saha-theme', 'rtl', 'replace' );

		wp_enqueue_script(
			'saha-theme',
			SAHA_THEME_URI . '/assets/build/frontend.js',
			(array) ( $asset['dependencies'] ?? array() ),
			$version,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
			wp_enqueue_script( 'comment-reply' );
		}
	},
	20
);
