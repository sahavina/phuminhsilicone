<?php
/**
 * Product filter — chạy server-side qua pre_get_posts.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Filter: lọc archive sản phẩm theo brand, ứng dụng, tình trạng, giá, attribute.
 *
 * Nguyên tắc (spec §10): filter là **server-side trước**, AJAX chỉ là lớp tăng
 * cường. State luôn nằm trên URL nên copy link và SEO hoạt động đúng.
 */
final class Filter {

	/**
	 * Query var được phép, map sang cách xử lý.
	 *
	 * @return array<string, string>
	 */
	public static function allowed_vars(): array {
		$vars = array(
			'saha_brand'       => 'taxonomy',
			'saha_application' => 'taxonomy',
			'saha_material'    => 'taxonomy',
			'saha_availability' => 'availability',
			'saha_min_price'   => 'price',
			'saha_max_price'   => 'price',
			'saha_attr'        => 'attribute',
		);

		/**
		 * Lọc danh sách query var được phép.
		 *
		 * @param array<string, string> $vars Map var => kiểu xử lý.
		 */
		return (array) apply_filters( 'saha_filter_allowed_vars', $vars );
	}

	/**
	 * Taxonomy tương ứng với mỗi query var.
	 *
	 * @return array<string, string>
	 */
	private static function taxonomy_map(): array {
		return array(
			'saha_brand'       => Taxonomies::BRAND,
			'saha_application' => Taxonomies::APPLICATION,
			'saha_material'    => Taxonomies::MATERIAL,
		);
	}

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'pre_get_posts', array( $this, 'apply' ), 20 );
	}

	/**
	 * Đăng ký query var để WordPress giữ trên URL.
	 *
	 * @param string[] $vars Query var hiện có.
	 * @return string[]
	 */
	public function register_query_vars( array $vars ): array {
		foreach ( array_keys( self::allowed_vars() ) as $var ) {
			$vars[] = $var;
		}

		return $vars;
	}

	/**
	 * Đọc filter hiện tại từ request, đã sanitize.
	 *
	 * @return array<string, mixed>
	 */
	public static function current(): array {
		$out = array();

		foreach ( self::allowed_vars() as $var => $type ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filter công khai, chỉ đọc.
			$raw = $_GET[ $var ] ?? null;

			if ( null === $raw ) {
				continue;
			}

			$value = self::sanitize_var( wp_unslash( $raw ), $type );

			if ( '' === $value || array() === $value ) {
				continue;
			}

			$out[ $var ] = $value;
		}

		return $out;
	}

	/**
	 * Sanitize một giá trị filter.
	 *
	 * @param mixed  $raw  Giá trị thô.
	 * @param string $type Kiểu xử lý.
	 * @return mixed
	 */
	private static function sanitize_var( $raw, string $type ) {
		if ( 'price' === $type ) {
			$value = is_scalar( $raw ) ? (string) $raw : '';

			return '' === $value ? '' : (string) max( 0, (float) $value );
		}

		if ( 'attribute' === $type ) {
			if ( ! is_array( $raw ) ) {
				return array();
			}

			$out = array();

			foreach ( $raw as $attribute => $values ) {
				$taxonomy = 'pa_' . sanitize_key( (string) $attribute );

				if ( ! taxonomy_exists( $taxonomy ) ) {
					continue;
				}

				$slugs = is_array( $values ) ? $values : explode( ',', (string) $values );
				$slugs = array_values( array_filter( array_map( 'sanitize_title', $slugs ) ) );

				if ( $slugs ) {
					$out[ $taxonomy ] = array_slice( $slugs, 0, 20 );
				}
			}

			return $out;
		}

		if ( 'availability' === $type ) {
			$value   = sanitize_key( (string) $raw );
			$allowed = array_keys( Product::availability_options() );

			return in_array( $value, $allowed, true ) ? $value : '';
		}

		// taxonomy: nhận slug, cho phép nhiều slug ngăn cách bởi dấu phẩy.
		$slugs = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
		$slugs = array_values( array_filter( array_map( 'sanitize_title', $slugs ) ) );

		return array_slice( $slugs, 0, 20 );
	}

	/**
	 * Áp filter vào main query của archive sản phẩm.
	 *
	 * @param \WP_Query $query Query.
	 */
	public function apply( \WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( ! self::is_product_archive( $query ) ) {
			return;
		}

		$filters = self::current();

		if ( ! $filters ) {
			return;
		}

		$this->apply_taxonomies( $query, $filters );
		$this->apply_availability( $query, $filters );
		$this->apply_price( $query, $filters );

		/**
		 * Vừa áp filter vào query.
		 *
		 * @param \WP_Query            $query   Query.
		 * @param array<string, mixed> $filters Filter đang áp dụng.
		 */
		do_action( 'saha_filter_applied', $query, $filters );
	}

	/**
	 * Query hiện tại có phải archive sản phẩm không.
	 *
	 * @param \WP_Query $query Query.
	 */
	private static function is_product_archive( \WP_Query $query ): bool {
		if ( $query->is_post_type_archive( 'product' ) || $query->is_search() ) {
			return true;
		}

		$taxonomies = array_merge( array( 'product_cat', 'product_tag' ), Taxonomies::active() );

		foreach ( $taxonomies as $taxonomy ) {
			if ( $query->is_tax( $taxonomy ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Thêm tax_query cho brand/application/material/attribute.
	 *
	 * @param \WP_Query            $query   Query.
	 * @param array<string, mixed> $filters Filter.
	 */
	private function apply_taxonomies( \WP_Query $query, array $filters ): void {
		$tax_query = (array) $query->get( 'tax_query', array() );
		$added     = false;

		foreach ( self::taxonomy_map() as $var => $taxonomy ) {
			if ( empty( $filters[ $var ] ) || ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$tax_query[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => (array) $filters[ $var ],
				'operator' => 'IN',
			);

			$added = true;
		}

		foreach ( (array) ( $filters['saha_attr'] ?? array() ) as $taxonomy => $slugs ) {
			$tax_query[] = array(
				'taxonomy' => (string) $taxonomy,
				'field'    => 'slug',
				'terms'    => (array) $slugs,
				'operator' => 'IN',
			);

			$added = true;
		}

		if ( ! $added ) {
			return;
		}

		if ( count( $tax_query ) > 1 && ! isset( $tax_query['relation'] ) ) {
			$tax_query['relation'] = 'AND';
		}

		$query->set( 'tax_query', $tax_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- filter archive, có pagination.
	}

	/**
	 * Lọc theo tình trạng hàng.
	 *
	 * Ưu tiên field tuỳ biến `_saha_availability`; nếu sản phẩm để trống thì
	 * so với `_stock_status` của WooCommerce (spec §66).
	 *
	 * @param \WP_Query            $query   Query.
	 * @param array<string, mixed> $filters Filter.
	 */
	private function apply_availability( \WP_Query $query, array $filters ): void {
		$value = (string) ( $filters['saha_availability'] ?? '' );

		if ( '' === $value ) {
			return;
		}

		$meta_query = (array) $query->get( 'meta_query', array() );

		$stock_status = 'out' === $value ? 'outofstock' : 'instock';

		$meta_query[] = array(
			'relation' => 'OR',
			array(
				'key'     => '_saha_availability',
				'value'   => $value,
				'compare' => '=',
			),
			array(
				'relation' => 'AND',
				array(
					'key'     => '_saha_availability',
					'value'   => '',
					'compare' => '=',
				),
				array(
					'key'     => '_stock_status',
					'value'   => $stock_status,
					'compare' => '=',
				),
			),
		);

		$query->set( 'meta_query', $meta_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- chỉ khi người dùng chủ động lọc.
	}

	/**
	 * Lọc theo khoảng giá.
	 *
	 * @param \WP_Query            $query   Query.
	 * @param array<string, mixed> $filters Filter.
	 */
	private function apply_price( \WP_Query $query, array $filters ): void {
		$min = $filters['saha_min_price'] ?? '';
		$max = $filters['saha_max_price'] ?? '';

		if ( '' === $min && '' === $max ) {
			return;
		}

		$meta_query = (array) $query->get( 'meta_query', array() );

		if ( '' !== $min && '' !== $max ) {
			$meta_query[] = array(
				'key'     => '_price',
				'value'   => array( (float) $min, (float) $max ),
				'type'    => 'DECIMAL(10,2)',
				'compare' => 'BETWEEN',
			);
		} elseif ( '' !== $min ) {
			$meta_query[] = array(
				'key'     => '_price',
				'value'   => (float) $min,
				'type'    => 'DECIMAL(10,2)',
				'compare' => '>=',
			);
		} else {
			$meta_query[] = array(
				'key'     => '_price',
				'value'   => (float) $max,
				'type'    => 'DECIMAL(10,2)',
				'compare' => '<=',
			);
		}

		$query->set( 'meta_query', $meta_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- chỉ khi người dùng chủ động lọc.
	}

	/**
	 * Sinh URL đã áp một filter, giữ nguyên các filter khác (spec §10).
	 *
	 * @param string $var   Query var.
	 * @param string $value Giá trị (slug). Rỗng để bỏ filter.
	 * @param string $base  URL gốc, mặc định là URL hiện tại.
	 */
	public static function build_url( string $var, string $value, string $base = '' ): string {
		$allowed = self::allowed_vars();

		if ( ! isset( $allowed[ $var ] ) ) {
			return $base;
		}

		$current = self::current();

		if ( '' === $value ) {
			unset( $current[ $var ] );
		} else {
			$current[ $var ] = $value;
		}

		$args = array();

		foreach ( $current as $key => $val ) {
			$args[ $key ] = is_array( $val ) ? implode( ',', array_map( 'strval', $val ) ) : (string) $val;
		}

		$base = '' !== $base ? $base : self::current_base_url();

		return $args ? add_query_arg( $args, $base ) : $base;
	}

	/**
	 * URL archive hiện tại, đã bỏ mọi query var filter và phân trang.
	 */
	public static function current_base_url(): string {
		$object = get_queried_object();

		if ( $object instanceof \WP_Term ) {
			$link = get_term_link( $object );

			if ( ! is_wp_error( $link ) ) {
				return $link;
			}
		}

		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$shop = wc_get_page_permalink( 'shop' );

			if ( $shop ) {
				return $shop;
			}
		}

		return home_url( '/' );
	}
}
