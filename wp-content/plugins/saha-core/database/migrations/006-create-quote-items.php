<?php
/**
 * Migration 006 — dòng sản phẩm của yêu cầu báo giá (danh sách báo giá nhiều sản phẩm, SCC 2.5).
 *
 * Một yêu cầu (wp_saha_quotes) có nhiều dòng (1-n). Báo giá cũ một sản phẩm không có dòng nào
 * ở đây — vẫn đọc từ cột product_id/product_name của wp_saha_quotes (Quote::items()).
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

return static function ( string $charset_collate, wpdb $wpdb ): void {
	unset( $wpdb );

	$table = Saha\Core\Migrator::table( 'quote_items' );

	$sql = "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		quote_id bigint(20) unsigned NOT NULL DEFAULT 0,
		product_id bigint(20) unsigned NOT NULL DEFAULT 0,
		variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
		sku varchar(100) NOT NULL DEFAULT '',
		product_name varchar(255) NOT NULL DEFAULT '',
		quantity int(10) unsigned NOT NULL DEFAULT 1,
		note varchar(255) NOT NULL DEFAULT '',
		created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY  (id),
		KEY idx_quote_id (quote_id),
		KEY idx_product_id (product_id)
	) {$charset_collate};";

	dbDelta( $sql );
};
