<?php
/**
 * Repository dùng chung cho các bảng CRM (quotes, leads).
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Repository: truy vấn có lọc/phân trang, cập nhật hàng loạt, ghi chú.
 *
 * Tên bảng và tên cột luôn đến từ code (whitelist), KHÔNG từ input người dùng.
 * Giá trị luôn đi qua $wpdb->prepare() (spec §77).
 */
final class Repository {

	/**
	 * Giới hạn số dòng mỗi trang của admin.
	 */
	public const MAX_PER_PAGE = 100;

	/**
	 * Truy vấn danh sách.
	 *
	 * @param string               $table       Tên bảng đầy đủ (nội bộ).
	 * @param array<string, mixed> $args        search, status, source, assigned_user_id, date_from, date_to, orderby, order, page, per_page.
	 * @param string[]             $search_cols Cột được phép tìm LIKE.
	 * @param string[]             $sortable    Cột được phép sort.
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public static function query( string $table, array $args, array $search_cols, array $sortable ): array {
		global $wpdb;

		$empty = array(
			'items' => array(),
			'total' => 0,
		);

		if ( ! Migrator::table_exists( $table ) ) {
			return $empty;
		}

		$where  = array( '1=1' );
		$params = array();

		$search = trim( (string) ( $args['search'] ?? '' ) );

		if ( '' !== $search && $search_cols ) {
			$like  = '%' . $wpdb->esc_like( $search ) . '%';
			$parts = array();

			foreach ( $search_cols as $col ) {
				$parts[]  = "`{$col}` LIKE %s";
				$params[] = $like;
			}

			$where[] = '( ' . implode( ' OR ', $parts ) . ' )';
		}

		foreach ( array( 'status', 'source' ) as $col ) {
			$value = (string) ( $args[ $col ] ?? '' );

			if ( '' !== $value ) {
				$where[]  = "`{$col}` = %s";
				$params[] = $value;
			}
		}

		if ( isset( $args['assigned_user_id'] ) && '' !== (string) $args['assigned_user_id'] ) {
			$where[]  = '`assigned_user_id` = %d';
			$params[] = (int) $args['assigned_user_id'];
		}

		$date_from = self::sanitize_date( (string) ( $args['date_from'] ?? '' ) );

		if ( '' !== $date_from ) {
			$where[]  = '`created_at` >= %s';
			$params[] = $date_from . ' 00:00:00';
		}

		$date_to = self::sanitize_date( (string) ( $args['date_to'] ?? '' ) );

		if ( '' !== $date_to ) {
			$where[]  = '`created_at` <= %s';
			$params[] = $date_to . ' 23:59:59';
		}

		$orderby = in_array( (string) ( $args['orderby'] ?? '' ), $sortable, true ) ? (string) $args['orderby'] : 'id';
		$order   = 'ASC' === strtoupper( (string) ( $args['order'] ?? '' ) ) ? 'ASC' : 'DESC';

		$per_page = max( 1, min( self::MAX_PER_PAGE, (int) ( $args['per_page'] ?? 20 ) ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );

		$where_sql = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$count_sql = "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}";
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );

		if ( $total <= 0 ) {
			// phpcs:enable
			return $empty;
		}

		$list_sql = "SELECT * FROM `{$table}` WHERE {$where_sql} ORDER BY `{$orderby}` {$order} LIMIT %d OFFSET %d";
		$items    = $wpdb->get_results(
			$wpdb->prepare( $list_sql, array_merge( $params, array( $per_page, ( $page - 1 ) * $per_page ) ) ),
			ARRAY_A
		);
		// phpcs:enable

		return array(
			'items' => (array) $items,
			'total' => $total,
		);
	}

	/**
	 * Cập nhật hàng loạt theo ID, luôn cập nhật updated_at.
	 *
	 * @param string               $table   Tên bảng.
	 * @param int[]                $ids     ID.
	 * @param array<string, mixed> $fields  Cột => giá trị (tên cột từ code).
	 * @param string[]             $formats Format tương ứng.
	 * @return int Số dòng đã cập nhật.
	 */
	public static function update_many( string $table, array $ids, array $fields, array $formats ): int {
		global $wpdb;

		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

		if ( ! $ids || ! $fields ) {
			return 0;
		}

		$fields['updated_at'] = current_time( 'mysql' );
		$formats[]            = '%s';

		$updated = 0;

		foreach ( $ids as $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table, $wpdb->update đã prepare.
			$result = $wpdb->update( $table, $fields, array( 'id' => $id ), $formats, array( '%d' ) );

			if ( false !== $result ) {
				$updated += (int) $result;
			}
		}

		return $updated;
	}

	/**
	 * Thêm ghi chú (JSON) vào cột notes.
	 *
	 * @param string $table     Tên bảng.
	 * @param int    $id        ID bản ghi.
	 * @param string $note      Nội dung.
	 * @param int    $max_notes Giới hạn số ghi chú giữ lại.
	 */
	public static function add_note( string $table, int $id, string $note, int $max_notes = 100 ): bool {
		global $wpdb;

		$note = sanitize_textarea_field( $note );

		if ( '' === trim( $note ) || $id <= 0 ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tên bảng nội bộ.
		$current = $wpdb->get_var( $wpdb->prepare( "SELECT notes FROM `{$table}` WHERE id = %d", $id ) );

		$notes = self::decode_notes( (string) $current );

		$notes[] = array(
			'user_id'    => get_current_user_id(),
			'note'       => mb_substr( $note, 0, 2000 ),
			'created_at' => current_time( 'mysql' ),
		);

		$notes = array_slice( $notes, - $max_notes );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table, $wpdb->update đã prepare.
		$result = $wpdb->update(
			$table,
			array(
				'notes'      => (string) wp_json_encode( $notes ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Giải mã cột notes.
	 *
	 * @param string $raw JSON.
	 * @return array<int, array<string, mixed>>
	 */
	public static function decode_notes( string $raw ): array {
		if ( '' === $raw ) {
			return array();
		}

		$decoded = json_decode( $raw, true );

		return is_array( $decoded ) ? array_values( $decoded ) : array();
	}

	/**
	 * Đếm theo trạng thái.
	 *
	 * @param string   $table    Tên bảng.
	 * @param string[] $statuses Trạng thái hợp lệ.
	 * @return array<string, int>
	 */
	public static function count_by_status( string $table, array $statuses ): array {
		global $wpdb;

		$out = array_fill_keys( $statuses, 0 );

		if ( ! Migrator::table_exists( $table ) ) {
			return $out;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tên bảng nội bộ, không có input.
		$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM `{$table}` GROUP BY status", ARRAY_A );

		foreach ( (array) $rows as $row ) {
			$status = (string) $row['status'];

			if ( array_key_exists( $status, $out ) ) {
				$out[ $status ] = (int) $row['total'];
			}
		}

		return $out;
	}

	/**
	 * User có được phép nhận phân công không (theo capability, không theo tên role).
	 *
	 * @param int    $user_id    User ID.
	 * @param string $capability Capability yêu cầu.
	 */
	public static function is_assignable_user( int $user_id, string $capability ): bool {
		$user = get_userdata( $user_id );

		return $user instanceof \WP_User && user_can( $user, $capability );
	}

	/**
	 * Danh sách user có thể được phân công.
	 *
	 * @param string $capability Capability yêu cầu.
	 * @return array<int, string> user_id => display_name
	 */
	public static function assignable_users( string $capability ): array {
		$users = get_users(
			array(
				'capability' => $capability,
				'fields'     => array( 'ID', 'display_name' ),
				'orderby'    => 'display_name',
				'number'     => 200,
			)
		);

		$out = array();

		foreach ( $users as $user ) {
			$out[ (int) $user->ID ] = (string) $user->display_name;
		}

		return $out;
	}

	/**
	 * Chỉ nhận ngày dạng Y-m-d.
	 *
	 * @param string $date Chuỗi ngày.
	 */
	private static function sanitize_date( string $date ): string {
		$date = trim( $date );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return '';
		}

		[ $y, $m, $d ] = array_map( 'intval', explode( '-', $date ) );

		return checkdate( $m, $d, $y ) ? $date : '';
	}
}
