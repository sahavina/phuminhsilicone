<?php
/**
 * Template header/footer: loại, điều kiện, chỉ mục (TemplateMap).
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Templates;

defined( 'ABSPATH' ) || exit;

/**
 * Repository (TECHNICAL-DESIGN §5.2, §5.5).
 *
 * Mốc 1.5 chỉ dùng điều kiện `all` (toàn site). Bộ điều kiện đầy đủ (trang, danh
 * mục, sản phẩm… theo độ cụ thể) thuộc Template Builder — Phase 2; cấu trúc dữ liệu
 * `_saha_template_conditions` và `saha_template_map` đã theo đúng thiết kế.
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
	 * Loại template của mốc 1.5.
	 *
	 * @return array<string, string>
	 */
	public static function types(): array {
		return array(
			'header' => __( 'Header', 'saha-core' ),
			'footer' => __( 'Footer', 'saha-core' ),
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
		$raw  = json_decode( (string) get_post_meta( $post_id, self::COND_META, true ), true );
		$raw  = is_array( $raw ) ? $raw : array();

		return array(
			'include' => array_values( array_filter( (array) ( $raw['include'] ?? array() ), 'is_array' ) ),
			'exclude' => array_values( array_filter( (array) ( $raw['exclude'] ?? array() ), 'is_array' ) ),
		);
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

		foreach ( $others as $other ) {
			if ( (int) $other !== $post_id ) {
				update_post_meta( (int) $other, self::COND_META, wp_slash( (string) wp_json_encode( array( 'include' => array(), 'exclude' => array() ) ) ) );
			}
		}

		update_post_meta( $post_id, self::COND_META, wp_slash( (string) wp_json_encode( array( 'include' => array( array( 'rule' => 'all' ) ), 'exclude' => array() ) ) ) );

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
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$map    = array();
		$weight = array();

		foreach ( $ids as $id ) {
			$id   = (int) $id;
			$type = self::typeOf( $id );

			if ( '' === $type ) {
				continue;
			}

			$weight[ $id ] = (int) get_post_meta( $id, self::PRIORITY_META, true );

			foreach ( self::conditions( $id )['include'] as $rule ) {
				$name = sanitize_key( (string) ( $rule['rule'] ?? '' ) );

				if ( '' !== $name ) {
					$map[ $type ][ $name ][] = $id;
				}
			}
		}

		foreach ( $map as $type => $rules ) {
			foreach ( $rules as $rule => $list ) {
				usort( $list, static fn( int $a, int $b ): int => array( $weight[ $b ], $a ) <=> array( $weight[ $a ], $b ) );
				$map[ $type ][ $rule ] = array_values( array_unique( $list ) );
			}
		}

		update_option( self::MAP_OPTION, $map, true );

		/**
		 * Chỉ mục template vừa được biên dịch lại.
		 *
		 * @param array<string, array<string, int[]>> $map Chỉ mục.
		 */
		do_action( 'saha_template_saved', $map );

		return $map;
	}

	/**
	 * Template áp dụng cho request hiện tại.
	 *
	 * @param string $type header | footer.
	 */
	public static function resolve( string $type ): ?int {
		$map = get_option( self::MAP_OPTION, null );

		if ( ! is_array( $map ) ) {
			$map = self::compile();
		}

		$id = (int) ( $map[ $type ]['all'][0] ?? 0 );

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
