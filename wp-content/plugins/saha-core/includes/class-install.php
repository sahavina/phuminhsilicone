<?php
/**
 * Activation, deactivation, upgrade routine.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Install: chịu trách nhiệm lifecycle của plugin.
 *
 * Không xoá dữ liệu khi deactivate (spec §60).
 */
final class Install {

	/**
	 * Option lưu DB version đã migrate.
	 */
	public const DB_VERSION_OPTION = 'saha_core_db_version';

	/**
	 * Option lưu plugin version đã cài.
	 */
	public const VERSION_OPTION = 'saha_core_version';

	/**
	 * Gắn hook kiểm tra upgrade.
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
	}

	/**
	 * Chạy khi kích hoạt plugin.
	 */
	public function activate(): void {
		Settings::install_defaults();

		( new Migrator() )->migrate();

		( new Roles() )->install();

		update_option( self::VERSION_OPTION, SAHA_CORE_VERSION );

		// Taxonomy của phase sau cần rewrite rule mới.
		add_option( 'saha_core_flush_rewrite', 1 );

		/**
		 * Plugin vừa được kích hoạt.
		 */
		do_action( 'saha_core_activated' );
	}

	/**
	 * Chạy khi tắt plugin. KHÔNG xoá dữ liệu.
	 */
	public function deactivate(): void {
		( new Roles() )->remove_roles();

		flush_rewrite_rules();

		/**
		 * Plugin vừa bị tắt.
		 */
		do_action( 'saha_core_deactivated' );
	}

	/**
	 * Tự migrate khi code mới hơn DB.
	 */
	public function maybe_upgrade(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( get_option( 'saha_core_flush_rewrite' ) ) {
			delete_option( 'saha_core_flush_rewrite' );
			flush_rewrite_rules();
		}

		$installed_db = (string) get_option( self::DB_VERSION_OPTION, '0' );

		if ( version_compare( $installed_db, SAHA_CORE_DB_VERSION, '<' ) ) {
			( new Migrator() )->migrate();
		}

		$installed = (string) get_option( self::VERSION_OPTION, '0' );

		if ( version_compare( $installed, SAHA_CORE_VERSION, '<' ) ) {
			Settings::install_defaults();
			( new Roles() )->install();
			update_option( self::VERSION_OPTION, SAHA_CORE_VERSION );

			/**
			 * Plugin vừa được nâng cấp.
			 *
			 * @param string $from Version cũ.
			 * @param string $to   Version mới.
			 */
			do_action( 'saha_core_upgraded', $installed, SAHA_CORE_VERSION );
		}
	}
}
