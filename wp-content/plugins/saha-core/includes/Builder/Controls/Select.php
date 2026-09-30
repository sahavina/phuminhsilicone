<?php
/**
 * Control: chọn một giá trị trong danh sách.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Controls;

use Saha\Core\Builder\InvalidValue;

defined( 'ABSPATH' ) || exit;

/**
 * Select. `options`: [ giá trị => nhãn ].
 */
final class Select extends Control {

	/**
	 * Type.
	 */
	public function type(): string {
		return 'select';
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed                $value Giá trị.
	 * @param array<string, mixed> $def   Định nghĩa.
	 * @throws InvalidValue Khi giá trị không có trong danh sách.
	 */
	public function sanitize( $value, array $def ) {
		$raw = self::scalar( $value );

		if ( '' === $raw ) {
			return null;
		}

		// Key số (ví dụ '400') bị PHP đổi thành int — so sánh dạng chuỗi.
		$allowed = array_map( 'strval', array_keys( (array) ( $def['options'] ?? array() ) ) );

		if ( ! in_array( $raw, $allowed, true ) ) {
			throw new InvalidValue( __( 'Lựa chọn không hợp lệ.', 'saha-core' ) );
		}

		return $raw;
	}

	/**
	 * Gửi options dạng danh sách để giữ thứ tự và key chuỗi trong JSON.
	 *
	 * @param array<string, mixed> $def Định nghĩa.
	 * @return array<string, mixed>
	 */
	public function forClient( array $def ): array {
		$options = array();

		foreach ( (array) ( $def['options'] ?? array() ) as $value => $label ) {
			$options[] = array(
				'value' => (string) $value,
				'label' => (string) $label,
			);
		}

		$def['options'] = $options;

		return $def;
	}
}
