<?php
/**
 * Theme Options — đọc / ghi.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\ThemeOptions;

use Saha\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Repository.
 *
 * Một option duy nhất `saha_theme_options` (spec SCC §64). Field khai báo
 * `storage` (ví dụ chế độ catalogue) được đọc/ghi thẳng ở nơi lưu gốc →
 * không bao giờ có hai nguồn sự thật cho cùng một cài đặt.
 */
final class Repository {

	public const OPTION = 'saha_theme_options';

	/**
	 * Cache trong request.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private static ?array $cache = null;

	/**
	 * Toàn bộ giá trị (đã trộn mặc định).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$values = Schema::defaults();

		foreach ( Schema::groups() as $group => $definition ) {
			foreach ( (array) $definition['fields'] as $key => $field ) {
				if ( ! empty( $field['storage'] ) ) {
					$values[ $group ][ $key ] = self::readStorage( (string) $field['storage'], $field['default'] ?? null );
					continue;
				}

				if ( isset( $stored[ $group ] ) && is_array( $stored[ $group ] ) && array_key_exists( $key, $stored[ $group ] ) ) {
					$values[ $group ][ $key ] = $stored[ $group ][ $key ];
				}
			}
		}

		/**
		 * Lọc Theme Options khi đọc (spec SCC §78).
		 *
		 * @param array<string, array<string, mixed>> $values Giá trị.
		 */
		self::$cache = (array) apply_filters( 'saha_theme_options', $values );

		return self::$cache;
	}

	/**
	 * Một giá trị theo đường dẫn "nhóm.field".
	 *
	 * @param string $path     Ví dụ `colors.primary`.
	 * @param mixed  $fallback Giá trị khi không có.
	 * @return mixed
	 */
	public static function get( string $path, $fallback = null ) {
		[ $group, $key ] = array_pad( explode( '.', $path, 2 ), 2, '' );

		$all = self::all();

		return $all[ $group ][ $key ] ?? $fallback;
	}

	/**
	 * Lưu giá trị gửi lên từ admin.
	 *
	 * Chỉ nhận field có trong schema; field không hợp lệ giữ giá trị cũ và
	 * được trả về trong `errors` (khoá "nhóm.field") để UI đánh dấu.
	 *
	 * @param array<string, mixed> $input Giá trị thô, lồng theo nhóm.
	 * @return array{values: array<string, array<string, mixed>>, errors: array<string, string>, changed: bool}
	 */
	public static function save( array $input ): array {
		$current = self::all();
		$next    = $current;
		$errors  = array();

		foreach ( Schema::groups() as $group => $definition ) {
			if ( ! isset( $input[ $group ] ) || ! is_array( $input[ $group ] ) ) {
				continue;
			}

			foreach ( (array) $definition['fields'] as $key => $field ) {
				if ( ! array_key_exists( $key, $input[ $group ] ) ) {
					continue;
				}

				// Field cần quyền riêng (CSS tuỳ chỉnh cần edit_css) — bỏ qua im lặng nếu thiếu quyền.
				if ( ! empty( $field['capability'] ) && ! current_user_can( (string) $field['capability'] ) ) {
					continue;
				}

				try {
					$next[ $group ][ $key ] = Sanitizer::field( $input[ $group ][ $key ], $field );
				} catch ( InvalidValue $e ) {
					$errors[ $group . '.' . $key ] = $e->getMessage();
				}
			}
		}

		$changed = $next !== $current;

		if ( $changed ) {
			self::persist( $next );
		}

		return array(
			'values'  => self::all(),
			'errors'  => $errors,
			'changed' => $changed,
		);
	}

	/**
	 * Ghi xuống database: field `storage` về nơi gốc, phần còn lại vào option.
	 *
	 * @param array<string, array<string, mixed>> $values Giá trị sạch.
	 */
	private static function persist( array $values ): void {
		$own = array();

		foreach ( Schema::groups() as $group => $definition ) {
			foreach ( (array) $definition['fields'] as $key => $field ) {
				if ( ! array_key_exists( $key, $values[ $group ] ?? array() ) ) {
					continue;
				}

				if ( ! empty( $field['storage'] ) ) {
					self::writeStorage( (string) $field['storage'], $values[ $group ][ $key ] );
					continue;
				}

				$own[ $group ][ $key ] = $values[ $group ][ $key ];
			}
		}

		update_option( self::OPTION, $own, true );
		self::flush();

		/**
		 * Theme Options vừa được lưu — sinh lại CSS toàn cục.
		 *
		 * @param array<string, array<string, mixed>> $values Giá trị mới.
		 */
		do_action( 'saha_theme_options_saved', self::all() );
	}

	/**
	 * Đọc field lưu ở nơi khác. Hiện hỗ trợ `saha_core_settings.<key>`.
	 *
	 * @param string $storage  Đường dẫn.
	 * @param mixed  $fallback Mặc định.
	 * @return mixed
	 */
	private static function readStorage( string $storage, $fallback ) {
		[ $source, $key ] = array_pad( explode( '.', $storage, 2 ), 2, '' );

		if ( Settings::OPTION === $source ) {
			return Settings::get( $key, $fallback );
		}

		return $fallback;
	}

	/**
	 * Ghi field lưu ở nơi khác.
	 *
	 * @param string $storage Đường dẫn.
	 * @param mixed  $value   Giá trị đã sanitize.
	 */
	private static function writeStorage( string $storage, $value ): void {
		[ $source, $key ] = array_pad( explode( '.', $storage, 2 ), 2, '' );

		if ( Settings::OPTION !== $source ) {
			return;
		}

		$settings         = Settings::all();
		$settings[ $key ] = $value;

		// Qua update_option để Settings tự xoá cache của nó (hook update_option_*).
		update_option( Settings::OPTION, $settings );
	}

	/**
	 * Xoá cache trong request.
	 */
	public static function flush(): void {
		self::$cache = null;
	}
}
