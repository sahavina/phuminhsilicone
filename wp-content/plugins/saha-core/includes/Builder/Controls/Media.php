<?php
/**
 * Control: ảnh từ Media Library.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Controls;

use Saha\Core\Builder\InvalidValue;

defined( 'ABSPATH' ) || exit;

/**
 * Media — `{id, size}`; lưu attachment ID, không lưu URL (spec §80: đổi domain không vỡ).
 */
final class Media extends Control {

	/**
	 * Type.
	 */
	public function type(): string {
		return 'media';
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed                $value Giá trị: ID hoặc {id, size}.
	 * @param array<string, mixed> $def   Định nghĩa.
	 * @throws InvalidValue Khi ID không phải ảnh hoặc size lạ.
	 */
	public function sanitize( $value, array $def ) {
		$id   = is_array( $value ) ? absint( $value['id'] ?? 0 ) : absint( is_scalar( $value ) ? $value : 0 );
		$size = is_array( $value ) ? self::scalar( $value['size'] ?? '' ) : '';

		if ( 0 === $id ) {
			return null;
		}

		if ( ! wp_attachment_is_image( $id ) ) {
			throw new InvalidValue( __( 'Tệp đã chọn không phải ảnh.', 'saha-core' ) );
		}

		$size = '' !== $size ? $size : (string) ( $def['defaultSize'] ?? 'large' );

		if ( ! in_array( $size, self::sizes(), true ) ) {
			throw new InvalidValue( __( 'Kích thước ảnh không hợp lệ.', 'saha-core' ) );
		}

		return array(
			'id'   => $id,
			'size' => $size,
		);
	}

	/**
	 * Kích thước ảnh đã đăng ký + full.
	 *
	 * @return string[]
	 */
	public static function sizes(): array {
		return array_values( array_unique( array_merge( get_intermediate_image_sizes(), array( 'full' ) ) ) );
	}

	/**
	 * Editor cần danh sách kích thước.
	 *
	 * @param array<string, mixed> $def Định nghĩa.
	 * @return array<string, mixed>
	 */
	public function forClient( array $def ): array {
		$def['sizes'] = self::sizes();

		return $def;
	}
}
