<?php
/**
 * Control: văn bản nhiều dòng, không HTML.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Controls;

defined( 'ABSPATH' ) || exit;

/**
 * Textarea. Tuỳ chọn: `maxLength` (mặc định 5000).
 */
final class Textarea extends Control {

	/**
	 * Type.
	 */
	public function type(): string {
		return 'textarea';
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed                $value Giá trị.
	 * @param array<string, mixed> $def   Định nghĩa.
	 */
	public function sanitize( $value, array $def ) {
		$text = sanitize_textarea_field( is_scalar( $value ) ? (string) $value : '' );

		return mb_substr( $text, 0, (int) ( $def['maxLength'] ?? 5000 ) );
	}
}
