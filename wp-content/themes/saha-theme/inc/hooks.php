<?php
/**
 * Hook và filter của theme (spec SCC §77–78).
 *
 * Action: saha_before_header, saha_after_header, saha_before_content,
 *         saha_after_content, saha_before_footer, saha_after_footer,
 *         saha_before_product, saha_after_product.
 * Filter: saha_header_classes, saha_body_classes.
 *
 * @package Saha\Theme
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class của thẻ <header>.
 */
function saha_theme_header_classes(): string {
	$classes = array( 'saha-header' );

	if ( saha_theme_option( 'header.sticky', true ) ) {
		$classes[] = 'saha-header--sticky';
		$classes[] = 'saha-header--sticky-' . ( 'scrollUp' === saha_theme_option( 'header.sticky_mode', 'always' ) ? 'up' : 'always' );
	}

	/**
	 * Lọc class của header (spec SCC §78).
	 *
	 * @param string[] $classes Class.
	 */
	$classes = (array) apply_filters( 'saha_header_classes', $classes );

	return implode( ' ', array_map( 'sanitize_html_class', $classes ) );
}

add_filter(
	'body_class',
	static function ( array $classes ): array {
		$classes[] = 'saha-site';

		if ( saha_theme_is_catalog() ) {
			$classes[] = 'saha-catalog-mode';
		}

		/**
		 * Lọc class của <body>.
		 *
		 * @param string[] $classes Class.
		 */
		return (array) apply_filters( 'saha_body_classes', $classes );
	}
);

/**
 * Cầu nối hook sản phẩm WooCommerce → hook SAHA (spec SCC §77).
 */
add_action(
	'woocommerce_before_single_product',
	static function (): void {
		do_action( 'saha_before_product' );
	}
);

add_action(
	'woocommerce_after_single_product',
	static function (): void {
		do_action( 'saha_after_product' );
	}
);
