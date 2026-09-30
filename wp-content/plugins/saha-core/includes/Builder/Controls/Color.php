<?php
/**
 * Control: màu.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Controls;

use Saha\Core\Builder\InvalidValue;
use Saha\Core\ThemeOptions\Sanitizer as ThemeSanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Color — cùng quy tắc với Theme Options: hex, rgb(a), `transparent`,
 * hoặc `var(--saha-*)` (đổi màu toàn cục là element đổi theo).
 */
final class Color extends Control {

	/**
	 * Type.
	 */
	public function type(): string {
		return 'color';
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed                $value Giá trị.
	 * @param array<string, mixed> $def   Định nghĩa.
	 * @throws InvalidValue Khi không phải màu.
	 */
	public function sanitize( $value, array $def ) {
		if ( '' === self::scalar( $value ) ) {
			return null;
		}

		try {
			return ThemeSanitizer::color( $value );
		} catch ( \Saha\Core\ThemeOptions\InvalidValue $e ) {
			throw new InvalidValue( $e->getMessage() );
		}
	}
}
