<?php
/**
 * Giá trị responsive: scalar hoặc {desktop, tablet, mobile}.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder;

defined( 'ABSPATH' ) || exit;

/**
 * Chuẩn hoá giá trị responsive.
 *
 * Quy tắc (spec §55): thiếu breakpoint thì kế thừa breakpoint lớn hơn — nên chỉ
 * lưu breakpoint có giá trị; CSS sinh ra tự kế thừa nhờ media query max-width.
 */
final class Responsive {

	public const DEVICES = array( 'desktop', 'tablet', 'mobile' );

	/**
	 * Giá trị có phải dạng {desktop?, tablet?, mobile?} không.
	 *
	 * Mảng rỗng không tính (có thể là giá trị rỗng của control dạng object).
	 *
	 * @param mixed $value Giá trị.
	 */
	public static function isResponsive( $value ): bool {
		if ( ! is_array( $value ) || array() === $value ) {
			return false;
		}

		return array() === array_diff( array_keys( $value ), self::DEVICES );
	}

	/**
	 * Áp hàm sanitize cho từng breakpoint.
	 *
	 * Scalar (hoặc object không phải dạng responsive) được hiểu là desktop.
	 *
	 * @param mixed    $value Giá trị thô.
	 * @param callable $fn    fn( mixed $v ): mixed — trả null nghĩa là "không đặt".
	 * @return array<string, mixed>|null Null khi không breakpoint nào có giá trị.
	 * @throws InvalidValue Khi một breakpoint sai (thông báo có tên breakpoint).
	 */
	public static function map( $value, callable $fn ): ?array {
		$input = self::isResponsive( $value ) ? $value : array( 'desktop' => $value );
		$out   = array();

		foreach ( self::DEVICES as $device ) {
			if ( ! array_key_exists( $device, $input ) || null === $input[ $device ] || '' === $input[ $device ] ) {
				continue;
			}

			try {
				$clean = $fn( $input[ $device ] );
			} catch ( InvalidValue $e ) {
				throw new InvalidValue( self::label( $device ) . ': ' . $e->getMessage() );
			}

			if ( null !== $clean ) {
				$out[ $device ] = $clean;
			}
		}

		return $out ? $out : null;
	}

	/**
	 * Lấy giá trị của một breakpoint (không kế thừa).
	 *
	 * @param mixed  $value  Giá trị đã chuẩn hoá.
	 * @param string $device Breakpoint.
	 * @return mixed
	 */
	public static function at( $value, string $device ) {
		if ( self::isResponsive( $value ) ) {
			return $value[ $device ] ?? null;
		}

		return 'desktop' === $device ? $value : null;
	}

	/**
	 * Tên breakpoint cho thông báo lỗi.
	 *
	 * @param string $device Breakpoint.
	 */
	private static function label( string $device ): string {
		$labels = array(
			'desktop' => __( 'Desktop', 'saha-core' ),
			'tablet'  => __( 'Tablet', 'saha-core' ),
			'mobile'  => __( 'Mobile', 'saha-core' ),
		);

		return $labels[ $device ] ?? $device;
	}
}
