<?php
/**
 * View: SAHA Dashboard.
 *
 * @package Saha\Core
 *
 * @var array<int, array<string, mixed>> $stats Số liệu tổng quan.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use Saha\Core\Admin;
use Saha\Core\Install;

$db_version = (string) get_option( Install::DB_VERSION_OPTION, '—' );
?>
<div class="wrap saha-admin">
	<h1><?php esc_html_e( 'SAHA Dashboard', 'saha-core' ); ?></h1>

	<div class="saha-cards">
		<?php foreach ( $stats as $stat ) : ?>
			<div class="saha-card">
				<span class="saha-card__value"><?php echo esc_html( (string) $stat['value'] ); ?></span>
				<span class="saha-card__label"><?php echo esc_html( (string) $stat['label'] ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>

	<h2><?php esc_html_e( 'Trạng thái hệ thống', 'saha-core' ); ?></h2>
	<table class="widefat striped saha-status">
		<tbody>
			<tr>
				<td><?php esc_html_e( 'Phiên bản plugin', 'saha-core' ); ?></td>
				<td><code><?php echo esc_html( SAHA_CORE_VERSION ); ?></code></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Phiên bản database', 'saha-core' ); ?></td>
				<td><code><?php echo esc_html( $db_version ); ?></code></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'WooCommerce', 'saha-core' ); ?></td>
				<td>
					<?php
					echo class_exists( 'WooCommerce' )
						? esc_html__( 'Đang hoạt động', 'saha-core' )
						: esc_html__( 'Chưa kích hoạt — cần cho module sản phẩm', 'saha-core' );
					?>
				</td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Chế độ catalogue', 'saha-core' ); ?></td>
				<td>
					<?php
					echo saha_is_catalogue_mode()
						? esc_html__( 'Bật (ẩn giá, dùng CTA báo giá)', 'saha-core' )
						: esc_html__( 'Tắt (bán hàng đầy đủ)', 'saha-core' );
					?>
				</td>
			</tr>
		</tbody>
	</table>

	<p>
		<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . Admin::MENU_SLUG . '-settings' ) ); ?>">
			<?php esc_html_e( 'Mở cấu hình', 'saha-core' ); ?>
		</a>
	</p>
</div>
