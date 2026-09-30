<?php
/**
 * Giới hạn của tài liệu builder (TECHNICAL-DESIGN §6.2).
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder;

defined( 'ABSPATH' ) || exit;

/**
 * Giới hạn cứng — chống tài liệu phình to làm chậm render/editor và chống DoS qua REST.
 */
final class Limits {

	/** Độ sâu lồng tối đa (section > row > column > … ). */
	public const MAX_DEPTH = 12;

	/** Số element tối đa trong một tài liệu. */
	public const MAX_ELEMENTS = 2000;

	/** Kích thước JSON tối đa (byte). */
	public const MAX_BYTES = 1048576;

	/** Block lồng block tối đa (mốc 1.4). */
	public const MAX_BLOCK_DEPTH = 3;

	/** Breakpoint — trùng với Theme Options (ThemeOptions\CssVariables). */
	public const TABLET_MAX = 1024;
	public const MOBILE_MAX = 767;
}
