<?php
/**
 * Lead: khách hàng tiềm năng từ mọi nguồn.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Lead.
 *
 * Mỗi form liên hệ tạo một lead. Mỗi yêu cầu báo giá cũng tạo một lead
 * nguồn `quote`, để đội sales có một danh sách khách hàng tiềm năng hợp nhất (spec §13).
 */
final class Lead {

	/**
	 * Cửa sổ chống trùng (giây).
	 */
	private const DUPLICATE_WINDOW = 10 * MINUTE_IN_SECONDS;

	/**
	 * Nguồn lead hợp lệ (spec §13).
	 *
	 * @return array<string, string>
	 */
	public static function sources(): array {
		return array(
			'website'      => __( 'Website', 'saha-core' ),
			'product'      => __( 'Trang sản phẩm', 'saha-core' ),
			'brand'        => __( 'Trang thương hiệu', 'saha-core' ),
			'landing_page' => __( 'Landing page', 'saha-core' ),
			'contact'      => __( 'Form liên hệ', 'saha-core' ),
			'quote'        => __( 'Yêu cầu báo giá', 'saha-core' ),
		);
	}

	/**
	 * Trạng thái lead.
	 *
	 * @return array<string, string>
	 */
	public static function statuses(): array {
		return array(
			'new'       => __( 'Mới', 'saha-core' ),
			'contacted' => __( 'Đã liên hệ', 'saha-core' ),
			'qualified' => __( 'Tiềm năng cao', 'saha-core' ),
			'converted' => __( 'Đã chuyển đổi', 'saha-core' ),
			'lost'      => __( 'Không tiềm năng', 'saha-core' ),
		);
	}

	/**
	 * Tên bảng.
	 */
	public static function table(): string {
		return Migrator::table( 'leads' );
	}

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_action( 'saha_quote_created', array( $this, 'create_from_quote' ), 10, 2 );
		add_filter( 'saha_core_dashboard_stats', array( $this, 'add_dashboard_stats' ) );
	}

	/**
	 * Validate payload form liên hệ (spec §32).
	 *
	 * @param array<string, mixed> $input Payload thô.
	 * @return array{data: array<string, mixed>, errors: array<string, string>}
	 */
	public static function validate( array $input ): array {
		$errors = array();

		$source = sanitize_key( (string) ( $input['source'] ?? 'contact' ) );

		$data = array(
			'name'       => Security::sanitize_by_type( $input['name'] ?? '', 'text' ),
			'phone'      => Security::sanitize_phone( (string) ( $input['phone'] ?? '' ) ),
			'email'      => sanitize_email( (string) ( $input['email'] ?? '' ) ),
			'company'    => Security::sanitize_by_type( $input['company'] ?? '', 'text' ),
			'source'     => isset( self::sources()[ $source ] ) ? $source : 'contact',
			'source_url' => Quote::sanitize_source_url( (string) ( $input['source_url'] ?? '' ) ),
			'message'    => Security::sanitize_by_type( $input['message'] ?? '', 'textarea' ),
		);

		if ( '' === $data['name'] ) {
			$errors['name'] = __( 'Vui lòng nhập họ tên.', 'saha-core' );
		} elseif ( mb_strlen( $data['name'] ) > 191 ) {
			$errors['name'] = __( 'Họ tên quá dài.', 'saha-core' );
		}

		$digits = preg_replace( '/\D/', '', $data['phone'] ) ?? '';

		if ( '' === $data['phone'] ) {
			$errors['phone'] = __( 'Vui lòng nhập số điện thoại.', 'saha-core' );
		} elseif ( strlen( $digits ) < 8 || strlen( $digits ) > 15 ) {
			$errors['phone'] = __( 'Số điện thoại không hợp lệ.', 'saha-core' );
		}

		$raw_email = trim( (string) ( $input['email'] ?? '' ) );

		if ( '' !== $raw_email && ! is_email( $data['email'] ) ) {
			$errors['email'] = __( 'Email không hợp lệ.', 'saha-core' );
			$data['email']   = '';
		}

		if ( '' === trim( $data['message'] ) ) {
			$errors['message'] = __( 'Vui lòng nhập nội dung cần tư vấn.', 'saha-core' );
		} elseif ( mb_strlen( $data['message'] ) > 5000 ) {
			$errors['message'] = __( 'Nội dung quá dài (tối đa 5000 ký tự).', 'saha-core' );
		}

		/**
		 * Lọc kết quả validate form liên hệ.
		 *
		 * @param array{data: array<string, mixed>, errors: array<string, string>} $result Kết quả.
		 * @param array<string, mixed>                                             $input  Payload thô.
		 */
		return (array) apply_filters(
			'saha_contact_validate',
			array(
				'data'   => $data,
				'errors' => $errors,
			),
			$input
		);
	}

	/**
	 * Tạo lead.
	 *
	 * @param array<string, mixed> $data Dữ liệu đã sanitize.
	 * @return array{id: int, duplicate: bool}
	 */
	public static function create( array $data ): array {
		global $wpdb;

		$source = (string) ( $data['source'] ?? 'website' );

		$duplicate_id = self::find_recent_duplicate( (string) $data['phone'], $source );

		if ( $duplicate_id > 0 ) {
			return array(
				'id'        => $duplicate_id,
				'duplicate' => true,
			);
		}

		$now = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table, $wpdb->insert đã prepare.
		$inserted = $wpdb->insert(
			self::table(),
			array(
				'name'             => (string) ( $data['name'] ?? '' ),
				'phone'            => (string) ( $data['phone'] ?? '' ),
				'email'            => (string) ( $data['email'] ?? '' ),
				'company'          => (string) ( $data['company'] ?? '' ),
				'source'           => isset( self::sources()[ $source ] ) ? $source : 'website',
				'source_url'       => (string) ( $data['source_url'] ?? '' ),
				'message'          => (string) ( $data['message'] ?? '' ),
				'status'           => 'new',
				'assigned_user_id' => 0,
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			Logger::error( 'Không lưu được lead.', 'lead', array( 'db_error' => $wpdb->last_error ) );

			return array(
				'id'        => 0,
				'duplicate' => false,
			);
		}

		$lead_id = (int) $wpdb->insert_id;

		/**
		 * Vừa tạo lead.
		 *
		 * @param int                  $lead_id Lead ID.
		 * @param array<string, mixed> $data    Dữ liệu.
		 */
		do_action( 'saha_lead_created', $lead_id, $data );

		return array(
			'id'        => $lead_id,
			'duplicate' => false,
		);
	}

	/**
	 * Mỗi báo giá tạo một lead nguồn `quote`.
	 *
	 * @param int                  $quote_id Quote ID.
	 * @param array<string, mixed> $data     Dữ liệu báo giá.
	 */
	public function create_from_quote( int $quote_id, array $data ): void {
		$summary = (string) ( $data['product_name'] ?? '' );

		if ( '' !== (string) ( $data['quantity'] ?? '' ) ) {
			$summary .= ' — ' . sprintf(
				/* translators: %s: số lượng */
				__( 'SL: %s', 'saha-core' ),
				(string) $data['quantity']
			);
		}

		$message = trim(
			sprintf(
				/* translators: 1: quote ID, 2: tóm tắt sản phẩm */
				__( 'Yêu cầu báo giá #%1$d %2$s', 'saha-core' ),
				$quote_id,
				$summary
			) . "\n" . (string) ( $data['message'] ?? '' )
		);

		self::create(
			array(
				'name'       => (string) ( $data['customer_name'] ?? '' ),
				'phone'      => (string) ( $data['phone'] ?? '' ),
				'email'      => (string) ( $data['email'] ?? '' ),
				'company'    => (string) ( $data['company'] ?? '' ),
				'source'     => 'quote',
				'source_url' => (string) ( $data['source_url'] ?? '' ),
				'message'    => $message,
			)
		);
	}

	/**
	 * Tìm lead trùng gần đây.
	 *
	 * @param string $phone  Số điện thoại.
	 * @param string $source Nguồn.
	 */
	private static function find_recent_duplicate( string $phone, string $source ): int {
		global $wpdb;

		// Lead từ báo giá được chống trùng ở tầng Quote rồi.
		if ( 'quote' === $source ) {
			return 0;
		}

		$since = gmdate( 'Y-m-d H:i:s', (int) current_time( 'timestamp' ) - self::DUPLICATE_WINDOW ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- so với created_at giờ site.

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tên bảng nội bộ.
		$id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . self::table() . ' WHERE phone = %s AND source = %s AND created_at >= %s ORDER BY id DESC LIMIT 1',
				$phone,
				$source,
				$since
			)
		);

		return (int) $id;
	}

	/**
	 * Lấy một lead.
	 *
	 * @param int $id Lead ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tên bảng nội bộ.
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Danh sách có lọc + phân trang.
	 *
	 * @param array<string, mixed> $args Tham số lọc.
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public static function query( array $args ): array {
		return Repository::query(
			self::table(),
			$args,
			array( 'name', 'phone', 'email', 'company', 'message' ),
			array( 'id', 'name', 'status', 'source', 'created_at', 'updated_at' )
		);
	}

	/**
	 * Đổi trạng thái hàng loạt.
	 *
	 * @param int[]  $ids    Lead ID.
	 * @param string $status Trạng thái.
	 */
	public static function update_status( array $ids, string $status ): int {
		if ( ! isset( self::statuses()[ $status ] ) ) {
			return 0;
		}

		return Repository::update_many( self::table(), $ids, array( 'status' => $status ), array( '%s' ) );
	}

	/**
	 * Gán sales hàng loạt.
	 *
	 * @param int[] $ids     Lead ID.
	 * @param int   $user_id User ID.
	 */
	public static function assign( array $ids, int $user_id ): int {
		if ( $user_id > 0 && ! Repository::is_assignable_user( $user_id, Roles::CAP_LEADS ) ) {
			return 0;
		}

		return Repository::update_many( self::table(), $ids, array( 'assigned_user_id' => $user_id ), array( '%d' ) );
	}

	/**
	 * Thêm ghi chú.
	 *
	 * @param int    $id   Lead ID.
	 * @param string $note Nội dung.
	 */
	public static function add_note( int $id, string $note ): bool {
		return Repository::add_note( self::table(), $id, $note );
	}

	/**
	 * Đếm theo trạng thái.
	 *
	 * @return array<string, int>
	 */
	public static function count_by_status(): array {
		return Repository::count_by_status( self::table(), array_keys( self::statuses() ) );
	}

	/**
	 * Số liệu dashboard.
	 *
	 * @param array<int, array<string, mixed>> $stats Số liệu hiện có.
	 * @return array<int, array<string, mixed>>
	 */
	public function add_dashboard_stats( array $stats ): array {
		$counts = self::count_by_status();

		$stats[] = array(
			'label' => __( 'Lead mới', 'saha-core' ),
			'value' => $counts['new'] ?? 0,
		);

		return $stats;
	}
}
