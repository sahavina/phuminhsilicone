<?php
/**
 * SAHA Theme Child.
 *
 * Theme cha tự nạp CSS/JS của nó. Child chỉ nạp style.css của mình khi có nội dung.
 *
 * @package Saha\Theme\Child
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		wp_enqueue_style(
			'saha-child',
			get_stylesheet_uri(),
			array( 'saha-theme' ),
			(string) wp_get_theme()->get( 'Version' )
		);
	},
	20
);
