<?php
/**
 * Mở trang: <head>, header.
 *
 * @package Saha\Theme
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="saha-skip-link" href="#saha-main"><?php esc_html_e( 'Chuyển tới nội dung', 'saha' ); ?></a>

<?php do_action( 'saha_before_header' ); ?>

<?php saha_theme_render_header(); ?>

<?php do_action( 'saha_after_header' ); ?>

<main id="saha-main" class="saha-main" tabindex="-1">
<?php do_action( 'saha_before_content' ); ?>
