<?php
/**
 * Nút nổi: liên hệ (Gọi · Zalo · Báo giá) + lên đầu trang.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\ThemeOptions;

use Saha\Core\Builder\Icons;

defined( 'ABSPATH' ) || exit;

/**
 * FloatingActions — bật ở Theme Options → Nút nổi; chạy với mọi theme hỗ trợ Theme Options.
 *
 * Nút liên hệ là `<button aria-expanded aria-controls>` mở một danh sách link thật
 * (tel:, Zalo, mở form báo giá). Esc / bấm ra ngoài để đóng. Nút lên đầu trang chỉ hiện
 * khi đã cuộn quá một màn hình. Mặc định ẩn trên điện thoại (saha-theme có thanh liên hệ dính).
 */
final class FloatingActions {

	public const SCRIPT = 'saha-floating';

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_footer', array( $this, 'render' ), 5 );
	}

	/**
	 * Thiết lập.
	 *
	 * @return array{contact: bool, back_to_top: bool, position: string, mobile: bool}
	 */
	private static function settings(): array {
		$value = (array) ( Repository::all()['floating'] ?? array() );

		return array(
			'contact'     => ! empty( $value['contact'] ),
			'back_to_top' => ! empty( $value['back_to_top'] ),
			'position'    => 'left' === ( $value['position'] ?? '' ) ? 'left' : 'right',
			'mobile'      => ! empty( $value['mobile'] ),
		);
	}

	/**
	 * Các lựa chọn liên hệ có dữ liệu.
	 *
	 * @return array<int, string> HTML từng mục.
	 */
	private static function items(): array {
		$items = array();
		$phone = function_exists( 'saha_hotline' ) ? saha_hotline( 'north' ) : '';

		if ( '' !== $phone ) {
			$href    = function_exists( 'saha_tel_href' ) ? saha_tel_href( $phone ) : 'tel:' . preg_replace( '/[^\d+]/', '', $phone );
			$items[] = '<a class="saha-fab__item" href="' . esc_url( $href, array( 'tel' ) ) . '" data-saha-event="click_phone">' . Icons::svg( 'phone' ) . '<span>' . esc_html( sprintf( /* translators: %s: số điện thoại */ __( 'Gọi %s', 'saha-core' ), $phone ) ) . '</span></a>';
		}

		$zalo = function_exists( 'saha_zalo_url' ) ? saha_zalo_url() : '';

		if ( '' !== $zalo ) {
			$items[] = '<a class="saha-fab__item" href="' . esc_url( $zalo ) . '" target="_blank" rel="noopener" data-saha-event="click_zalo">' . Icons::svg( 'message' ) . '<span>' . esc_html__( 'Chat Zalo', 'saha-core' ) . '</span></a>';
		}

		// Mở modal báo giá của saha-theme (theme in modal ở wp_footer 15 khi có action này).
		if ( function_exists( 'saha_quote_page_url' ) ) {
			do_action( 'saha_quote_modal_needed' );
			$items[] = '<button type="button" class="saha-fab__item" data-saha-open-quote="1">' . Icons::svg( 'file-text' ) . '<span>' . esc_html__( 'Yêu cầu báo giá', 'saha-core' ) . '</span></button>';
		}

		return $items;
	}

	/**
	 * In nút (wp_footer, trước modal báo giá của theme ở priority 15).
	 */
	public function render(): void {
		if ( ! current_theme_supports( 'saha-theme-options' ) || is_admin() ) {
			return;
		}

		$settings = self::settings();
		$items    = $settings['contact'] ? self::items() : array();

		if ( ! $items && ! $settings['back_to_top'] ) {
			return;
		}

		$classes = 'saha-fab saha-fab--' . $settings['position'] . ( $settings['mobile'] ? '' : ' saha-fab--no-mobile' );

		echo '<div class="' . esc_attr( $classes ) . '" data-saha-fab>';

		if ( $settings['back_to_top'] ) {
			echo '<a class="saha-fab__top" href="#" data-saha-fab-top hidden aria-label="' . esc_attr__( 'Lên đầu trang', 'saha-core' ) . '">' . Icons::svg( 'chevron-right' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput -- SVG tĩnh.
		}

		if ( $items ) {
			echo '<div class="saha-fab__menu" id="saha-fab-menu" hidden>' . implode( '', $items ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- từng mục đã escape.
			echo '<button type="button" class="saha-fab__toggle" aria-expanded="false" aria-controls="saha-fab-menu" aria-label="' . esc_attr__( 'Liên hệ', 'saha-core' ) . '">' . Icons::svg( 'headset' ) . Icons::svg( 'close', 'saha-fab__close' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}

		echo '</div>';
	}

	/**
	 * CSS/JS (chỉ khi bật).
	 */
	public function enqueue(): void {
		$settings = self::settings();

		if ( ! current_theme_supports( 'saha-theme-options' ) || ( ! $settings['contact'] && ! $settings['back_to_top'] ) ) {
			return;
		}

		wp_enqueue_style( self::SCRIPT, SAHA_CORE_URL . 'public/assets/css/floating.css', array(), SAHA_CORE_VERSION );
		wp_enqueue_script(
			self::SCRIPT,
			SAHA_CORE_URL . 'public/assets/js/floating.js',
			array(),
			SAHA_CORE_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}
}
