<?php
/**
 * Duyệt tài liệu builder: gom / đổi ID ảnh và block khi xuất – nhập.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\ImportExport;

defined( 'ABSPATH' ) || exit;

/**
 * Walker — thuần dữ liệu (test được không cần WordPress).
 *
 * - Ảnh: mọi giá trị dạng `{id, size}` trong props (control `media`, ảnh của `background`, kể cả
 *   bọc responsive) — đúng hình dạng Controls\Media lưu.
 * - Block: prop `blockId` của element `block`.
 * - Term / menu lưu bằng slug (Controls\Term) → không cần đổi; chỉ cần có trên site nhận.
 */
final class Walker {

	/**
	 * Rule điều kiện template trỏ tới bài viết (ID) / term (ID) — Templates\Conditions.
	 */
	public const POST_RULES = array( 'page', 'post', 'product' );
	public const TERM_RULES = array( 'category', 'post_tag', 'product_cat', 'product_brand' );

	/**
	 * Giá trị ảnh `{id, size}`.
	 *
	 * @param mixed $value Giá trị.
	 */
	public static function isMedia( $value ): bool {
		return is_array( $value )
			&& isset( $value['id'] )
			&& array_key_exists( 'size', $value )
			&& is_numeric( $value['id'] )
			&& ! isset( $value['type'] );
	}

	/**
	 * Gom ID ảnh và block được dùng.
	 *
	 * @param array<string, mixed> $layout Tài liệu.
	 * @param int[]                $media  ID ảnh (ghi thêm).
	 * @param int[]                $blocks ID block (ghi thêm).
	 */
	public static function collect( array $layout, array &$media, array &$blocks ): void {
		self::nodes(
			(array) ( $layout['elements'] ?? array() ),
			static function ( array $props ) use ( &$media, &$blocks ): array {
				self::props(
					$props,
					static function ( int $id ) use ( &$media ): int {
						$media[] = $id;
						return $id;
					}
				);

				if ( isset( $props['blockId'] ) && (int) $props['blockId'] > 0 ) {
					$blocks[] = (int) $props['blockId'];
				}

				return $props;
			}
		);
	}

	/**
	 * Đổi ID ảnh / block theo bảng map của site nhận. ID không có trong map → bỏ giá trị đó
	 * (ảnh trống / block chưa chọn) và ghi cảnh báo — tài liệu vẫn hợp lệ.
	 *
	 * @param array<string, mixed> $layout   Tài liệu.
	 * @param array<int, int>      $media    Ảnh cũ → mới.
	 * @param array<int, int>      $blocks   Block cũ → mới.
	 * @param string[]             $warnings Cảnh báo (ghi thêm).
	 * @param string               $label    Tên tài liệu (cho cảnh báo).
	 * @return array<string, mixed>
	 */
	public static function remap( array $layout, array $media, array $blocks, array &$warnings, string $label ): array {
		$missing_media  = 0;
		$missing_blocks = 0;

		$layout['elements'] = self::nodes(
			(array) ( $layout['elements'] ?? array() ),
			static function ( array $props ) use ( $media, $blocks, &$missing_media, &$missing_blocks ): array {
				$props = self::props(
					$props,
					static function ( int $id ) use ( $media, &$missing_media ): int {
						if ( isset( $media[ $id ] ) && $media[ $id ] > 0 ) {
							return $media[ $id ];
						}

						++$missing_media;
						return 0;
					}
				);

				if ( isset( $props['blockId'] ) ) {
					$old = (int) $props['blockId'];

					if ( isset( $blocks[ $old ] ) ) {
						$props['blockId'] = $blocks[ $old ];
					} else {
						unset( $props['blockId'] );
						++$missing_blocks;
					}
				}

				return $props;
			}
		);

		if ( $missing_media > 0 ) {
			/* translators: 1: tên trang/block, 2: số ảnh */
			$warnings[] = sprintf( __( '%1$s: %2$d ảnh không tải được — để trống, chọn lại trong builder.', 'saha-core' ), $label, $missing_media );
		}

		if ( $missing_blocks > 0 ) {
			/* translators: 1: tên trang/block, 2: số block */
			$warnings[] = sprintf( __( '%1$s: %2$d Block dùng chung không có trong file — để trống.', 'saha-core' ), $label, $missing_blocks );
		}

		return $layout;
	}

	/**
	 * Bỏ mọi ảnh / block (kiểm tra cấu trúc khi chạy thử — ID của site nguồn không tồn tại ở đây).
	 *
	 * @param array<string, mixed> $layout Tài liệu.
	 * @return array<string, mixed>
	 */
	public static function strip( array $layout ): array {
		$warnings = array();

		return self::remap( $layout, array(), array(), $warnings, '' );
	}

	/**
	 * Gọi `$fn( props )` cho mọi node (đệ quy con).
	 *
	 * @param array<int, mixed> $nodes Node.
	 * @param callable          $fn    fn( array $props ): array.
	 * @return array<int, mixed>
	 */
	private static function nodes( array $nodes, callable $fn ): array {
		foreach ( $nodes as $i => $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}

			$nodes[ $i ]['props'] = $fn( (array) ( $node['props'] ?? array() ) );

			if ( isset( $node['children'] ) && is_array( $node['children'] ) ) {
				$nodes[ $i ]['children'] = self::nodes( $node['children'], $fn );
			}
		}

		return $nodes;
	}

	/**
	 * Duyệt sâu props, gọi `$fn( id )` cho mỗi giá trị ảnh; trả 0 → xoá giá trị ảnh đó.
	 *
	 * @param array<string, mixed> $props Props (hoặc mảng con).
	 * @param callable             $fn    fn( int $id ): int.
	 * @return array<string, mixed>
	 */
	private static function props( array $props, callable $fn ): array {
		foreach ( $props as $key => $value ) {
			if ( ! is_array( $value ) ) {
				continue;
			}

			if ( self::isMedia( $value ) ) {
				$id = $fn( (int) $value['id'] );

				if ( $id > 0 ) {
					$props[ $key ]['id'] = $id;
				} else {
					unset( $props[ $key ] );
				}

				continue;
			}

			$props[ $key ] = self::props( $value, $fn );
		}

		return $props;
	}
}
