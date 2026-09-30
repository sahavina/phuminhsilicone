<?php
/**
 * WP_List_Table cho quotes / leads.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Tables;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

use Saha\Core\Lead;
use Saha\Core\Quote;
use Saha\Core\Repository;
use Saha\Core\Roles;

/**
 * Crm_Table: search, filter status/sales/nguồn/ngày, pagination, bulk action (spec §14).
 *
 * Bảng chỉ render; mọi thao tác ghi đi qua Crm::process_actions() có
 * capability + nonce.
 */
final class Crm_Table extends \WP_List_Table {

	/**
	 * quote|lead.
	 *
	 * @var string
	 */
	private string $type;

	/**
	 * Slug trang admin.
	 *
	 * @var string
	 */
	private string $page_slug;

	/**
	 * Cache tên user được phân công.
	 *
	 * @var array<int, string>
	 */
	private array $users = array();

	/**
	 * Khởi tạo.
	 *
	 * @param string $type      quote|lead.
	 * @param string $page_slug Slug trang.
	 */
	public function __construct( string $type, string $page_slug ) {
		$this->type      = 'lead' === $type ? 'lead' : 'quote';
		$this->page_slug = $page_slug;
		$this->users     = Repository::assignable_users( $this->capability() );

		parent::__construct(
			array(
				'singular' => $this->type,
				'plural'   => $this->type . 's',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Capability tương ứng.
	 */
	private function capability(): string {
		return 'lead' === $this->type ? Roles::CAP_LEADS : Roles::CAP_QUOTES;
	}

	/**
	 * Trạng thái hợp lệ.
	 *
	 * @return array<string, string>
	 */
	private function statuses(): array {
		return 'lead' === $this->type ? Lead::statuses() : Quote::statuses();
	}

	/**
	 * Đọc tham số lọc từ URL (GET, chỉ đọc).
	 *
	 * @return array<string, mixed>
	 */
	public function current_filters(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- màn hình danh sách, chỉ đọc.
		$get = static fn( string $key ): string => isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ $key ] ) ) : '';

		$status = sanitize_key( $get( 'status' ) );
		$source = sanitize_key( $get( 'source' ) );

		$filters = array(
			'search'           => $get( 's' ),
			'status'           => isset( $this->statuses()[ $status ] ) ? $status : '',
			'source'           => 'lead' === $this->type && isset( Lead::sources()[ $source ] ) ? $source : '',
			'assigned_user_id' => '' !== $get( 'assigned' ) ? (string) absint( $get( 'assigned' ) ) : '',
			'date_from'        => $get( 'date_from' ),
			'date_to'          => $get( 'date_to' ),
			'orderby'          => sanitize_key( $get( 'orderby' ) ),
			'order'            => $get( 'order' ),
		);
		// phpcs:enable

		return $filters;
	}

	/**
	 * Cột.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		$columns = array(
			'cb'         => '<input type="checkbox">',
			'id'         => __( 'ID', 'saha-core' ),
			'customer'   => __( 'Khách hàng', 'saha-core' ),
			'phone'      => __( 'Điện thoại', 'saha-core' ),
		);

		if ( 'quote' === $this->type ) {
			$columns['product']  = __( 'Sản phẩm', 'saha-core' );
			$columns['quantity'] = __( 'Số lượng', 'saha-core' );
		} else {
			$columns['source']  = __( 'Nguồn', 'saha-core' );
			$columns['message'] = __( 'Nội dung', 'saha-core' );
		}

		$columns['status']     = __( 'Trạng thái', 'saha-core' );
		$columns['assigned']   = __( 'Sales', 'saha-core' );
		$columns['created_at'] = __( 'Ngày tạo', 'saha-core' );

		return $columns;
	}

	/**
	 * Cột sort được.
	 *
	 * @return array<string, array<int, string|bool>>
	 */
	protected function get_sortable_columns(): array {
		return array(
			'id'         => array( 'id', true ),
			'customer'   => array( 'lead' === $this->type ? 'name' : 'customer_name', false ),
			'status'     => array( 'status', false ),
			'created_at' => array( 'created_at', true ),
		);
	}

	/**
	 * Bulk action.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions(): array {
		$actions = array();

		foreach ( $this->statuses() as $key => $label ) {
			/* translators: %s: trạng thái */
			$actions[ 'status_' . $key ] = sprintf( __( 'Chuyển sang: %s', 'saha-core' ), $label );
		}

		$actions['assign'] = __( 'Gán cho sales đã chọn', 'saha-core' );

		return $actions;
	}

	/**
	 * Chuẩn bị dữ liệu.
	 */
	public function prepare_items(): void {
		$per_page = $this->get_items_per_page( 'saha_' . $this->type . 's_per_page', 20 );
		$filters  = $this->current_filters();

		$filters['page']     = $this->get_pagenum();
		$filters['per_page'] = $per_page;

		$result = 'lead' === $this->type ? Lead::query( $filters ) : Quote::query( $filters );

		$this->items = $result['items'];

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'customer' );

		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $result['total'] / max( 1, $per_page ) ),
			)
		);
	}

	/**
	 * Checkbox.
	 *
	 * @param array<string, mixed> $item Dòng.
	 */
	protected function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="ids[]" value="%d">', absint( $item['id'] ) );
	}

	/**
	 * Cột khách hàng, kèm row action.
	 *
	 * @param array<string, mixed> $item Dòng.
	 */
	protected function column_customer( array $item ): string {
		$name = 'lead' === $this->type ? (string) $item['name'] : (string) $item['customer_name'];
		$url  = add_query_arg(
			array(
				'page'   => $this->page_slug,
				'action' => 'view',
				'id'     => absint( $item['id'] ),
			),
			admin_url( 'admin.php' )
		);

		$out = sprintf( '<strong><a href="%1$s">%2$s</a></strong>', esc_url( $url ), esc_html( '' !== $name ? $name : '—' ) );

		if ( '' !== (string) ( $item['company'] ?? '' ) ) {
			$out .= '<br><span class="description">' . esc_html( (string) $item['company'] ) . '</span>';
		}

		return $out . $this->row_actions(
			array(
				'view' => sprintf( '<a href="%1$s">%2$s</a>', esc_url( $url ), esc_html__( 'Xem & xử lý', 'saha-core' ) ),
			)
		);
	}

	/**
	 * Cột điện thoại — click để gọi.
	 *
	 * @param array<string, mixed> $item Dòng.
	 */
	protected function column_phone( array $item ): string {
		$phone = (string) $item['phone'];
		$href  = saha_tel_href( $phone );

		$out = '' !== $href
			? sprintf( '<a href="%1$s">%2$s</a>', esc_url( $href ), esc_html( $phone ) )
			: esc_html( $phone );

		if ( '' !== (string) $item['email'] ) {
			$out .= '<br><a href="mailto:' . esc_attr( (string) $item['email'] ) . '">' . esc_html( (string) $item['email'] ) . '</a>';
		}

		return $out;
	}

	/**
	 * Cột sản phẩm.
	 *
	 * @param array<string, mixed> $item Dòng.
	 */
	protected function column_product( array $item ): string {
		$name = (string) $item['product_name'];

		if ( '' === $name ) {
			return '—';
		}

		$product_id = absint( $item['product_id'] );
		$out        = $product_id > 0
			? sprintf( '<a href="%1$s" target="_blank" rel="noopener">%2$s</a>', esc_url( (string) get_permalink( $product_id ) ), esc_html( $name ) )
			: esc_html( $name );

		if ( '' !== (string) $item['sku'] ) {
			$out .= '<br><code>' . esc_html( (string) $item['sku'] ) . '</code>';
		}

		return $out;
	}

	/**
	 * Cột trạng thái.
	 *
	 * @param array<string, mixed> $item Dòng.
	 */
	protected function column_status( array $item ): string {
		$status = (string) $item['status'];
		$label  = $this->statuses()[ $status ] ?? $status;

		return sprintf( '<span class="saha-status saha-status--%1$s">%2$s</span>', esc_attr( sanitize_html_class( $status ) ), esc_html( $label ) );
	}

	/**
	 * Cột sales.
	 *
	 * @param array<string, mixed> $item Dòng.
	 */
	protected function column_assigned( array $item ): string {
		$user_id = absint( $item['assigned_user_id'] );

		if ( $user_id <= 0 ) {
			return '<span class="description">' . esc_html__( 'Chưa gán', 'saha-core' ) . '</span>';
		}

		return esc_html( $this->users[ $user_id ] ?? ( '#' . $user_id ) );
	}

	/**
	 * Cột nguồn (lead).
	 *
	 * @param array<string, mixed> $item Dòng.
	 */
	protected function column_source( array $item ): string {
		$source = (string) $item['source'];

		return esc_html( Lead::sources()[ $source ] ?? $source );
	}

	/**
	 * Cột nội dung rút gọn (lead).
	 *
	 * @param array<string, mixed> $item Dòng.
	 */
	protected function column_message( array $item ): string {
		return esc_html( wp_trim_words( (string) $item['message'], 14, '…' ) );
	}

	/**
	 * Cột ngày tạo theo múi giờ site (đã lưu bằng current_time — spec §91).
	 *
	 * @param array<string, mixed> $item Dòng.
	 */
	protected function column_created_at( array $item ): string {
		$timestamp = strtotime( (string) $item['created_at'] );

		if ( ! $timestamp ) {
			return '—';
		}

		return esc_html( date_i18n( get_option( 'date_format' ) . ' H:i', $timestamp ) );
	}

	/**
	 * Cột mặc định.
	 *
	 * @param array<string, mixed> $item        Dòng.
	 * @param string               $column_name Cột.
	 */
	protected function column_default( $item, $column_name ): string {
		return esc_html( (string) ( $item[ $column_name ] ?? '' ) );
	}

	/**
	 * Không có dữ liệu.
	 */
	public function no_items(): void {
		esc_html_e( 'Chưa có bản ghi nào khớp bộ lọc.', 'saha-core' );
	}

	/**
	 * Bộ lọc phía trên bảng + dropdown chọn sales cho bulk assign.
	 *
	 * @param string $which top|bottom.
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		$filters = $this->current_filters();
		?>
		<div class="alignleft actions saha-crm-filters">
			<label class="screen-reader-text" for="saha-filter-status"><?php esc_html_e( 'Lọc theo trạng thái', 'saha-core' ); ?></label>
			<select name="status" id="saha-filter-status">
				<option value=""><?php esc_html_e( 'Mọi trạng thái', 'saha-core' ); ?></option>
				<?php foreach ( $this->statuses() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filters['status'], $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>

			<?php if ( 'lead' === $this->type ) : ?>
				<label class="screen-reader-text" for="saha-filter-source"><?php esc_html_e( 'Lọc theo nguồn', 'saha-core' ); ?></label>
				<select name="source" id="saha-filter-source">
					<option value=""><?php esc_html_e( 'Mọi nguồn', 'saha-core' ); ?></option>
					<?php foreach ( Lead::sources() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filters['source'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>

			<label class="screen-reader-text" for="saha-filter-assigned"><?php esc_html_e( 'Lọc theo sales', 'saha-core' ); ?></label>
			<select name="assigned" id="saha-filter-assigned">
				<option value=""><?php esc_html_e( 'Mọi sales', 'saha-core' ); ?></option>
				<option value="0" <?php selected( $filters['assigned_user_id'], '0' ); ?>><?php esc_html_e( 'Chưa gán', 'saha-core' ); ?></option>
				<?php foreach ( $this->users as $user_id => $name ) : ?>
					<option value="<?php echo esc_attr( (string) $user_id ); ?>" <?php selected( $filters['assigned_user_id'], (string) $user_id ); ?>><?php echo esc_html( $name ); ?></option>
				<?php endforeach; ?>
			</select>

			<label class="screen-reader-text" for="saha-filter-from"><?php esc_html_e( 'Từ ngày', 'saha-core' ); ?></label>
			<input type="date" name="date_from" id="saha-filter-from" value="<?php echo esc_attr( $filters['date_from'] ); ?>">

			<label class="screen-reader-text" for="saha-filter-to"><?php esc_html_e( 'Đến ngày', 'saha-core' ); ?></label>
			<input type="date" name="date_to" id="saha-filter-to" value="<?php echo esc_attr( $filters['date_to'] ); ?>">

			<?php submit_button( __( 'Lọc', 'saha-core' ), '', 'filter_action', false ); ?>
		</div>

		<div class="alignleft actions saha-crm-assign">
			<label class="screen-reader-text" for="saha-bulk-user"><?php esc_html_e( 'Sales nhận phân công', 'saha-core' ); ?></label>
			<select name="bulk_user_id" id="saha-bulk-user">
				<option value="0"><?php esc_html_e( '— Sales cho bulk "Gán" —', 'saha-core' ); ?></option>
				<?php foreach ( $this->users as $user_id => $name ) : ?>
					<option value="<?php echo esc_attr( (string) $user_id ); ?>"><?php echo esc_html( $name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php
	}
}
