<?php
/**
 * Màn hình SAHA → Import / Export.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\ImportExport;

use Saha\Core\Templates\Repository as Templates;

defined( 'ABSPATH' ) || exit;

/**
 * Module — chỉ `manage_options` (thiết kế §15). Xuất: tải file JSON (admin-post). Nhập: upload →
 * chạy (hoặc chạy thử) → báo cáo hiện một lần (transient theo user, ngắn hạn).
 */
final class Module {

	public const SLUG       = 'saha-import-export';
	public const CAPABILITY = 'manage_options';
	private const NONCE_EXPORT = 'saha_export';
	private const NONCE_IMPORT = 'saha_import';

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_action( 'saha_core_admin_menu', array( $this, 'menu' ), 40 );
		add_action( 'admin_post_saha_export', array( $this, 'handleExport' ) );
		add_action( 'admin_post_saha_import', array( $this, 'handleImport' ) );
	}

	/**
	 * Menu.
	 *
	 * @param string $parent Slug menu cha.
	 */
	public function menu( string $parent ): void {
		add_submenu_page( $parent, __( 'Import / Export giao diện', 'saha-core' ), __( 'Import / Export', 'saha-core' ), self::CAPABILITY, self::SLUG, array( $this, 'render' ) );
	}

	/**
	 * Khoá transient báo cáo của user hiện tại.
	 */
	private static function reportKey(): string {
		return 'saha_import_report_' . get_current_user_id();
	}

	/**
	 * Tải file xuất.
	 */
	public function handleExport(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'saha-core' ), 403 );
		}

		check_admin_referer( self::NONCE_EXPORT );

		$ids = static function ( string $key ): array {
			return array_map( 'absint', (array) wp_unslash( $_POST[ $key ] ?? array() ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- đã kiểm nonce; ép absint.
		};

		$package = Exporter::build(
			array(
				'pages'         => $ids( 'pages' ),
				'templates'     => $ids( 'templates' ),
				'blocks'        => $ids( 'blocks' ),
				'theme_options' => ! empty( $_POST['theme_options'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- đã kiểm nonce.
			)
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . Exporter::filename() . '"' );
		header( 'X-Content-Type-Options: nosniff' );

		echo Exporter::json( $package ); // phpcs:ignore WordPress.Security.EscapeOutput -- file JSON tải về.
		exit;
	}

	/**
	 * Nhập.
	 */
	public function handleImport(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'saha-core' ), 403 );
		}

		check_admin_referer( self::NONCE_IMPORT );

		$file   = $_FILES['saha_file'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- kiểm từng phần bên dưới.
		$report = null;
		$error  = '';

		if ( ! is_array( $file ) || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
			$error = __( 'Chưa chọn file hoặc tải file lên lỗi.', 'saha-core' );
		} elseif ( (int) $file['size'] > Importer::MAX_BYTES ) {
			$error = __( 'File quá lớn (tối đa 10 MB).', 'saha-core' );
		} elseif ( 'json' !== strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) ) ) {
			$error = __( 'Chỉ nhận file .json xuất từ SAHA.', 'saha-core' );
		} else {
			$data = Importer::parse( (string) file_get_contents( (string) $file['tmp_name'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file tạm vừa upload.

			if ( is_wp_error( $data ) ) {
				$error = $data->get_error_message();
			} else {
				// phpcs:disable WordPress.Security.NonceVerification.Missing -- đã kiểm nonce.
				$report = ( new Importer(
					array(
						'dry_run'           => ! empty( $_POST['dry_run'] ),
						'blocks'            => ! empty( $_POST['kinds']['blocks'] ),
						'pages'             => ! empty( $_POST['kinds']['pages'] ),
						'templates'         => ! empty( $_POST['kinds']['templates'] ),
						'theme_options'     => ! empty( $_POST['kinds']['theme_options'] ),
						'page_status'       => 'keep' === ( $_POST['page_status'] ?? '' ) ? 'keep' : 'draft',
						'front_page'        => ! empty( $_POST['front_page'] ),
						'replace_templates' => ! empty( $_POST['replace_templates'] ),
					)
				) )->run( $data );
				// phpcs:enable

				$report['dry_run'] = ! empty( $_POST['dry_run'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- đã kiểm nonce.
				$report['file']    = sanitize_file_name( (string) $file['name'] );
			}
		}

		set_transient( self::reportKey(), array( 'report' => $report, 'error' => $error ), 10 * MINUTE_IN_SECONDS );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&done=1' ) );
		exit;
	}

	/**
	 * Màn hình.
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bạn không có quyền truy cập trang này.', 'saha-core' ), 403 );
		}

		$result = get_transient( self::reportKey() );

		if ( false !== $result ) {
			delete_transient( self::reportKey() );
		}

		$available = Exporter::available();
		$types     = Templates::types();

		require SAHA_CORE_PATH . 'admin/views/import-export.php';
	}
}
