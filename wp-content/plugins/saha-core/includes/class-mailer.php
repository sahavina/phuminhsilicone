<?php
/**
 * Mailer: gửi email thông báo qua wp_mail() với template tách riêng.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Mailer.
 *
 * - Dùng wp_mail(), không dùng mail() (spec §64) — tương thích plugin SMTP.
 * - Người nhận lấy từ settings, không hardcode.
 * - Template nằm ở templates/emails/, theme có thể override tại
 *   {child-theme}/saha-core/emails/{template}.php.
 * - Lỗi gửi mail không làm hỏng việc lưu dữ liệu: chỉ ghi log.
 */
final class Mailer {

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_action( 'saha_quote_created', array( $this, 'notify_quote' ), 20, 2 );
		add_action( 'saha_lead_created', array( $this, 'notify_contact' ), 20, 2 );
	}

	/**
	 * Báo admin có yêu cầu báo giá mới.
	 *
	 * @param int                  $quote_id Quote ID.
	 * @param array<string, mixed> $data     Dữ liệu.
	 */
	public function notify_quote( int $quote_id, array $data ): void {
		$recipient = saha_quote_recipient();

		/* translators: 1: quote ID, 2: tên khách */
		$subject = sprintf( __( '[Báo giá #%1$d] %2$s', 'saha-core' ), $quote_id, (string) ( $data['customer_name'] ?? '' ) );

		if ( '' !== (string) ( $data['product_name'] ?? '' ) ) {
			$subject .= ' — ' . (string) $data['product_name'];
		}

		$this->send(
			$recipient,
			$subject,
			'quote-admin',
			array(
				'quote_id'  => $quote_id,
				'data'      => $data,
				'admin_url' => admin_url( 'admin.php?page=' . Crm::QUOTES_SLUG . '&action=view&id=' . $quote_id ),
			),
			(string) ( $data['email'] ?? '' )
		);
	}

	/**
	 * Báo admin có liên hệ mới (chỉ lead nguồn contact — lead từ quote đã có email riêng).
	 *
	 * @param int                  $lead_id Lead ID.
	 * @param array<string, mixed> $data    Dữ liệu.
	 */
	public function notify_contact( int $lead_id, array $data ): void {
		if ( 'contact' !== (string) ( $data['source'] ?? '' ) ) {
			return;
		}

		$recipient = (string) Settings::get( 'email', '' );

		if ( '' === $recipient || ! is_email( $recipient ) ) {
			$recipient = saha_quote_recipient();
		}

		/* translators: 1: lead ID, 2: tên khách */
		$subject = sprintf( __( '[Liên hệ #%1$d] %2$s', 'saha-core' ), $lead_id, (string) ( $data['name'] ?? '' ) );

		$this->send(
			$recipient,
			$subject,
			'contact-admin',
			array(
				'lead_id'   => $lead_id,
				'data'      => $data,
				'admin_url' => admin_url( 'admin.php?page=' . Crm::LEADS_SLUG . '&action=view&id=' . $lead_id ),
			),
			(string) ( $data['email'] ?? '' )
		);
	}

	/**
	 * Gửi email HTML từ template.
	 *
	 * @param string               $to       Người nhận.
	 * @param string               $subject  Tiêu đề.
	 * @param string               $template Tên template (không đuôi).
	 * @param array<string, mixed> $vars     Biến cho template.
	 * @param string               $reply_to Email khách để trả lời nhanh.
	 */
	private function send( string $to, string $subject, string $template, array $vars, string $reply_to = '' ): bool {
		/**
		 * Cho phép tắt email thông báo (ví dụ khi đã có CRM đẩy notification).
		 *
		 * @param bool   $enabled  Có gửi không.
		 * @param string $template Template.
		 */
		if ( ! apply_filters( 'saha_mail_enabled', true, $template ) ) {
			return false;
		}

		if ( '' === $to || ! is_email( $to ) ) {
			Logger::warning( 'Không có email người nhận hợp lệ, bỏ qua gửi mail.', 'mail', array( 'template' => $template ) );
			return false;
		}

		$body = $this->render( $template, $vars );

		if ( '' === $body ) {
			Logger::error( 'Không tìm thấy template email.', 'mail', array( 'template' => $template ) );
			return false;
		}

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		if ( '' !== $reply_to && is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		/**
		 * Lọc header email.
		 *
		 * @param string[] $headers  Header.
		 * @param string   $template Template.
		 */
		$headers = (array) apply_filters( 'saha_mail_headers', $headers, $template );

		// Chặn CRLF injection trong tiêu đề.
		$subject = str_replace( array( "\r", "\n" ), ' ', wp_strip_all_tags( $subject ) );

		$sent = wp_mail( $to, $subject, $body, $headers );

		if ( ! $sent ) {
			Logger::error( 'wp_mail trả về false.', 'mail', array( 'template' => $template ) );
		}

		return $sent;
	}

	/**
	 * Render template email.
	 *
	 * @param string               $template Tên template.
	 * @param array<string, mixed> $vars     Biến.
	 */
	private function render( string $template, array $vars ): string {
		$template = sanitize_file_name( $template );
		$override = get_stylesheet_directory() . '/saha-core/emails/' . $template . '.php';
		$default  = SAHA_CORE_PATH . 'templates/emails/' . $template . '.php';
		$file     = is_readable( $override ) ? $override : $default;

		if ( ! is_readable( $file ) ) {
			return '';
		}

		$site_name = (string) Settings::get( 'site_brand', get_bloginfo( 'name' ) );

		ob_start();
		// Biến có sẵn trong template: $vars, $site_name.
		include $file;

		return (string) ob_get_clean();
	}
}
