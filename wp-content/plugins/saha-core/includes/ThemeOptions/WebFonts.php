<?php
/**
 * Tải font web (Google Fonts) đã chọn trong Theme Options.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\ThemeOptions;

defined( 'ABSPATH' ) || exit;

/**
 * WebFonts — chỉ tải khi một nhóm chữ (nội dung, tiêu đề, menu, nút) chọn font web;
 * mặc định (font hệ thống) không có request ra ngoài.
 *
 * Một request CSS duy nhất cho mọi họ font, `display=swap` (chữ hiện ngay bằng font
 * dự phòng, không chặn LCP), preconnect tới fonts.gstatic.com.
 *
 * Element builder chọn font web khác (không có trong Theme Options) thì hiển thị
 * bằng font dự phòng trong stack — chọn font đó ở Theme Options để tải.
 */
final class WebFonts {

	public const HANDLE  = 'saha-webfonts';
	public const WEIGHTS = '400;500;600;700;800';

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 4 );
		add_filter( 'wp_resource_hints', array( $this, 'hints' ), 10, 2 );
	}

	/**
	 * Họ font web đang dùng trong Theme Options.
	 *
	 * @param array<string, mixed>|null $options Theme Options (null = đọc từ DB).
	 * @return string[]
	 */
	public static function families( ?array $options = null ): array {
		$options = $options ?? Repository::all();
		$stacks  = Schema::fontStacks();
		$out     = array();

		foreach ( (array) ( $options['typography'] ?? array() ) as $value ) {
			$key = is_array( $value ) ? (string) ( $value['fontFamily'] ?? '' ) : '';

			if ( isset( $stacks[ $key ]['google'] ) ) {
				$out[] = (string) $stacks[ $key ]['google'];
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * URL CSS của Google Fonts ('' nếu không dùng font web).
	 *
	 * @param string[] $families Họ font.
	 */
	public static function url( array $families ): string {
		if ( ! $families ) {
			return '';
		}

		$query = implode(
			'&',
			array_map(
				static fn( string $family ): string => 'family=' . str_replace( ' ', '+', $family ) . ':wght@' . self::WEIGHTS,
				$families
			)
		);

		return 'https://fonts.googleapis.com/css2?' . $query . '&display=swap';
	}

	/**
	 * Nạp CSS font.
	 */
	public function enqueue(): void {
		if ( ! current_theme_supports( 'saha-theme-options' ) ) {
			return;
		}

		$url = self::url( self::families() );

		if ( '' !== $url ) {
			wp_enqueue_style( self::HANDLE, $url, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- URL đã cố định theo họ font.
		}
	}

	/**
	 * Preconnect tới máy chủ font (chỉ khi có dùng).
	 *
	 * @param array<int, mixed> $urls          URL.
	 * @param string            $relation_type Loại.
	 * @return array<int, mixed>
	 */
	public function hints( $urls, $relation_type ): array {
		$urls = (array) $urls;

		if ( 'preconnect' === $relation_type && current_theme_supports( 'saha-theme-options' ) && self::families() ) {
			$urls[] = 'https://fonts.googleapis.com';
			$urls[] = array(
				'href'        => 'https://fonts.gstatic.com',
				'crossorigin' => 'anonymous',
			);
		}

		return $urls;
	}
}
