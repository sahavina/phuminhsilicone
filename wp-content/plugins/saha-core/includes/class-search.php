<?php
/**
 * Product search service.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Search: tìm sản phẩm theo tên, SKU, thương hiệu, danh mục, mô tả, nội dung.
 *
 * Toàn bộ query dùng $wpdb->prepare(). Không nối chuỗi input (spec §77).
 *
 * Chấm điểm relevance để "243" ra Loctite 243 và "apollo a500" ra
 * Apollo Silicone A500 (spec §9).
 *
 * Dấu tiếng Việt: dựa vào collation utf8mb4_*_ci của MySQL (accent-insensitive),
 * kết hợp remove_accents() khi chuẩn hoá từ khoá.
 */
final class Search {

	/**
	 * Độ dài tối thiểu của từ khoá.
	 */
	public const MIN_LENGTH = 2;

	/**
	 * Số kết quả tối đa mỗi trang.
	 */
	public const MAX_PER_PAGE = 50;

	/**
	 * TTL cache kết quả. Ngắn vì số từ khoá khác nhau rất nhiều: khi không có
	 * object cache, mỗi từ khoá là một transient trong bảng options.
	 * Dữ liệu sản phẩm đổi thì Cache đã tự vô hiệu theo thế hệ.
	 */
	private const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

	/**
	 * Số kết quả tối đa trang kết quả tìm kiếm của WordPress phân trang được.
	 */
	public const MAX_RESULTS = 1000;

	/**
	 * Điểm cho từng loại khớp.
	 */
	private const SCORE_SKU_EXACT   = 100;
	private const SCORE_SKU_PARTIAL = 60;
	private const SCORE_TITLE_FULL  = 50;
	private const SCORE_TITLE_TOKEN = 12;
	private const SCORE_TERM        = 15;
	private const SCORE_EXCERPT     = 8;
	private const SCORE_CONTENT     = 4;

	/**
	 * Gắn hook: search log + tích hợp vào search mặc định của WordPress.
	 */
	public function register(): void {
		add_action( 'saha_search_performed', array( $this, 'maybe_log' ), 10, 2 );
		add_action( 'pre_get_posts', array( $this, 'filter_main_search' ) );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Public API
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Tìm sản phẩm.
	 *
	 * @param string $term     Từ khoá thô.
	 * @param int    $page     Trang, bắt đầu từ 1.
	 * @param int    $per_page Số kết quả mỗi trang.
	 * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, query: string}
	 */
	public static function search( string $term, int $page = 1, int $per_page = 10 ): array {
		$term     = self::normalize( $term );
		$page     = max( 1, $page );
		$per_page = max( 1, min( self::MAX_PER_PAGE, $per_page ) );

		$empty = array(
			'items'    => array(),
			'total'    => 0,
			'page'     => $page,
			'per_page' => $per_page,
			'query'    => $term,
		);

		if ( mb_strlen( $term ) < self::MIN_LENGTH || ! post_type_exists( 'product' ) ) {
			return $empty;
		}

		$cache_args = array( $term, $page, $per_page );
		$cached     = Cache::get( 'search', $cache_args );

		if ( $cached['hit'] ) {
			return (array) $cached['value'];
		}

		$ids_result = self::query_ids( $term, $page, $per_page );

		$result = array(
			'items'    => self::hydrate( $ids_result['ids'] ),
			'total'    => $ids_result['total'],
			'page'     => $page,
			'per_page' => $per_page,
			'query'    => $term,
		);

		Cache::set( 'search', $cache_args, $result, self::CACHE_TTL );

		/**
		 * Vừa thực hiện một lượt tìm kiếm.
		 *
		 * @param string $term  Từ khoá đã chuẩn hoá.
		 * @param int    $total Tổng số kết quả.
		 */
		do_action( 'saha_search_performed', $term, $result['total'] );

		return $result;
	}

	/**
	 * Thêm giá dạng chữ (`price`) vào kết quả — tính lúc trả về, không nằm trong cache,
	 * để đổi giá / bật tắt chế độ catalogue có hiệu lực ngay. Catalogue → chuỗi rỗng (không lộ giá).
	 *
	 * @param array<int, array<string, mixed>> $items Kết quả của search().
	 * @return array<int, array<string, mixed>>
	 */
	public static function with_prices( array $items ): array {
		$hide = function_exists( 'saha_is_catalogue_mode' ) && saha_is_catalogue_mode();

		foreach ( $items as $i => $item ) {
			$price   = '';
			$product = ( ! $hide && function_exists( 'wc_get_product' ) ) ? wc_get_product( (int) ( $item['id'] ?? 0 ) ) : null;

			// Tự dựng thay cho get_price_html(): bản đó có chữ ẩn cho trình đọc màn hình ("Giá gốc là…").
			if ( $product instanceof \WC_Product_Variable ) {
				$min   = (float) $product->get_variation_price( 'min', true );
				$max   = (float) $product->get_variation_price( 'max', true );
				$price = '' === $product->get_price() ? '' : ( $min === $max ? self::money( $min ) : self::money( $min ) . ' – ' . self::money( $max ) );
			} elseif ( $product && '' !== $product->get_price() ) {
				$price = self::money( (float) wc_get_price_to_display( $product ) );
			}

			$items[ $i ]['price'] = $price;
		}

		return $items;
	}

	/**
	 * Số tiền dạng chữ thuần (định dạng tiền tệ của WooCommerce).
	 *
	 * @param float $amount Số tiền.
	 */
	private static function money( float $amount ): string {
		return trim( html_entity_decode( wp_strip_all_tags( (string) wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Chỉ lấy ID sản phẩm khớp — dùng cho trang kết quả của WordPress.
	 *
	 * @param string $term  Từ khoá.
	 * @param int    $limit Giới hạn.
	 * @return int[]
	 */
	public static function search_ids( string $term, int $limit = self::MAX_RESULTS ): array {
		$term  = self::normalize( $term );
		$limit = max( 1, min( self::MAX_RESULTS, $limit ) );

		if ( mb_strlen( $term ) < self::MIN_LENGTH ) {
			return array();
		}

		$ids = Cache::remember(
			'search_ids',
			array( $term, $limit ),
			static fn(): array => self::query_ids( $term, 1, $limit )['ids'],
			self::CACHE_TTL
		);

		return array_map( 'absint', (array) $ids );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Query
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Chuẩn hoá từ khoá: bỏ tag, lowercase, gộp khoảng trắng.
	 *
	 * @param string $term Từ khoá thô.
	 */
	public static function normalize( string $term ): string {
		$term = wp_strip_all_tags( $term );
		$term = preg_replace( '/\s+/u', ' ', $term ) ?? '';

		return trim( mb_strtolower( $term, 'UTF-8' ) );
	}

	/**
	 * Tách token, đã mở rộng theo từ đồng nghĩa.
	 *
	 * @param string $term Từ khoá đã chuẩn hoá.
	 * @return string[]
	 */
	public static function tokenize( string $term ): array {
		$tokens = array_filter(
			explode( ' ', $term ),
			static fn( string $token ): bool => mb_strlen( $token ) >= 1
		);

		$tokens = array_merge( $tokens, self::synonyms_for( $term, $tokens ) );

		$tokens = array_values( array_unique( $tokens ) );

		// Giới hạn để không sinh SQL quá dài.
		return array_slice( $tokens, 0, 8 );
	}

	/**
	 * Từ đồng nghĩa cấu hình trong SAHA → Cấu hình.
	 *
	 * Mỗi dòng dạng: `keo silicone = silicone, keo kính`.
	 *
	 * @param string   $term   Từ khoá đầy đủ.
	 * @param string[] $tokens Token hiện có.
	 * @return string[]
	 */
	private static function synonyms_for( string $term, array $tokens ): array {
		$raw = (string) Settings::get( 'search_synonyms', '' );

		if ( '' === trim( $raw ) ) {
			return array();
		}

		$out = array();

		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) ?: array() as $line ) {
			if ( false === strpos( $line, '=' ) ) {
				continue;
			}

			[ $key, $values ] = array_map( 'trim', explode( '=', $line, 2 ) );

			$key = mb_strtolower( $key, 'UTF-8' );

			if ( '' === $key ) {
				continue;
			}

			$matched = false !== mb_strpos( $term, $key ) || in_array( $key, $tokens, true );

			if ( ! $matched ) {
				continue;
			}

			foreach ( explode( ',', $values ) as $value ) {
				$value = trim( mb_strtolower( $value, 'UTF-8' ) );

				if ( '' !== $value ) {
					$out[] = $value;
				}
			}
		}

		return $out;
	}

	/**
	 * Truy vấn ID sản phẩm theo relevance.
	 *
	 * @param string $term     Từ khoá đã chuẩn hoá.
	 * @param int    $page     Trang.
	 * @param int    $per_page Số kết quả.
	 * @return array{ids: int[], total: int}
	 */
	private static function query_ids( string $term, int $page, int $per_page ): array {
		global $wpdb;

		if ( '' === $term ) {
			return array(
				'ids'   => array(),
				'total' => 0,
			);
		}

		$tokens    = self::tokenize( $term );
		$like_full = '%' . $wpdb->esc_like( $term ) . '%';
		$term_ids  = self::matching_term_product_ids( $term, $tokens );

		// --- Điểm relevance ------------------------------------------------
		$score_sql    = array();
		$score_params = array();

		// sku.* phải bọc trong hàm gộp: GROUP BY p.ID chỉ bảo đảm functional
		// dependency cho cột của bảng posts, nếu không MySQL bật
		// ONLY_FULL_GROUP_BY (mặc định từ 5.7) sẽ từ chối query.
		$score_sql[]    = 'MAX( CASE WHEN sku.meta_value = %s THEN ' . self::SCORE_SKU_EXACT . ' ELSE 0 END )';
		$score_params[] = $term;

		$score_sql[]    = 'MAX( CASE WHEN sku.meta_value LIKE %s THEN ' . self::SCORE_SKU_PARTIAL . ' ELSE 0 END )';
		$score_params[] = $like_full;

		$score_sql[]    = 'CASE WHEN p.post_title LIKE %s THEN ' . self::SCORE_TITLE_FULL . ' ELSE 0 END';
		$score_params[] = $like_full;

		foreach ( $tokens as $token ) {
			$score_sql[]    = 'CASE WHEN p.post_title LIKE %s THEN ' . self::SCORE_TITLE_TOKEN . ' ELSE 0 END';
			$score_params[] = '%' . $wpdb->esc_like( $token ) . '%';
		}

		$score_sql[]    = 'CASE WHEN p.post_excerpt LIKE %s THEN ' . self::SCORE_EXCERPT . ' ELSE 0 END';
		$score_params[] = $like_full;

		$score_sql[]    = 'CASE WHEN p.post_content LIKE %s THEN ' . self::SCORE_CONTENT . ' ELSE 0 END';
		$score_params[] = $like_full;

		if ( $term_ids ) {
			$placeholders = implode( ', ', array_fill( 0, count( $term_ids ), '%d' ) );
			$score_sql[]  = 'CASE WHEN p.ID IN (' . $placeholders . ') THEN ' . self::SCORE_TERM . ' ELSE 0 END';
			$score_params = array_merge( $score_params, $term_ids );
		}

		// --- Điều kiện WHERE ----------------------------------------------
		$where_sql    = array();
		$where_params = array();

		$where_sql[]    = 'sku.meta_value LIKE %s';
		$where_params[] = $like_full;

		$where_sql[]    = 'p.post_title LIKE %s';
		$where_params[] = $like_full;

		foreach ( $tokens as $token ) {
			$where_sql[]    = 'p.post_title LIKE %s';
			$where_params[] = '%' . $wpdb->esc_like( $token ) . '%';
		}

		$where_sql[]    = 'p.post_excerpt LIKE %s';
		$where_params[] = $like_full;

		$where_sql[]    = 'p.post_content LIKE %s';
		$where_params[] = $like_full;

		if ( $term_ids ) {
			$placeholders = implode( ', ', array_fill( 0, count( $term_ids ), '%d' ) );
			$where_sql[]  = 'p.ID IN (' . $placeholders . ')';
			$where_params = array_merge( $where_params, $term_ids );
		}

		$from = "FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} sku ON sku.post_id = p.ID AND sku.meta_key = '_sku'
			WHERE p.post_type = 'product'
				AND p.post_status = 'publish'
				AND ( " . implode( ' OR ', $where_sql ) . ' )';

		// --- Đếm tổng -------------------------------------------------------
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$total = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(DISTINCT p.ID) ' . $from, $where_params )
		);

		if ( $total <= 0 ) {
			// phpcs:enable
			return array(
				'ids'   => array(),
				'total' => 0,
			);
		}

		// --- Lấy ID theo trang ---------------------------------------------
		$sql = 'SELECT p.ID, ( ' . implode( ' + ', $score_sql ) . ' ) AS relevance '
			. $from
			. ' GROUP BY p.ID ORDER BY relevance DESC, p.post_title ASC LIMIT %d OFFSET %d';

		$params = array_merge(
			$score_params,
			$where_params,
			array( $per_page, ( $page - 1 ) * $per_page )
		);

		$rows = $wpdb->get_col( $wpdb->prepare( $sql, $params ) );
		// phpcs:enable

		return array(
			'ids'   => array_map( 'absint', (array) $rows ),
			'total' => $total,
		);
	}

	/**
	 * ID sản phẩm thuộc các term (thương hiệu/danh mục/ứng dụng) khớp từ khoá.
	 *
	 * @param string   $term   Từ khoá.
	 * @param string[] $tokens Token.
	 * @return int[]
	 */
	private static function matching_term_product_ids( string $term, array $tokens ): array {
		$taxonomies = array_values(
			array_filter(
				array_merge( array( 'product_cat', 'product_tag' ), Taxonomies::active() ),
				'taxonomy_exists'
			)
		);

		if ( ! $taxonomies ) {
			return array();
		}

		$names = array_values( array_unique( array_merge( array( $term ), $tokens ) ) );
		$found = array();

		foreach ( $names as $name ) {
			if ( mb_strlen( $name ) < self::MIN_LENGTH ) {
				continue;
			}

			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomies,
					'name__like' => $name,
					'hide_empty' => true,
					'number'     => 10,
					'fields'     => 'ids',
				)
			);

			if ( ! is_wp_error( $terms ) ) {
				$found = array_merge( $found, array_map( 'absint', (array) $terms ) );
			}
		}

		$found = array_values( array_unique( $found ) );

		if ( ! $found ) {
			return array();
		}

		// Mỗi taxonomy một clause: term_id không thuộc taxonomy nào thì clause đó
		// đơn giản là không khớp, nên OR cho kết quả đúng.
		$tax_query = array( 'relation' => 'OR' );

		foreach ( $taxonomies as $taxonomy ) {
			$tax_query[] = array(
				'taxonomy'         => $taxonomy,
				'field'            => 'term_id',
				'terms'            => $found,
				'include_children' => true,
				'operator'         => 'IN',
			);
		}

		$ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'tax_query'      => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- giới hạn 200 bản ghi.
			)
		);

		return array_map( 'absint', (array) $ids );
	}

	/**
	 * Chuyển ID thành dữ liệu hiển thị, tránh N+1 bằng prime cache.
	 *
	 * @param int[] $ids Product ID.
	 * @return array<int, array<string, mixed>>
	 */
	private static function hydrate( array $ids ): array {
		if ( ! $ids ) {
			return array();
		}

		_prime_post_caches( $ids, true, true );

		$items = array();

		foreach ( $ids as $id ) {
			$card = Product::get_card_data( $id );

			if ( ! $card ) {
				continue;
			}

			$thumbnail = $card['thumbnail_id'] > 0
				? wp_get_attachment_image_url( (int) $card['thumbnail_id'], 'thumbnail' )
				: '';

			$category = '';
			$cats     = get_the_terms( $id, 'product_cat' );

			if ( $cats && ! is_wp_error( $cats ) ) {
				$category = $cats[0]->name;
			}

			$items[] = array(
				'id'           => (int) $card['id'],
				'name'         => (string) $card['name'],
				'url'          => (string) $card['url'],
				'sku'          => (string) $card['sku'],
				'brand'        => (string) $card['brand'],
				'category'     => $category,
				'availability' => (string) $card['availability'],
				'thumbnail'    => $thumbnail ? $thumbnail : '',
			);
		}

		/**
		 * Lọc danh sách kết quả tìm kiếm.
		 *
		 * @param array<int, array<string, mixed>> $items Kết quả.
		 * @param int[]                            $ids   ID gốc.
		 */
		return (array) apply_filters( 'saha_search_results', $items, $ids );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Tích hợp & log
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Dùng relevance của SAHA cho trang kết quả tìm kiếm sản phẩm.
	 *
	 * @param \WP_Query $query Query.
	 */
	public function filter_main_search( \WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
			return;
		}

		$post_type = $query->get( 'post_type' );

		// Chỉ can thiệp khi đang tìm trong phạm vi sản phẩm.
		if ( 'product' !== $post_type ) {
			return;
		}

		$term = (string) $query->get( 's' );
		$ids  = self::search_ids( $term );

		if ( ! $ids ) {
			// Không có kết quả: để WordPress trả empty state thay vì trả toàn bộ sản phẩm.
			$query->set( 'post__in', array( 0 ) );
			return;
		}

		$query->set( 's', '' );
		$query->set( 'post__in', $ids );
		$query->set( 'orderby', 'post__in' );
	}

	/**
	 * Ghi log tìm kiếm nếu admin bật. Không lưu user/IP (spec §87).
	 *
	 * @param string $term  Từ khoá.
	 * @param int    $total Số kết quả.
	 */
	public function maybe_log( string $term, int $total ): void {
		if ( ! Settings::get( 'enable_search_log', false ) ) {
			return;
		}

		global $wpdb;

		$table = Migrator::table( 'search_logs' );

		if ( ! Migrator::table_exists( $table ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table, $wpdb->insert đã prepare.
		$wpdb->insert(
			$table,
			array(
				'query'        => mb_substr( $term, 0, 191 ),
				'result_count' => $total,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s' )
		);
	}
}
