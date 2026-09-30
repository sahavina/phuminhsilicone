<?php
/**
 * Bảo trì định kỳ: dọn log cũ, dọn option cũ sau nâng cấp.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Maintenance.
 *
 * - wp_saha_logs và wp_saha_search_logs tăng theo lưu lượng → xoá bản ghi
 *   quá hạn lưu trữ mỗi ngày, xoá theo lô để không khoá bảng lâu (spec §28).
 * - Không bao giờ đụng tới quotes / leads — đó là dữ liệu kinh doanh (spec §60).
 */
final class Maintenance {

	/**
	 * Tên cron event.
	 */
	public const CRON_HOOK = 'saha_core_daily_maintenance';

	/**
	 * Số dòng xoá mỗi lô.
	 */
	private const BATCH = 5000;

	/**
	 * Số lô tối đa mỗi lần chạy — chặn trên thời gian chạy của cron.
	 */
	private const MAX_BATCHES = 20;

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_action( self::CRON_HOOK, array( $this, 'run' ) );
		add_action( 'init', array( $this, 'schedule' ) );
		add_action( 'saha_core_upgraded', array( $this, 'cleanup_legacy_options' ) );
		add_action( 'saha_core_deactivated', array( __CLASS__, 'unschedule' ) );
	}

	/**
	 * Đặt lịch chạy hằng ngày nếu chưa có.
	 */
	public function schedule(): void {
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}

		// Chạy lúc ít truy cập: 3 giờ sáng theo múi giờ site.
		$next = ( new \DateTimeImmutable( 'tomorrow 03:00', wp_timezone() ) )->getTimestamp();

		wp_schedule_event( $next, 'daily', self::CRON_HOOK );
	}

	/**
	 * Huỷ lịch khi tắt plugin.
	 */
	public static function unschedule(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Chạy toàn bộ tác vụ bảo trì.
	 *
	 * @return array<string, int> Số dòng đã xoá theo bảng.
	 */
	public function run(): array {
		$result = array(
			'logs'        => self::purge( 'logs', (int) Settings::get( 'log_retention_days', 90 ) ),
			'search_logs' => self::purge( 'search_logs', (int) Settings::get( 'search_log_retention_days', 180 ) ),
		);

		if ( array_sum( $result ) > 0 ) {
			Logger::info( 'Đã dọn dữ liệu log cũ.', 'maintenance', $result );
		}

		/**
		 * Vừa chạy bảo trì định kỳ.
		 *
		 * @param array<string, int> $result Số dòng đã xoá.
		 */
		do_action( 'saha_core_maintenance_ran', $result );

		return $result;
	}

	/**
	 * Xoá bản ghi cũ hơn N ngày, theo lô.
	 *
	 * @param string $name Tên bảng không prefix — chỉ nhận logs | search_logs.
	 * @param int    $days Số ngày giữ lại; 0 = không xoá.
	 * @return int Số dòng đã xoá.
	 */
	public static function purge( string $name, int $days ): int {
		global $wpdb;

		if ( ! in_array( $name, array( 'logs', 'search_logs' ), true ) || $days <= 0 ) {
			return 0;
		}

		$table = Migrator::table( $name );

		if ( ! Migrator::table_exists( $table ) ) {
			return 0;
		}

		// created_at lưu theo giờ site (current_time) → so sánh cũng theo giờ site.
		$cutoff = wp_date( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$total  = 0;

		for ( $i = 0; $i < self::MAX_BATCHES; $i++ ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tên bảng nội bộ (whitelist ở trên).
			$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE created_at < %s LIMIT %d", $cutoff, self::BATCH ) );

			if ( ! is_int( $deleted ) || $deleted <= 0 ) {
				break;
			}

			$total += $deleted;

			if ( $deleted < self::BATCH ) {
				break;
			}
		}

		return $total;
	}

	/**
	 * Xoá option của cơ chế cache cũ (trước Phase 7).
	 */
	public function cleanup_legacy_options(): void {
		foreach ( (array) get_option( 'saha_brand_cache_keys', array() ) as $key ) {
			delete_transient( (string) $key );
		}

		delete_option( 'saha_brand_cache_keys' );
		delete_option( 'saha_catalog_cache_gen' );
	}
}
