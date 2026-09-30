<?php
/**
 * Migrate tài liệu builder giữa các phiên bản schema.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * SchemaMigrator.
 *
 * Đổi cấu trúc JSON → tăng CURRENT và thêm bước `step{N}` (N → N+1) — không bao
 * giờ sửa dữ liệu cũ tại chỗ mà không có migration (spec §91). Editor có bước
 * tương ứng ở `saha-builder/src/shared/migrate.js` (mốc 1.3).
 */
final class SchemaMigrator {

	public const CURRENT = 1;

	/**
	 * Tài liệu có phiên bản mới hơn code (ví dụ lưu bởi plugin mới rồi hạ cấp).
	 *
	 * @param array<string, mixed> $data Dữ liệu.
	 */
	public static function isFromFuture( array $data ): bool {
		return (int) ( $data['version'] ?? 1 ) > self::CURRENT;
	}

	/**
	 * Đưa tài liệu lên phiên bản hiện tại.
	 *
	 * Tài liệu từ tương lai giữ nguyên (không hạ cấp được) — Sanitizer từ chối lưu,
	 * renderer vẫn cố render phần hiểu được.
	 *
	 * @param array<string, mixed> $data Dữ liệu.
	 * @return array<string, mixed>
	 */
	public static function migrate( array $data ): array {
		$version = max( 1, (int) ( $data['version'] ?? 1 ) );

		while ( $version < self::CURRENT ) {
			$method = 'step' . $version;

			if ( ! method_exists( self::class, $method ) ) {
				break;
			}

			$data = self::$method( $data );
			++$version;
		}

		$data['version'] = $version;

		/**
		 * Migrate thêm cho element của add-on.
		 *
		 * @param array<string, mixed> $data    Tài liệu.
		 * @param int                  $version Phiên bản sau migrate.
		 */
		return (array) apply_filters( 'saha_builder_migrate_document', $data, $version );
	}
}
