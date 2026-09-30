<?php
/**
 * Loại control của builder.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Controls;

use Saha\Core\Builder\InvalidValue;

defined( 'ABSPATH' ) || exit;

/**
 * Control = một loại giá trị (màu, kích thước, link…) + cách sanitize nó.
 *
 * Định nghĩa control trong element (`$def`) là mảng: type, label, default,
 * responsive, section (content|style|advanced) và tuỳ chọn riêng của loại.
 */
abstract class Control {

	/**
	 * Tên loại, ví dụ `color`.
	 */
	abstract public function type(): string;

	/**
	 * Sanitize MỘT giá trị (một breakpoint nếu control responsive).
	 *
	 * @param mixed                $value Giá trị thô từ client.
	 * @param array<string, mixed> $def   Định nghĩa control.
	 * @return mixed Giá trị sạch; null = "không đặt" (prop bị bỏ khỏi JSON).
	 * @throws InvalidValue Khi không hợp lệ.
	 */
	abstract public function sanitize( $value, array $def );

	/**
	 * Định nghĩa gửi cho editor (mặc định gửi nguyên).
	 *
	 * @param array<string, mixed> $def Định nghĩa.
	 * @return array<string, mixed>
	 */
	public function forClient( array $def ): array {
		return $def;
	}

	/**
	 * Ép về chuỗi; mảng/đối tượng thành chuỗi rỗng.
	 *
	 * @param mixed $value Giá trị.
	 */
	protected static function scalar( $value ): string {
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}
}
