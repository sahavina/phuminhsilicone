<?php
/**
 * Migration 004 — bảng log tìm kiếm (optional, spec §87).
 *
 * Chỉ lưu từ khoá và số kết quả. Không lưu user/IP.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

return static function ( string $charset_collate, wpdb $wpdb ): void {
	$table = Saha\Core\Migrator::table( 'search_logs' );

	$sql = "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		query varchar(191) NOT NULL DEFAULT '',
		result_count int(11) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY  (id),
		KEY idx_query (query),
		KEY idx_created_at (created_at)
	) {$charset_collate};";

	dbDelta( $sql );
};
