<?php
/**
 * Control: kích thước CSS.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Controls;

use Saha\Core\Builder\InvalidValue;

defined( 'ABSPATH' ) || exit;

/**
 * Size — số + đơn vị.
 *
 * Tuỳ chọn: `units` (mặc định px, %, em, rem, vw, vh), `min`, `max` (so với phần
 * số, bất kể đơn vị), `allowAuto`, `allowVar` (mặc định true — nhận `var(--saha-*)`).
 * Số trần hiểu là đơn vị đầu tiên; `0` giữ nguyên không đơn vị.
 */
final class Size extends Control {

	public const DEFAULT_UNITS = array( 'px', '%', 'em', 'rem', 'vw', 'vh' );

	/**
	 * Type.
	 */
	public function type(): string {
		return 'size';
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed                $value Giá trị.
	 * @param array<string, mixed> $def   Định nghĩa.
	 * @throws InvalidValue Khi sai định dạng, đơn vị hoặc khoảng.
	 */
	public function sanitize( $value, array $def ) {
		return self::clean( $value, $def );
	}

	/**
	 * Dùng chung cho Spacing, Typography.
	 *
	 * @param mixed                $value Giá trị.
	 * @param array<string, mixed> $def   Định nghĩa.
	 * @return string|null
	 * @throws InvalidValue Khi không hợp lệ.
	 */
	public static function clean( $value, array $def ): ?string {
		$raw = strtolower( self::scalar( $value ) );

		if ( '' === $raw ) {
			return null;
		}

		if ( 'auto' === $raw ) {
			if ( empty( $def['allowAuto'] ) ) {
				throw new InvalidValue( __( 'Không dùng được "auto" ở đây.', 'saha-core' ) );
			}
			return 'auto';
		}

		if ( ( $def['allowVar'] ?? true ) && preg_match( '/^var\(--saha-[a-z0-9-]+\)$/', $raw ) ) {
			return $raw;
		}

		if ( ! preg_match( '/^(-?\d+(?:\.\d+)?)([a-z%]*)$/', $raw, $m ) ) {
			throw new InvalidValue( __( 'Kích thước không hợp lệ (ví dụ: 24px).', 'saha-core' ) );
		}

		$units  = (array) ( $def['units'] ?? self::DEFAULT_UNITS );
		$number = (float) $m[1];

		if ( ( isset( $def['min'] ) && $number < (float) $def['min'] ) || ( isset( $def['max'] ) && $number > (float) $def['max'] ) ) {
			/* translators: 1: min, 2: max */
			throw new InvalidValue( sprintf( __( 'Giá trị phải trong khoảng %1$s–%2$s.', 'saha-core' ), (string) ( $def['min'] ?? '' ), (string) ( $def['max'] ?? '' ) ) );
		}

		if ( 0.0 === $number && '' === $m[2] ) {
			return '0';
		}

		$unit = '' !== $m[2] ? $m[2] : (string) ( $units[0] ?? 'px' );

		if ( ! in_array( $unit, $units, true ) ) {
			/* translators: %s: danh sách đơn vị */
			throw new InvalidValue( sprintf( __( 'Đơn vị không được hỗ trợ. Dùng: %s', 'saha-core' ), implode( ', ', $units ) ) );
		}

		return rtrim( rtrim( number_format( $number, 4, '.', '' ), '0' ), '.' ) . $unit;
	}
}
