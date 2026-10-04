<?php
/**
 * `sizes` đúng cho ảnh thẻ sản phẩm.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Performance;

use Saha\Core\ThemeOptions\Repository as ThemeOptions;

defined( 'ABSPATH' ) || exit;

/**
 * CardImageSizes.
 *
 * WooCommerce in ảnh thẻ với `sizes="(max-width: 400px) 100vw, 400px"` — thẻ thật
 * chỉ ~200px nên màn hình mật độ 1.25–2 tải bản 768px (nặng gấp 3–4 lần). Tính
 * `sizes` theo số cột của lưới (element Sản phẩm đặt qua `run()`, còn lại theo cột
 * của vòng lặp WooCommerce) và độ rộng khung nội dung. Ảnh tải lười thêm `auto,`
 * để Chrome/Edge chọn theo kích thước hiển thị thật.
 */
final class CardImageSizes {

	/**
	 * Số cột của lưới đang render (lồng nhau được).
	 *
	 * @var array<int, array{desktop: int, tablet: int, mobile: int}>
	 */
	private static array $stack = array();

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_filter( 'wp_get_attachment_image_attributes', array( __CLASS__, 'filter' ), 30 );
	}

	/**
	 * Render một lưới với số cột cho trước.
	 *
	 * @param mixed    $columns  Prop responsive (`{desktop, tablet, mobile}`) hoặc một số.
	 * @param callable $callback Hàm render.
	 * @return mixed Kết quả của $callback.
	 */
	public static function run( $columns, callable $callback ) {
		$columns = is_array( $columns ) ? $columns : array( 'desktop' => $columns );
		$desktop = max( 1, (int) ( $columns['desktop'] ?? 4 ) );
		$tablet  = max( 1, (int) ( $columns['tablet'] ?? min( 3, $desktop ) ) );
		$mobile  = max( 1, (int) ( $columns['mobile'] ?? min( 2, $tablet ) ) );

		self::$stack[] = array(
			'desktop' => $desktop,
			'tablet'  => $tablet,
			'mobile'  => $mobile,
		);

		try {
			return $callback();
		} finally {
			array_pop( self::$stack );
		}
	}

	/**
	 * Sửa `sizes` của ảnh thẻ sản phẩm.
	 *
	 * @param array<string, mixed> $attr Thuộc tính ảnh.
	 * @return array<string, mixed>
	 */
	public static function filter( $attr ): array {
		$attr = (array) $attr;

		if ( ! doing_action( 'woocommerce_before_shop_loop_item_title' ) || empty( $attr['srcset'] ) ) {
			return $attr;
		}

		$sizes = self::sizes( self::columns() );

		if ( 'lazy' === ( $attr['loading'] ?? '' ) ) {
			$sizes = 'auto, ' . $sizes;
		}

		$attr['sizes'] = $sizes;

		return $attr;
	}

	/**
	 * Chuỗi `sizes` theo số cột.
	 *
	 * @param array{desktop: int, tablet: int, mobile: int} $columns Số cột.
	 */
	public static function sizes( array $columns ): string {
		$container = (int) ThemeOptions::get( 'layout.container_width', 1200 );
		$container = $container > 0 ? $container : 1200;

		return sprintf(
			'(max-width: 767px) %dvw, (max-width: 1024px) %dvw, %dpx',
			(int) ceil( 100 / $columns['mobile'] ),
			(int) ceil( 100 / $columns['tablet'] ),
			(int) ceil( $container / $columns['desktop'] )
		);
	}

	/**
	 * Số cột hiện tại: lưới builder đang render, hoặc vòng lặp WooCommerce.
	 *
	 * @return array{desktop: int, tablet: int, mobile: int}
	 */
	private static function columns(): array {
		if ( self::$stack ) {
			return (array) end( self::$stack );
		}

		$desktop = function_exists( 'wc_get_loop_prop' ) ? max( 1, (int) wc_get_loop_prop( 'columns', 4 ) ) : 4;

		return array(
			'desktop' => $desktop,
			'tablet'  => min( 3, $desktop ),
			'mobile'  => min( 2, $desktop ),
		);
	}
}
