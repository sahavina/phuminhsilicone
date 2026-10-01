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
	 * Danh sách báo giá: số dòng tối đa / số lượng tối đa mỗi dòng.
	 */
	public const MAX_ITEMS    = 50;
	public const MAX_ITEM_QTY = 100000;

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
	 * Bảng dòng sản phẩm (migration 006).
	 */
	public static function items_table(): string {
		return Migrator::table( 'quote_items' );
	}

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_filter( 'saha_core_dashboard_stats', array( $this, 'add_dashboard_stats' ) );

		// Số báo giá mới hiện trên menu ở MỌI trang admin → cache, xoá khi dữ liệu đổi.
		foreach ( array( 'saha_quote_created', 'saha_quote_status_changed' ) as $event ) {
			add_action( $event, array( __CLASS__, 'flush_new_count' ) );
		}
	}

	/**
	 * Số báo giá trạng thái "Mới", có cache ngắn.
	 */
	public static function new_count(): int {
		$cached = get_transient( 'saha_quote_new_count' );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		$count = self::count_by_status()['new'] ?? 0;

		set_transient( 'saha_quote_new_count', $count, 10 * MINUTE_IN_SECONDS );

		return $count;
	}

	/**
	 * Xoá cache số báo giá mới.
	 */
	public static function flush_new_count(): void {
		delete_transient( 'saha_quote_new_count' );
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
	 * Validate danh sách báo giá nhiều sản phẩm (spec §102).
	 *
	 * Như form một sản phẩm: chỉ tin product_id / variation_id, tên và SKU đọc lại từ database.
	 * Dòng trùng (cùng sản phẩm + biến thể) được gộp, cộng số lượng.
	 *
	 * @param mixed $raw Mảng dòng thô: [{product_id, variation_id?, quantity, note?}].
	 * @return array{items: array<int, array{product_id: int, variation_id: int, sku: string, product_name: string, quantity: int, note: string}>, errors: array<string, string>, invalid: int[]}
	 */
	public static function validate_items( $raw ): array {
		$items   = array();
		$invalid = array();
		$errors  = array();

		if ( ! is_array( $raw ) || ! $raw ) {
			return array(
				'items'   => array(),
				'errors'  => array( 'items' => __( 'Danh sách báo giá đang trống.', 'saha-core' ) ),
				'invalid' => array(),
			);
		}

		if ( count( $raw ) > self::MAX_ITEMS ) {
			return array(
				'items'   => array(),
				/* translators: %d: số dòng tối đa */
				'errors'  => array( 'items' => sprintf( __( 'Danh sách tối đa %d sản phẩm.', 'saha-core' ), self::MAX_ITEMS ) ),
				'invalid' => array(),
			);
		}

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$product_id   = absint( $row['product_id'] ?? 0 );
			$variation_id = absint( $row['variation_id'] ?? 0 );
			$quantity     = max( 1, min( self::MAX_ITEM_QTY, (int) ( $row['quantity'] ?? 1 ) ) );
			$note         = mb_substr( Security::sanitize_by_type( $row['note'] ?? '', 'text' ), 0, 255 );
			$product      = $product_id > 0 ? self::resolve_product( $product_id ) : null;

			if ( $product && $variation_id > 0 ) {
				$product = self::resolve_variation( $product_id, $variation_id, $product );
			}

			if ( ! $product ) {
				$invalid[] = $product_id;
				continue;
			}

			$key = $product_id . ':' . $variation_id;

			if ( isset( $items[ $key ] ) ) {
				$items[ $key ]['quantity'] = min( self::MAX_ITEM_QTY, $items[ $key ]['quantity'] + $quantity );
				$items[ $key ]['note']     = '' === $items[ $key ]['note'] ? $note : $items[ $key ]['note'];
				continue;
			}

			$items[ $key ] = array(
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
				'sku'          => mb_substr( $product['sku'], 0, 100 ),
				'product_name' => mb_substr( $product['name'], 0, 255 ),
				'quantity'     => $quantity,
				'note'         => $note,
			);
		}

		if ( $invalid ) {
			$errors['items'] = __( 'Một số sản phẩm trong danh sách không còn kinh doanh. Hãy xoá chúng rồi gửi lại.', 'saha-core' );
		} elseif ( ! $items ) {
			$errors['items'] = __( 'Danh sách báo giá đang trống.', 'saha-core' );
		}

		return array(
			'items'   => array_values( $items ),
			'errors'  => $errors,
			'invalid' => array_values( array_unique( $invalid ) ),
		);
	}

	/**
	 * Biến thể phải thuộc đúng sản phẩm cha và đang bán.
	 *
	 * @param int                               $product_id   Sản phẩm cha.
	 * @param int                               $variation_id Biến thể.
	 * @param array{name: string, sku: string}  $parent       Dữ liệu sản phẩm cha.
	 * @return array{name: string, sku: string}|null
	 */
	private static function resolve_variation( int $product_id, int $variation_id, array $parent ): ?array {
		if ( 'product_variation' !== get_post_type( $variation_id ) || 'publish' !== get_post_status( $variation_id ) || (int) wp_get_post_parent_id( $variation_id ) !== $product_id ) {
			return null;
		}

		$variation = function_exists( 'wc_get_product' ) ? wc_get_product( $variation_id ) : null;

		if ( ! $variation ) {
			return null;
		}

		// Tên biến thể của WooCommerce có thể không kèm thuộc tính → tự thêm "– Đen, 280 ml".
		$name  = wp_strip_all_tags( (string) $variation->get_name() );
		$attrs = function_exists( 'wc_get_formatted_variation' ) ? wp_strip_all_tags( (string) wc_get_formatted_variation( $variation, true, false, false ) ) : '';

		if ( '' !== $attrs && false === mb_strpos( $name, $attrs ) ) {
			$name .= ' – ' . $attrs;
		}

		return array(
			'name' => $name,
			'sku'  => '' !== (string) $variation->get_sku() ? (string) $variation->get_sku() : $parent['sku'],
		);
	}

	/**
	 * Tên tóm tắt của yêu cầu nhiều sản phẩm (cột product_name của bảng quotes — danh sách admin,
	 * tìm kiếm, tiêu đề email): "Tên dòng đầu (+N sản phẩm khác)".
	 *
	 * @param array<int, array<string, mixed>> $items Dòng đã validate.
	 */
	public static function summary( array $items ): string {
		if ( ! $items ) {
			return '';
		}

		$name = (string) $items[0]['product_name'];

		if ( count( $items ) > 1 ) {
			/* translators: %d: số sản phẩm còn lại */
			$name .= ' ' . sprintf( __( '(+%d sản phẩm khác)', 'saha-core' ), count( $items ) - 1 );
		}

		return mb_substr( $name, 0, 255 );
	}

	/**
	 * Chuỗi nhận diện danh sách (chống gửi trùng).
	 *
	 * @param array<int, array<string, mixed>> $items Dòng.
	 */
	public static function signature( array $items ): string {
		$parts = array_map(
			static fn( array $item ): string => (int) $item['product_id'] . ':' . (int) $item['variation_id'] . ':' . (int) $item['quantity'],
			$items
		);

		sort( $parts );

		return implode( ',', $parts );
	}

	/**
	 * Dòng sản phẩm của một yêu cầu. Yêu cầu cũ (một sản phẩm, trước mốc 2.5) không có dòng
	 * trong bảng quote_items → dựng một dòng từ chính bản ghi quotes.
	 *
	 * @param int                       $quote_id Quote ID.
	 * @param array<string, mixed>|null $quote    Bản ghi quotes (đỡ một query nếu đã có).
	 * @return array<int, array{product_id: int, variation_id: int, sku: string, product_name: string, quantity: string, note: string}>
	 */
	public static function items( int $quote_id, ?array $quote = null ): array {
		$rows = self::list_rows( $quote_id );

		if ( ! $rows ) {
			$quote ??= self::get( $quote_id );

			if ( ! $quote || ( (int) $quote['product_id'] <= 0 && '' === (string) $quote['product_name'] ) ) {
				return array();
			}

			$rows = array(
				array(
					'product_id'   => $quote['product_id'],
					'variation_id' => 0,
					'sku'          => $quote['sku'],
					'product_name' => $quote['product_name'],
					'quantity'     => $quote['quantity'],
					'note'         => '',
				),
			);
		}

		return array_map(
			static fn( array $row ): array => array(
				'product_id'   => (int) $row['product_id'],
				'variation_id' => (int) $row['variation_id'],
				'sku'          => (string) $row['sku'],
				'product_name' => (string) $row['product_name'],
				'quantity'     => (string) $row['quantity'],
				'note'         => (string) $row['note'],
			),
			$rows
		);
	}

	/**
	 * Chỉ các dòng trong bảng quote_items (rỗng = báo giá một sản phẩm kiểu cũ).
	 *
	 * @param int $quote_id Quote ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_rows( int $quote_id ): array {
		global $wpdb;

		if ( ! Migrator::table_exists( self::items_table() ) ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tên bảng nội bộ.
		return (array) $wpdb->get_results(
			$wpdb->prepare( 'SELECT product_id, variation_id, sku, product_name, quantity, note FROM ' . self::items_table() . ' WHERE quote_id = %d ORDER BY id ASC', $quote_id ),
			ARRAY_A
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
	 * Danh sách nhiều sản phẩm (`$items` từ validate_items()): cột sản phẩm của bảng quotes giữ dòng
	 * đầu + tên tóm tắt (danh sách admin, tìm kiếm, email cũ vẫn chạy), từng dòng lưu ở quote_items.
	 * Dòng được lưu TRƯỚC khi phát `saha_quote_created` → email / lead đọc được đủ danh sách.
	 *
	 * @param array<string, mixed>             $data  Dữ liệu sạch từ validate().
	 * @param array<int, array<string, mixed>> $items Dòng sản phẩm (rỗng = báo giá một sản phẩm như cũ).
	 * @return array{id: int, duplicate: bool}
	 */
	public static function create( array $data, array $items = array() ): array {
		global $wpdb;

		if ( $items ) {
			$data['product_id']   = (int) $items[0]['product_id'];
			$data['product_name'] = self::summary( $items );
			$data['sku']          = (string) $items[0]['sku'];
			$data['quantity']     = '';
		}

		$duplicate_id = $items
			? self::find_recent_list_duplicate( (string) $data['phone'], $items )
			: self::find_recent_duplicate( (string) $data['phone'], (int) $data['product_id'] );

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

		foreach ( $items as $item ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table, $wpdb->insert đã prepare.
			$ok = $wpdb->insert(
				self::items_table(),
				array(
					'quote_id'     => $quote_id,
					'product_id'   => (int) $item['product_id'],
					'variation_id' => (int) $item['variation_id'],
					'sku'          => (string) $item['sku'],
					'product_name' => (string) $item['product_name'],
					'quantity'     => (int) $item['quantity'],
					'note'         => (string) $item['note'],
					'created_at'   => $now,
				),
				array( '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s' )
			);

			if ( false === $ok ) {
				Logger::error( 'Không lưu được dòng sản phẩm của báo giá.', 'quote', array( 'quote_id' => $quote_id, 'db_error' => $wpdb->last_error ) );
			}
		}

		if ( $items ) {
			$data['items'] = $items;
		}

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
	 * Danh sách trùng gần đây: cùng số điện thoại, cùng các dòng (sản phẩm, biến thể, số lượng).
	 *
	 * @param string                           $phone Số điện thoại.
	 * @param array<int, array<string, mixed>> $items Dòng đã validate.
	 */
	private static function find_recent_list_duplicate( string $phone, array $items ): int {
		global $wpdb;

		if ( ! Migrator::table_exists( self::items_table() ) ) {
			return 0;
		}

		$since = gmdate( 'Y-m-d H:i:s', (int) current_time( 'timestamp' ) - self::DUPLICATE_WINDOW ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- so sánh với created_at lưu theo giờ site.

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tên bảng nội bộ.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM ' . self::table() . ' WHERE phone = %s AND created_at >= %s ORDER BY id DESC LIMIT 5',
				$phone,
				$since
			)
		);

		$signature = self::signature( $items );

		foreach ( (array) $ids as $id ) {
			$rows = array_map(
				static fn( array $row ): array => array(
					'product_id'   => (int) $row['product_id'],
					'variation_id' => (int) $row['variation_id'],
					'quantity'     => (int) $row['quantity'],
				),
				self::items( (int) $id )
			);

			if ( $rows && self::signature( $rows ) === $signature ) {
				return (int) $id;
			}
		}

		return 0;
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
	 * Đếm theo **số yêu cầu** có hỏi sản phẩm đó: dòng của danh sách báo giá (quote_items) + báo giá
	 * một sản phẩm cũ (cột product_id của quotes, khi yêu cầu đó không có dòng nào). `quantity` là tổng
	 * số lượng khách ghi trong danh sách (báo giá cũ ghi số lượng dạng chữ → không cộng).
	 *
	 * @return array<int, array{product_id: int, product_name: string, total: int, quantity: int}>
	 */
	public static function top_products( int $limit = 10, int $days = 30 ): array {
		global $wpdb;

		if ( ! Migrator::table_exists( self::table() ) ) {
			return array();
		}

		$since = gmdate( 'Y-m-d H:i:s', (int) current_time( 'timestamp' ) - max( 1, $days ) * DAY_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- so với created_at giờ site.
		$limit = max( 1, min( 50, $limit ) );

		if ( Migrator::table_exists( self::items_table() ) ) {
			$quotes = self::table();
			$items  = self::items_table();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tên bảng nội bộ.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT product_id, MAX(product_name) AS product_name, COUNT(DISTINCT quote_id) AS total, SUM(quantity) AS quantity
					FROM (
						SELECT i.product_id, i.product_name, i.quote_id, i.quantity
						FROM {$items} i INNER JOIN {$quotes} q ON q.id = i.quote_id
						WHERE i.product_id > 0 AND q.created_at >= %s
						UNION ALL
						SELECT q.product_id, q.product_name, q.id AS quote_id, 0 AS quantity
						FROM {$quotes} q
						WHERE q.product_id > 0 AND q.created_at >= %s
							AND NOT EXISTS ( SELECT 1 FROM {$items} x WHERE x.quote_id = q.id )
					) t
					GROUP BY product_id
					ORDER BY total DESC, quantity DESC
					LIMIT %d",
					$since,
					$since,
					$limit
				),
				ARRAY_A
			);

			return self::map_top_rows( (array) $rows );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tên bảng nội bộ.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT product_id, MAX(product_name) AS product_name, COUNT(*) AS total, 0 AS quantity
				FROM ' . self::table() . '
				WHERE product_id > 0 AND created_at >= %s
				GROUP BY product_id
				ORDER BY total DESC
				LIMIT %d',
				$since,
				$limit
			),
			ARRAY_A
		);

		return self::map_top_rows( (array) $rows );
	}

	/**
	 * Chuẩn hoá dòng báo cáo. Tên lấy theo sản phẩm hiện tại (dòng biến thể lưu tên kèm thuộc tính,
	 * báo giá danh sách lưu tên tóm tắt) — sản phẩm đã xoá thì giữ tên đã lưu.
	 *
	 * @param array<int, array<string, mixed>> $rows Dòng thô.
	 * @return array<int, array{product_id: int, product_name: string, total: int, quantity: int}>
	 */
	private static function map_top_rows( array $rows ): array {
		return array_map(
			static function ( array $row ): array {
				$id    = (int) $row['product_id'];
				$title = 'product' === get_post_type( $id ) ? (string) get_the_title( $id ) : '';

				return array(
					'product_id'   => $id,
					'product_name' => '' !== $title ? $title : (string) $row['product_name'],
					'total'        => (int) $row['total'],
					'quantity'     => (int) $row['quantity'],
				);
			},
			$rows
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
