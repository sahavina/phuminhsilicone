<?php
/**
 * Đọc/ghi layout builder trong post meta.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder;

use Saha\Core\Builder\Schema\Document;
use Saha\Core\Builder\Schema\SchemaMigrator;

defined( 'ABSPATH' ) || exit;

/**
 * LayoutRepository (TECHNICAL-DESIGN §5.2).
 */
final class LayoutRepository {

	public const META_ENABLED = '_saha_builder_enabled';
	public const META_DATA    = '_saha_builder_data';
	public const META_VERSION = '_saha_builder_version';
	public const META_HASH    = '_saha_builder_hash';
	public const META_CSS     = '_saha_css_file';

	/**
	 * Tài liệu đã đọc trong request (the_content và enqueue cùng cần).
	 *
	 * @var array<int, Document|null>
	 */
	private static array $memo = array();

	/**
	 * Post type dùng được builder.
	 *
	 * @return string[]
	 */
	public static function postTypes(): array {
		/**
		 * Post type dùng được builder. Product thêm ở mốc 1.6.
		 *
		 * @param string[] $types Post type.
		 */
		return array_values( array_filter( (array) apply_filters( 'saha_builder_post_types', array( 'page', 'post' ) ), 'is_string' ) );
	}

	/**
	 * Post có dùng được builder không.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function supports( int $post_id ): bool {
		$type = get_post_type( $post_id );

		return false !== $type && in_array( $type, self::postTypes(), true );
	}

	/**
	 * Loại gốc của tài liệu: header có gốc riêng (chỉ nhận element Header).
	 *
	 * @param int $post_id Post ID.
	 */
	public static function rootType( int $post_id ): string {
		/**
		 * Loại gốc của tài liệu builder.
		 *
		 * @param string $root    `root` | `header-root`.
		 * @param int    $post_id Post ID.
		 */
		return (string) apply_filters( 'saha_builder_root_type', 'root', $post_id );
	}

	/**
	 * Đăng ký meta (có revision — WordPress ≥ 6.4).
	 */
	public static function registerMeta(): void {
		$keys = array(
			self::META_ENABLED => 'string',
			self::META_DATA    => 'string',
			self::META_VERSION => 'integer',
			self::META_HASH    => 'string',
		);

		foreach ( self::postTypes() as $type ) {
			$revisions = post_type_supports( $type, 'revisions' );

			foreach ( $keys as $key => $kind ) {
				register_post_meta(
					$type,
					$key,
					array(
						'type'              => $kind,
						'single'            => true,
						'show_in_rest'      => false,
						'revisions_enabled' => $revisions,
						// Chỉ ghi qua REST builder (đã sanitize); chặn ghi qua đường meta khác.
						'auth_callback'     => static fn( $allowed, $meta_key, $post_id ): bool => current_user_can( 'edit_saha_builder' ) && current_user_can( 'edit_post', (int) $post_id ),
					)
				);
			}
		}
	}

	/**
	 * Builder có đang bật cho post không.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function isEnabled( int $post_id ): bool {
		return $post_id > 0 && '1' === get_post_meta( $post_id, self::META_ENABLED, true ) && self::supports( $post_id );
	}

	/**
	 * Dữ liệu thô (đã migrate) — trả cho editor nguyên vẹn, kể cả node của add-on đã tắt.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>|null
	 */
	public static function raw( int $post_id ): ?array {
		$json = get_post_meta( $post_id, self::META_DATA, true );

		if ( ! is_string( $json ) || '' === $json ) {
			return null;
		}

		$data = json_decode( $json, true );

		return is_array( $data ) ? SchemaMigrator::migrate( $data ) : null;
	}

	/**
	 * Tài liệu để render.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function get( int $post_id ): ?Document {
		if ( ! array_key_exists( $post_id, self::$memo ) ) {
			$raw                    = self::raw( $post_id );
			self::$memo[ $post_id ] = null === $raw ? null : Document::fromArray( $raw );
		}

		return self::$memo[ $post_id ];
	}

	/**
	 * Hash của layout đang lưu ('' nếu chưa có).
	 *
	 * @param int $post_id Post ID.
	 */
	public static function hash( int $post_id ): string {
		return (string) get_post_meta( $post_id, self::META_HASH, true );
	}

	/**
	 * Ghi layout (đã sanitize).
	 *
	 * @param int      $post_id  Post ID.
	 * @param Document $document Tài liệu.
	 * @param bool     $enabled  Bật builder cho post.
	 * @return string Hash mới.
	 */
	public static function save( int $post_id, Document $document, bool $enabled ): string {
		$hash = $document->hash();

		// update_post_meta bỏ dấu \ một lần — JSON có \" \\ phải wp_slash trước.
		update_post_meta( $post_id, self::META_DATA, wp_slash( $document->toJson() ) );
		update_post_meta( $post_id, self::META_VERSION, $document->version );
		update_post_meta( $post_id, self::META_HASH, $hash );
		update_post_meta( $post_id, self::META_ENABLED, $enabled ? '1' : '0' );

		self::$memo[ $post_id ] = $document;

		return $hash;
	}

	/**
	 * Trạng thái file CSS của post.
	 *
	 * @param int $post_id Post ID.
	 * @return array{file?: string, inline?: bool, layout?: string, env?: string}
	 */
	public static function cssState( int $post_id ): array {
		$state = get_post_meta( $post_id, self::META_CSS, true );

		return is_array( $state ) ? $state : array();
	}

	/**
	 * Ghi trạng thái file CSS.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $state   Trạng thái.
	 */
	public static function setCssState( int $post_id, array $state ): void {
		update_post_meta( $post_id, self::META_CSS, $state );
	}

	/**
	 * Xoá cache trong request (test, sau khi khôi phục revision).
	 */
	public static function flush(): void {
		self::$memo = array();
	}
}
