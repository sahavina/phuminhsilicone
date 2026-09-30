<?php
/**
 * Control: số.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Controls;

use Saha\Core\Builder\InvalidValue;

defined( 'ABSPATH' ) || exit;

/**
 * Number. Tuỳ chọn: `min`, `max`, `integer` (mặc định true).
 */
final class Number extends Control {

	/**
	 * Type.
	 */
	public function type(): string {
		return 'number';
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed                $value Giá trị.
	 * @param array<string, mixed> $def   Định nghĩa.
	 * @throws InvalidValue Khi không phải số hoặc ngoài khoảng.
	 */
	public function sanitize( $value, array $def ) {
		$raw = self::scalar( $value );

		if ( '' === $raw ) {
			return null;
		}

		if ( ! is_numeric( $raw ) ) {
			throw new InvalidValue( __( 'Cần nhập số.', 'saha-core' ) );
		}

		$number = ( $def['integer'] ?? true ) ? (int) round( (float) $raw ) : round( (float) $raw, 4 );

		if ( ( isset( $def['min'] ) && $number < $def['min'] ) || ( isset( $def['max'] ) && $number > $def['max'] ) ) {
			/* translators: 1: min, 2: max */
			throw new InvalidValue( sprintf( __( 'Giá trị phải trong khoảng %1$s–%2$s.', 'saha-core' ), (string) ( $def['min'] ?? '' ), (string) ( $def['max'] ?? '' ) ) );
		}

		return $number;
	}
}
