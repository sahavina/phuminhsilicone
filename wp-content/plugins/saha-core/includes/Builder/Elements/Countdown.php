<?php
/**
 * Element: đếm ngược tới một thời điểm (khuyến mãi, sự kiện).
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\CssRules;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * Countdown — server in sẵn số còn lại (không nhảy bố cục, không JS vẫn đúng lúc tải trang);
 * elements.js (`[data-saha-countdown]`) cập nhật mỗi giây. Hết giờ → chữ "Đã kết thúc" (hoặc ẩn).
 * Giờ nhập theo múi giờ của website. `dynamic`: số phụ thuộc thời điểm tải trang.
 */
final class Countdown extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'countdown',
			'name'           => __( 'Đếm ngược', 'saha-core' ),
			'icon'           => 'clock',
			'category'       => 'marketing',
			'allowedParents' => self::CONTENT_PARENTS,
			'dynamic'        => true,
			'controls'       => array(
				'until'      => array(
					'type'      => 'text',
					'label'     => __( 'Kết thúc lúc (YYYY-MM-DD HH:MM, giờ website)', 'saha-core' ),
					'section'   => 'content',
					'default'   => '',
					'maxLength' => 16,
				),
				'expired'    => array(
					'type'    => 'select',
					'label'   => __( 'Khi hết giờ', 'saha-core' ),
					'section' => 'content',
					'default' => 'text',
					'options' => array(
						'text' => __( 'Hiện chữ thông báo', 'saha-core' ),
						'hide' => __( 'Ẩn đếm ngược', 'saha-core' ),
					),
				),
				'doneText'   => array(
					'type'      => 'text',
					'label'     => __( 'Chữ khi hết giờ', 'saha-core' ),
					'section'   => 'content',
					'default'   => __( 'Chương trình đã kết thúc', 'saha-core' ),
					'maxLength' => 120,
				),
				'showDays'   => array(
					'type'    => 'toggle',
					'label'   => __( 'Hiện ngày', 'saha-core' ),
					'section' => 'content',
					'default' => true,
				),
				'boxBg'      => array(
					'type'    => 'color',
					'label'   => __( 'Nền ô số', 'saha-core' ),
					'section' => 'style',
				),
				'numberColor' => array(
					'type'    => 'color',
					'label'   => __( 'Màu số', 'saha-core' ),
					'section' => 'style',
				),
				'align'      => array(
					'type'       => 'align',
					'label'      => __( 'Căn lề', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'options'    => array( 'left', 'center', 'right' ),
				),
			),
		);
	}

	/**
	 * Thời điểm kết thúc (timestamp) hoặc null nếu chuỗi sai định dạng.
	 *
	 * @param string $value YYYY-MM-DD HH:MM.
	 */
	public static function target( string $value ): ?int {
		$value = trim( $value );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}$/', $value ) ) {
			return null;
		}

		$date = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i', str_replace( 'T', ' ', $value ), wp_timezone() );
		$errs = \DateTimeImmutable::getLastErrors();

		if ( ! $date || ( is_array( $errs ) && ( $errs['warning_count'] || $errs['error_count'] ) ) ) {
			return null;
		}

		return $date->getTimestamp();
	}

	/**
	 * Chia số giây còn lại.
	 *
	 * @param int  $seconds Giây (≥ 0).
	 * @param bool $days    Có tách ngày không (không → dồn vào giờ).
	 * @return array{d: int, h: int, m: int, s: int}
	 */
	public static function split( int $seconds, bool $days ): array {
		$seconds = max( 0, $seconds );
		$d       = $days ? intdiv( $seconds, 86400 ) : 0;
		$rest    = $seconds - $d * 86400;

		return array(
			'd' => $d,
			'h' => intdiv( $rest, 3600 ),
			'm' => intdiv( $rest % 3600, 60 ),
			's' => $rest % 60,
		);
	}

	/**
	 * Render.
	 *
	 * @param Node          $node    Node.
	 * @param RenderContext $ctx     Ngữ cảnh.
	 * @param string        $content HTML con (không dùng).
	 */
	public function render( Node $node, RenderContext $ctx, string $content ): string {
		$target = self::target( (string) $this->prop( $node, 'until' ) );

		if ( null === $target ) {
			return $ctx->editor
				? '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-countdown', 'saha-image--empty' ) ) . '>' . esc_html__( 'Nhập thời điểm kết thúc, ví dụ 2026-12-31 23:59', 'saha-core' ) . '</div>'
				: '';
		}

		$left    = $target - time();
		$days    = (bool) $this->prop( $node, 'showDays' );
		$done    = (string) $this->prop( $node, 'doneText' );
		$hide    = 'hide' === $this->prop( $node, 'expired' );
		$attrs   = array(
			'data-saha-countdown' => (string) $target,
			'data-expired'        => $hide ? 'hide' : 'text',
			'role'                => 'timer',
		);

		if ( $left <= 0 ) {
			if ( $hide && ! $ctx->editor ) {
				return '';
			}

			return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-countdown', 'is-done' ), $attrs ) . '><p class="saha-countdown__done">' . esc_html( $done ) . '</p></div>';
		}

		$parts  = self::split( $left, $days );
		$labels = array(
			'd' => __( 'Ngày', 'saha-core' ),
			'h' => __( 'Giờ', 'saha-core' ),
			'm' => __( 'Phút', 'saha-core' ),
			's' => __( 'Giây', 'saha-core' ),
		);
		$boxes  = '';

		foreach ( $labels as $key => $label ) {
			if ( 'd' === $key && ! $days ) {
				continue;
			}

			$boxes .= '<span class="saha-countdown__box"><span class="saha-countdown__num" data-part="' . $key . '">' . esc_html( str_pad( (string) $parts[ $key ], 2, '0', STR_PAD_LEFT ) ) . '</span><span class="saha-countdown__label">' . esc_html( $label ) . '</span></span>';
		}

		return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-countdown' ), $attrs + array( 'data-done' => $done ) ) . '>'
			. '<div class="saha-countdown__boxes">' . $boxes . '</div></div>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', '--saha-countdown-bg', $node->prop( 'boxBg' ) );
		$css->set( '', '--saha-countdown-color', $node->prop( 'numberColor' ) );
		$css->set( '', 'text-align', $node->prop( 'align' ) );
	}
}
