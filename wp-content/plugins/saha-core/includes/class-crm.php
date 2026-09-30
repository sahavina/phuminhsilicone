<?php
/**
 * CRM mini trong wp-admin: báo giá, lead, liên hệ, báo cáo.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core;

defined( 'ABSPATH' ) || exit;

use Saha\Core\Tables\Crm_Table;

/**
 * Crm.
 *
 * Mọi thao tác ghi (bulk action, đổi trạng thái, gán sales, ghi chú) đều:
 *   1. kiểm tra capability,
 *   2. kiểm tra nonce,
 *   3. xử lý ở hook load-{page} rồi redirect (Post/Redirect/Get),
 * nên không có output nào trước khi xử lý xong (spec §74).
 */
final class Crm {

	public const QUOTES_SLUG  = 'saha-quotes';
	public const LEADS_SLUG   = 'saha-leads';
	public const REPORTS_SLUG = 'saha-reports';

	/**
	 * Nonce action cho form trong trang chi tiết.
	 */
	private const DETAIL_NONCE = 'saha_crm_detail';

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_action( 'saha_core_admin_menu', array( $this, 'add_menu' ) );
		add_filter( 'set-screen-option', array( $this, 'save_screen_option' ), 10, 3 );
	}

	/**
	 * Submenu theo đúng thứ tự spec §14.
	 *
	 * @param string $parent Slug menu gốc.
	 */
	public function add_menu( string $parent ): void {
		if ( post_type_exists( 'product' ) ) {
			add_submenu_page( $parent, __( 'Sản phẩm', 'saha-core' ), __( 'Sản phẩm', 'saha-core' ), Roles::CAP_PRODUCTS, 'edit.php?post_type=product' );
		}

		if ( taxonomy_exists( Taxonomies::BRAND ) ) {
			add_submenu_page( $parent, __( 'Thương hiệu', 'saha-core' ), __( 'Thương hiệu', 'saha-core' ), Roles::CAP_BRANDS, 'edit-tags.php?taxonomy=' . Taxonomies::BRAND . '&post_type=product' );
		}

		$new_quotes = Quote::new_count();

		$quotes_hook = add_submenu_page(
			$parent,
			__( 'Yêu cầu báo giá', 'saha-core' ),
			__( 'Yêu cầu báo giá', 'saha-core' ) . self::bubble( $new_quotes ),
			Roles::CAP_QUOTES,
			self::QUOTES_SLUG,
			array( $this, 'render_quotes' )
		);

		$leads_hook = add_submenu_page(
			$parent,
			__( 'Khách hàng tiềm năng', 'saha-core' ),
			__( 'Khách hàng tiềm năng', 'saha-core' ),
			Roles::CAP_LEADS,
			self::LEADS_SLUG,
			array( $this, 'render_leads' )
		);

		// "Liên hệ" = lead nguồn contact, dùng chung màn hình lead.
		add_submenu_page(
			$parent,
			__( 'Liên hệ', 'saha-core' ),
			__( 'Liên hệ', 'saha-core' ),
			Roles::CAP_LEADS,
			'admin.php?page=' . self::LEADS_SLUG . '&source=contact'
		);

		add_submenu_page(
			$parent,
			__( 'Báo cáo', 'saha-core' ),
			__( 'Báo cáo', 'saha-core' ),
			Roles::CAP_REPORTS,
			self::REPORTS_SLUG,
			array( $this, 'render_reports' )
		);

		if ( $quotes_hook ) {
			add_action( 'load-' . $quotes_hook, array( $this, 'load_quotes' ) );
		}

		if ( $leads_hook ) {
			add_action( 'load-' . $leads_hook, array( $this, 'load_leads' ) );
		}
	}

	/**
	 * Bong bóng đếm số mới trên menu.
	 *
	 * @param int $count Số lượng.
	 */
	private static function bubble( int $count ): string {
		if ( $count <= 0 ) {
			return '';
		}

		return sprintf( ' <span class="awaiting-mod"><span class="pending-count">%d</span></span>', $count );
	}

	/**
	 * Lưu screen option "số dòng mỗi trang".
	 *
	 * @param mixed  $status Giá trị cũ.
	 * @param string $option Tên option.
	 * @param mixed  $value  Giá trị mới.
	 * @return mixed
	 */
	public function save_screen_option( $status, string $option, $value ) {
		if ( in_array( $option, array( 'saha_quotes_per_page', 'saha_leads_per_page' ), true ) ) {
			return max( 1, min( Repository::MAX_PER_PAGE, (int) $value ) );
		}

		return $status;
	}

	/*
	 * ---------------------------------------------------------------------
	 * Load (xử lý trước khi render)
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Load trang báo giá.
	 */
	public function load_quotes(): void {
		$this->load( 'quote' );
	}

	/**
	 * Load trang lead.
	 */
	public function load_leads(): void {
		$this->load( 'lead' );
	}

	/**
	 * Xử lý action rồi redirect.
	 *
	 * @param string $type quote|lead.
	 */
	private function load( string $type ): void {
		$capability = 'lead' === $type ? Roles::CAP_LEADS : Roles::CAP_QUOTES;

		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'Bạn không có quyền truy cập trang này.', 'saha-core' ), 403 );
		}

		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Số dòng mỗi trang', 'saha-core' ),
				'default' => 20,
				'option'  => 'saha_' . $type . 's_per_page',
			)
		);

		$this->maybe_process_detail( $type, $capability );
		$this->maybe_process_bulk( $type, $capability );
	}

	/**
	 * Bulk action từ list table.
	 *
	 * @param string $type       quote|lead.
	 * @param string $capability Capability.
	 */
	private function maybe_process_bulk( string $type, string $capability ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- verify bằng guard_admin_action bên dưới.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['action'] ) ) : '-1';

		if ( '-1' === $action || '' === $action ) {
			$action = isset( $_REQUEST['action2'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['action2'] ) ) : '-1';
		}

		if ( '-1' === $action || '' === $action || 'view' === $action ) {
			return;
		}

		$ids = isset( $_REQUEST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_REQUEST['ids'] ) ) : array();
		// phpcs:enable

		// Nonce do WP_List_Table tự sinh: bulk-{plural}.
		if ( ! Security::guard_admin_action( $capability, 'bulk-' . $type . 's' ) ) {
			wp_die( esc_html__( 'Liên kết đã hết hạn. Vui lòng tải lại trang.', 'saha-core' ), 403 );
		}

		$count = 0;

		if ( 0 === strpos( $action, 'status_' ) ) {
			$status = substr( $action, 7 );
			$count  = 'lead' === $type ? Lead::update_status( $ids, $status ) : Quote::update_status( $ids, $status );
		} elseif ( 'assign' === $action ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- đã verify.
			$user_id = isset( $_REQUEST['bulk_user_id'] ) ? absint( $_REQUEST['bulk_user_id'] ) : 0;
			$count   = 'lead' === $type ? Lead::assign( $ids, $user_id ) : Quote::assign( $ids, $user_id );
		}

		Logger::info(
			'Bulk action CRM.',
			$type,
			array(
				'action' => $action,
				'ids'    => $ids,
				'count'  => $count,
			)
		);

		$this->redirect_back( $type, array( 'saha_updated' => $count ) );
	}

	/**
	 * Form trong trang chi tiết: đổi trạng thái, gán sales, thêm ghi chú.
	 *
	 * @param string $type       quote|lead.
	 * @param string $capability Capability.
	 */
	private function maybe_process_detail( string $type, string $capability ): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['saha_detail_submit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput -- chỉ kiểm tra có submit không.
			return;
		}

		if ( ! Security::guard_admin_action( $capability, self::DETAIL_NONCE, 'saha_detail_nonce' ) ) {
			wp_die( esc_html__( 'Liên kết đã hết hạn. Vui lòng tải lại trang.', 'saha-core' ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- đã verify.
		$id      = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$status  = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( (string) $_POST['status'] ) ) : '';
		$user_id = isset( $_POST['assigned_user_id'] ) ? absint( $_POST['assigned_user_id'] ) : 0;
		$note    = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['note'] ) ) : '';
		// phpcs:enable

		$record = 'lead' === $type ? Lead::get( $id ) : Quote::get( $id );

		if ( ! $record ) {
			wp_die( esc_html__( 'Không tìm thấy bản ghi.', 'saha-core' ), 404 );
		}

		if ( '' !== $status && $status !== (string) $record['status'] ) {
			'lead' === $type ? Lead::update_status( array( $id ), $status ) : Quote::update_status( array( $id ), $status );
		}

		if ( (int) $record['assigned_user_id'] !== $user_id ) {
			'lead' === $type ? Lead::assign( array( $id ), $user_id ) : Quote::assign( array( $id ), $user_id );
		}

		if ( '' !== trim( $note ) ) {
			'lead' === $type ? Lead::add_note( $id, $note ) : Quote::add_note( $id, $note );
		}

		$this->redirect_back(
			$type,
			array(
				'action'       => 'view',
				'id'           => $id,
				'saha_updated' => 1,
			)
		);
	}

	/**
	 * Redirect về màn hình, giữ bộ lọc hiện tại, bỏ tham số bulk.
	 *
	 * @param string               $type quote|lead.
	 * @param array<string, mixed> $args Tham số thêm.
	 * @return never
	 */
	private function redirect_back( string $type, array $args ): void {
		$slug = 'lead' === $type ? self::LEADS_SLUG : self::QUOTES_SLUG;
		$keep = array();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- chỉ giữ tham số lọc.
		foreach ( array( 's', 'status', 'source', 'assigned', 'date_from', 'date_to', 'orderby', 'order', 'paged' ) as $key ) {
			if ( isset( $_REQUEST[ $key ] ) && '' !== $_REQUEST[ $key ] && ! isset( $args['action'] ) ) {
				$keep[ $key ] = sanitize_text_field( wp_unslash( (string) $_REQUEST[ $key ] ) );
			}
		}
		// phpcs:enable

		$url = add_query_arg( array_merge( array( 'page' => $slug ), $keep, $args ), admin_url( 'admin.php' ) );

		wp_safe_redirect( $url );
		exit;
	}

	/*
	 * ---------------------------------------------------------------------
	 * Render
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Trang báo giá.
	 */
	public function render_quotes(): void {
		$this->render( 'quote', self::QUOTES_SLUG, __( 'Yêu cầu báo giá', 'saha-core' ) );
	}

	/**
	 * Trang lead.
	 */
	public function render_leads(): void {
		$this->render( 'lead', self::LEADS_SLUG, __( 'Khách hàng tiềm năng', 'saha-core' ) );
	}

	/**
	 * Render list hoặc chi tiết.
	 *
	 * @param string $type  quote|lead.
	 * @param string $slug  Slug trang.
	 * @param string $title Tiêu đề.
	 */
	private function render( string $type, string $slug, string $title ): void {
		$capability = 'lead' === $type ? Roles::CAP_LEADS : Roles::CAP_QUOTES;

		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'Bạn không có quyền truy cập trang này.', 'saha-core' ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- chỉ đọc.
		$action  = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( (string) $_GET['action'] ) ) : '';
		$id      = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$updated = isset( $_GET['saha_updated'] ) ? absint( $_GET['saha_updated'] ) : null;
		// phpcs:enable

		if ( 'view' === $action && $id > 0 ) {
			$record = 'lead' === $type ? Lead::get( $id ) : Quote::get( $id );

			if ( ! $record ) {
				wp_die( esc_html__( 'Không tìm thấy bản ghi.', 'saha-core' ), 404 );
			}

			$statuses     = 'lead' === $type ? Lead::statuses() : Quote::statuses();
			$users        = Repository::assignable_users( $capability );
			$notes        = Repository::decode_notes( (string) ( $record['notes'] ?? '' ) );
			$history      = Customer::history( (string) $record['phone'] );
			$back_url     = add_query_arg( 'page', $slug, admin_url( 'admin.php' ) );
			$nonce_action = self::DETAIL_NONCE;

			require SAHA_CORE_PATH . 'admin/views/crm-detail.php';
			return;
		}

		$table = new Crm_Table( $type, $slug );
		$table->prepare_items();

		require SAHA_CORE_PATH . 'admin/views/crm-list.php';
	}

	/**
	 * Trang báo cáo (spec §86 — không chart nặng ở phase đầu).
	 */
	public function render_reports(): void {
		if ( ! current_user_can( Roles::CAP_REPORTS ) ) {
			wp_die( esc_html__( 'Bạn không có quyền truy cập trang này.', 'saha-core' ), 403 );
		}

		$quote_counts = Quote::count_by_status();
		$lead_counts  = Lead::count_by_status();
		$top_products = Quote::top_products( 10, 30 );
		$top_searches = Settings::get( 'enable_search_log', false ) ? self::top_searches( 20, 30 ) : null;

		require SAHA_CORE_PATH . 'admin/views/crm-reports.php';
	}

	/**
	 * Từ khoá tìm kiếm phổ biến (nếu bật search log).
	 *
	 * @param int $limit Số dòng.
	 * @param int $days  Số ngày.
	 * @return array<int, array{query: string, total: int, zero: int}>
	 */
	public static function top_searches( int $limit, int $days ): array {
		global $wpdb;

		$table = Migrator::table( 'search_logs' );

		if ( ! Migrator::table_exists( $table ) ) {
			return array();
		}

		$since = gmdate( 'Y-m-d H:i:s', (int) current_time( 'timestamp' ) - max( 1, $days ) * DAY_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- so với created_at giờ site.

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tên bảng nội bộ.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT query, COUNT(*) AS total, SUM(CASE WHEN result_count = 0 THEN 1 ELSE 0 END) AS zero
				FROM `{$table}`
				WHERE created_at >= %s
				GROUP BY query
				ORDER BY total DESC
				LIMIT %d",
				$since,
				max( 1, min( 100, $limit ) )
			),
			ARRAY_A
		);

		return array_map(
			static fn( array $row ): array => array(
				'query' => (string) $row['query'],
				'total' => (int) $row['total'],
				'zero'  => (int) $row['zero'],
			),
			(array) $rows
		);
	}
}
