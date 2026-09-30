<?php
/**
 * Theme Options — lỗi giá trị không hợp lệ.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\ThemeOptions;

defined( 'ABSPATH' ) || exit;

/**
 * Ném bởi Sanitizer; thông điệp hiển thị được cho người dùng (đã dịch).
 */
final class InvalidValue extends \InvalidArgumentException {
}
