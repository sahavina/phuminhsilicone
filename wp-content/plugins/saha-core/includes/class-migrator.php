<?php
/**
 * Database migration runner.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Migrator: chạy tuần tự các file trong database/migrations/.
 *
 * Mỗi migration file return một closure nhận ( string $charset_collate, \wpdb $wpdb )
 * và phải idempotent — dbDelta() an toàn khi gọi lại. Không DROP dữ liệu production.
 */
final class Migrator {

	/**
	 * Option lưu danh sách migration đã chạy.
	 */
	private const RAN_OPTION = 'saha_core_migrations_ran';

	/**
	 * Lấy tên bảng đầy đủ theo prefix hiện tại.
	 *
	 * @param string $name Tên bảng không prefix, ví dụ `quotes`.
	 */
	public static function table( string $name ): string {
		global $wpdb;

		return $wpdb->prefix . 'saha_' . $name;
	}

	/**
	 * Chạy các migration chưa áp dụng.
	 *
	 * @return string[] Danh sách migration vừa chạy.
	 */
	public function migrate(): array {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$ran             = (array) get_option( self::RAN_OPTION, array() );
		$applied         = array();

		foreach ( $this->migration_files() as $file ) {
			$key = basename( $file, '.php' );

			if ( in_array( $key, $ran, true ) ) {
				continue;
			}

			$migration = require $file;

			if ( ! is_callable( $migration ) ) {
				continue;
			}

			$migration( $charset_collate, $wpdb );

			$ran[]     = $key;
			$applied[] = $key;
		}

		update_option( self::RAN_OPTION, array_values( array_unique( $ran ) ) );
		update_option( Install::DB_VERSION_OPTION, SAHA_CORE_DB_VERSION );

		if ( $applied ) {
			/**
			 * Đã chạy migration.
			 *
			 * @param string[] $applied Danh sách migration key.
			 */
			do_action( 'saha_core_migrated', $applied );
		}

		return $applied;
	}

	/**
	 * Danh sách file migration, sort theo tên.
	 *
	 * @return string[]
	 */
	private function migration_files(): array {
		$files = glob( SAHA_CORE_PATH . 'database/migrations/*.php' );

		if ( ! $files ) {
			return array();
		}

		sort( $files, SORT_NATURAL );

		return $files;
	}

	/**
	 * Bảng đã tồn tại chưa.
	 *
	 * @param string $table Tên bảng đầy đủ.
	 */
	public static function table_exists( string $table ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- schema check, không cache được.
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		return (string) $found === $table;
	}
}
