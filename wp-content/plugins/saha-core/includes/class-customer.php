<?php
/**
 * Customer: gộp lịch sử quote + lead theo số điện thoại.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Customer.
 *
 * Không tạo bảng khách hàng riêng ở giai đoạn này: số điện thoại là khoá
 * tự nhiên để nhận diện cùng một khách qua nhiều lần hỏi giá / liên hệ.
 * Khi tích hợp CRM/ERP sau này, đây là điểm cắm duy nhất cần thay.
 */
final class Customer {

	/**
	 * Số bản ghi lịch sử tối đa mỗi loại.
	 */
	private const HISTORY_LIMIT = 20;

	/**
	 * Không cần hook.
	 */
	public function register(): void {}

	/**
	 * Chuẩn hoá số điện thoại để so khớp: chỉ giữ chữ số, đổi 84 đầu thành 0.
	 *
	 * @param string $phone Số điện thoại.
	 */
	public static function normalize_phone( string $phone ): string {
		$digits = preg_replace( '/\D/', '', $phone ) ?? '';

		if ( str_starts_with( $digits, '84' ) && strlen( $digits ) >= 11 ) {
			$digits = '0' . substr( $digits, 2 );
		}

		return $digits;
	}

	/**
	 * Lịch sử tương tác của một số điện thoại.
	 *
	 * So khớp trên cột phone đã có index (spec §12). Vì người dùng có thể
	 * nhập định dạng khác nhau, ta so cả chuỗi gốc lẫn dạng đã chuẩn hoá.
	 *
	 * @param string $phone Số điện thoại.
	 * @return array{quotes: array<int, array<string, mixed>>, leads: array<int, array<string, mixed>>}
	 */
	public static function history( string $phone ): array {
		global $wpdb;

		$out = array(
			'quotes' => array(),
			'leads'  => array(),
		);

		$normalized = self::normalize_phone( $phone );

		if ( strlen( $normalized ) < 8 ) {
			return $out;
		}

		$variants = array_values( array_unique( array( $phone, $normalized ) ) );
		$holders  = implode( ', ', array_fill( 0, count( $variants ), '%s' ) );

		$tables = array(
			'quotes' => array( Quote::table(), 'id, customer_name AS name, product_name, status, created_at' ),
			'leads'  => array( Lead::table(), 'id, name, source, status, created_at' ),
		);

		foreach ( $tables as $key => $config ) {
			[ $table, $columns ] = $config;

			if ( ! Migrator::table_exists( $table ) ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- tên bảng/cột nội bộ, giá trị đã prepare.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT {$columns} FROM `{$table}` WHERE phone IN ({$holders}) ORDER BY id DESC LIMIT %d",
					array_merge( $variants, array( self::HISTORY_LIMIT ) )
				),
				ARRAY_A
			);

			$out[ $key ] = (array) $rows;
		}

		return $out;
	}
}
