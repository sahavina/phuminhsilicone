<?php
/**
 * Dải CTA: tiêu đề + mô tả + nút báo giá + hotline + Zalo (spec §17, §18).
 *
 * Nội dung chữ do người biên tập nhập trong UX Builder; hotline/Zalo luôn
 * lấy từ settings, không hardcode.
 *
 * @package Flatsome_Child_Saha
 *
 * @var array<string, mixed> $args title, text, button, show_hotline, show_zalo, style (primary|light).
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'saha-sections' );

$saha_title   = trim( (string) ( $args['title'] ?? '' ) );
$saha_text    = trim( (string) ( $args['text'] ?? '' ) );
$saha_button  = trim( (string) ( $args['button'] ?? '' ) );
$saha_button  = '' !== $saha_button ? $saha_button : saha_theme_cta_label();
$saha_style   = 'light' === ( $args['style'] ?? '' ) ? 'light' : 'primary';
$saha_hotline = ! isset( $args['show_hotline'] ) || ! empty( $args['show_hotline'] );
$saha_zalo    = ( ! isset( $args['show_zalo'] ) || ! empty( $args['show_zalo'] ) ) ? saha_theme_zalo_url() : '';
$saha_quote   = function_exists( 'saha_quote_page_url' ) ? saha_quote_page_url() : '';
?>
<section class="saha-quote-cta saha-quote-cta--<?php echo esc_attr( $saha_style ); ?>">
	<div class="saha-quote-cta__text">
		<?php if ( '' !== $saha_title ) : ?>
			<h2 class="saha-quote-cta__title"><?php echo esc_html( $saha_title ); ?></h2>
		<?php endif; ?>

		<?php if ( '' !== $saha_text ) : ?>
			<p class="saha-quote-cta__desc"><?php echo esc_html( $saha_text ); ?></p>
		<?php endif; ?>
	</div>

	<div class="saha-quote-cta__actions">
		<?php
		// Có modal trên trang thì mở modal; không có thì <a> dẫn tới trang báo giá.
		$saha_tag = '' !== $saha_quote ? 'a' : 'button';
		?>
		<<?php echo esc_html( $saha_tag ); ?>
			<?php if ( 'a' === $saha_tag ) : ?>
				href="<?php echo esc_url( $saha_quote ); ?>"
			<?php else : ?>
				type="button"
			<?php endif; ?>
			class="button saha-quote-cta__button"
			data-saha-open-quote="1"
			aria-haspopup="dialog"
		><?php echo esc_html( $saha_button ); ?></<?php echo esc_html( $saha_tag ); ?>>

		<?php if ( $saha_hotline ) : ?>
			<?php foreach ( saha_theme_hotlines() as $saha_line ) : ?>
				<?php if ( '' === $saha_line['href'] ) { continue; } ?>
				<a
					class="saha-quote-cta__phone"
					href="<?php echo esc_url( $saha_line['href'] ); ?>"
					data-saha-event="click_phone"
					data-saha-region="<?php echo esc_attr( $saha_line['region'] ); ?>"
				>
					<span class="saha-quote-cta__phone-label"><?php echo esc_html( $saha_line['label'] ); ?></span>
					<span class="saha-quote-cta__phone-number"><?php echo esc_html( $saha_line['number'] ); ?></span>
				</a>
			<?php endforeach; ?>
		<?php endif; ?>

		<?php if ( '' !== $saha_zalo ) : ?>
			<a class="saha-quote-cta__zalo" href="<?php echo esc_url( $saha_zalo ); ?>" target="_blank" rel="noopener nofollow" data-saha-event="click_zalo">
				<?php esc_html_e( 'Chat Zalo', 'flatsome-child' ); ?>
			</a>
		<?php endif; ?>
	</div>
</section>
