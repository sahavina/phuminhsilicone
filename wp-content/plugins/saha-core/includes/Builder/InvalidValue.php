<?php
/**
 * Giá trị control không hợp lệ.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder;

defined( 'ABSPATH' ) || exit;

/**
 * Kế thừa exception của Theme Options: control builder tái dùng sanitizer màu
 * của Theme Options, bắt một kiểu là đủ cho cả hai.
 */
class InvalidValue extends \Saha\Core\ThemeOptions\InvalidValue {
}
