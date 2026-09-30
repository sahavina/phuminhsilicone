<?php
/**
 * Nạp file build của @wordpress/scripts.
 *
 * @package Saha\Builder
 */

declare( strict_types=1 );

namespace Saha\Builder;

defined( 'ABSPATH' ) || exit;

/**
 * Assets.
 *
 * Mỗi entry build ra `build/{entry}.js`, `{entry}.css` và `{entry}.asset.php`
 * (danh sách phụ thuộc `wp-*` + version do webpack tính). Đọc file asset để
 * enqueue đúng phụ thuộc — không liệt kê tay.
 */
final class Assets {

	/**
	 * Enqueue một entry.
	 *
	 * @param string $entry Tên entry (ví dụ `theme-options`).
	 * @return string Handle script; rỗng nếu chưa build.
	 */
	public static function enqueue( string $entry ): string {
		$asset_file = SAHA_BUILDER_PATH . 'build/' . $entry . '.asset.php';

		if ( ! is_readable( $asset_file ) ) {
			return '';
		}

		$asset  = require $asset_file;
		$handle = 'saha-builder-' . $entry;

		wp_enqueue_script(
			$handle,
			SAHA_BUILDER_URL . 'build/' . $entry . '.js',
			(array) ( $asset['dependencies'] ?? array() ),
			(string) ( $asset['version'] ?? SAHA_BUILDER_VERSION ),
			true
		);

		wp_set_script_translations( $handle, 'saha-builder', SAHA_BUILDER_PATH . 'languages' );

		if ( is_readable( SAHA_BUILDER_PATH . 'build/' . $entry . '.css' ) ) {
			wp_enqueue_style(
				$handle,
				SAHA_BUILDER_URL . 'build/' . $entry . '.css',
				array( 'wp-components' ),
				(string) ( $asset['version'] ?? SAHA_BUILDER_VERSION )
			);
		}

		return $handle;
	}
}
