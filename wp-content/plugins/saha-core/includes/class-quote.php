<?php
/**
 * Quote: yêu cầu báo giá — validate, repository, service.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Quote.
 *
 * Nguyên tắc:
 * - Không tin product_id/product_name/sku từ client: chỉ nhận product_id,
 *   còn tên và SKU luôn đọc lại từ database (spec §78).
 * - Chống double submit ở backend: cùng số điện thoại + cùng sản phẩm trong
 *   DUPLICATE_WINDOW giây thì trả lại bản ghi cũ, không tạo mới (spec §84).
 * - Mọi query qua $wpdb->prepare() hoặc $wpdb->insert/update (spec §77).
 */
final class Quote {

	/**
	 * Cửa sổ chống trùng (giây).
	 */
	private const DUPLICATE_WINDOW = 10 * MINUTE_IN_SECONDS;

	/**
	 * Số ghi chú tối đa lưu trên một bản ghi.
	 */
	private const MAX_NOTES = 100;

	/**
	 * Trạng thái hợp lệ (spec §12).
	 *
	 * @return array<string, string>
	 */
	public static function statuses(): array {
		return array(
			'new'       => __( 'Mới', 'saha-core' ),
			'contacted' => __( 'Đã liên hệ', 'saha-core' ),
			'quoted'    => __( 'Đã báo giá', 'saha-core' ),
			'won'       => __( 'Thành công', 'saha-core' ),
			'lost'      => __( 'Thất bại', 'saha-core' ),
		);
	}

	/**
	 * Tên bảng.
	 */
	public static function table(): string {
		return Migrator::table( 'quotes' );
	}

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_filter( 'saha_core_dashboard_stats', array( $this, 'add_dashboard_stats' ) );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Validate
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Validate + sanitize payload từ form.
	 *
	 * @param array<string, mixed> $input Payload thô.
	 * @return array{data: array<string, mixed>, errors: array<string, string>}
	 */
	public static function validate( array $input ): array {
		$errors = array();

		$data = array(
			'customer_name' => Security::sanitize_by_type( $input['name'] ?? '', 'text' ),
			'phone'         => Security::sanitize_phone( (string) ( $input['phone'] ?? '' ) ),
			'email'         => sanitize_email( (string) ( $input['email'] ?? '' ) ),
			'company'       => Security::sanitize_by_type( $input['company'] ?? '', 'text' ),
			'product_id'    => absint( $input['product_id'] ?? 0 ),
			'product_name'  => '',
			'sku'           => '',
			'quantity'      => Security::sanitize_by_type( $input['quantity'] ?? '', 'text' ),
			'message'       => Security::sanitize_by_type( $input['message'] ?? '', 'textarea' ),
			'source_url'    => self::sanitize_source_url( (string) ( $input['source_url'] ?? '' ) ),
		);

		if ( '' === $data['customer_name'] ) {
			$errors['name'] = __( 'Vui lòng nhập họ tên.', 'saha-core' );
		} elseif ( mb_strlen( $data['customer_name'] ) > 191 ) {
			$errors['name'] = __( 'Họ tên quá dài.', 'saha-core' );
		}

		// Không ép định dạng số điện thoại quá chặt (spec §65) — chỉ cần đủ chữ số.
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

		if ( mb_strlen( $data['company'] ) > 191 ) {
			$errors['company'] = __( 'Tên công ty quá dài.', 'saha-core' );
		}

		if ( mb_strlen( $data['quantity'] ) > 100 ) {
			$errors['quantity'] = __( 'Số lượng quá dài.', 'saha-core' );
		}

		if ( mb_strlen( $data['message'] ) > 5000 ) {
			$errors['message'] = __( 'Nội dung quá dài (tối đa 5000 ký tự).', 'saha-core' );
		}

		// Xác thực sản phẩm tồn tại — không tin hidden field (spec §78).
		if ( $data['product_id'] > 0 ) {
			$product = self::resolve_product( $data['product_id'] );

			if ( ! $product ) {
				$errors['product_id'] = __( 'Sản phẩm không tồn tại hoặc đã ngừng kinh doanh.', 'saha-core' );
				$data['product_id']   = 0;
			} else {
				$data['product_name'] = $product['name'];
				$data['sku']          = $product['sku'];
			}
		}

		/**
		 * Lọc kết quả validate báo giá.
		 *
		 * @param array{data: array<string, mixed>, errors: array<string, string>} $result Kết quả.
		 * @param array<string, mixed>                                             $input  Payload thô.
		 */
		return (array) apply_filters(
			'saha_quote_validate',
			array(
				'data'   => $data,
				'errors' => $errors,
			),
			$input
		);
	}

	/**
	 * Lấy tên + SKU sản phẩm từ database.
	 *
	 * @param int $product_id Product ID.
	 * @return array{name: string, sku: string}|null
	 */
	private static function resolve_product( int $product_id ): ?array {
		if ( 'product' !== get_post_type( $product_id ) || 'publish' !== get_post_status( $product_id ) ) {
			return null;
		}

		$sku = '';

		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
			$sku     = $product ? (string) $product->get_sku() : '';
		}

		return array(
			'name' => (string) get_the_title( $product_id ),
			'sku'  => $sku,
		);
	}

	/**
	 * Chỉ giữ source URL thuộc chính website này.
	 *
	 * @param string $url URL thô.
	 */
	public static function sanitize_source_url( string $url ): string {
		$url = esc_url_raw( trim( $url ) );

		if ( '' === $url ) {
			return '';
		}

		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$url_host  = wp_parse_url( $url, PHP_URL_HOST );

		if ( ! $home_host || ! $url_host || strtolower( (string) $home_host ) !== strtolower( (string) $url_host ) ) {
			return '';
		}

		return mb_substr( $url, 0, 255 );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Service
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Tạo yêu cầu báo giá từ dữ liệu đã validate.
	 *
	 * @param array<string, mixed> $data Dữ liệu sạch từ validate().
	 * @return array{id: int, duplicate: bool}
	 */
	public static function create( array $data ): array {
		global $wpdb;

		$duplicate_id = self::find_recent_duplicate( (string) $data['phone'], (int) $data['product_id'] );

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
				'customer_name'    => (string) $data['customer_name'],
				'phone'            => (string) $data['phone'],
				'email'            => (string) $data['email'],
				'company'          => (string) $data['company'],
				'product_id'       => (int) $data['product_id'],
				'product_name'     => (string) $data['product_name'],
				'sku'              => (string) $data['sku'],
				'quantity'         => (string) $data['quantity'],
				'message'          => (string) $data['message'],
				'source_url'       => (string) $data['source_url'],
				'status'           => 'new',
				'assigned_user_id' => 0,
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			Logger::error( 'Không lưu được yêu cầu báo giá.', 'quote', array( 'db_error' => $wpdb->last_error ) );

			return array(
				'id'        => 0,
				'duplicate' => false,
			);
		}

		$quote_id = (int) $wpdb->insert_id;

		/**
		 * Vừa tạo yêu cầu báo giá (spec §63).
		 *
		 * @param int                  $quote_id Quote ID.
		 * @param array<string, mixed> $data     Dữ liệu đã lưu.
		 */
		do_action( 'saha_quote_created', $quote_id, $data );

		return array(
			'id'        => $quote_id,
			'duplicate' => false,
		);
	}

	/**
	 * Tìm bản ghi trùng gần đây.
	 *
	 * @param string $phone      Số điện thoại.
	 * @param int    $product_id Product ID.
	 */
	private static function find_recent_duplicate( string $phone, int $product_id ): int {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', (int) current_time( 'timestamp' ) - self::DUPLICATE_WINDOW ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- so sánh với created_at lưu theo giờ site.

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tên bảng nội bộ.
		$id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . self::table() . ' WHERE phone = %s AND product_id = %d AND created_at >= %s ORDER BY id DESC LIMIT 1',
				$phone,
				$product_id,
				$since
			)
		);

		return (int) $id;
	}

	/**
	 * Lấy một bản ghi.
	 *
	 * @param int $id Quote ID.
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
	 * Danh sách có lọc + phân trang — dùng cho admin list table.
	 *
	 * @param array<string, mixed> $args search, status, assigned_user_id, date_from, date_to, orderby, order, page, per_page.
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public static function query( array $args ): array {
		return Repository::query(
			self::table(),
			$args,
			array( 'customer_name', 'phone', 'email', 'company', 'product_name', 'sku' ),
			array( 'id', 'customer_name', 'status', 'created_at', 'updated_at', 'product_name' )
		);
	}

	/**
	 * Đổi trạng thái hàng loạt.
	 *
	 * @param int[]  $ids    Quote ID.
	 * @param string $status Trạng thái mới.
	 * @return int Số bản ghi đã cập nhật.
	 */
	public static function update_status( array $ids, string $status ): int {
		if ( ! isset( self::statuses()[ $status ] ) ) {
			return 0;
		}

		$updated = Repository::update_many( self::table(), $ids, array( 'status' => $status ), array( '%s' ) );

		if ( $updated > 0 ) {
			/**
			 * Trạng thái báo giá vừa đổi.
			 *
			 * @param int[]  $ids    Quote ID.
			 * @param string $status Trạng thái mới.
			 */
			do_action( 'saha_quote_status_changed', $ids, $status );
		}

		return $updated;
	}

	/**
	 * Gán sales hàng loạt.
	 *
	 * @param int[] $ids     Quote ID.
	 * @param int   $user_id User ID; 0 để bỏ gán.
	 * @return int Số bản ghi đã cập nhật.
	 */
	public static function assign( array $ids, int $user_id ): int {
		if ( $user_id > 0 && ! Repository::is_assignable_user( $user_id, Roles::CAP_QUOTES ) ) {
			return 0;
		}

		$updated = Repository::update_many( self::table(), $ids, array( 'assigned_user_id' => $user_id ), array( '%d' ) );

		if ( $updated > 0 ) {
			/**
			 * Vừa gán sales cho báo giá.
			 *
			 * @param int[] $ids     Quote ID.
			 * @param int   $user_id User ID.
			 */
			do_action( 'saha_quote_assigned', $ids, $user_id );
		}

		return $updated;
	}

	/**
	 * Thêm ghi chú nội bộ.
	 *
	 * @param int    $id   Quote ID.
	 * @param string $note Nội dung.
	 */
	public static function add_note( int $id, string $note ): bool {
		return Repository::add_note( self::table(), $id, $note, self::MAX_NOTES );
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
	 * Top sản phẩm được hỏi giá nhiều nhất (spec §86).
	 *
	 * @param int $limit Số dòng.
	 * @param int $days  Số ngày gần đây.
	 * @return array<int, array{product_id: int, product_name: string, total: int}>
	 */
	public static function top_products( int $limit = 10, int $days = 30 ): array {
		global $wpdb;

		if ( ! Migrator::table_exists( self::table() ) ) {
			return array();
		}

		$since = gmdate( 'Y-m-d H:i:s', (int) current_time( 'timestamp' ) - max( 1, $days ) * DAY_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- so với created_at giờ site.

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tên bảng nội bộ.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT product_id, MAX(product_name) AS product_name, COUNT(*) AS total
				FROM ' . self::table() . '
				WHERE product_id > 0 AND created_at >= %s
				GROUP BY product_id
				ORDER BY total DESC
				LIMIT %d',
				$since,
				max( 1, min( 50, $limit ) )
			),
			ARRAY_A
		);

		return array_map(
			static fn( array $row ): array => array(
				'product_id'   => (int) $row['product_id'],
				'product_name' => (string) $row['product_name'],
				'total'        => (int) $row['total'],
			),
			(array) $rows
		);
	}

	/**
	 * Số liệu cho dashboard.
	 *
	 * @param array<int, array<string, mixed>> $stats Số liệu hiện có.
	 * @return array<int, array<string, mixed>>
	 */
	public function add_dashboard_stats( array $stats ): array {
		$counts = self::count_by_status();

		$stats[] = array(
			'label' => __( 'Báo giá mới', 'saha-core' ),
			'value' => $counts['new'] ?? 0,
		);

		return $stats;
	}
}
