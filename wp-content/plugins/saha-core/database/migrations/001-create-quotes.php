<?php
/**
 * Migration 001 — bảng yêu cầu báo giá.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

return static function ( string $charset_collate, wpdb $wpdb ): void {
	$table = Saha\Core\Migrator::table( 'quotes' );

	$sql = "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		customer_name varchar(191) NOT NULL DEFAULT '',
		phone varchar(32) NOT NULL DEFAULT '',
		email varchar(191) NOT NULL DEFAULT '',
		company varchar(191) NOT NULL DEFAULT '',
		product_id bigint(20) unsigned NOT NULL DEFAULT 0,
		product_name varchar(255) NOT NULL DEFAULT '',
		sku varchar(100) NOT NULL DEFAULT '',
		quantity varchar(100) NOT NULL DEFAULT '',
		message text NULL,
		source_url varchar(255) NOT NULL DEFAULT '',
		status varchar(20) NOT NULL DEFAULT 'new',
		assigned_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY  (id),
		KEY idx_phone (phone),
		KEY idx_product_id (product_id),
		KEY idx_status (status),
		KEY idx_assigned_user_id (assigned_user_id),
		KEY idx_created_at (created_at)
	) {$charset_collate};";

	dbDelta( $sql );
};
