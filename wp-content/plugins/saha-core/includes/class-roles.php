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

	/** SCC: dùng SAHA Builder (spec SCC §67). */
	public const CAP_BUILDER         = 'edit_saha_builder';

	/** SCC: tạo/sửa template header, footer, điều kiện hiển thị. */
	public const CAP_TEMPLATES       = 'manage_saha_templates';

	/**
	 * Capability SCC theo role nền của WordPress.
	 *
	 * Tách khỏi all_caps() vì all_caps() còn cấp cho shop_manager — spec SCC §67
	 * chỉ cho administrator và editor dùng builder.
	 *
	 * @return array<string, string[]> role => capability
	 */
	public static function builder_caps(): array {
		/**
		 * Lọc capability SCC theo role.
		 *
		 * @param array<string, string[]> $map role => capability.
		 */
		return (array) apply_filters(
			'saha_builder_role_caps',
			array(
				'administrator' => array( self::CAP_BUILDER, self::CAP_TEMPLATES ),
				'editor'        => array( self::CAP_BUILDER ),
			)
		);
	}

	/**
	 * Option lưu hash định nghĩa role đã cài.
	 */
	public const HASH_OPTION = 'saha_core_roles_hash';

	/**
	 * Hash của toàn bộ định nghĩa role/capability hiện tại (gồm cả filter).
	 *
	 * Install::maybe_upgrade() so hash này với hash đã cài để cài lại khi định
	 * nghĩa đổi — không chỉ khi đổi version plugin. Trước đây role chỉ được cài
	 * lại lúc đổi version, nên thay đổi qua filter hoặc code sửa sau khi đã
	 * tăng version sẽ không bao giờ được áp dụng (phát hiện khi làm mốc SCC 1.1).
	 */
	public static function definitionHash(): string {
		// Chỉ phần quyền — KHÔNG gồm tên role: tên đã dịch theo ngôn ngữ người
		// dùng, nếu đưa vào hash thì admin khác ngôn ngữ sẽ làm role cài lại liên tục.
		$roles = array();

		foreach ( self::custom_roles() as $slug => $config ) {
			$roles[ $slug ] = array(
				'base' => (string) ( $config['base_role'] ?? '' ),
				'caps' => array_values( (array) ( $config['caps'] ?? array() ) ),
			);
		}

		return md5( (string) wp_json_encode( array( self::all_caps(), self::builder_caps(), $roles ) ) );
	}

	/**
	 * Định nghĩa role đã cài có khớp với code hiện tại không.
	 */
	public static function isCurrent(): bool {
		return get_option( self::HASH_OPTION ) === self::definitionHash();
	}

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
	 * Capability sản phẩm của WooCommerce.
	 *
	 * Role nền editor/author của WordPress KHÔNG có các quyền này — thiếu chúng
	 * thì role nghiệp vụ không mở được màn hình sửa sản phẩm (phát hiện khi QA
	 * trên WooCommerce thật).
	 *
	 * @param bool $can_delete Có quyền xoá / xuất bản / quản lý danh mục không.
	 * @return string[]
	 */
	public static function woocommerce_product_caps( bool $can_delete ): array {
		$caps = array(
			'read_product',
			'edit_product',
			'edit_products',
			'edit_others_products',
			'edit_published_products',
			'read_private_products',
			'assign_product_terms',
		);

		if ( $can_delete ) {
			$caps = array_merge(
				$caps,
				array(
					'publish_products',
					'edit_private_products',
					'delete_product',
					'delete_products',
					'delete_others_products',
					'delete_published_products',
					'delete_private_products',
					'manage_product_terms',
					'edit_product_terms',
					'delete_product_terms',
				)
			);
		}

		return $caps;
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
				// Sửa nội dung/SEO sản phẩm và thương hiệu, không xoá sản phẩm.
				'caps'      => array_merge(
					array( self::CAP_MANAGE, self::CAP_PRODUCTS, self::CAP_BRANDS, self::CAP_REPORTS ),
					self::woocommerce_product_caps( false ),
					array( 'manage_product_terms', 'edit_product_terms' )
				),
			),
			'saha_content_manager' => array(
				'name'      => __( 'Content Manager', 'saha-core' ),
				'base_role' => 'editor',
				'caps'      => array_merge(
					array( self::CAP_MANAGE, self::CAP_PRODUCTS, self::CAP_BRANDS ),
					self::woocommerce_product_caps( true )
				),
			),
			'saha_sales'           => array(
				'name'      => __( 'Sales', 'saha-core' ),
				'base_role' => 'author',
				'caps'      => array( self::CAP_MANAGE, self::CAP_QUOTES, self::CAP_LEADS, self::CAP_REPORTS ),
			),
			'saha_warehouse'       => array(
				'name'      => __( 'Warehouse', 'saha-core' ),
				'base_role' => 'author',
				// Kho: cập nhật tồn kho / tình trạng hàng, không xoá, không sửa danh mục.
				'caps'      => array_merge(
					array( self::CAP_MANAGE, self::CAP_PRODUCTS ),
					self::woocommerce_product_caps( false )
				),
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
		update_option( self::HASH_OPTION, self::definitionHash(), true );

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

		foreach ( self::builder_caps() as $slug => $caps ) {
			$role = get_role( (string) $slug );

			if ( ! $role ) {
				continue;
			}

			foreach ( (array) $caps as $cap ) {
				$role->add_cap( (string) $cap );
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

		foreach ( self::builder_caps() as $slug => $caps ) {
			$role = get_role( (string) $slug );

			if ( ! $role ) {
				continue;
			}

			foreach ( (array) $caps as $cap ) {
				$role->remove_cap( (string) $cap );
			}
		}
	}
}
