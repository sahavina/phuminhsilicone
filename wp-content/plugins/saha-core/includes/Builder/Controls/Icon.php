<?php
/**
 * Control: chọn icon.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Controls;

use Saha\Core\Builder\Icons;
use Saha\Core\Builder\InvalidValue;

defined( 'ABSPATH' ) || exit;

/**
 * Icon — tên icon trong `Builder\Icons`.
 */
final class Icon extends Control {

	/**
	 * Type.
	 */
	public function type(): string {
		return 'icon';
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed                $value Giá trị.
	 * @param array<string, mixed> $def   Định nghĩa.
	 * @throws InvalidValue Khi icon không tồn tại.
	 */
	public function sanitize( $value, array $def ) {
		$name = self::scalar( $value );

		if ( '' === $name ) {
			return null;
		}

		if ( ! Icons::has( $name ) ) {
			throw new InvalidValue( __( 'Icon không tồn tại.', 'saha-core' ) );
		}

		return $name;
	}

	/**
	 * Gửi danh sách icon kèm SVG để editor hiện lưới chọn.
	 *
	 * @param array<string, mixed> $def Định nghĩa.
	 * @return array<string, mixed>
	 */
	public function forClient( array $def ): array {
		$icons = array();

		foreach ( Icons::all() as $name => $icon ) {
			$icons[] = array(
				'value' => (string) $name,
				'label' => (string) $icon[0],
				'svg'   => Icons::svg( (string) $name ),
			);
		}

		$def['icons'] = $icons;

		return $def;
	}
}
