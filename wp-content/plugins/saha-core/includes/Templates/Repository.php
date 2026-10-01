<?php
/**
 * Template (header, footer, trang sản phẩm, danh mục…): loại, điều kiện, chỉ mục.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Templates;

defined( 'ABSPATH' ) || exit;

/**
 * Repository (TECHNICAL-DESIGN §5.2, §5.5).
 *
 * Điều kiện + độ cụ thể: Conditions (mốc 2.2). Request hiện tại: RequestContext.
 *
 * Mỗi request chỉ đọc option `saha_template_map` (autoload) — không query danh sách
 * template. Chỉ mục được biên dịch lại khi template được lưu / đổi trạng thái / xoá.
 */
final class Repository {

	public const POST_TYPE     = 'saha_template';
	public const TYPE_META     = '_saha_template_type';
	public const COND_META     = '_saha_template_conditions';
	public const PRIORITY_META = '_saha_template_priority';
	public const MAP_OPTION    = 'saha_template_map';

	/**
	 * Kết quả resolve trong request (điều kiện không đổi giữa header, nội dung, footer).
	 *
	 * @var array<string, int>
	 */
	private static array $cache = array();

	/**
	 * Mọi loại template.
	 *
	 * @return array<string, string>
	 */
	public static function types(): array {
		return self::layoutTypes() + self::contentTypes();
	}

	/**
	 * Header/footer — bao quanh mọi trang.
	 *
	 * @return array<string, string>
	 */
	public static function layoutTypes(): array {
		return array(
			'header' => __( 'Header', 'saha-core' ),
			'footer' => __( 'Footer', 'saha-core' ),
		);
	}

	/**
	 * Template nội dung (mốc 2.2) — thay khung PHP của theme cho loại trang đó.
	 *
	 * @return array<string, string>
	 */
	public static function contentTypes(): array {
		return array(
			'single_product'  => __( 'Trang sản phẩm', 'saha-core' ),
			'product_archive' => __( 'Shop / danh mục sản phẩm', 'saha-core' ),
			'single_post'     => __( 'Bài viết', 'saha-core' ),
			'archive'         => __( 'Blog / chuyên mục', 'saha-core' ),
			'page'            => __( 'Trang', 'saha-core' ),
			'search'          => __( 'Kết quả tìm kiếm', 'saha-core' ),
			'404'             => __( 'Trang 404', 'saha-core' ),
		);
	}

	/**
	 * Loại của một template ('' nếu không phải template).
	 *
	 * @param int $post_id Post ID.
	 */
	public static function typeOf( int $post_id ): string {
		if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
			return '';
		}

		$type = (string) get_post_meta( $post_id, self::TYPE_META, true );

		return isset( self::types()[ $type ] ) ? $type : '';
	}

	/**
	 * Điều kiện của template.
	 *
	 * @param int $post_id Post ID.
	 * @return array{include: array<int, array<string, mixed>>, exclude: array<int, array<string, mixed>>}
	 */
	public static function conditions( int $post_id ): array {
		$raw = json_decode( (string) get_post_meta( $post_id, self::COND_META, true ), true );

		return Conditions::sanitize( is_array( $raw ) ? $raw : array(), self::typeOf( $post_id ) );
	}

	/**
	 * Template có áp cho toàn site không.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function isSiteWide( int $post_id ): bool {
		foreach ( self::conditions( $post_id )['include'] as $rule ) {
			if ( 'all' === ( $rule['rule'] ?? '' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Lưu điều kiện + ưu tiên rồi biên dịch lại chỉ mục.
	 *
	 * @param int   $post_id    Template ID.
	 * @param mixed $conditions Điều kiện thô.
	 * @param int   $priority   Ưu tiên (cao thắng khi cùng độ cụ thể).
	 */
	public static function saveConditions( int $post_id, $conditions, int $priority ): void {
		$type = self::typeOf( $post_id );

		if ( '' === $type ) {
			return;
		}

		update_post_meta( $post_id, self::COND_META, wp_slash( (string) wp_json_encode( Conditions::sanitize( $conditions, $type ) ) ) );
		update_post_meta( $post_id, self::PRIORITY_META, max( -100, min( 100, $priority ) ) );

		self::compile();
	}

	/**
	 * Dùng template cho toàn site; template cùng loại khác thôi áp toàn site.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function activate( int $post_id ): void {
		$type = self::typeOf( $post_id );

		if ( '' === $type ) {
			return;
		}

		$others = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => self::TYPE_META, // phpcs:ignore WordPress.DB.SlowDBQuery -- admin, ít bản ghi.
				'meta_value'     => $type, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);

		// Template khác cùng loại thôi áp toàn site nhưng giữ điều kiện cụ thể (danh mục, trang…).
		foreach ( $others as $other ) {
			if ( (int) $other !== $post_id ) {
				$conditions            = self::conditions( (int) $other );
				$conditions['include'] = array_values( array_filter( $conditions['include'], static fn( array $rule ): bool => 'all' !== $rule['rule'] ) );
				update_post_meta( (int) $other, self::COND_META, wp_slash( (string) wp_json_encode( $conditions ) ) );
			}
		}

		$mine            = self::conditions( $post_id );
		$mine['include'] = array_merge( array( array( 'rule' => 'all' ) ), array_values( array_filter( $mine['include'], static fn( array $rule ): bool => 'all' !== $rule['rule'] ) ) );
		update_post_meta( $post_id, self::COND_META, wp_slash( (string) wp_json_encode( $mine ) ) );

		if ( 'publish' !== get_post_status( $post_id ) ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'publish',
				)
			);
		}

		self::compile();
	}

	/**
	 * Biên dịch chỉ mục: type → rule → [templateId…] (ưu tiên cao trước, rồi ID nhỏ).
	 *
	 * @return array<string, array<string, int[]>>
	 */
	public static function compile(): array {
		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$templates = array();

		foreach ( $ids as $id ) {
			$id   = (int) $id;
			$type = self::typeOf( $id );

			if ( '' !== $type ) {
				$templates[] = array(
					'id'         => $id,
					'type'       => $type,
					'priority'   => (int) get_post_meta( $id, self::PRIORITY_META, true ),
					'conditions' => self::conditions( $id ),
				);
			}
		}

		$map = Conditions::compile( $templates );

		update_option( self::MAP_OPTION, $map, true );
		self::$cache = array();

		/**
		 * Chỉ mục template vừa được biên dịch lại.
		 *
		 * @param array<string, mixed> $map Chỉ mục.
		 */
		do_action( 'saha_template_saved', $map );

		return $map;
	}

	/**
	 * Template áp dụng cho request hiện tại.
	 *
	 * @param string $type Loại template.
	 */
	public static function resolve( string $type ): ?int {
		if ( ! array_key_exists( $type, self::$cache ) ) {
			$map = get_option( self::MAP_OPTION, null );

			// Chỉ mục cũ (mốc 1.5) hoặc chưa có → biên dịch lại một lần.
			if ( ! is_array( $map ) || Conditions::VERSION !== ( $map['v'] ?? 0 ) ) {
				$map = self::compile();
			}

			self::$cache[ $type ] = (int) Conditions::resolve( $map, $type, RequestContext::matches() );
		}

		$id = self::$cache[ $type ];

		/**
		 * Ép dùng template khác (ví dụ header riêng cho landing page).
		 *
		 * @param int|null $id   Template ID; null = dùng header/footer PHP của theme.
		 * @param string   $type Loại.
		 */
		$id = apply_filters( 'saha_template_resolved', $id > 0 ? $id : null, $type );

		return is_int( $id ) && $id > 0 && 'publish' === get_post_status( $id ) ? $id : null;
	}
}
