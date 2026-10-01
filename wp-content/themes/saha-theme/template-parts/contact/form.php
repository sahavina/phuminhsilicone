<?php
/**
 * Form liên hệ (spec §32) — không phụ thuộc Contact Form 7.
 *
 * @package Saha\Theme
 *
 * @var array<string, mixed> $args title, source.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! saha_theme_has_core() ) {
	return;
}

wp_enqueue_style( 'saha-form' );
wp_enqueue_script( 'saha-quote-form' );

$saha_uid    = 'saha-contact-' . wp_unique_id();
$saha_result = saha_form_result();
$saha_policy = get_privacy_policy_url();
$saha_source = sanitize_key( (string) ( $args['source'] ?? 'contact' ) );
?>
<form
	class="saha-form saha-contact-form"
	method="post"
	action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
	data-saha-form="contact"
	novalidate
>
	<?php if ( ! empty( $args['title'] ) ) : ?>
		<h2 class="saha-form__title"><?php echo esc_html( (string) $args['title'] ); ?></h2>
	<?php endif; ?>

	<div class="saha-form__notice" data-saha-form-notice role="status" aria-live="polite" id="saha-form-result"
		<?php echo $saha_result ? '' : 'hidden'; ?>
		<?php echo $saha_result ? 'data-type="' . esc_attr( $saha_result['type'] ) . '"' : ''; ?>
	>
		<?php echo $saha_result ? esc_html( $saha_result['message'] ) : ''; ?>
	</div>

	<input type="hidden" name="action" value="saha_contact">
	<input type="hidden" name="source" value="<?php echo esc_attr( $saha_source ); ?>">
	<input type="hidden" name="source_url" value="<?php echo esc_url( is_singular() ? (string) get_permalink() : home_url( '/' ) ); ?>" data-saha-source-url>
	<?php
	echo saha_form_nonce_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- đã escape trong hàm.
	echo saha_honeypot_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- đã escape trong hàm.
	?>

	<div class="saha-form__grid">
		<p class="saha-form__field">
			<label for="<?php echo esc_attr( $saha_uid ); ?>-name">
				<?php esc_html_e( 'Họ tên', 'saha' ); ?> <span class="saha-required" aria-hidden="true">*</span>
			</label>
			<input type="text" id="<?php echo esc_attr( $saha_uid ); ?>-name" name="name" required autocomplete="name" maxlength="191">
			<span class="saha-form__error" data-saha-error="name"></span>
		</p>

		<p class="saha-form__field">
			<label for="<?php echo esc_attr( $saha_uid ); ?>-phone">
				<?php esc_html_e( 'Số điện thoại', 'saha' ); ?> <span class="saha-required" aria-hidden="true">*</span>
			</label>
			<input type="tel" id="<?php echo esc_attr( $saha_uid ); ?>-phone" name="phone" required autocomplete="tel" inputmode="tel" maxlength="32">
			<span class="saha-form__error" data-saha-error="phone"></span>
		</p>

		<p class="saha-form__field saha-form__field--full">
			<label for="<?php echo esc_attr( $saha_uid ); ?>-email"><?php esc_html_e( 'Email', 'saha' ); ?></label>
			<input type="email" id="<?php echo esc_attr( $saha_uid ); ?>-email" name="email" autocomplete="email" maxlength="191">
			<span class="saha-form__error" data-saha-error="email"></span>
		</p>
	</div>

	<p class="saha-form__field">
		<label for="<?php echo esc_attr( $saha_uid ); ?>-message">
			<?php esc_html_e( 'Nội dung cần tư vấn', 'saha' ); ?> <span class="saha-required" aria-hidden="true">*</span>
		</label>
		<textarea id="<?php echo esc_attr( $saha_uid ); ?>-message" name="message" rows="5" required maxlength="5000"></textarea>
		<span class="saha-form__error" data-saha-error="message"></span>
	</p>

	<p class="saha-form__actions">
		<button type="submit" class="button primary saha-form__submit" data-saha-submit>
			<span class="saha-form__submit-text"><?php esc_html_e( 'Gửi liên hệ', 'saha' ); ?></span>
			<span class="saha-form__spinner" aria-hidden="true"></span>
		</button>
	</p>

	<p class="saha-form__privacy">
		<?php
		if ( $saha_policy ) {
			printf(
				/* translators: %s: link chính sách bảo mật */
				esc_html__( 'Thông tin chỉ dùng để phản hồi liên hệ. Xem %s.', 'saha' ),
				'<a href="' . esc_url( $saha_policy ) . '" target="_blank" rel="noopener">' . esc_html__( 'chính sách bảo mật', 'saha' ) . '</a>'
			);
		} else {
			esc_html_e( 'Thông tin chỉ dùng để phản hồi liên hệ, không chia sẻ cho bên thứ ba.', 'saha' );
		}
		?>
	</p>
</form>
