<?php
/**
 * Tự xoá cache Cloudflare khi nội dung thay đổi.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Performance;

use Saha\Core\Logger;
use Saha\Core\ThemeOptions\Repository as ThemeOptions;

defined( 'ABSPATH' ) || exit;

/**
 * CloudflarePurge.
 *
 * Site cache HTML cho khách ở Cloudflare (Cache Rule). Lưu sản phẩm / trang / bài,
 * sửa danh mục… → gom URL liên quan trong request, cuối request gọi API
 * `purge_cache` một lần. Đổi thứ hiện trên mọi trang (header, footer, template,
 * block dùng chung, menu, Theme Options) → xoá toàn bộ.
 *
 * Cấu hình bằng hằng số trong wp-config.php (không lưu DB, không vào repo):
 *
 *     define( 'SAHA_CF_ZONE_ID', '…' );   // Cloudflare → zone → Overview → Zone ID
 *     define( 'SAHA_CF_API_TOKEN', '…' ); // token quyền Zone → Cache Purge → Purge
 *
 * Thiếu một trong hai → module không làm gì.
 */
final class CloudflarePurge {

	/**
	 * Cloudflare (gói Free) nhận tối đa 30 URL mỗi lệnh purge theo URL.
	 */
	public const MAX_FILES = 30;

	/**
	 * Option lưu kết quả lần purge gần nhất (hiển thị trong Kiểm tra hệ thống).
	 */
	public const STATUS_OPTION = 'saha_cf_last_purge';

	private const ADMIN_ACTION = 'saha_cf_purge_all';

	/**
	 * URL chờ xoá trong request.
	 *
	 * @var array<string, true>
	 */
	private static array $urls = array();

	/**
	 * Xoá toàn bộ ở cuối request.
	 *
	 * @var bool
	 */
	private static bool $everything = false;

	/**
	 * Đã gắn hook shutdown chưa.
	 *
	 * @var bool
	 */
	private static bool $scheduled = false;

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		if ( ! self::configured() ) {
			return;
		}

		add_action( 'transition_post_status', array( __CLASS__, 'on_status' ), 10, 3 );
		add_action( 'post_updated', array( __CLASS__, 'on_post_updated' ), 10, 3 );
		add_action( 'saha_builder_saved', array( __CLASS__, 'on_builder_saved' ) );

		// Tồn kho / trạng thái hàng đổi khi có đơn (không qua màn sửa sản phẩm).
		foreach ( array( 'woocommerce_product_set_stock', 'woocommerce_variation_set_stock' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'on_product_object' ) );
		}
		add_action( 'woocommerce_product_set_stock_status', array( __CLASS__, 'on_product_id' ) );

		add_action( 'created_term', array( __CLASS__, 'on_term' ), 10, 3 );
		add_action( 'edited_term', array( __CLASS__, 'on_term' ), 10, 3 );
		add_action( 'pre_delete_term', array( __CLASS__, 'on_term_delete' ), 10, 2 );

		// Hiện trên mọi trang → xoá toàn bộ.
		foreach ( array( 'wp_update_nav_menu', 'customize_save_after', 'switch_theme', 'update_option_' . ThemeOptions::OPTION, 'update_option_woocommerce_currency' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'purge_everything' ) );
		}

		add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 100 );
		add_action( 'admin_post_' . self::ADMIN_ACTION, array( $this, 'handle_admin_purge' ) );
		add_action( 'admin_notices', array( $this, 'admin_notice' ) );
	}

	/**
	 * Đã cấu hình Zone ID + token chưa.
	 */
	public static function configured(): bool {
		return '' !== self::zone() && '' !== self::token();
	}

	/**
	 * Zone ID.
	 */
	private static function zone(): string {
		return defined( 'SAHA_CF_ZONE_ID' ) ? trim( (string) SAHA_CF_ZONE_ID ) : '';
	}

	/**
	 * API token.
	 */
	private static function token(): string {
		return defined( 'SAHA_CF_API_TOKEN' ) ? trim( (string) SAHA_CF_API_TOKEN ) : '';
	}

	/*
	 * ---------------------------------------------------------------------
	 * Hook nội dung
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Đăng / gỡ / cập nhật bài đã đăng.
	 *
	 * @param string   $new_status Trạng thái mới.
	 * @param string   $old_status Trạng thái cũ.
	 * @param \WP_Post $post       Bài.
	 */
	public static function on_status( string $new_status, string $old_status, $post ): void {
		if ( ! $post instanceof \WP_Post || ( 'publish' !== $new_status && 'publish' !== $old_status ) ) {
			return;
		}

		self::queue_post( $post );
	}

	/**
	 * Đổi slug: xoá cả URL cũ.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $after   Sau.
	 * @param \WP_Post $before  Trước.
	 */
	public static function on_post_updated( int $post_id, $after, $before ): void {
		unset( $post_id );

		if ( $before instanceof \WP_Post && $after instanceof \WP_Post && 'publish' === $before->post_status && $before->post_name !== $after->post_name ) {
			self::add( (string) get_permalink( $before ) );
		}
	}

	/**
	 * Lưu trong SAHA Builder (trang, bài, template, block).
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_builder_saved( int $post_id ): void {
		$post = get_post( $post_id );

		if ( $post instanceof \WP_Post ) {
			self::queue_post( $post );
		}
	}

	/**
	 * Tồn kho đổi (nhận WC_Product).
	 *
	 * @param mixed $product Sản phẩm.
	 */
	public static function on_product_object( $product ): void {
		if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
			// Biến thể → sản phẩm cha.
			$id = method_exists( $product, 'get_parent_id' ) && $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
			self::on_product_id( (int) $id );
		}
	}

	/**
	 * Tồn kho đổi (nhận ID).
	 *
	 * @param int $product_id Product ID.
	 */
	public static function on_product_id( $product_id ): void {
		$post = get_post( (int) $product_id );

		if ( $post instanceof \WP_Post && 'publish' === $post->post_status ) {
			self::queue_post( $post );
		}
	}

	/**
	 * Tạo / sửa term công khai.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy.
	 */
	public static function on_term( $term_id, $tt_id = 0, $taxonomy = '' ): void {
		unset( $tt_id );

		if ( 'nav_menu' === $taxonomy ) {
			self::purge_everything();
			return;
		}

		if ( ! is_taxonomy_viewable( (string) $taxonomy ) ) {
			return;
		}

		$link = get_term_link( (int) $term_id, (string) $taxonomy );

		if ( ! is_wp_error( $link ) ) {
			self::add( $link );
		}

		// Lưới danh mục / thương hiệu trên trang chủ, cửa hàng.
		self::add( home_url( '/' ) );
		self::add_shop();
	}

	/**
	 * Xoá term: lấy link trước khi term mất.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy.
	 */
	public static function on_term_delete( $term_id, $taxonomy ): void {
		self::on_term( $term_id, 0, (string) $taxonomy );
	}

	/**
	 * URL liên quan tới một bài.
	 *
	 * @param \WP_Post $post Bài.
	 */
	private static function queue_post( \WP_Post $post ): void {
		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}

		// Template / block dùng chung / mega menu: hiện ở nhiều trang.
		if ( in_array( $post->post_type, (array) apply_filters( 'saha_cf_purge_everything_post_types', array( 'saha_template', 'saha_block', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation' ) ), true ) ) {
			self::purge_everything();
			return;
		}

		if ( ! is_post_type_viewable( $post->post_type ) ) {
			return;
		}

		self::add( (string) get_permalink( $post ) );
		self::add( home_url( '/' ) );

		if ( 'product' === $post->post_type ) {
			self::add_shop();
		} elseif ( 'post' === $post->post_type ) {
			$blog = (int) get_option( 'page_for_posts' );
			self::add( $blog ? (string) get_permalink( $blog ) : '' );
		} elseif ( (int) get_option( 'page_on_front' ) === $post->ID ) {
			// Trang chủ tĩnh: home_url('/') ở trên đã đủ.
			return;
		}

		foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
			if ( ! is_taxonomy_viewable( $taxonomy ) ) {
				continue;
			}

			$terms = get_the_terms( $post, $taxonomy );

			foreach ( is_array( $terms ) ? $terms : array() as $term ) {
				$link = get_term_link( $term );
				self::add( is_wp_error( $link ) ? '' : $link );
			}
		}

		/**
		 * Thêm URL cần xoá khi một bài thay đổi.
		 *
		 * @param string[] $urls URL đang chờ.
		 * @param \WP_Post $post Bài.
		 */
		$extra = (array) apply_filters( 'saha_cf_purge_post_urls', array(), $post );

		foreach ( $extra as $url ) {
			self::add( (string) $url );
		}
	}

	/**
	 * Trang cửa hàng.
	 */
	private static function add_shop(): void {
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			self::add( (string) wc_get_page_permalink( 'shop' ) );
		}
	}

	/*
	 * ---------------------------------------------------------------------
	 * Hàng đợi + gửi
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Thêm URL vào hàng đợi (chỉ URL cùng site).
	 *
	 * @param string $url URL.
	 */
	public static function add( string $url ): void {
		if ( '' === $url || self::$everything ) {
			return;
		}

		$home = wp_parse_url( home_url( '/' ), PHP_URL_HOST );

		if ( wp_parse_url( $url, PHP_URL_HOST ) !== $home ) {
			return;
		}

		self::$urls[ $url ] = true;
		self::schedule();
	}

	/**
	 * Xoá toàn bộ cache zone ở cuối request.
	 */
	public static function purge_everything(): void {
		self::$everything = true;
		self::$urls       = array();
		self::schedule();
	}

	/**
	 * Gắn hook shutdown một lần.
	 */
	private static function schedule(): void {
		if ( ! self::$scheduled ) {
			self::$scheduled = true;
			add_action( 'shutdown', array( __CLASS__, 'flush' ) );
		}
	}

	/**
	 * Gửi các lệnh purge đang chờ.
	 */
	public static function flush(): void {
		$payloads = self::payloads( array_keys( self::$urls ), self::$everything );

		self::$urls       = array();
		self::$everything = false;
		self::$scheduled  = false;

		if ( ! $payloads || ! self::configured() ) {
			return;
		}

		// Trả trang cho người dùng trước, gọi API sau (PHP-FPM).
		if ( function_exists( 'fastcgi_finish_request' ) && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			fastcgi_finish_request();
		}

		self::send( $payloads );
	}

	/**
	 * Thân lệnh purge: toàn bộ, hoặc URL chia lô ≤ 30.
	 *
	 * @param string[] $urls       URL.
	 * @param bool     $everything Xoá toàn bộ.
	 * @return array<int, array<string, mixed>>
	 */
	public static function payloads( array $urls, bool $everything ): array {
		if ( $everything ) {
			return array( array( 'purge_everything' => true ) );
		}

		$urls = array_values( array_unique( array_filter( array_map( 'strval', $urls ) ) ) );

		return array_map(
			static fn( array $chunk ): array => array( 'files' => $chunk ),
			array_chunk( $urls, self::MAX_FILES )
		);
	}

	/**
	 * Gọi API Cloudflare.
	 *
	 * @param array<int, array<string, mixed>> $payloads Thân lệnh.
	 * @return array{ok: bool, message: string}
	 */
	public static function send( array $payloads ): array {
		$endpoint = 'https://api.cloudflare.com/client/v4/zones/' . rawurlencode( self::zone() ) . '/purge_cache';
		$ok       = true;
		$message  = '';
		$count    = 0;

		foreach ( $payloads as $payload ) {
			$response = wp_remote_post(
				$endpoint,
				array(
					'timeout' => 8,
					'headers' => array(
						'Authorization' => 'Bearer ' . self::token(),
						'Content-Type'  => 'application/json',
					),
					'body'    => (string) wp_json_encode( $payload ),
				)
			);

			$body = is_wp_error( $response ) ? array() : json_decode( (string) wp_remote_retrieve_body( $response ), true );

			if ( is_wp_error( $response ) || empty( $body['success'] ) ) {
				$ok      = false;
				$message = is_wp_error( $response ) ? $response->get_error_message() : (string) ( $body['errors'][0]['message'] ?? 'HTTP ' . wp_remote_retrieve_response_code( $response ) );
				Logger::error( 'Cloudflare purge lỗi: ' . $message, 'cloudflare', array( 'payload' => $payload ) );
				continue;
			}

			$count += isset( $payload['files'] ) ? count( (array) $payload['files'] ) : 0;
		}

		$everything = isset( $payloads[0]['purge_everything'] );

		update_option(
			self::STATUS_OPTION,
			array(
				'time'    => time(),
				'ok'      => $ok,
				'scope'   => $everything ? 'all' : $count . ' URL',
				'message' => $message,
			),
			false
		);

		return array(
			'ok'      => $ok,
			'message' => $ok ? ( $everything ? 'Đã xoá toàn bộ cache Cloudflare.' : sprintf( 'Đã xoá %d URL trên Cloudflare.', $count ) ) : $message,
		);
	}

	/*
	 * ---------------------------------------------------------------------
	 * Nút trên thanh admin
	 * ---------------------------------------------------------------------
	 */

	/**
	 * "Xoá cache Cloudflare" trên thanh admin (quản trị viên).
	 *
	 * @param \WP_Admin_Bar $bar Thanh admin.
	 */
	public function admin_bar( $bar ): void {
		if ( ! current_user_can( 'manage_options' ) || ! is_object( $bar ) ) {
			return;
		}

		$bar->add_node(
			array(
				'id'    => 'saha-cf-purge',
				'title' => __( 'Xoá cache Cloudflare', 'saha-core' ),
				'href'  => wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ADMIN_ACTION ), self::ADMIN_ACTION ),
			)
		);
	}

	/**
	 * Xử lý nút: xoá toàn bộ ngay rồi quay lại trang trước.
	 */
	public function handle_admin_purge(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Không có quyền.', 'saha-core' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::ADMIN_ACTION );

		$result = self::send( self::payloads( array(), true ) );

		set_transient( 'saha_cf_notice_' . get_current_user_id(), $result, MINUTE_IN_SECONDS );

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/**
	 * Thông báo sau khi bấm nút.
	 */
	public function admin_notice(): void {
		$key    = 'saha_cf_notice_' . get_current_user_id();
		$result = get_transient( $key );

		if ( ! is_array( $result ) ) {
			return;
		}

		delete_transient( $key );

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			! empty( $result['ok'] ) ? 'success' : 'error',
			esc_html( (string) ( $result['message'] ?? '' ) )
		);
	}
}
