<?php
/**
 * Email: thông báo yêu cầu báo giá mới cho admin/sales.
 *
 * Override: {child-theme}/saha-core/emails/quote-admin.php
 *
 * @package Saha\Core
 *
 * @var array<string, mixed> $vars      quote_id, data, admin_url.
 * @var string               $site_name Tên website.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$saha_data = (array) ( $vars['data'] ?? array() );

$saha_rows = array(
	__( 'Họ tên', 'saha-core' )       => $saha_data['customer_name'] ?? '',
	__( 'Điện thoại', 'saha-core' )   => $saha_data['phone'] ?? '',
	__( 'Email', 'saha-core' )        => $saha_data['email'] ?? '',
	__( 'Công ty', 'saha-core' )      => $saha_data['company'] ?? '',
	__( 'Sản phẩm', 'saha-core' )     => $saha_data['product_name'] ?? '',
	__( 'Mã SKU', 'saha-core' )       => $saha_data['sku'] ?? '',
	__( 'Số lượng', 'saha-core' )     => $saha_data['quantity'] ?? '',
	__( 'Trang gửi', 'saha-core' )    => $saha_data['source_url'] ?? '',
);
?>
<!doctype html>
<html lang="vi">
<head>
	<meta charset="utf-8">
	<title><?php echo esc_html( $site_name ); ?></title>
</head>
<body style="margin:0;padding:24px;background:#f6f8fa;font-family:Arial,Helvetica,sans-serif;color:#1f2328;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e3e6ea;border-radius:6px;">
		<tr>
			<td style="padding:20px 24px;border-bottom:1px solid #e3e6ea;">
				<h1 style="margin:0;font-size:18px;color:#0b5cab;">
					<?php
					printf(
						/* translators: %d: quote ID */
						esc_html__( 'Yêu cầu báo giá mới #%d', 'saha-core' ),
						(int) ( $vars['quote_id'] ?? 0 )
					);
					?>
				</h1>
			</td>
		</tr>
		<tr>
			<td style="padding:16px 24px;">
				<table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:14px;">
					<?php foreach ( $saha_rows as $saha_label => $saha_value ) : ?>
						<?php if ( '' === (string) $saha_value ) { continue; } ?>
						<tr>
							<td style="width:130px;color:#5b6570;vertical-align:top;border-bottom:1px solid #f0f0f1;"><?php echo esc_html( $saha_label ); ?></td>
							<td style="vertical-align:top;border-bottom:1px solid #f0f0f1;"><strong><?php echo esc_html( (string) $saha_value ); ?></strong></td>
						</tr>
					<?php endforeach; ?>
				</table>

				<?php if ( '' !== (string) ( $saha_data['message'] ?? '' ) ) : ?>
					<p style="margin:16px 0 4px;color:#5b6570;font-size:13px;"><?php esc_html_e( 'Nội dung', 'saha-core' ); ?></p>
					<div style="padding:12px;background:#f6f8fa;border-radius:4px;font-size:14px;line-height:1.5;">
						<?php echo nl2br( esc_html( (string) $saha_data['message'] ) ); ?>
					</div>
				<?php endif; ?>

				<p style="margin:20px 0 0;">
					<a href="<?php echo esc_url( (string) ( $vars['admin_url'] ?? '' ) ); ?>" style="display:inline-block;padding:10px 16px;background:#0b5cab;color:#ffffff;text-decoration:none;border-radius:4px;font-size:14px;">
						<?php esc_html_e( 'Mở trong quản trị', 'saha-core' ); ?>
					</a>
				</p>
			</td>
		</tr>
		<tr>
			<td style="padding:12px 24px;border-top:1px solid #e3e6ea;color:#5b6570;font-size:12px;">
				<?php echo esc_html( $site_name ); ?>
			</td>
		</tr>
	</table>
</body>
</html>
