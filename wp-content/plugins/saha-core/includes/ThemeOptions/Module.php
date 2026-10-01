<?php
/**
 * Theme Options — đăng ký hook.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\ThemeOptions;

use Saha\Core\Performance\CssFileStore;
use Saha\Core\ThemeOptions\Rest\SettingsController;

defined( 'ABSPATH' ) || exit;

/**
 * Module.
 *
 * - REST `/saha/v1/settings` cho ứng dụng Theme Options (saha-builder).
 * - Sinh lại `global.css` khi lưu.
 * - Nạp `global.css` ở frontend CHỈ khi theme khai báo
 *   `add_theme_support( 'saha-theme-options' )` → theme khác không bị ảnh hưởng.
 */
final class Module {

	/**
	 * Option giữ thông tin file CSS toàn cục hiện tại.
	 */
	public const CSS_OPTION = 'saha_theme_css';

	/**
	 * Handle stylesheet — theme dùng làm dependency để CSS của theme nạp sau biến.
	 */
	public const STYLE_HANDLE = 'saha-global';

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( new SettingsController(), 'registerRoutes' ) );
		add_action( 'saha_theme_options_saved', array( self::class, 'regenerateCss' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueueGlobalCss' ), 5 );
		( new WebFonts() )->register();
		( new FloatingActions() )->register();
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueueGlobalCss' ) );
	}

	/**
	 * Sinh lại CSS toàn cục và ghi nhớ file.
	 *
	 * @return array{file: string, hash: string, inline: bool}
	 */
	public static function regenerateCss(): array {
		$css    = CssVariables::build( Repository::all() );
		$result = CssFileStore::write( 'global', $css );

		update_option(
			self::CSS_OPTION,
			array(
				'file'   => $result['file'],
				'hash'   => $result['hash'],
				'inline' => $result['inline'],
				// Theme Options cũ hơn code hiện tại? So phiên bản plugin để sinh lại sau nâng cấp.
				'core'   => SAHA_CORE_VERSION,
			),
			true
		);

		return $result;
	}

	/**
	 * Nạp CSS toàn cục.
	 */
	public function enqueueGlobalCss(): void {
		if ( ! current_theme_supports( 'saha-theme-options' ) ) {
			return;
		}

		$state = get_option( self::CSS_OPTION, array() );
		$state = is_array( $state ) ? $state : array();

		$stale = empty( $state['hash'] )
			|| ( $state['core'] ?? '' ) !== SAHA_CORE_VERSION
			|| ( empty( $state['inline'] ) && ! CssFileStore::exists( (string) ( $state['file'] ?? '' ) ) );

		if ( $stale ) {
			$state = self::regenerateCss();
		}

		if ( empty( $state['inline'] ) && '' !== (string) $state['file'] ) {
			// Hash nằm trong tên file → không cần ?ver.
			wp_enqueue_style( self::STYLE_HANDLE, CssFileStore::url( (string) $state['file'] ), array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			return;
		}

		wp_register_style( self::STYLE_HANDLE, false, array(), SAHA_CORE_VERSION );
		wp_enqueue_style( self::STYLE_HANDLE );
		wp_add_inline_style( self::STYLE_HANDLE, CssVariables::build( Repository::all() ) );
	}
}
