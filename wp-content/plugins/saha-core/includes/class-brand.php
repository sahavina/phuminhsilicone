<?php
/**
 * Brand term meta + brand service.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Brand: quản lý term meta của `product_brand` và cung cấp dữ liệu cho frontend.
 *
 * Child theme không đọc term meta trực tiếp — dùng saha_get_brand() / saha_get_brands().
 */
final class Brand {

	/**
	 * Prefix term meta.
	 */
	private const META_PREFIX = 'saha_brand_';

	/**
	 * Nonce action khi lưu term.
	 */
	private const NONCE_ACTION = 'saha_save_brand_meta';

	/**
	 * Nonce field.
	 */
	private const NONCE_FIELD = 'saha_brand_nonce';

	/**
	 * Schema term meta: key => [ type, label, description ].
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function meta_schema(): array {
		return array(
			'logo_id'           => array(
				'type'  => 'attachment',
				'label' => __( 'Logo', 'saha-core' ),
			),
			'banner_id'         => array(
				'type'  => 'attachment',
				'label' => __( 'Banner', 'saha-core' ),
			),
			'short_description' => array(
				'type'  => 'textarea',
				'label' => __( 'Mô tả ngắn', 'saha-core' ),
			),
			'seo_content'       => array(
				'type'        => 'html',
				'label'       => __( 'Nội dung SEO', 'saha-core' ),
				'description' => __( 'Hiển thị dưới danh sách sản phẩm của trang thương hiệu.', 'saha-core' ),
			),
			'website'           => array(
				'type'  => 'url',
				'label' => __( 'Website thương hiệu', 'saha-core' ),
			),
			'country'           => array(
				'type'  => 'text',
				'label' => __( 'Xuất xứ', 'saha-core' ),
			),
			'seo_title'         => array(
				'type'        => 'text',
				'label'       => __( 'SEO title', 'saha-core' ),
				'description' => __( 'Để trống sẽ dùng tiêu đề do plugin SEO sinh ra.', 'saha-core' ),
			),
			'meta_description'  => array(
				'type'  => 'textarea',
				'label' => __( 'Meta description', 'saha-core' ),
			),
		);
	}

	/**
	 * Gắn hook admin + cache invalidation.
	 */
	public function register(): void {
		$taxonomy = Taxonomies::BRAND;

		add_action( $taxonomy . '_add_form_fields', array( $this, 'render_add_fields' ) );
		add_action( $taxonomy . '_edit_form_fields', array( $this, 'render_edit_fields' ), 10, 2 );
		add_action( 'created_' . $taxonomy, array( $this, 'save_meta' ) );
		add_action( 'edited_' . $taxonomy, array( $this, 'save_meta' ) );

		add_filter( 'manage_edit-' . $taxonomy . '_columns', array( $this, 'add_logo_column' ) );
		add_filter( 'manage_' . $taxonomy . '_custom_column', array( $this, 'render_logo_column' ), 10, 3 );

		// Cache invalidation do Cache đảm nhiệm (spec §80).

		add_filter( 'saha_core_dashboard_stats', array( $this, 'add_dashboard_stat' ) );
	}

	/**
	 * Số thương hiệu đang có sản phẩm, hiển thị ở dashboard.
	 *
	 * @param array<int, array<string, mixed>> $stats Số liệu hiện có.
	 * @return array<int, array<string, mixed>>
	 */
	public function add_dashboard_stat( array $stats ): array {
		if ( ! taxonomy_exists( Taxonomies::BRAND ) ) {
			return $stats;
		}

		$count = wp_count_terms(
			array(
				'taxonomy'   => Taxonomies::BRAND,
				'hide_empty' => true,
			)
		);

		$stats[] = array(
			'label' => __( 'Thương hiệu có sản phẩm', 'saha-core' ),
			'value' => is_wp_error( $count ) ? 0 : (int) $count,
		);

		return $stats;
	}

	/*
	 * ---------------------------------------------------------------------
	 * Service API
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Toàn bộ meta của một thương hiệu, đã sanitize sẵn để render.
	 *
	 * @param int $term_id Term ID.
	 * @return array<string, mixed>
	 */
	public static function get_meta( int $term_id ): array {
		$out = array();

		foreach ( self::meta_schema() as $key => $field ) {
			$raw = get_term_meta( $term_id, self::META_PREFIX . $key, true );

			if ( 'attachment' === ( $field['type'] ?? '' ) ) {
				$out[ $key ] = (int) $raw;
				continue;
			}

			$out[ $key ] = is_string( $raw ) ? $raw : '';
		}

		return $out;
	}

	/**
	 * Dữ liệu một thương hiệu để render card/header.
	 *
	 * @param int|string|\WP_Term $brand Term ID, slug hoặc WP_Term.
	 * @return array<string, mixed> Rỗng nếu không tìm thấy.
	 */
	public static function get( $brand ): array {
		$term = self::resolve_term( $brand );

		if ( ! $term ) {
			return array();
		}

		$meta = self::get_meta( $term->term_id );
		$link = get_term_link( $term );

		$data = array(
			'id'                => $term->term_id,
			'name'              => $term->name,
			'slug'              => $term->slug,
			'url'               => is_wp_error( $link ) ? '' : $link,
			'count'             => (int) $term->count,
			'description'       => $term->description,
			'logo_id'           => $meta['logo_id'],
			'banner_id'         => $meta['banner_id'],
			'short_description' => $meta['short_description'],
			'seo_content'       => $meta['seo_content'],
			'website'           => $meta['website'],
			'country'           => $meta['country'],
			'seo_title'         => $meta['seo_title'],
			'meta_description'  => $meta['meta_description'],
		);

		/**
		 * Lọc dữ liệu thương hiệu trước khi render.
		 *
		 * @param array<string, mixed> $data Dữ liệu.
		 * @param \WP_Term             $term Term.
		 */
		return (array) apply_filters( 'saha_brand_data', $data, $term );
	}

	/**
	 * Danh sách thương hiệu, có cache.
	 *
	 * @param array<string, mixed> $args orderby, order, number, hide_empty, include, parent-agnostic.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_all( array $args = array() ): array {
		if ( ! taxonomy_exists( Taxonomies::BRAND ) ) {
			return array();
		}

		$args = wp_parse_args(
			$args,
			array(
				'orderby'    => 'name',
				'order'      => 'ASC',
				'number'     => 0,
				'hide_empty' => true,
				'include'    => array(),
			)
		);

		return (array) Cache::remember(
			'brands',
			$args,
			static fn(): array => self::query_all( $args )
		);
	}

	/**
	 * Truy vấn danh sách thương hiệu.
	 *
	 * @param array<string, mixed> $args Tham số đã merge mặc định.
	 * @return array<int, array<string, mixed>>
	 */
	private static function query_all( array $args ): array {
		$query = array(
			'taxonomy'   => Taxonomies::BRAND,
			'orderby'    => in_array( $args['orderby'], array( 'name', 'count', 'slug', 'term_order' ), true ) ? $args['orderby'] : 'name',
			'order'      => 'DESC' === strtoupper( (string) $args['order'] ) ? 'DESC' : 'ASC',
			'hide_empty' => (bool) $args['hide_empty'],
		);

		if ( (int) $args['number'] > 0 ) {
			$query['number'] = min( 200, (int) $args['number'] );
		}

		if ( $args['include'] ) {
			$query['include'] = array_map( 'absint', (array) $args['include'] );
		}

		$terms = get_terms( $query );

		if ( is_wp_error( $terms ) || ! $terms ) {
			return array();
		}

		$out = array();

		foreach ( $terms as $term ) {
			if ( $term instanceof \WP_Term ) {
				$out[] = self::get( $term );
			}
		}

		return $out;
	}

	/**
	 * Thương hiệu của một sản phẩm (lấy term đầu tiên).
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, mixed> Rỗng nếu sản phẩm không gắn thương hiệu.
	 */
	public static function get_for_product( int $product_id ): array {
		if ( ! taxonomy_exists( Taxonomies::BRAND ) ) {
			return array();
		}

		$terms = get_the_terms( $product_id, Taxonomies::BRAND );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return array();
		}

		return self::get( $terms[0] );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Admin UI
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Form thêm term mới.
	 */
	public function render_add_fields(): void {
		if ( ! current_user_can( Roles::CAP_BRANDS ) ) {
			return;
		}

		$schema = self::meta_schema();
		$values = array_fill_keys( array_keys( $schema ), '' );
		$mode   = 'add';

		require SAHA_CORE_PATH . 'admin/views/brand-fields.php';
	}

	/**
	 * Form sửa term.
	 *
	 * @param \WP_Term $term     Term đang sửa.
	 * @param string   $taxonomy Taxonomy.
	 */
	public function render_edit_fields( \WP_Term $term, string $taxonomy ): void {
		unset( $taxonomy );

		if ( ! current_user_can( Roles::CAP_BRANDS ) ) {
			return;
		}

		$schema = self::meta_schema();
		$values = self::get_meta( $term->term_id );
		$mode   = 'edit';

		require SAHA_CORE_PATH . 'admin/views/brand-fields.php';
	}

	/**
	 * Lưu term meta. Capability + nonce (spec §74).
	 *
	 * @param int $term_id Term ID.
	 */
	public function save_meta( int $term_id ): void {
		if ( ! Security::guard_admin_action( Roles::CAP_BRANDS, self::NONCE_ACTION, self::NONCE_FIELD ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- đã verify ở guard_admin_action().
		$bag = isset( $_POST['saha_brand'] ) && is_array( $_POST['saha_brand'] )
			? (array) wp_unslash( $_POST['saha_brand'] )
			: array();

		foreach ( self::meta_schema() as $key => $field ) {
			$type = (string) ( $field['type'] ?? 'text' );

			if ( ! array_key_exists( $key, $bag ) ) {
				continue;
			}

			$meta_key = self::META_PREFIX . $key;

			if ( 'attachment' === $type ) {
				$attachment_id = absint( $bag[ $key ] );

				// Không tin ID từ form: phải là attachment thật.
				if ( $attachment_id > 0 && 'attachment' !== get_post_type( $attachment_id ) ) {
					$attachment_id = 0;
				}

				if ( 0 === $attachment_id ) {
					delete_term_meta( $term_id, $meta_key );
					continue;
				}

				update_term_meta( $term_id, $meta_key, $attachment_id );
				continue;
			}

			$value = Security::sanitize_by_type( $bag[ $key ], $type );

			if ( '' === $value ) {
				delete_term_meta( $term_id, $meta_key );
				continue;
			}

			update_term_meta( $term_id, $meta_key, $value );
		}

		self::flush_cache();

		/**
		 * Term meta thương hiệu vừa được lưu.
		 *
		 * @param int $term_id Term ID.
		 */
		do_action( 'saha_brand_saved', $term_id );
	}

	/**
	 * Thêm cột logo vào danh sách term.
	 *
	 * @param array<string, string> $columns Cột hiện có.
	 * @return array<string, string>
	 */
	public function add_logo_column( array $columns ): array {
		$out = array();

		foreach ( $columns as $key => $label ) {
			if ( 'name' === $key ) {
				$out['saha_logo'] = __( 'Logo', 'saha-core' );
			}

			$out[ $key ] = $label;
		}

		return $out;
	}

	/**
	 * Render cột logo.
	 *
	 * @param string $content Nội dung hiện tại.
	 * @param string $column  Cột.
	 * @param int    $term_id Term ID.
	 */
	public function render_logo_column( string $content, string $column, int $term_id ): string {
		if ( 'saha_logo' !== $column ) {
			return $content;
		}

		$logo_id = (int) get_term_meta( $term_id, self::META_PREFIX . 'logo_id', true );

		if ( $logo_id <= 0 ) {
			return '—';
		}

		return wp_get_attachment_image( $logo_id, array( 48, 48 ), false, array( 'style' => 'max-width:48px;height:auto;' ) );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Cache
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Vô hiệu cache thương hiệu.
	 *
	 * Giữ lại tên hàm cũ để code/add-on đang gọi không vỡ; nay chỉ chuyển sang Cache.
	 */
	public static function flush_cache(): void {
		Cache::bump();
	}

	/**
	 * Chuẩn hoá tham số về WP_Term.
	 *
	 * @param int|string|\WP_Term $brand Term ID, slug hoặc WP_Term.
	 */
	private static function resolve_term( $brand ): ?\WP_Term {
		if ( $brand instanceof \WP_Term ) {
			return $brand;
		}

		if ( ! taxonomy_exists( Taxonomies::BRAND ) ) {
			return null;
		}

		$term = is_numeric( $brand )
			? get_term( (int) $brand, Taxonomies::BRAND )
			: get_term_by( 'slug', (string) $brand, Taxonomies::BRAND );

		return $term instanceof \WP_Term ? $term : null;
	}

	/**
	 * Nonce field cho view.
	 */
	public static function nonce_field(): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
	}
}
