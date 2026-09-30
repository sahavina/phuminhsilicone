<?php
/**
 * Theme helpers — wrapper an toàn quanh plugin saha-core.
 *
 * Child theme không được truy cập $wpdb hay business logic trực tiếp.
 * Mọi hàm ở đây phải hoạt động (degrade) khi plugin bị tắt.
 *
 * @package Flatsome_Child_Saha
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'saha_theme_setting' ) ) {
	/**
	 * Đọc setting toàn cục, an toàn khi plugin chưa bật.
	 *
	 * @param string $key      Key setting.
	 * @param mixed  $fallback Giá trị mặc định.
	 * @return mixed
	 */
	function saha_theme_setting( string $key, $fallback = '' ) {
		if ( ! function_exists( 'saha_get_setting' ) ) {
			return $fallback;
		}

		return saha_get_setting( $key, $fallback );
	}
}

if ( ! function_exists( 'saha_theme_has_core' ) ) {
	/**
	 * Plugin saha-core có đang hoạt động?
	 */
	function saha_theme_has_core(): bool {
		return function_exists( 'saha_get_setting' );
	}
}

if ( ! function_exists( 'saha_theme_hotlines' ) ) {
	/**
	 * Danh sách hotline để render CTA.
	 *
	 * @return array<int, array<string, string>>
	 */
	function saha_theme_hotlines(): array {
		$map = array(
			'north' => __( 'Miền Bắc', 'flatsome-child' ),
			'south' => __( 'Miền Nam', 'flatsome-child' ),
		);

		$out = array();

		foreach ( $map as $region => $label ) {
			$number = (string) saha_theme_setting( 'north' === $region ? 'hotline_north' : 'hotline_south' );

			if ( '' === $number ) {
				continue;
			}

			$out[] = array(
				'region' => $region,
				'label'  => $label,
				'number' => $number,
				'href'   => function_exists( 'saha_tel_href' ) ? saha_tel_href( $number ) : '',
			);
		}

		return $out;
	}
}

if ( ! function_exists( 'saha_theme_zalo_url' ) ) {
	/**
	 * Link Zalo lấy từ settings.
	 */
	function saha_theme_zalo_url(): string {
		return function_exists( 'saha_zalo_url' ) ? saha_zalo_url() : '';
	}
}

if ( ! function_exists( 'saha_theme_cta_label' ) ) {
	/**
	 * Nhãn CTA báo giá.
	 */
	function saha_theme_cta_label(): string {
		return function_exists( 'saha_cta_label' ) ? saha_cta_label() : __( 'Yêu cầu báo giá', 'flatsome-child' );
	}
}

if ( ! function_exists( 'saha_theme_catalogue_mode' ) ) {
	/**
	 * Đang ở chế độ catalogue?
	 */
	function saha_theme_catalogue_mode(): bool {
		return function_exists( 'saha_is_catalogue_mode' ) ? saha_is_catalogue_mode() : true;
	}
}

if ( ! function_exists( 'saha_theme_part' ) ) {
	/**
	 * Render một template part với dữ liệu truyền vào.
	 *
	 * @param string              $slug Đường dẫn trong template-parts, ví dụ `common/empty-state`.
	 * @param array<string, mixed> $args Biến truyền cho part.
	 */
	function saha_theme_part( string $slug, array $args = array() ): void {
		$slug = ltrim( str_replace( array( '..', "\0" ), '', $slug ), '/' );
		$file = SAHA_THEME_PATH . '/template-parts/' . $slug . '.php';

		if ( ! is_readable( $file ) ) {
			return;
		}

		/**
		 * Lọc dữ liệu truyền vào template part.
		 *
		 * @param array<string, mixed> $args Dữ liệu.
		 * @param string               $slug Slug part.
		 */
		$args = (array) apply_filters( 'saha_theme_part_args', $args, $slug );

		load_template( $file, false, $args );
	}
}

if ( ! function_exists( 'saha_theme_asset_version' ) ) {
	/**
	 * Version asset theo filemtime để cache-bust chính xác.
	 *
	 * @param string $relative Đường dẫn tương đối trong theme, ví dụ `assets/css/main.css`.
	 */
	function saha_theme_asset_version( string $relative ): string {
		$path = SAHA_THEME_PATH . '/' . ltrim( $relative, '/' );

		return is_readable( $path ) ? (string) filemtime( $path ) : SAHA_THEME_VERSION;
	}
}
