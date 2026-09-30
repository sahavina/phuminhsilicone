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

// Cron luôn được huỷ khi gỡ plugin, kể cả khi giữ lại dữ liệu.
wp_clear_scheduled_hook( 'saha_core_daily_maintenance' );

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
delete_option( 'saha_catalog_cache_gen' );
delete_option( 'saha_cache_gen' );
delete_option( 'saha_theme_options' );
delete_option( 'saha_theme_css' );

// Layout builder (post meta). post_content dự phòng giữ lại — trang vẫn còn nội dung tĩnh.
foreach ( array( '_saha_builder_enabled', '_saha_builder_data', '_saha_builder_version', '_saha_builder_hash', '_saha_css_file' ) as $saha_meta_key ) {
	delete_metadata( 'post', 0, $saha_meta_key, '', true );
}

// File CSS sinh ra trong uploads/saha/css.
$saha_uploads = wp_upload_dir( null, false );

if ( empty( $saha_uploads['error'] ) ) {
	foreach ( (array) glob( trailingslashit( $saha_uploads['basedir'] ) . 'saha/css/*.css' ) as $saha_css_file ) {
		if ( is_string( $saha_css_file ) ) {
			wp_delete_file( $saha_css_file );
		}
	}
}

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

// Capability SCC (Roles::builder_caps) trên role nền của WordPress.
foreach ( array( 'administrator', 'editor' ) as $saha_builtin ) {
	$saha_role_object = get_role( $saha_builtin );

	if ( $saha_role_object ) {
		$saha_role_object->remove_cap( 'edit_saha_builder' );
		$saha_role_object->remove_cap( 'manage_saha_templates' );
	}
}

foreach ( array( 'administrator', 'shop_manager' ) as $saha_builtin ) {
	$saha_role_object = get_role( $saha_builtin );

	if ( ! $saha_role_object ) {
		continue;
	}

	foreach ( $saha_caps as $saha_cap ) {
		$saha_role_object->remove_cap( $saha_cap );
	}
}
