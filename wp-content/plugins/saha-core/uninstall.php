<?php
/**
 * Uninstall routine.
 *
 * CHỈ xoá dữ liệu khi admin đã bật "Xoá dữ liệu khi gỡ plugin" (spec §60).
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$saha_settings = get_option( 'saha_core_settings', array() );
$saha_purge    = is_array( $saha_settings ) && ! empty( $saha_settings['delete_data_on_uninstall'] );

if ( ! $saha_purge ) {
	return;
}

global $wpdb;

foreach ( array( 'quotes', 'leads', 'logs', 'search_logs' ) as $saha_table ) {
	$saha_full = $wpdb->prefix . 'saha_' . $saha_table;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tên bảng nội bộ, không phải input user.
	$wpdb->query( "DROP TABLE IF EXISTS `{$saha_full}`" );
}

delete_option( 'saha_core_settings' );
delete_option( 'saha_core_version' );
delete_option( 'saha_core_db_version' );
delete_option( 'saha_core_migrations_ran' );
delete_option( 'saha_core_flush_rewrite' );
delete_option( 'saha_brand_cache_keys' );

foreach ( array( 'saha_seo_manager', 'saha_content_manager', 'saha_sales', 'saha_warehouse' ) as $saha_role ) {
	if ( get_role( $saha_role ) ) {
		remove_role( $saha_role );
	}
}

$saha_caps = array(
	'manage_saha',
	'manage_saha_quotes',
	'manage_saha_leads',
	'manage_saha_products',
	'manage_saha_brands',
	'manage_saha_reports',
	'manage_saha_settings',
);

foreach ( array( 'administrator', 'shop_manager' ) as $saha_builtin ) {
	$saha_role_object = get_role( $saha_builtin );

	if ( ! $saha_role_object ) {
		continue;
	}

	foreach ( $saha_caps as $saha_cap ) {
		$saha_role_object->remove_cap( $saha_cap );
	}
}
