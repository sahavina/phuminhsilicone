<?php
/**
 * View: chi tiết một báo giá / lead — đổi trạng thái, gán sales, ghi chú, lịch sử khách.
 *
 * @package Saha\Core
 *
 * @var string                                $type         quote|lead.
 * @var string                                $slug         Slug trang.
 * @var string                                $title        Tiêu đề trang.
 * @var array<string, mixed>                  $record       Bản ghi.
 * @var array<string, string>                 $statuses     Trạng thái.
 * @var array<int, string>                    $users        Sales có thể gán.
 * @var array<int, array<string, mixed>>      $notes        Ghi chú.
 * @var array<string, array<int, array>>      $history      Lịch sử theo SĐT.
 * @var string                                $back_url     URL quay lại.
 * @var string                                $nonce_action Nonce action.
 * @var int|null                              $updated      Cờ vừa cập nhật.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use Saha\Core\Crm;
use Saha\Core\Lead;
use Saha\Core\Quote;

$saha_is_quote = 'quote' === $type;
$saha_name     = $saha_is_quote ? (string) $record['customer_name'] : (string) $record['name'];
$saha_date_fmt = get_option( 'date_format' ) . ' H:i';

$saha_fields = array(
	__( 'Họ tên', 'saha-core' )     => $saha_name,
	__( 'Điện thoại', 'saha-core' ) => (string) $record['phone'],
	__( 'Email', 'saha-core' )      => (string) $record['email'],
	__( 'Công ty', 'saha-core' )    => (string) $record['company'],
);

// Danh sách báo giá nhiều sản phẩm (mốc 2.5): bảng dòng riêng thay cho 3 ô sản phẩm / SKU / số lượng.
$saha_lines = $saha_is_quote ? Quote::list_rows( (int) $record['id'] ) : array();

if ( $saha_is_quote && ! $saha_lines ) {
	$saha_fields[ __( 'Sản phẩm', 'saha-core' ) ] = (string) $record['product_name'];
	$saha_fields[ __( 'SKU', 'saha-core' ) ]      = (string) $record['sku'];
	$saha_fields[ __( 'Số lượng', 'saha-core' ) ] = (string) $record['quantity'];
} elseif ( ! $saha_is_quote ) {
	$saha_source                              = (string) $record['source'];
	$saha_fields[ __( 'Nguồn', 'saha-core' ) ] = Lead::sources()[ $saha_source ] ?? $saha_source;
}

$saha_tel = saha_tel_href( (string) $record['phone'] );
?>
<div class="wrap saha-admin saha-crm-detail">
	<p><a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php echo esc_html( $title ); ?></a></p>

	<h1>
		<?php
		printf(
			'%1$s #%2$d — %3$s',
			esc_html( $saha_is_quote ? __( 'Báo giá', 'saha-core' ) : __( 'Lead', 'saha-core' ) ),
			absint( $record['id'] ),
			esc_html( '' !== $saha_name ? $saha_name : '—' )
		);
		?>
	</h1>

	<?php if ( null !== $updated ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Đã lưu thay đổi.', 'saha-core' ); ?></p></div>
	<?php endif; ?>

	<div class="saha-crm-detail__grid">
		<div class="saha-crm-detail__main">
			<div class="saha-panel">
				<h2><?php esc_html_e( 'Thông tin', 'saha-core' ); ?></h2>
				<table class="widefat striped">
					<tbody>
						<?php foreach ( $saha_fields as $saha_label => $saha_value ) : ?>
							<?php if ( '' === $saha_value ) { continue; } ?>
							<tr>
								<th scope="row"><?php echo esc_html( $saha_label ); ?></th>
								<td>
									<?php if ( __( 'Điện thoại', 'saha-core' ) === $saha_label && '' !== $saha_tel ) : ?>
										<a href="<?php echo esc_url( $saha_tel ); ?>"><?php echo esc_html( $saha_value ); ?></a>
									<?php elseif ( __( 'Email', 'saha-core' ) === $saha_label ) : ?>
										<a href="mailto:<?php echo esc_attr( $saha_value ); ?>"><?php echo esc_html( $saha_value ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $saha_value ); ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>

						<?php if ( '' !== (string) $record['source_url'] ) : ?>
							<tr>
								<th scope="row"><?php esc_html_e( 'Trang gửi', 'saha-core' ); ?></th>
								<td><a href="<?php echo esc_url( (string) $record['source_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( (string) $record['source_url'] ); ?></a></td>
							</tr>
						<?php endif; ?>

						<tr>
							<th scope="row"><?php esc_html_e( 'Ngày tạo', 'saha-core' ); ?></th>
							<td><?php echo esc_html( date_i18n( $saha_date_fmt, (int) strtotime( (string) $record['created_at'] ) ) ); ?></td>
						</tr>
					</tbody>
				</table>

				<?php if ( $saha_lines ) : ?>
					<h3>
						<?php
						/* translators: %d: số sản phẩm */
						echo esc_html( sprintf( _n( 'Danh sách báo giá (%d sản phẩm)', 'Danh sách báo giá (%d sản phẩm)', count( $saha_lines ), 'saha-core' ), count( $saha_lines ) ) );
						?>
					</h3>
					<table class="widefat striped saha-quote-lines">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Sản phẩm', 'saha-core' ); ?></th>
								<th scope="col"><?php esc_html_e( 'SKU', 'saha-core' ); ?></th>
								<th scope="col" class="saha-num"><?php esc_html_e( 'Số lượng', 'saha-core' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Ghi chú của khách', 'saha-core' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $saha_lines as $saha_line ) : ?>
								<?php $saha_pid = (int) $saha_line['product_id']; ?>
								<tr>
									<td>
										<?php if ( 'product' === get_post_type( $saha_pid ) ) : ?>
											<a href="<?php echo esc_url( (string) get_permalink( $saha_pid ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( (string) $saha_line['product_name'] ); ?></a>
										<?php else : ?>
											<?php echo esc_html( (string) $saha_line['product_name'] ); ?>
										<?php endif; ?>
									</td>
									<td><?php echo '' !== (string) $saha_line['sku'] ? '<code>' . esc_html( (string) $saha_line['sku'] ) . '</code>' : '—'; ?></td>
									<td class="saha-num"><?php echo esc_html( number_format_i18n( (int) $saha_line['quantity'] ) ); ?></td>
									<td><?php echo esc_html( '' !== (string) $saha_line['note'] ? (string) $saha_line['note'] : '—' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>

				<?php if ( '' !== trim( (string) $record['message'] ) ) : ?>
					<h3><?php esc_html_e( 'Nội dung khách gửi', 'saha-core' ); ?></h3>
					<div class="saha-crm-message"><?php echo nl2br( esc_html( (string) $record['message'] ) ); ?></div>
				<?php endif; ?>
			</div>

			<div class="saha-panel">
				<h2><?php esc_html_e( 'Ghi chú nội bộ', 'saha-core' ); ?></h2>

				<?php if ( ! $notes ) : ?>
					<p class="description"><?php esc_html_e( 'Chưa có ghi chú.', 'saha-core' ); ?></p>
				<?php else : ?>
					<ol class="saha-notes">
						<?php foreach ( array_reverse( $notes ) as $saha_note ) : ?>
							<?php $saha_author = get_userdata( (int) ( $saha_note['user_id'] ?? 0 ) ); ?>
							<li>
								<div class="saha-notes__meta">
									<strong><?php echo esc_html( $saha_author ? $saha_author->display_name : __( 'Hệ thống', 'saha-core' ) ); ?></strong>
									— <?php echo esc_html( date_i18n( $saha_date_fmt, (int) strtotime( (string) ( $saha_note['created_at'] ?? '' ) ) ) ); ?>
								</div>
								<div class="saha-notes__body"><?php echo nl2br( esc_html( (string) ( $saha_note['note'] ?? '' ) ) ); ?></div>
							</li>
						<?php endforeach; ?>
					</ol>
				<?php endif; ?>
			</div>
		</div>

		<div class="saha-crm-detail__side">
			<form method="post" class="saha-panel">
				<h2><?php esc_html_e( 'Xử lý', 'saha-core' ); ?></h2>
				<?php wp_nonce_field( $nonce_action, 'saha_detail_nonce' ); ?>
				<input type="hidden" name="id" value="<?php echo esc_attr( (string) absint( $record['id'] ) ); ?>">

				<p>
					<label for="saha-detail-status"><strong><?php esc_html_e( 'Trạng thái', 'saha-core' ); ?></strong></label><br>
					<select name="status" id="saha-detail-status" class="widefat">
						<?php foreach ( $statuses as $saha_key => $saha_label ) : ?>
							<option value="<?php echo esc_attr( $saha_key ); ?>" <?php selected( (string) $record['status'], $saha_key ); ?>><?php echo esc_html( $saha_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>

				<p>
					<label for="saha-detail-user"><strong><?php esc_html_e( 'Sales phụ trách', 'saha-core' ); ?></strong></label><br>
					<select name="assigned_user_id" id="saha-detail-user" class="widefat">
						<option value="0"><?php esc_html_e( '— Chưa gán —', 'saha-core' ); ?></option>
						<?php foreach ( $users as $saha_user_id => $saha_user_name ) : ?>
							<option value="<?php echo esc_attr( (string) $saha_user_id ); ?>" <?php selected( (int) $record['assigned_user_id'], $saha_user_id ); ?>><?php echo esc_html( $saha_user_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>

				<p>
					<label for="saha-detail-note"><strong><?php esc_html_e( 'Thêm ghi chú', 'saha-core' ); ?></strong></label><br>
					<textarea name="note" id="saha-detail-note" rows="4" class="widefat" placeholder="<?php esc_attr_e( 'Ví dụ: đã gọi, khách hẹn gọi lại thứ 5…', 'saha-core' ); ?>"></textarea>
				</p>

				<p>
					<button type="submit" name="saha_detail_submit" value="1" class="button button-primary"><?php esc_html_e( 'Lưu', 'saha-core' ); ?></button>
				</p>
			</form>

			<?php
			$saha_other_quotes = array_filter( $history['quotes'], static fn( array $row ): bool => ! ( $saha_is_quote && (int) $row['id'] === (int) $record['id'] ) );
			$saha_other_leads  = array_filter( $history['leads'], static fn( array $row ): bool => ! ( ! $saha_is_quote && (int) $row['id'] === (int) $record['id'] ) );
			?>
			<div class="saha-panel">
				<h2><?php esc_html_e( 'Lịch sử khách hàng', 'saha-core' ); ?></h2>

				<?php if ( ! $saha_other_quotes && ! $saha_other_leads ) : ?>
					<p class="description"><?php esc_html_e( 'Chưa có tương tác nào khác với số điện thoại này.', 'saha-core' ); ?></p>
				<?php endif; ?>

				<?php if ( $saha_other_quotes ) : ?>
					<h3><?php esc_html_e( 'Báo giá', 'saha-core' ); ?></h3>
					<ul class="saha-history">
						<?php foreach ( $saha_other_quotes as $saha_row ) : ?>
							<li>
								<a href="<?php echo esc_url( add_query_arg( array( 'page' => Crm::QUOTES_SLUG, 'action' => 'view', 'id' => (int) $saha_row['id'] ), admin_url( 'admin.php' ) ) ); ?>">#<?php echo esc_html( (string) $saha_row['id'] ); ?></a>
								<?php echo esc_html( (string) $saha_row['product_name'] ); ?>
								— <?php echo esc_html( Quote::statuses()[ (string) $saha_row['status'] ] ?? (string) $saha_row['status'] ); ?>
								<span class="description">(<?php echo esc_html( date_i18n( get_option( 'date_format' ), (int) strtotime( (string) $saha_row['created_at'] ) ) ); ?>)</span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<?php if ( $saha_other_leads ) : ?>
					<h3><?php esc_html_e( 'Lead', 'saha-core' ); ?></h3>
					<ul class="saha-history">
						<?php foreach ( $saha_other_leads as $saha_row ) : ?>
							<li>
								<a href="<?php echo esc_url( add_query_arg( array( 'page' => Crm::LEADS_SLUG, 'action' => 'view', 'id' => (int) $saha_row['id'] ), admin_url( 'admin.php' ) ) ); ?>">#<?php echo esc_html( (string) $saha_row['id'] ); ?></a>
								<?php echo esc_html( Lead::sources()[ (string) $saha_row['source'] ] ?? (string) $saha_row['source'] ); ?>
								— <?php echo esc_html( Lead::statuses()[ (string) $saha_row['status'] ] ?? (string) $saha_row['status'] ); ?>
								<span class="description">(<?php echo esc_html( date_i18n( get_option( 'date_format' ), (int) strtotime( (string) $saha_row['created_at'] ) ) ); ?>)</span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
