<?php
/**
 * Control: thuộc tính id HTML.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Controls;

use Saha\Core\Builder\InvalidValue;

defined( 'ABSPATH' ) || exit;

/**
 * HtmlId — dùng làm neo `#lien-he`. Chữ cái đầu, sau đó chữ/số/-/_.
 */
final class HtmlId extends Control {

	/**
	 * Type.
	 */
	public function type(): string {
		return 'htmlId';
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed                $value Giá trị.
	 * @param array<string, mixed> $def   Định nghĩa.
	 * @throws InvalidValue Khi sai định dạng.
	 */
	public function sanitize( $value, array $def ) {
		$raw = self::scalar( $value );

		if ( '' === $raw ) {
			return null;
		}

		if ( ! preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', $raw ) ) {
			throw new InvalidValue( __( 'ID chỉ gồm chữ không dấu, số, "-" và "_", bắt đầu bằng chữ.', 'saha-core' ) );
		}

		return $raw;
	}
}
