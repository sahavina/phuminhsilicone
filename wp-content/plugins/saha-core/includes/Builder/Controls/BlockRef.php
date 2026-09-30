<?php
/**
 * Control: chọn một Block tái sử dụng.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Controls;

use Saha\Core\Blocks\PostType;
use Saha\Core\Builder\InvalidValue;

defined( 'ABSPATH' ) || exit;

/**
 * BlockRef — ID bài `saha_block`. Danh sách do editor lấy qua REST `/blocks`
 * (luôn mới, kể cả block vừa tạo bằng "Lưu thành block").
 */
final class BlockRef extends Control {

	/**
	 * Type.
	 */
	public function type(): string {
		return 'blockRef';
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed                $value Giá trị.
	 * @param array<string, mixed> $def   Định nghĩa.
	 * @throws InvalidValue Khi không phải block.
	 */
	public function sanitize( $value, array $def ) {
		$id = absint( is_scalar( $value ) ? $value : 0 );

		if ( 0 === $id ) {
			return null;
		}

		if ( PostType::NAME !== get_post_type( $id ) ) {
			throw new InvalidValue( __( 'Block đã chọn không tồn tại.', 'saha-core' ) );
		}

		return $id;
	}
}
