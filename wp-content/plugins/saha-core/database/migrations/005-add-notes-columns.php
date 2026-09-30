<?php
/**
 * Migration 005 — thêm cột ghi chú nội bộ cho quotes và leads (spec §14).
 *
 * Dùng ALTER TABLE có kiểm tra cột trước, nên chạy lại bao nhiêu lần cũng an toàn.
 * Không đụng tới dữ liệu hiện có.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

return static function ( string $charset_collate, wpdb $wpdb ): void {
	unset( $charset_collate );

	foreach ( array( 'quotes', 'leads' ) as $name ) {
		$table = Saha\Core\Migrator::table( $name );

		if ( ! Saha\Core\Migrator::table_exists( $table ) ) {
			continue;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tên bảng nội bộ, không phải input.
		$exists = $wpdb->get_var(
			$wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", 'notes' )
		);

		if ( null === $exists ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN notes longtext NULL AFTER assigned_user_id" );
		}
		// phpcs:enable
	}
};
