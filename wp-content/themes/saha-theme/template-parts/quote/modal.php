<?php
/**
 * Quote modal — dùng <dialog> native: có sẵn focus trap, phím Esc, backdrop.
 *
 * Chỉ render ở trang có nút mở báo giá (single product, hoặc trang có shortcode).
 * Ở trang khác, nút "Báo giá" là link thường tới trang báo giá (spec §73).
 *
 * @package Saha\Theme
 *
 * @var array<string, mixed> $args product_id.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! saha_theme_has_core() ) {
	return;
}
?>
<dialog class="saha-quote-modal" data-saha-quote-modal aria-labelledby="saha-quote-modal-title">
	<div class="saha-quote-modal__inner">
		<header class="saha-quote-modal__header">
			<h2 class="saha-quote-modal__title" id="saha-quote-modal-title"><?php echo esc_html( saha_theme_cta_label() ); ?></h2>
			<button type="button" class="saha-quote-modal__close" data-saha-quote-close aria-label="<?php esc_attr_e( 'Đóng', 'saha' ); ?>">&times;</button>
		</header>

		<?php
		saha_theme_part(
			'quote/form',
			array(
				'product_id' => absint( $args['product_id'] ?? 0 ),
				'context'    => 'modal',
			)
		);
		?>
	</div>
</dialog>
