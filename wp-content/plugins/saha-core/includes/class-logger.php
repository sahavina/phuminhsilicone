<?php
/**
 * Logger abstraction.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Logger: ghi vào wp_saha_logs và/hoặc debug.log.
 *
 * Không bao giờ log password, secret, token, payment info (spec §41).
 */
final class Logger {

	public const DEBUG   = 'debug';
	public const INFO    = 'info';
	public const WARNING = 'warning';
	public const ERROR   = 'error';

	/**
	 * Key bị loại khỏi context trước khi ghi.
	 *
	 * @var string[]
	 */
	private const REDACTED_KEYS = array( 'password', 'pass', 'pwd', 'secret', 'token', 'api_key', 'apikey', 'authorization', 'auth', 'card', 'cvv', 'nonce', '_wpnonce' );

	/**
	 * Không cần hook, nhưng giữ interface chung của module.
	 */
	public function register(): void {
		add_action( 'saha_log', array( __CLASS__, 'log' ), 10, 4 );
	}

	/**
	 * Ghi log.
	 *
	 * @param string               $message Nội dung.
	 * @param string               $level   Mức độ.
	 * @param string               $channel Kênh, ví dụ `quote`.
	 * @param array<string, mixed> $context Dữ liệu kèm.
	 */
	public static function log( string $message, string $level = self::INFO, string $channel = 'core', array $context = array() ): void {
		$level   = in_array( $level, array( self::DEBUG, self::INFO, self::WARNING, self::ERROR ), true ) ? $level : self::INFO;
		$channel = sanitize_key( $channel );
		$message = wp_strip_all_tags( $message );
		$context = self::redact( $context );

		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- chỉ khi WP_DEBUG_LOG.
				sprintf( '[saha:%s][%s] %s %s', $channel, $level, $message, $context ? wp_json_encode( $context ) : '' )
			);
		}

		if ( ! Settings::get( 'log_to_database', true ) ) {
			return;
		}

		self::write_row( $level, $channel, $message, $context );
	}

	/**
	 * Shortcut các mức.
	 *
	 * @param string               $message Nội dung.
	 * @param string               $channel Kênh.
	 * @param array<string, mixed> $context Context.
	 */
	public static function error( string $message, string $channel = 'core', array $context = array() ): void {
		self::log( $message, self::ERROR, $channel, $context );
	}

	/**
	 * Warning.
	 *
	 * @param string               $message Nội dung.
	 * @param string               $channel Kênh.
	 * @param array<string, mixed> $context Context.
	 */
	public static function warning( string $message, string $channel = 'core', array $context = array() ): void {
		self::log( $message, self::WARNING, $channel, $context );
	}

	/**
	 * Info.
	 *
	 * @param string               $message Nội dung.
	 * @param string               $channel Kênh.
	 * @param array<string, mixed> $context Context.
	 */
	public static function info( string $message, string $channel = 'core', array $context = array() ): void {
		self::log( $message, self::INFO, $channel, $context );
	}

	/**
	 * Ghi một dòng vào bảng logs.
	 *
	 * @param string               $level   Mức.
	 * @param string               $channel Kênh.
	 * @param string               $message Nội dung.
	 * @param array<string, mixed> $context Context.
	 */
	private static function write_row( string $level, string $channel, string $message, array $context ): void {
		global $wpdb;

		$table = Migrator::table( 'logs' );

		if ( ! Migrator::table_exists( $table ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table, $wpdb->insert đã prepare.
		$wpdb->insert(
			$table,
			array(
				'level'      => $level,
				'channel'    => $channel,
				'message'    => mb_substr( $message, 0, 5000 ),
				'context'    => $context ? (string) wp_json_encode( $context ) : null,
				'user_id'    => get_current_user_id(),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Loại bỏ dữ liệu nhạy cảm khỏi context.
	 *
	 * @param array<string, mixed> $context Context thô.
	 * @return array<string, mixed>
	 */
	private static function redact( array $context ): array {
		$clean = array();

		foreach ( $context as $key => $value ) {
			$needle = strtolower( (string) $key );
			$hit    = false;

			foreach ( self::REDACTED_KEYS as $blocked ) {
				if ( false !== strpos( $needle, $blocked ) ) {
					$hit = true;
					break;
				}
			}

			if ( $hit ) {
				$clean[ $key ] = '[redacted]';
				continue;
			}

			$clean[ $key ] = is_array( $value ) ? self::redact( $value ) : $value;
		}

		return $clean;
	}
}
