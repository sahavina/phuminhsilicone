<?php
/**
 * Taxonomy registration.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Taxonomies: product_brand, product_application, product_material.
 *
 * Nếu WooCommerce (hoặc plugin khác) đã đăng ký `product_brand`, ta KHÔNG đăng ký
 * lại — chỉ dùng lại taxonomy đó và bổ sung term meta ở class Brand (spec §7, §72).
 */
final class Taxonomies {

	public const BRAND       = 'product_brand';
	public const APPLICATION = 'product_application';
	public const MATERIAL    = 'product_material';

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		// Ưu tiên 11: sau khi WooCommerce đăng ký taxonomy của nó ở priority 10.
		add_action( 'init', array( $this, 'register_taxonomies' ), 11 );

		add_filter( 'register_taxonomy_args', array( $this, 'filter_native_brand_args' ), 10, 2 );
	}

	/**
	 * WooCommerce ≥ 9.6 tự đăng ký product_brand với URL /brand/.
	 *
	 * Spec §7 yêu cầu /thuong-hieu/ → đổi slug khi WooCommerce đang dùng slug
	 * mặc định. Nếu admin đã tự đặt "Brand base" khác trong Settings → Permalinks
	 * thì tôn trọng lựa chọn đó, không ghi đè.
	 *
	 * @param array<string, mixed> $args     Args taxonomy.
	 * @param string               $taxonomy Tên taxonomy.
	 * @return array<string, mixed>
	 */
	public function filter_native_brand_args( $args, $taxonomy ): array {
		$args = is_array( $args ) ? $args : array();

		if ( self::BRAND !== $taxonomy || ! isset( $args['rewrite'] ) || ! is_array( $args['rewrite'] ) ) {
			return $args;
		}

		$slug = (string) ( $args['rewrite']['slug'] ?? '' );

		/**
		 * Slug URL của trang thương hiệu.
		 *
		 * @param string $slug Slug.
		 */
		$ours = (string) apply_filters( 'saha_brand_rewrite_slug', 'thuong-hieu' );

		if ( in_array( $slug, array( '', 'brand' ), true ) ) {
			$args['rewrite']['slug']       = $ours;
			$args['rewrite']['with_front'] = false;
		}

		return $args;
	}

	/**
	 * product_brand do WooCommerce (≥ 9.6) đăng ký và quản lý?
	 *
	 * Khi đúng, WooCommerce đã có sẵn field ảnh thương hiệu (term meta
	 * `thumbnail_id`) và cột "Image" — SAHA dùng chung, không tạo field trùng.
	 */
	public static function brand_is_native(): bool {
		return class_exists( 'WC_Brands' );
	}

	/**
	 * Taxonomy nào đang bật.
	 *
	 * @return string[]
	 */
	public static function active(): array {
		$active = array( self::BRAND, self::APPLICATION );

		if ( Settings::get( 'enable_material_taxonomy', false ) ) {
			$active[] = self::MATERIAL;
		}

		return $active;
	}

	/**
	 * Đăng ký taxonomy cho post_type product.
	 */
	public function register_taxonomies(): void {
		if ( ! post_type_exists( 'product' ) ) {
			return;
		}

		if ( ! taxonomy_exists( self::BRAND ) ) {
			register_taxonomy( self::BRAND, array( 'product' ), $this->brand_args() );
		}

		if ( ! taxonomy_exists( self::APPLICATION ) ) {
			register_taxonomy( self::APPLICATION, array( 'product' ), $this->application_args() );
		}

		if ( Settings::get( 'enable_material_taxonomy', false ) && ! taxonomy_exists( self::MATERIAL ) ) {
			register_taxonomy( self::MATERIAL, array( 'product' ), $this->material_args() );
		}
	}

	/**
	 * Args taxonomy thương hiệu.
	 *
	 * @return array<string, mixed>
	 */
	private function brand_args(): array {
		$args = array(
			'labels'            => array(
				'name'              => __( 'Thương hiệu', 'saha-core' ),
				'singular_name'     => __( 'Thương hiệu', 'saha-core' ),
				'menu_name'         => __( 'Thương hiệu', 'saha-core' ),
				'all_items'         => __( 'Tất cả thương hiệu', 'saha-core' ),
				'edit_item'         => __( 'Sửa thương hiệu', 'saha-core' ),
				'view_item'         => __( 'Xem thương hiệu', 'saha-core' ),
				'add_new_item'      => __( 'Thêm thương hiệu', 'saha-core' ),
				'new_item_name'     => __( 'Tên thương hiệu mới', 'saha-core' ),
				'search_items'      => __( 'Tìm thương hiệu', 'saha-core' ),
				'not_found'         => __( 'Không có thương hiệu nào.', 'saha-core' ),
				'back_to_items'     => __( '← Về danh sách thương hiệu', 'saha-core' ),
				'parent_item'       => null,
				'parent_item_colon' => null,
			),
			'public'            => true,
			'publicly_queryable' => true,
			'hierarchical'      => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
			'show_in_quick_edit' => true,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => array(
				'slug'         => 'thuong-hieu',
				'with_front'   => false,
				'hierarchical' => false,
			),
			'capabilities'      => array(
				'manage_terms' => Roles::CAP_BRANDS,
				'edit_terms'   => Roles::CAP_BRANDS,
				'delete_terms' => Roles::CAP_BRANDS,
				'assign_terms' => Roles::CAP_PRODUCTS,
			),
		);

		/**
		 * Lọc args taxonomy thương hiệu.
		 *
		 * @param array<string, mixed> $args Args.
		 */
		return (array) apply_filters( 'saha_brand_taxonomy_args', $args );
	}

	/**
	 * Args taxonomy ứng dụng (công dụng).
	 *
	 * @return array<string, mixed>
	 */
	private function application_args(): array {
		$args = array(
			'labels'            => array(
				'name'          => __( 'Ứng dụng', 'saha-core' ),
				'singular_name' => __( 'Ứng dụng', 'saha-core' ),
				'menu_name'     => __( 'Ứng dụng', 'saha-core' ),
				'all_items'     => __( 'Tất cả ứng dụng', 'saha-core' ),
				'edit_item'     => __( 'Sửa ứng dụng', 'saha-core' ),
				'add_new_item'  => __( 'Thêm ứng dụng', 'saha-core' ),
				'search_items'  => __( 'Tìm ứng dụng', 'saha-core' ),
				'not_found'     => __( 'Không có ứng dụng nào.', 'saha-core' ),
			),
			'public'            => true,
			'hierarchical'      => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => array(
				'slug'         => 'ung-dung',
				'with_front'   => false,
				'hierarchical' => true,
			),
			'capabilities'      => array(
				'manage_terms' => Roles::CAP_PRODUCTS,
				'edit_terms'   => Roles::CAP_PRODUCTS,
				'delete_terms' => Roles::CAP_PRODUCTS,
				'assign_terms' => Roles::CAP_PRODUCTS,
			),
		);

		/**
		 * Lọc args taxonomy ứng dụng.
		 *
		 * @param array<string, mixed> $args Args.
		 */
		return (array) apply_filters( 'saha_application_taxonomy_args', $args );
	}

	/**
	 * Args taxonomy vật liệu — chỉ bật khi dùng để filter (spec §69).
	 *
	 * @return array<string, mixed>
	 */
	private function material_args(): array {
		$args = array(
			'labels'            => array(
				'name'          => __( 'Vật liệu', 'saha-core' ),
				'singular_name' => __( 'Vật liệu', 'saha-core' ),
				'menu_name'     => __( 'Vật liệu', 'saha-core' ),
				'all_items'     => __( 'Tất cả vật liệu', 'saha-core' ),
				'add_new_item'  => __( 'Thêm vật liệu', 'saha-core' ),
				'not_found'     => __( 'Không có vật liệu nào.', 'saha-core' ),
			),
			'public'            => true,
			'hierarchical'      => false,
			'show_ui'           => true,
			'show_admin_column' => false,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => array(
				'slug'       => 'vat-lieu',
				'with_front' => false,
			),
			'capabilities'      => array(
				'manage_terms' => Roles::CAP_PRODUCTS,
				'edit_terms'   => Roles::CAP_PRODUCTS,
				'delete_terms' => Roles::CAP_PRODUCTS,
				'assign_terms' => Roles::CAP_PRODUCTS,
			),
		);

		/**
		 * Lọc args taxonomy vật liệu.
		 *
		 * @param array<string, mixed> $args Args.
		 */
		return (array) apply_filters( 'saha_material_taxonomy_args', $args );
	}
}
