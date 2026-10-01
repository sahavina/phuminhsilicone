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
			'saha_price'       => 'price_range',
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

		if ( 'price_range' === $type ) {
			return self::sanitize_price_range( $raw );
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

		$meta_query[] = self::availability_clause( $value );

		$query->set( 'meta_query', $meta_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- chỉ khi người dùng chủ động lọc.
	}

	/**
	 * meta_query cho một giá trị tình trạng hàng.
	 *
	 * - Khớp field SAHA `_saha_availability` nếu sản phẩm có đặt.
	 * - Sản phẩm KHÔNG đặt field (không có dòng meta — Product::save xoá meta
	 *   rỗng) thì rơi về `_stock_status` của WooCommerce. Phải dùng NOT EXISTS:
	 *   so sánh `= ''` không bao giờ khớp vì dòng meta không tồn tại.
	 * - "Liên hệ" không có trạng thái tương ứng trong WooCommerce → không fallback,
	 *   nếu không sẽ gộp nhầm mọi sản phẩm còn hàng.
	 *
	 * Cả hai lỗi trên được phát hiện khi QA trên WooCommerce thật.
	 *
	 * @param string $value in_stock | contact | out.
	 * @return array<string|int, mixed>
	 */
	public static function availability_clause( string $value ): array {
		$explicit = array(
			'key'     => '_saha_availability',
			'value'   => $value,
			'compare' => '=',
		);

		$stock_map = array(
			'in_stock' => 'instock',
			'out'      => 'outofstock',
		);

		if ( ! isset( $stock_map[ $value ] ) ) {
			return $explicit;
		}

		return array(
			'relation' => 'OR',
			$explicit,
			array(
				'relation' => 'AND',
				array(
					'relation' => 'OR',
					array(
						'key'     => '_saha_availability',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_saha_availability',
						'value'   => '',
						'compare' => '=',
					),
				),
				array(
					'key'     => '_stock_status',
					'value'   => $stock_map[ $value ],
					'compare' => '=',
				),
			),
		);
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

		// Khoảng chọn sẵn "min-max" (cột lọc kiểu cửa hàng); ô Từ/Đến nhập tay được ưu tiên.
		if ( '' === $min && '' === $max && ! empty( $filters['saha_price'] ) ) {
			list( $min, $max ) = explode( '-', (string) $filters['saha_price'], 2 );
		}

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
	 * Chuẩn hoá khoảng giá "min-max" ("-3000000", "3000000-6000000", "10000000-").
	 *
	 * @param mixed $raw Giá trị thô.
	 */
	private static function sanitize_price_range( $raw ): string {
		if ( ! is_scalar( $raw ) || ! preg_match( '/^\s*(\d{0,12})\s*-\s*(\d{0,12})\s*$/', (string) $raw, $m ) ) {
			return '';
		}

		if ( '' === $m[1] && '' === $m[2] ) {
			return '';
		}

		$min = '' === $m[1] ? '' : (string) (int) $m[1];
		$max = '' === $m[2] ? '' : (string) (int) $m[2];

		if ( '' !== $min && '' !== $max && (int) $min > (int) $max ) {
			list( $min, $max ) = array( $max, $min );
		}

		return $min . '-' . $max;
	}

	/**
	 * Các khoảng giá gợi ý cho cột lọc, tính từ giá thật của sản phẩm trong danh mục
	 * (gồm danh mục con): mốc ở phân vị 25/50/75%, làm tròn số đẹp (1; 1,5; 2; 2,5; 3; 4; 5; 6; 8 × 10ⁿ).
	 *
	 * Rỗng khi ít hơn 2 mức giá khác nhau. Cache theo phiên bản dữ liệu sản phẩm của WooCommerce.
	 *
	 * @param \WP_Term|null $term Danh mục / thương hiệu đang xem; null = toàn shop.
	 * @return array<int, array{value: string, min: int, max: int}> max = 0: không giới hạn trên.
	 */
	public static function price_ranges( ?\WP_Term $term = null ): array {
		global $wpdb;

		if ( ! isset( $wpdb->wc_product_meta_lookup ) ) {
			return array();
		}

		$term_ids = array();

		if ( $term instanceof \WP_Term ) {
			$children = get_term_children( $term->term_id, $term->taxonomy );
			$term_ids = array_map( 'intval', array_merge( array( $term->term_id ), is_array( $children ) ? $children : array() ) );
		}

		$version = class_exists( '\WC_Cache_Helper' ) ? \WC_Cache_Helper::get_transient_version( 'product' ) : '';
		$key     = 'saha_price_ranges_' . md5( implode( ',', $term_ids ) . '|' . $version );
		$cached  = get_transient( $key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$join  = '';
		$where = '';

		if ( $term_ids ) {
			$join  = " INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = l.product_id INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id";
			$where = ' AND tt.term_id IN (' . implode( ',', $term_ids ) . ')';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- bảng lookup của WooCommerce; ID đã ép int; có cache transient.
		$prices = $wpdb->get_col( "SELECT DISTINCT l.product_id, l.min_price FROM {$wpdb->wc_product_meta_lookup} l INNER JOIN {$wpdb->posts} p ON p.ID = l.product_id AND p.post_type = 'product' AND p.post_status = 'publish'{$join} WHERE l.min_price > 0{$where} LIMIT 2000", 1 );

		/**
		 * Lọc khoảng giá gợi ý (ví dụ đặt mốc cố định).
		 *
		 * @param array<int, array{value: string, min: int, max: int}> $ranges Khoảng giá.
		 * @param \WP_Term|null                                         $term   Danh mục đang xem.
		 */
		$ranges = (array) apply_filters( 'saha_filter_price_ranges', self::buckets( array_map( 'floatval', (array) $prices ) ), $term );

		set_transient( $key, $ranges, DAY_IN_SECONDS );

		return $ranges;
	}

	/**
	 * Chia danh sách giá thành các khoảng có mốc tròn.
	 *
	 * @param float[] $prices Giá (> 0).
	 * @return array<int, array{value: string, min: int, max: int}>
	 */
	public static function buckets( array $prices ): array {
		$prices = array_values( array_filter( $prices, static fn( $p ): bool => $p > 0 ) );
		sort( $prices );

		if ( count( array_unique( $prices ) ) < 2 ) {
			return array();
		}

		$low   = $prices[0];
		$high  = $prices[ count( $prices ) - 1 ];
		$edges = array();

		foreach ( array( 0.25, 0.5, 0.75 ) as $q ) {
			$edge = self::nice( $prices[ (int) floor( $q * ( count( $prices ) - 1 ) ) ] );

			if ( $edge > $low && $edge < $high && ! in_array( $edge, $edges, true ) ) {
				$edges[] = $edge;
			}
		}

		if ( ! $edges ) {
			$edge = self::nice( ( $low + $high ) / 2 );

			if ( $edge <= $low || $edge >= $high ) {
				return array();
			}

			$edges[] = $edge;
		}

		sort( $edges );

		$ranges = array();
		$from   = 0;

		foreach ( $edges as $edge ) {
			$ranges[] = array(
				'value' => ( $from > 0 ? (string) $from : '' ) . '-' . $edge,
				'min'   => $from,
				'max'   => $edge,
			);
			$from     = $edge;
		}

		$ranges[] = array(
			'value' => $from . '-',
			'min'   => $from,
			'max'   => 0,
		);

		return $ranges;
	}

	/**
	 * Làm tròn về số "đẹp" gần nhất.
	 *
	 * @param float $value Giá trị.
	 */
	private static function nice( float $value ): int {
		if ( $value <= 0 ) {
			return 0;
		}

		$magnitude = 10 ** (int) floor( log10( $value ) );
		$best      = $magnitude;

		foreach ( array( 1, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10 ) as $step ) {
			if ( abs( $step * $magnitude - $value ) < abs( $best - $value ) ) {
				$best = $step * $magnitude;
			}
		}

		return (int) round( $best );
	}

	/**
	 * Điều kiện lọc đang áp dụng, mỗi giá trị một mục kèm URL bỏ riêng giá trị đó.
	 *
	 * @return array<int, array{var: string, label: string, url: string}>
	 */
	public static function active_items(): array {
		$items = array();

		foreach ( self::current() as $var => $value ) {
			if ( 'saha_attr' === $var ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$taxonomy = self::taxonomy_map()[ $var ] ?? '';

				foreach ( $value as $slug ) {
					$term    = '' !== $taxonomy ? get_term_by( 'slug', (string) $slug, $taxonomy ) : false;
					$items[] = array(
						'var'   => $var,
						'label' => $term instanceof \WP_Term ? $term->name : (string) $slug,
						'url'   => self::keep_orderby( self::build_url( $var, implode( ',', array_diff( $value, array( $slug ) ) ) ) ),
					);
				}

				continue;
			}

			$label = (string) $value;

			if ( 'saha_availability' === $var ) {
				$label = (string) ( Product::availability_options()[ $value ] ?? $value );
			} elseif ( 'saha_price' === $var ) {
				list( $min, $max ) = explode( '-', (string) $value, 2 );
				$label             = self::price_label( (int) $min, (int) $max );
			} elseif ( 'saha_min_price' === $var ) {
				/* translators: %s: giá */
				$label = sprintf( __( 'Từ %s', 'saha-core' ), self::money( (int) $value ) );
			} elseif ( 'saha_max_price' === $var ) {
				/* translators: %s: giá */
				$label = sprintf( __( 'Đến %s', 'saha-core' ), self::money( (int) $value ) );
			}

			$items[] = array(
				'var'   => $var,
				'label' => $label,
				'url'   => self::keep_orderby( self::build_url( $var, '' ) ),
			);
		}

		return $items;
	}

	/**
	 * Giữ kiểu sắp xếp của WooCommerce trên URL bỏ lọc.
	 *
	 * @param string $url URL.
	 */
	public static function keep_orderby( string $url ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- chỉ đọc, đã sanitize.
		$orderby = isset( $_GET['orderby'] ) && is_string( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';

		return '' !== $orderby ? add_query_arg( 'orderby', $orderby, $url ) : $url;
	}

	/**
	 * Nhãn một khoảng giá: "Dưới 3.000.000 đ", "3.000.000 đ – 6.000.000 đ", "Trên 10.000.000 đ".
	 *
	 * @param int $min Mốc dưới (0 = không có).
	 * @param int $max Mốc trên (0 = không có).
	 */
	public static function price_label( int $min, int $max ): string {
		if ( $min <= 0 && $max > 0 ) {
			/* translators: %s: giá */
			return sprintf( __( 'Dưới %s', 'saha-core' ), self::money( $max ) );
		}

		if ( $max <= 0 ) {
			/* translators: %s: giá */
			return sprintf( __( 'Trên %s', 'saha-core' ), self::money( $min ) );
		}

		return self::money( $min ) . ' – ' . self::money( $max );
	}

	/**
	 * Số tiền dạng chữ (không HTML), theo dấu phân cách của WooCommerce.
	 *
	 * @param int $amount Số tiền.
	 */
	private static function money( int $amount ): string {
		$thousand = function_exists( 'wc_get_price_thousand_separator' ) ? wc_get_price_thousand_separator() : '.';
		$symbol   = function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) : 'đ';

		return number_format( $amount, 0, ',', $thousand ) . ' ' . $symbol;
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
