<?php
/**
 * Roles & capabilities.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Roles: tạo role nghiệp vụ và gán capability `manage_saha*`.
 *
 * Kiểm tra quyền ở mọi nơi phải dùng current_user_can() với capability,
 * không so sánh tên role (spec §15).
 */
final class Roles {

	public const CAP_MANAGE          = 'manage_saha';
	public const CAP_QUOTES          = 'manage_saha_quotes';
	public const CAP_LEADS           = 'manage_saha_leads';
	public const CAP_PRODUCTS        = 'manage_saha_products';
	public const CAP_BRANDS          = 'manage_saha_brands';
	public const CAP_REPORTS         = 'manage_saha_reports';
	public const CAP_SETTINGS        = 'manage_saha_settings';

	/**
	 * Toàn bộ capability của hệ thống.
	 *
	 * @return string[]
	 */
	public static function all_caps(): array {
		return array(
			self::CAP_MANAGE,
			self::CAP_QUOTES,
			self::CAP_LEADS,
			self::CAP_PRODUCTS,
			self::CAP_BRANDS,
			self::CAP_REPORTS,
			self::CAP_SETTINGS,
		);
	}

	/**
	 * Role tuỳ biến: slug => [ name, base_role, caps ].
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function custom_roles(): array {
		$roles = array(
			'saha_seo_manager'     => array(
				'name'      => __( 'SEO Manager', 'saha-core' ),
				'base_role' => 'editor',
				'caps'      => array( self::CAP_MANAGE, self::CAP_PRODUCTS, self::CAP_BRANDS, self::CAP_REPORTS ),
			),
			'saha_content_manager' => array(
				'name'      => __( 'Content Manager', 'saha-core' ),
				'base_role' => 'editor',
				'caps'      => array( self::CAP_MANAGE, self::CAP_PRODUCTS, self::CAP_BRANDS ),
			),
			'saha_sales'           => array(
				'name'      => __( 'Sales', 'saha-core' ),
				'base_role' => 'author',
				'caps'      => array( self::CAP_MANAGE, self::CAP_QUOTES, self::CAP_LEADS, self::CAP_REPORTS ),
			),
			'saha_warehouse'       => array(
				'name'      => __( 'Warehouse', 'saha-core' ),
				'base_role' => 'author',
				'caps'      => array( self::CAP_MANAGE, self::CAP_PRODUCTS ),
			),
		);

		/**
		 * Cho phép chỉnh danh sách role.
		 *
		 * @param array<string, array<string, mixed>> $roles Role definition.
		 */
		return (array) apply_filters( 'saha_core_roles', $roles );
	}

	/**
	 * Không cần hook runtime ở phase 1.
	 */
	public function register(): void {}

	/**
	 * Tạo role và gán capability. Idempotent.
	 */
	public function install(): void {
		$this->grant_admin_caps();

		foreach ( self::custom_roles() as $slug => $config ) {
			$base  = get_role( (string) ( $config['base_role'] ?? 'author' ) );
			$caps  = $base ? $base->capabilities : array( 'read' => true );
			$role  = get_role( $slug );
			$extra = (array) ( $config['caps'] ?? array() );

			foreach ( $extra as $cap ) {
				$caps[ $cap ] = true;
			}

			if ( ! $role ) {
				add_role( $slug, (string) $config['name'], $caps );
				continue;
			}

			foreach ( $extra as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Administrator luôn có toàn bộ capability.
	 */
	private function grant_admin_caps(): void {
		$admin = get_role( 'administrator' );

		if ( ! $admin ) {
			return;
		}

		foreach ( self::all_caps() as $cap ) {
			$admin->add_cap( $cap );
		}

		$shop_manager = get_role( 'shop_manager' );

		if ( $shop_manager ) {
			foreach ( self::all_caps() as $cap ) {
				$shop_manager->add_cap( $cap );
			}
		}
	}

	/**
	 * Xoá role tuỳ biến khi deactivate. Không xoá user, không xoá dữ liệu.
	 */
	public function remove_roles(): void {
		foreach ( array_keys( self::custom_roles() ) as $slug ) {
			if ( get_role( $slug ) ) {
				remove_role( $slug );
			}
		}
	}

	/**
	 * Xoá capability khỏi các role built-in (dùng khi uninstall).
	 */
	public function remove_caps(): void {
		foreach ( array( 'administrator', 'shop_manager' ) as $slug ) {
			$role = get_role( $slug );

			if ( ! $role ) {
				continue;
			}

			foreach ( self::all_caps() as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}
}
