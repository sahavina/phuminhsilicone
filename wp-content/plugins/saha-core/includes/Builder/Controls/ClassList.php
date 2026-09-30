<?php
/**
 * Control: danh sách class CSS.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Controls;

defined( 'ABSPATH' ) || exit;

/**
 * ClassList — tối đa 10 class, mỗi class qua `sanitize_html_class`.
 */
final class ClassList extends Control {

	/**
	 * Type.
	 */
	public function type(): string {
		return 'classList';
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed                $value Giá trị.
	 * @param array<string, mixed> $def   Định nghĩa.
	 */
	public function sanitize( $value, array $def ) {
		$classes = preg_split( '/\s+/', self::scalar( $value ) ) ?: array();
		$classes = array_filter( array_map( 'sanitize_html_class', $classes ) );
		$classes = array_slice( array_values( array_unique( $classes ) ), 0, 10 );

		return $classes ? implode( ' ', $classes ) : null;
	}
}
