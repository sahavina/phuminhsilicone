<?php
/**
 * Migration 003 — bảng log hệ thống / audit.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

return static function ( string $charset_collate, wpdb $wpdb ): void {
	$table = Saha\Core\Migrator::table( 'logs' );

	$sql = "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		level varchar(16) NOT NULL DEFAULT 'info',
		channel varchar(32) NOT NULL DEFAULT 'core',
		message text NOT NULL,
		context longtext NULL,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY  (id),
		KEY idx_level (level),
		KEY idx_channel (channel),
		KEY idx_created_at (created_at)
	) {$charset_collate};";

	dbDelta( $sql );
};
