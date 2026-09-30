<?php
/**
 * Control: căn lề.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Controls;

use Saha\Core\Builder\InvalidValue;

defined( 'ABSPATH' ) || exit;

/**
 * Align — left | center | right | justify. `options` giới hạn tập con (ví dụ nút không có justify).
 */
final class Align extends Control {

	public const VALUES = array( 'left', 'center', 'right', 'justify' );

	/**
	 * Type.
	 */
	public function type(): string {
		return 'align';
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed                $value Giá trị.
	 * @param array<string, mixed> $def   Định nghĩa.
	 * @throws InvalidValue Khi không hợp lệ.
	 */
	public function sanitize( $value, array $def ) {
		$raw = self::scalar( $value );

		if ( '' === $raw ) {
			return null;
		}

		if ( ! in_array( $raw, (array) ( $def['options'] ?? self::VALUES ), true ) ) {
			throw new InvalidValue( __( 'Căn lề không hợp lệ.', 'saha-core' ) );
		}

		return $raw;
	}

	/**
	 * Editor cần danh sách lựa chọn.
	 *
	 * @param array<string, mixed> $def Định nghĩa.
	 * @return array<string, mixed>
	 */
	public function forClient( array $def ): array {
		$def['options'] = array_values( (array) ( $def['options'] ?? self::VALUES ) );

		return $def;
	}
}
