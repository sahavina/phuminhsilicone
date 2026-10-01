<?php
/**
 * View: báo cáo — bảng số liệu, không chart nặng (spec §86).
 *
 * @package Saha\Core
 *
 * @var array<string, int>                           $quote_counts Báo giá theo trạng thái.
 * @var array<string, int>                           $lead_counts  Lead theo trạng thái.
 * @var array<int, array<string, mixed>>             $top_products Top sản phẩm được hỏi.
 * @var array<int, array<string, mixed>>|null        $top_searches Từ khoá phổ biến; null nếu tắt log.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use Saha\Core\Admin;
use Saha\Core\Lead;
use Saha\Core\Quote;

$saha_quote_total = array_sum( $quote_counts );
$saha_won         = $quote_counts['won'] ?? 0;
$saha_closed      = $saha_won + ( $quote_counts['lost'] ?? 0 );
$saha_win_rate    = $saha_closed > 0 ? round( $saha_won / $saha_closed * 100, 1 ) : null;
?>
<div class="wrap saha-admin">
	<h1><?php esc_html_e( 'Báo cáo', 'saha-core' ); ?></h1>

	<div class="saha-cards">
		<div class="saha-card">
			<span class="saha-card__value"><?php echo esc_html( (string) $saha_quote_total ); ?></span>
			<span class="saha-card__label"><?php esc_html_e( 'Tổng yêu cầu báo giá', 'saha-core' ); ?></span>
		</div>
		<div class="saha-card">
			<span class="saha-card__value"><?php echo esc_html( (string) array_sum( $lead_counts ) ); ?></span>
			<span class="saha-card__label"><?php esc_html_e( 'Tổng lead', 'saha-core' ); ?></span>
		</div>
		<div class="saha-card">
			<span class="saha-card__value"><?php echo null === $saha_win_rate ? '—' : esc_html( $saha_win_rate . '%' ); ?></span>
			<span class="saha-card__label"><?php esc_html_e( 'Tỷ lệ chốt (thành công / đã đóng)', 'saha-core' ); ?></span>
		</div>
	</div>

	<div class="saha-report-grid">
		<div class="saha-panel">
			<h2><?php esc_html_e( 'Báo giá theo trạng thái', 'saha-core' ); ?></h2>
			<table class="widefat striped">
				<tbody>
					<?php foreach ( Quote::statuses() as $saha_key => $saha_label ) : ?>
						<tr>
							<td>
								<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'saha-quotes', 'status' => $saha_key ), admin_url( 'admin.php' ) ) ); ?>">
									<?php echo esc_html( $saha_label ); ?>
								</a>
							</td>
							<td class="saha-num"><?php echo esc_html( (string) ( $quote_counts[ $saha_key ] ?? 0 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="saha-panel">
			<h2><?php esc_html_e( 'Lead theo trạng thái', 'saha-core' ); ?></h2>
			<table class="widefat striped">
				<tbody>
					<?php foreach ( Lead::statuses() as $saha_key => $saha_label ) : ?>
						<tr>
							<td>
								<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'saha-leads', 'status' => $saha_key ), admin_url( 'admin.php' ) ) ); ?>">
									<?php echo esc_html( $saha_label ); ?>
								</a>
							</td>
							<td class="saha-num"><?php echo esc_html( (string) ( $lead_counts[ $saha_key ] ?? 0 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="saha-panel">
			<h2><?php esc_html_e( 'Top sản phẩm được hỏi giá (30 ngày)', 'saha-core' ); ?></h2>
			<?php if ( ! $top_products ) : ?>
				<p class="description"><?php esc_html_e( 'Chưa có dữ liệu.', 'saha-core' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Sản phẩm', 'saha-core' ); ?></th>
							<th scope="col" class="saha-num"><?php esc_html_e( 'Số yêu cầu', 'saha-core' ); ?></th>
							<th scope="col" class="saha-num"><?php esc_html_e( 'Tổng SL (danh sách báo giá)', 'saha-core' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $top_products as $saha_row ) : ?>
							<tr>
								<td>
									<a href="<?php echo esc_url( (string) get_edit_post_link( (int) $saha_row['product_id'] ) ); ?>">
										<?php echo esc_html( (string) $saha_row['product_name'] ); ?>
									</a>
								</td>
								<td class="saha-num"><?php echo esc_html( (string) $saha_row['total'] ); ?></td>
								<td class="saha-num"><?php echo esc_html( (int) ( $saha_row['quantity'] ?? 0 ) > 0 ? number_format_i18n( (int) $saha_row['quantity'] ) : '—' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<div class="saha-panel">
			<h2><?php esc_html_e( 'Từ khoá tìm kiếm phổ biến (30 ngày)', 'saha-core' ); ?></h2>
			<?php if ( null === $top_searches ) : ?>
				<p class="description">
					<?php esc_html_e( 'Chưa bật ghi log tìm kiếm.', 'saha-core' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Admin::MENU_SLUG . '-settings' ) ); ?>"><?php esc_html_e( 'Bật trong Cấu hình', 'saha-core' ); ?></a>
				</p>
			<?php elseif ( ! $top_searches ) : ?>
				<p class="description"><?php esc_html_e( 'Chưa có dữ liệu.', 'saha-core' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Từ khoá', 'saha-core' ); ?></th>
							<th class="saha-num"><?php esc_html_e( 'Lượt', 'saha-core' ); ?></th>
							<th class="saha-num"><?php esc_html_e( 'Không kết quả', 'saha-core' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $top_searches as $saha_row ) : ?>
							<tr class="<?php echo $saha_row['zero'] > 0 ? 'saha-row-warning' : ''; ?>">
								<td><?php echo esc_html( $saha_row['query'] ); ?></td>
								<td class="saha-num"><?php echo esc_html( (string) $saha_row['total'] ); ?></td>
								<td class="saha-num"><?php echo esc_html( (string) $saha_row['zero'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description"><?php esc_html_e( 'Dòng tô màu: khách tìm mà không ra kết quả — gợi ý thêm sản phẩm hoặc từ đồng nghĩa.', 'saha-core' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</div>
