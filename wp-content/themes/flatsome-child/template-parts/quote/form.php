<?php
/**
 * Form yêu cầu báo giá (spec §11).
 *
 * - Gửi qua REST bằng quote-form.js.
 * - Không có JS: form POST tới admin-post.php vẫn hoạt động.
 * - product_id là hidden field nhưng backend KHÔNG tin — luôn kiểm tra lại (spec §78).
 *
 * @package Flatsome_Child_Saha
 *
 * @var array<string, mixed> $args product_id, context (inline|modal), title.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! saha_theme_has_core() ) {
	return;
}

wp_enqueue_style( 'saha-form' );
wp_enqueue_script( 'saha-quote-form' );

$saha_product_id = absint( $args['product_id'] ?? 0 );
$saha_context    = 'modal' === ( $args['context'] ?? '' ) ? 'modal' : 'inline';
$saha_uid        = 'saha-quote-' . wp_unique_id();
$saha_product    = null;

if ( $saha_product_id > 0 && function_exists( 'wc_get_product' ) ) {
	$saha_product = wc_get_product( $saha_product_id );
}

$saha_result = 'inline' === $saha_context ? saha_form_result() : null;
$saha_policy = get_privacy_policy_url();

/**
 * Hiển thị ô "Công ty" và "Số lượng".
 *
 * @param bool $show Có hiển thị không.
 */
$saha_show_b2b = (bool) apply_filters( 'saha_theme_quote_show_b2b_fields', true );
?>
<form
	class="saha-form saha-quote-form"
	method="post"
	action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
	data-saha-form="quote"
	novalidate
>
	<?php if ( 'inline' === $saha_context && ! empty( $args['title'] ) ) : ?>
		<h2 class="saha-form__title"><?php echo esc_html( (string) $args['title'] ); ?></h2>
	<?php endif; ?>

	<div class="saha-form__notice" data-saha-form-notice role="status" aria-live="polite" id="saha-form-result"
		<?php echo $saha_result ? '' : 'hidden'; ?>
		<?php echo $saha_result ? 'data-type="' . esc_attr( $saha_result['type'] ) . '"' : ''; ?>
	>
		<?php echo $saha_result ? esc_html( $saha_result['message'] ) : ''; ?>
	</div>

	<div class="saha-form__product" data-saha-quote-product <?php echo $saha_product ? '' : 'hidden'; ?>>
		<span class="saha-form__product-label"><?php esc_html_e( 'Sản phẩm:', 'flatsome-child' ); ?></span>
		<strong data-saha-quote-product-name><?php echo $saha_product ? esc_html( $saha_product->get_name() ) : ''; ?></strong>
		<span class="saha-form__product-sku" data-saha-quote-product-sku>
			<?php echo $saha_product && $saha_product->get_sku() ? esc_html( '(' . $saha_product->get_sku() . ')' ) : ''; ?>
		</span>
	</div>

	<input type="hidden" name="action" value="saha_quote">
	<input type="hidden" name="product_id" value="<?php echo esc_attr( (string) $saha_product_id ); ?>" data-saha-field="product_id">
	<?php // JS ghi đè bằng location.href chính xác; giá trị này là dự phòng khi không có JS. ?>
	<input type="hidden" name="source_url" value="<?php echo esc_url( is_singular() ? (string) get_permalink() : home_url( '/' ) ); ?>" data-saha-source-url>
	<?php
	echo saha_form_nonce_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- đã escape trong hàm.
	echo saha_honeypot_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- đã escape trong hàm.
	?>

	<div class="saha-form__grid">
		<p class="saha-form__field">
			<label for="<?php echo esc_attr( $saha_uid ); ?>-name">
				<?php esc_html_e( 'Họ tên', 'flatsome-child' ); ?> <span class="saha-required" aria-hidden="true">*</span>
			</label>
			<input type="text" id="<?php echo esc_attr( $saha_uid ); ?>-name" name="name" required autocomplete="name" maxlength="191">
			<span class="saha-form__error" data-saha-error="name"></span>
		</p>

		<p class="saha-form__field">
			<label for="<?php echo esc_attr( $saha_uid ); ?>-phone">
				<?php esc_html_e( 'Số điện thoại', 'flatsome-child' ); ?> <span class="saha-required" aria-hidden="true">*</span>
			</label>
			<input type="tel" id="<?php echo esc_attr( $saha_uid ); ?>-phone" name="phone" required autocomplete="tel" inputmode="tel" maxlength="32">
			<span class="saha-form__error" data-saha-error="phone"></span>
		</p>

		<p class="saha-form__field">
			<label for="<?php echo esc_attr( $saha_uid ); ?>-email"><?php esc_html_e( 'Email', 'flatsome-child' ); ?></label>
			<input type="email" id="<?php echo esc_attr( $saha_uid ); ?>-email" name="email" autocomplete="email" maxlength="191">
			<span class="saha-form__error" data-saha-error="email"></span>
		</p>

		<?php if ( $saha_show_b2b ) : ?>
			<p class="saha-form__field">
				<label for="<?php echo esc_attr( $saha_uid ); ?>-company"><?php esc_html_e( 'Công ty / cửa hàng', 'flatsome-child' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $saha_uid ); ?>-company" name="company" autocomplete="organization" maxlength="191">
				<span class="saha-form__error" data-saha-error="company"></span>
			</p>

			<p class="saha-form__field">
				<label for="<?php echo esc_attr( $saha_uid ); ?>-quantity"><?php esc_html_e( 'Số lượng dự kiến', 'flatsome-child' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $saha_uid ); ?>-quantity" name="quantity" maxlength="100" placeholder="<?php esc_attr_e( 'Ví dụ: 2 thùng, 100 tuýp', 'flatsome-child' ); ?>">
				<span class="saha-form__error" data-saha-error="quantity"></span>
			</p>
		<?php endif; ?>
	</div>

	<p class="saha-form__field">
		<label for="<?php echo esc_attr( $saha_uid ); ?>-message"><?php esc_html_e( 'Nội dung cần báo giá', 'flatsome-child' ); ?></label>
		<textarea id="<?php echo esc_attr( $saha_uid ); ?>-message" name="message" rows="4" maxlength="5000" placeholder="<?php esc_attr_e( 'Quy cách, màu, công trình, thời gian cần hàng…', 'flatsome-child' ); ?>"></textarea>
		<span class="saha-form__error" data-saha-error="message"></span>
	</p>

	<p class="saha-form__actions">
		<button type="submit" class="button primary saha-form__submit" data-saha-submit>
			<span class="saha-form__submit-text"><?php echo esc_html( saha_theme_cta_label() ); ?></span>
			<span class="saha-form__spinner" aria-hidden="true"></span>
		</button>
	</p>

	<p class="saha-form__privacy">
		<?php
		if ( $saha_policy ) {
			printf(
				/* translators: %s: link chính sách bảo mật */
				esc_html__( 'Thông tin chỉ dùng để liên hệ báo giá. Xem %s.', 'flatsome-child' ),
				'<a href="' . esc_url( $saha_policy ) . '" target="_blank" rel="noopener">' . esc_html__( 'chính sách bảo mật', 'flatsome-child' ) . '</a>'
			);
		} else {
			esc_html_e( 'Thông tin chỉ dùng để liên hệ báo giá, không chia sẻ cho bên thứ ba.', 'flatsome-child' );
		}
		?>
	</p>
</form>
