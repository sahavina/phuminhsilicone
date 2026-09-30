<?php
/**
 * Catalog service: truy vấn danh sách sản phẩm / danh mục cho các khối trang chủ.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Catalog.
 *
 * UX element / shortcode KHÔNG query trực tiếp (spec §36) — chỉ gọi service này
 * rồi render. Kết quả được cache qua Cache (vô hiệu theo thế hệ — spec §80).
 */
final class Catalog {

	/**
	 * Giới hạn số sản phẩm một khối.
	 */
	public const MAX_LIMIT = 24;

	/**
	 * Kiểu nguồn sản phẩm được hỗ trợ.
	 *
	 * @return array<string, string>
	 */
	public static function sources(): array {
		return array(
			'featured'    => __( 'Sản phẩm nổi bật', 'saha-core' ),
			'latest'      => __( 'Sản phẩm mới', 'saha-core' ),
			'sale'        => __( 'Đang khuyến mại', 'saha-core' ),
			'category'    => __( 'Theo danh mục', 'saha-core' ),
			'brand'       => __( 'Theo thương hiệu', 'saha-core' ),
			'application' => __( 'Theo ứng dụng', 'saha-core' ),
			'ids'         => __( 'Chọn tay (ID)', 'saha-core' ),
		);
	}

	/**
	 * Invalidation do Cache đảm nhiệm — module này không cần hook riêng.
	 */
	public function register(): void {}

	/*
	 * ---------------------------------------------------------------------
	 * Sản phẩm
	 * ---------------------------------------------------------------------
	 */

	/**
	 * ID sản phẩm cho một khối.
	 *
	 * @param array<string, mixed> $args source, category, brand, application, ids, limit, orderby, hide_out_of_stock.
	 * @return int[]
	 */
	public static function product_ids( array $args ): array {
		if ( ! post_type_exists( 'product' ) ) {
			return array();
		}

		$args = self::normalize_product_args( $args );

		$ids = Cache::remember(
			'products',
			$args,
			static fn(): array => self::query_product_ids( $args )
		);

		return array_map( 'absint', (array) $ids );
	}

	/**
	 * Chuẩn hoá và whitelist tham số.
	 *
	 * @param array<string, mixed> $args Tham số thô từ shortcode/UX element.
	 * @return array<string, mixed>
	 */
	private static function normalize_product_args( array $args ): array {
		$source = sanitize_key( (string) ( $args['source'] ?? 'latest' ) );

		$ids = array_values(
			array_filter(
				array_map( 'absint', is_array( $args['ids'] ?? null ) ? $args['ids'] : explode( ',', (string) ( $args['ids'] ?? '' ) ) )
			)
		);

		$orderby = sanitize_key( (string) ( $args['orderby'] ?? '' ) );

		return array(
			'source'            => isset( self::sources()[ $source ] ) ? $source : 'latest',
			'category'          => sanitize_title( (string) ( $args['category'] ?? '' ) ),
			'brand'             => sanitize_title( (string) ( $args['brand'] ?? '' ) ),
			'application'       => sanitize_title( (string) ( $args['application'] ?? '' ) ),
			'ids'               => array_slice( $ids, 0, self::MAX_LIMIT ),
			'limit'             => max( 1, min( self::MAX_LIMIT, (int) ( $args['limit'] ?? 8 ) ) ),
			'orderby'           => in_array( $orderby, array( 'date', 'title', 'menu_order', 'popularity', 'rand' ), true ) ? $orderby : 'date',
			'hide_out_of_stock' => ! empty( $args['hide_out_of_stock'] ),
		);
	}

	/**
	 * Truy vấn thật. Chỉ lấy ID, không load post object (spec §28).
	 *
	 * @param array<string, mixed> $args Tham số đã chuẩn hoá.
	 * @return int[]
	 */
	private static function query_product_ids( array $args ): array {
		$query = array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'         => $args['limit'],
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		$tax_query = array();

		// Ẩn sản phẩm bị đánh dấu "ẩn khỏi catalogue" của WooCommerce.
		if ( taxonomy_exists( 'product_visibility' ) ) {
			$tax_query[] = array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => array( 'exclude-from-catalog' ),
				'operator' => 'NOT IN',
			);
		}

		switch ( $args['source'] ) {
			case 'featured':
				if ( taxonomy_exists( 'product_visibility' ) ) {
					$tax_query[] = array(
						'taxonomy' => 'product_visibility',
						'field'    => 'name',
						'terms'    => array( 'featured' ),
					);
				}
				break;

			case 'sale':
				$sale_ids = function_exists( 'wc_get_product_ids_on_sale' ) ? wc_get_product_ids_on_sale() : array();

				if ( ! $sale_ids ) {
					return array();
				}

				$query['post__in'] = array_map( 'absint', $sale_ids );
				break;

			case 'category':
				if ( '' === $args['category'] ) {
					return array();
				}

				$tax_query[] = self::tax_clause( 'product_cat', $args['category'], true );
				break;

			case 'brand':
				if ( '' === $args['brand'] || ! taxonomy_exists( Taxonomies::BRAND ) ) {
					return array();
				}

				$tax_query[] = self::tax_clause( Taxonomies::BRAND, $args['brand'] );
				break;

			case 'application':
				if ( '' === $args['application'] || ! taxonomy_exists( Taxonomies::APPLICATION ) ) {
					return array();
				}

				$tax_query[] = self::tax_clause( Taxonomies::APPLICATION, $args['application'], true );
				break;

			case 'ids':
				if ( ! $args['ids'] ) {
					return array();
				}

				$query['post__in']       = $args['ids'];
				$query['orderby']        = 'post__in';
				$query['posts_per_page'] = count( $args['ids'] );
				break;
		}

		// Có thể kết hợp: ví dụ "nổi bật" nhưng chỉ của thương hiệu Loctite.
		if ( 'brand' !== $args['source'] && '' !== $args['brand'] && taxonomy_exists( Taxonomies::BRAND ) ) {
			$tax_query[] = self::tax_clause( Taxonomies::BRAND, $args['brand'] );
		}

		if ( 'category' !== $args['source'] && '' !== $args['category'] ) {
			$tax_query[] = self::tax_clause( 'product_cat', $args['category'], true );
		}

		if ( $args['hide_out_of_stock'] && taxonomy_exists( 'product_visibility' ) ) {
			$tax_query[] = array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => array( 'outofstock' ),
				'operator' => 'NOT IN',
			);
		}

		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}

		if ( $tax_query ) {
			$query['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- có limit + cache.
		}

		if ( 'ids' !== $args['source'] ) {
			self::apply_order( $query, (string) $args['orderby'] );
		}

		/**
		 * Lọc query của khối sản phẩm.
		 *
		 * @param array<string, mixed> $query Tham số WP_Query.
		 * @param array<string, mixed> $args  Tham số khối.
		 */
		$query = (array) apply_filters( 'saha_catalog_product_query', $query, $args );

		$result = new \WP_Query( $query );

		return array_map( 'absint', (array) $result->posts );
	}

	/**
	 * Tax clause nhận cả slug (shortcode) lẫn term ID (termSelect của UX Builder).
	 *
	 * @param string $taxonomy         Taxonomy.
	 * @param string $value            Slug hoặc ID.
	 * @param bool   $include_children Gồm term con.
	 * @return array<string, mixed>
	 */
	private static function tax_clause( string $taxonomy, string $value, bool $include_children = false ): array {
		$is_id = ctype_digit( $value );

		$clause = array(
			'taxonomy' => $taxonomy,
			'field'    => $is_id ? 'term_id' : 'slug',
			'terms'    => $is_id ? (int) $value : $value,
		);

		if ( $include_children ) {
			$clause['include_children'] = true;
		}

		return $clause;
	}

	/**
	 * Thứ tự sắp xếp.
	 *
	 * @param array<string, mixed> $query   Query (tham chiếu).
	 * @param string               $orderby Kiểu sắp xếp.
	 */
	private static function apply_order( array &$query, string $orderby ): void {
		switch ( $orderby ) {
			case 'title':
				$query['orderby'] = 'title';
				$query['order']   = 'ASC';
				break;

			case 'menu_order':
				$query['orderby'] = array(
					'menu_order' => 'ASC',
					'date'       => 'DESC',
				);
				break;

			case 'popularity':
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- meta chuẩn của WooCommerce, có cache.
				$query['meta_key'] = 'total_sales';
				$query['orderby']  = array(
					'meta_value_num' => 'DESC',
					'date'           => 'DESC',
				);
				break;

			case 'rand':
				// Rand vẫn được cache theo TTL — tránh ORDER BY RAND() mỗi request.
				$query['orderby'] = 'rand';
				break;

			default:
				$query['orderby'] = 'date';
				$query['order']   = 'DESC';
		}
	}

	/*
	 * ---------------------------------------------------------------------
	 * Danh mục / ứng dụng
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Danh sách term để render grid danh mục / ứng dụng.
	 *
	 * @param string               $taxonomy product_cat | product_application.
	 * @param array<string, mixed> $args     parent, include, limit, hide_empty, orderby.
	 * @return array<int, array<string, mixed>>
	 */
	public static function terms( string $taxonomy, array $args = array() ): array {
		$allowed = array( 'product_cat', Taxonomies::APPLICATION );

		if ( ! in_array( $taxonomy, $allowed, true ) || ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}

		$include = array_values(
			array_filter(
				array_map( 'absint', is_array( $args['include'] ?? null ) ? $args['include'] : explode( ',', (string) ( $args['include'] ?? '' ) ) )
			)
		);

		$orderby = sanitize_key( (string) ( $args['orderby'] ?? 'menu_order' ) );

		$norm = array(
			'taxonomy'   => $taxonomy,
			'parent'     => isset( $args['parent'] ) && '' !== (string) $args['parent'] ? absint( $args['parent'] ) : 0,
			'include'    => array_slice( $include, 0, 48 ),
			'limit'      => max( 1, min( 48, (int) ( $args['limit'] ?? 8 ) ) ),
			'hide_empty' => ! isset( $args['hide_empty'] ) || ! empty( $args['hide_empty'] ),
			'orderby'    => in_array( $orderby, array( 'menu_order', 'name', 'count' ), true ) ? $orderby : 'menu_order',
		);

		return (array) Cache::remember(
			'terms',
			$norm,
			static fn(): array => self::query_terms( $taxonomy, $norm )
		);
	}

	/**
	 * Truy vấn term thật.
	 *
	 * @param string               $taxonomy Taxonomy.
	 * @param array<string, mixed> $norm     Tham số đã chuẩn hoá.
	 * @return array<int, array<string, mixed>>
	 */
	private static function query_terms( string $taxonomy, array $norm ): array {
		$query = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => $norm['hide_empty'],
			'number'     => $norm['limit'],
		);

		if ( $norm['include'] ) {
			$query['include'] = $norm['include'];
			$query['orderby'] = 'include';
		} else {
			$query['parent'] = $norm['parent'];

			if ( 'menu_order' === $norm['orderby'] ) {
				// WooCommerce hiểu tham số menu_order cho taxonomy sắp xếp được
				// (product_cat) và JOIN term meta "order" bằng LEFT JOIN — danh mục
				// chưa kéo-thả vẫn hiện. Taxonomy khác bỏ qua tham số này, sắp theo tên.
				$query['menu_order'] = 'ASC';
				$query['orderby']    = 'name';
			} else {
				$query['orderby'] = $norm['orderby'];
				$query['order']   = 'count' === $norm['orderby'] ? 'DESC' : 'ASC';
			}
		}

		$terms = get_terms( $query );

		if ( is_wp_error( $terms ) || ! $terms ) {
			return array();
		}

		$out = array();

		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$link = get_term_link( $term );

			$out[] = array(
				'id'           => $term->term_id,
				'name'         => $term->name,
				'slug'         => $term->slug,
				'url'          => is_wp_error( $link ) ? '' : $link,
				'count'        => (int) $term->count,
				'description'  => $term->description,
				// WooCommerce lưu ảnh danh mục ở term meta thumbnail_id.
				'thumbnail_id' => (int) get_term_meta( $term->term_id, 'thumbnail_id', true ),
			);
		}

		/**
		 * Lọc danh sách term cho grid.
		 *
		 * @param array<int, array<string, mixed>> $out      Term.
		 * @param string                           $taxonomy Taxonomy.
		 */
		return (array) apply_filters( 'saha_catalog_terms', $out, $taxonomy );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Blog
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Bài viết liên quan: cùng chuyên mục, mới nhất trước (spec §21).
	 *
	 * @param int $post_id Bài hiện tại.
	 * @param int $limit   Số bài.
	 * @return int[]
	 */
	public static function related_post_ids( int $post_id, int $limit = 3 ): array {
		if ( $post_id <= 0 || 'post' !== get_post_type( $post_id ) ) {
			return array();
		}

		$limit = max( 1, min( 12, $limit ) );
		$cats  = wp_get_post_categories( $post_id, array( 'fields' => 'ids' ) );
		$cats  = array_values( array_filter( array_map( 'absint', (array) $cats ) ) );

		$ids = Cache::remember(
			'related',
			array( $post_id, $cats, $limit ),
			static fn(): array => self::query_related_posts( $post_id, $cats, $limit )
		);

		return array_map( 'absint', (array) $ids );
	}

	/**
	 * Truy vấn bài viết liên quan.
	 *
	 * @param int   $post_id Bài hiện tại.
	 * @param int[] $cats    Chuyên mục của bài.
	 * @param int   $limit   Số bài.
	 * @return int[]
	 */
	private static function query_related_posts( int $post_id, array $cats, int $limit ): array {
		$query = array(
			'post_type'              => 'post',
			'post_status'            => 'publish',
			'posts_per_page'         => $limit,
			'post__not_in'           => array( $post_id ),
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		if ( $cats ) {
			$query['category__in'] = $cats;
		}

		$ids = array_map( 'absint', (array) ( new \WP_Query( $query ) )->posts );

		// Chuyên mục ít bài: bổ sung bài mới nhất để khối không bị thưa.
		if ( count( $ids ) < $limit && $cats ) {
			unset( $query['category__in'] );
			$query['posts_per_page'] = $limit - count( $ids );
			$query['post__not_in']   = array_merge( array( $post_id ), $ids );

			$ids = array_merge( $ids, array_map( 'absint', (array) ( new \WP_Query( $query ) )->posts ) );
		}

		return $ids;
	}
}
