<?php
/**
 * Cache dùng chung cho dữ liệu catalogue.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Cache.
 *
 * Một cơ chế duy nhất cho mọi cache đọc-nhiều của plugin (danh sách sản phẩm,
 * danh mục, thương hiệu, bài liên quan, kết quả tìm kiếm).
 *
 * Vô hiệu theo "thế hệ": key cache chứa số thế hệ hiện tại. Mọi thay đổi dữ
 * liệu liên quan tăng số thế hệ đúng một lần mỗi request → toàn bộ cache cũ
 * tự trượt, không phải theo dõi hay xoá từng key (spec §80). Cache cũ hết hạn
 * theo TTL và được WordPress dọn định kỳ.
 *
 * Chỉ dùng Transient API → khi có Redis Object Cache, dữ liệu tự nằm trong
 * Redis thay vì bảng options (spec §27). Không phụ thuộc plugin cache nào.
 */
final class Cache {

	/**
	 * Option giữ số thế hệ.
	 */
	public const GENERATION_OPTION = 'saha_cache_gen';

	/**
	 * TTL mặc định.
	 */
	public const TTL = 6 * HOUR_IN_SECONDS;

	/**
	 * Đã tăng thế hệ trong request này chưa.
	 *
	 * @var bool
	 */
	private static bool $bumped = false;

	/**
	 * Thế hệ đọc trong request (tránh get_option lặp lại).
	 *
	 * @var int|null
	 */
	private static ?int $generation = null;

	/**
	 * Gắn hook invalidation.
	 */
	public function register(): void {
		foreach ( self::invalidation_events() as $event ) {
			add_action( $event, array( __CLASS__, 'bump' ) );
		}

		add_action( 'deleted_post', array( __CLASS__, 'bump_on_post_delete' ), 10, 2 );
	}

	/**
	 * Các sự kiện làm dữ liệu catalogue thay đổi.
	 *
	 * @return string[]
	 */
	public static function invalidation_events(): array {
		$events = array(
			// Sản phẩm.
			'save_post_product',
			'woocommerce_update_product',
			'woocommerce_product_set_stock_status',
			'woocommerce_product_set_visibility',
			// Blog (bài viết liên quan).
			'save_post_post',
		);

		foreach ( array( 'product_cat', 'product_tag', Taxonomies::BRAND, Taxonomies::APPLICATION, Taxonomies::MATERIAL, 'category' ) as $taxonomy ) {
			foreach ( array( 'created_', 'edited_', 'delete_' ) as $prefix ) {
				$events[] = $prefix . $taxonomy;
			}
		}

		// Term meta thương hiệu (logo, banner…) lưu xong.
		$events[] = 'saha_brand_saved';

		/**
		 * Lọc danh sách sự kiện làm vô hiệu cache.
		 *
		 * @param string[] $events Tên action.
		 */
		return (array) apply_filters( 'saha_cache_invalidation_events', $events );
	}

	/**
	 * Key cache: saha_{group}_{thế hệ}_{md5(args)} — luôn dưới 172 ký tự của transient.
	 *
	 * @param string $group Nhóm, ví dụ `products`.
	 * @param mixed  $args  Tham số xác định nội dung.
	 */
	public static function key( string $group, $args ): string {
		return 'saha_' . sanitize_key( $group ) . '_' . self::generation() . '_' . md5( (string) wp_json_encode( $args ) );
	}

	/**
	 * Đọc cache.
	 *
	 * Giá trị được bọc trong mảng để phân biệt "chưa có cache" với "cache rỗng"
	 * (ví dụ khối không có sản phẩm nào vẫn phải được cache).
	 *
	 * @param string $group Nhóm.
	 * @param mixed  $args  Tham số.
	 * @return array{hit: bool, value: mixed}
	 */
	public static function get( string $group, $args ): array {
		$stored = get_transient( self::key( $group, $args ) );

		if ( is_array( $stored ) && array_key_exists( 'v', $stored ) ) {
			return array(
				'hit'   => true,
				'value' => $stored['v'],
			);
		}

		return array(
			'hit'   => false,
			'value' => null,
		);
	}

	/**
	 * Ghi cache.
	 *
	 * @param string $group Nhóm.
	 * @param mixed  $args  Tham số.
	 * @param mixed  $value Giá trị.
	 * @param int    $ttl   Thời gian sống (giây).
	 */
	public static function set( string $group, $args, $value, int $ttl = self::TTL ): void {
		set_transient( self::key( $group, $args ), array( 'v' => $value ), max( 60, $ttl ) );
	}

	/**
	 * Đọc cache, chưa có thì tính và ghi.
	 *
	 * @param string   $group    Nhóm.
	 * @param mixed    $args     Tham số.
	 * @param callable $callback Hàm tính giá trị.
	 * @param int      $ttl      Thời gian sống (giây).
	 * @return mixed
	 */
	public static function remember( string $group, $args, callable $callback, int $ttl = self::TTL ) {
		$cached = self::get( $group, $args );

		if ( $cached['hit'] ) {
			return $cached['value'];
		}

		$value = $callback();

		self::set( $group, $args, $value, $ttl );

		return $value;
	}

	/**
	 * Thế hệ hiện tại.
	 */
	public static function generation(): int {
		if ( null === self::$generation ) {
			self::$generation = max( 1, (int) get_option( self::GENERATION_OPTION, 1 ) );
		}

		return self::$generation;
	}

	/**
	 * Vô hiệu toàn bộ cache: tăng thế hệ, tối đa một lần mỗi request.
	 *
	 * Lưu một sản phẩm bắn nhiều hook (save_post, woocommerce_update_product,
	 * set_object_terms…) — chỉ cần tăng một lần.
	 */
	public static function bump(): void {
		if ( self::$bumped ) {
			return;
		}

		self::$bumped     = true;
		self::$generation = self::generation() + 1;

		update_option( self::GENERATION_OPTION, self::$generation, false );

		/**
		 * Cache catalogue vừa bị vô hiệu.
		 *
		 * Điểm cắm để purge page cache (LiteSpeed, Cloudflare…) nếu muốn.
		 *
		 * @param int $generation Thế hệ mới.
		 */
		do_action( 'saha_cache_bumped', self::$generation );
	}

	/**
	 * Chỉ tăng thế hệ khi post bị xoá là sản phẩm hoặc bài viết.
	 *
	 * @param int           $post_id Post ID.
	 * @param \WP_Post|null $post    Post (WP ≥ 5.5).
	 */
	public static function bump_on_post_delete( int $post_id, $post = null ): void {
		$type = $post instanceof \WP_Post ? $post->post_type : get_post_type( $post_id );

		if ( in_array( $type, array( 'product', 'post' ), true ) ) {
			self::bump();
		}
	}

	/**
	 * Chỉ dùng cho test: đặt lại trạng thái trong request.
	 */
	public static function reset_request_state(): void {
		self::$bumped     = false;
		self::$generation = null;
	}
}
