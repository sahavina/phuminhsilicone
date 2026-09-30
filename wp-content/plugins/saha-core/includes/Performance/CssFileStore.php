<?php
/**
 * Lưu CSS sinh ra thành file tĩnh trong uploads.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Performance;

use Saha\Core\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * CssFileStore.
 *
 * Spec SCC §70: không inline CSS lớn — ghi thành
 * `uploads/saha/css/{name}-{hash}.css`, tên có hash nên CDN/trình duyệt
 * không giữ bản cũ. Chỉ lưu đường dẫn TƯƠNG ĐỐI với uploads → không vỡ khi
 * đổi domain; `wp_upload_dir()` → đúng thư mục từng site trên multisite.
 *
 * Không ghi được (quyền thư mục…) → trả về chế độ inline để trang vẫn đúng.
 */
final class CssFileStore {

	public const SUBDIR = 'saha/css';

	/**
	 * Ghi CSS.
	 *
	 * @param string $name Tên logic, chỉ [a-z0-9-] (ví dụ `global`, `post-123`).
	 * @param string $css  Nội dung.
	 * @return array{file: string, hash: string, inline: bool}
	 *         file = đường dẫn tương đối trong uploads; rỗng nếu phải inline.
	 */
	public static function write( string $name, string $css ): array {
		$name = sanitize_key( $name );
		$hash = substr( md5( $css ), 0, 12 );
		$dir  = self::baseDir();

		if ( '' === $name || '' === $dir ) {
			return self::inline( $hash );
		}

		$relative = self::SUBDIR . '/' . $name . '-' . $hash . '.css';
		$path     = trailingslashit( $dir ) . $name . '-' . $hash . '.css';

		if ( ! is_file( $path ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				Logger::warning( 'Không tạo được thư mục CSS.', 'performance', array( 'dir' => $dir ) );
				return self::inline( $hash );
			}

			// Ghi file tạm rồi đổi tên: request khác không bao giờ đọc được file ghi dở.
			$tmp = $path . '.' . wp_generate_password( 6, false ) . '.tmp';

			if ( false === file_put_contents( $tmp, $css, LOCK_EX ) || ! @rename( $tmp, $path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors -- ghi file tĩnh cục bộ; lỗi xử lý ngay dưới.
				if ( is_file( $tmp ) ) {
					wp_delete_file( $tmp );
				}

				Logger::warning( 'Không ghi được file CSS — dùng CSS inline.', 'performance', array( 'file' => $relative ) );
				return self::inline( $hash );
			}
		}

		self::cleanup( $dir, $name, $path );

		return array(
			'file'   => $relative,
			'hash'   => $hash,
			'inline' => false,
		);
	}

	/**
	 * URL công khai của file (tính lúc chạy — không lưu URL tuyệt đối).
	 *
	 * @param string $relative Đường dẫn tương đối trong uploads.
	 */
	public static function url( string $relative ): string {
		$uploads = wp_upload_dir( null, false );

		if ( ! empty( $uploads['error'] ) || '' === $relative ) {
			return '';
		}

		return set_url_scheme( trailingslashit( $uploads['baseurl'] ) . ltrim( $relative, '/' ) );
	}

	/**
	 * File còn tồn tại không.
	 *
	 * @param string $relative Đường dẫn tương đối trong uploads.
	 */
	public static function exists( string $relative ): bool {
		$uploads = wp_upload_dir( null, false );

		return '' !== $relative && empty( $uploads['error'] ) && is_file( trailingslashit( $uploads['basedir'] ) . ltrim( $relative, '/' ) );
	}

	/**
	 * Thư mục tuyệt đối `uploads/saha/css`.
	 */
	private static function baseDir(): string {
		$uploads = wp_upload_dir( null, false );

		return empty( $uploads['error'] ) ? trailingslashit( $uploads['basedir'] ) . self::SUBDIR : '';
	}

	/**
	 * Xoá phiên bản cũ của cùng tên, giữ file hiện tại.
	 *
	 * @param string $dir     Thư mục.
	 * @param string $name    Tên logic.
	 * @param string $current File hiện tại.
	 */
	private static function cleanup( string $dir, string $name, string $current ): void {
		foreach ( (array) glob( trailingslashit( $dir ) . $name . '-*.css' ) as $file ) {
			// Chỉ đúng mẫu {name}-{12 hex}.css — không xoá nhầm "global-x-…" của tên khác.
			if ( is_string( $file ) && $file !== $current && preg_match( '/^' . preg_quote( $name, '/' ) . '-[0-9a-f]{12}\.css$/', basename( $file ) ) ) {
				wp_delete_file( $file );
			}
		}
	}

	/**
	 * Kết quả khi phải dùng inline.
	 *
	 * @param string $hash Hash nội dung.
	 * @return array{file: string, hash: string, inline: bool}
	 */
	private static function inline( string $hash ): array {
		return array(
			'file'   => '',
			'hash'   => $hash,
			'inline' => true,
		);
	}
}
