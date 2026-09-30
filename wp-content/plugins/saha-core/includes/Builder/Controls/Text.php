<?php
/**
 * Control: chuỗi một dòng.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Controls;

defined( 'ABSPATH' ) || exit;

/**
 * Text — không HTML. Tuỳ chọn: `maxLength` (mặc định 500).
 */
final class Text extends Control {

	/**
	 * Type.
	 */
	public function type(): string {
		return 'text';
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed                $value Giá trị.
	 * @param array<string, mixed> $def   Định nghĩa.
	 */
	public function sanitize( $value, array $def ) {
		$text = sanitize_text_field( self::scalar( $value ) );

		return mb_substr( $text, 0, (int) ( $def['maxLength'] ?? 500 ) );
	}
}
