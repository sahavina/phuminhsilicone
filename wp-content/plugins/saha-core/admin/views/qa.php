<?php
/**
 * View: SAHA → Kiểm tra hệ thống.
 *
 * @package Saha\Core
 *
 * @var array<int, array{group: string, label: string, status: string, detail: string}> $results Kết quả.
 * @var array<string, int>                                                               $summary Đếm theo trạng thái.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use Saha\Core\Qa;

$saha_labels = array(
	Qa::PASS => __( 'Đạt', 'saha-core' ),
	Qa::WARN => __( 'Cảnh báo', 'saha-core' ),
	Qa::FAIL => __( 'Lỗi', 'saha-core' ),
	Qa::SKIP => __( 'Bỏ qua', 'saha-core' ),
);

$saha_group = '';
?>
<div class="wrap saha-admin">
	<h1><?php esc_html_e( 'Kiểm tra hệ thống', 'saha-core' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Kiểm tra tự động cấu hình, database, quyền, REST API, tìm kiếm, SEO, bảo mật và hiệu năng. Chỉ đọc dữ liệu — an toàn khi chạy trên production. Tương đương lệnh `wp saha qa`.', 'saha-core' ); ?>
	</p>

	<div class="saha-cards">
		<?php foreach ( $saha_labels as $saha_key => $saha_label ) : ?>
			<div class="saha-card saha-card--<?php echo esc_attr( $saha_key ); ?>">
				<span class="saha-card__value"><?php echo esc_html( (string) ( $summary[ $saha_key ] ?? 0 ) ); ?></span>
				<span class="saha-card__label"><?php echo esc_html( $saha_label ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>

	<table class="widefat striped saha-qa-table">
		<thead>
			<tr>
				<th class="saha-qa-table__status"><?php esc_html_e( 'Kết quả', 'saha-core' ); ?></th>
				<th><?php esc_html_e( 'Kiểm tra', 'saha-core' ); ?></th>
				<th><?php esc_html_e( 'Chi tiết / cách sửa', 'saha-core' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $results as $saha_row ) : ?>
				<?php if ( $saha_row['group'] !== $saha_group ) : ?>
					<?php $saha_group = $saha_row['group']; ?>
					<tr class="saha-qa-table__group">
						<th colspan="3" scope="colgroup"><?php echo esc_html( $saha_group ); ?></th>
					</tr>
				<?php endif; ?>
				<tr>
					<td>
						<span class="saha-status saha-qa--<?php echo esc_attr( $saha_row['status'] ); ?>">
							<?php echo esc_html( $saha_labels[ $saha_row['status'] ] ?? $saha_row['status'] ); ?>
						</span>
					</td>
					<td><?php echo esc_html( $saha_row['label'] ); ?></td>
					<td><?php echo esc_html( $saha_row['detail'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<p>
		<a class="button" href="<?php echo esc_url( add_query_arg( 'page', 'saha-core-qa', admin_url( 'admin.php' ) ) ); ?>">
			<?php esc_html_e( 'Chạy lại', 'saha-core' ); ?>
		</a>
	</p>
</div>
