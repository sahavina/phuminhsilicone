<?php
/**
 * Element: Mạng xã hội.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\Controls\Link;
use Saha\Core\Builder\CssRules;
use Saha\Core\Builder\Icons;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * Social — danh sách link mạng xã hội (icon SVG, mở tab mới, rel=noopener).
 * Zalo để trống = lấy từ SAHA → Cấu hình.
 */
final class Social extends Element {

	public const NETWORKS = array( 'facebook', 'zalo', 'youtube', 'tiktok', 'instagram', 'linkedin' );

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		$controls = array();

		foreach ( self::NETWORKS as $network ) {
			$controls[ $network ] = array(
				'type'    => 'text',
				'label'   => ucfirst( $network ),
				'section' => 'content',
				'maxLength' => 300,
			);
		}

		$controls['zalo']['help'] = __( 'Để trống = số Zalo trong SAHA → Cấu hình.', 'saha-core' );

		return array(
			'type'           => 'social',
			'name'           => __( 'Mạng xã hội', 'saha-core' ),
			'icon'           => 'share',
			'category'       => 'header',
			'allowedParents' => self::CONTENT_PARENTS,
			'controls'       => $controls + array(
				'size'  => array(
					'type'    => 'size',
					'label'   => __( 'Cỡ icon', 'saha-core' ),
					'section' => 'style',
					'units'   => array( 'px' ),
					'min'     => 12,
					'max'     => 64,
				),
				'color' => array(
					'type'    => 'color',
					'label'   => __( 'Màu', 'saha-core' ),
					'section' => 'style',
				),
				'gap'   => array(
					'type'    => 'size',
					'label'   => __( 'Khoảng cách', 'saha-core' ),
					'section' => 'style',
					'units'   => array( 'px' ),
					'min'     => 0,
					'max'     => 60,
				),
			),
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
		$items = '';

		foreach ( self::NETWORKS as $network ) {
			$url = trim( (string) $node->prop( $network, '' ) );

			if ( '' === $url && 'zalo' === $network && function_exists( 'saha_zalo_url' ) ) {
				$url = saha_zalo_url();
			}

			try {
				$url = Link::url( $url );
			} catch ( \Throwable $e ) {
				$url = '';
			}

			if ( '' === $url ) {
				continue;
			}

			$items .= '<li><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . Icons::svg( $network ) . '<span class="screen-reader-text">' . esc_html( ucfirst( $network ) ) . '</span></a></li>';
		}

		if ( '' === $items ) {
			return $ctx->editor ? '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-social', 'saha-image--empty' ) ) . '>' . esc_html__( 'Nhập link mạng xã hội.', 'saha-core' ) . '</div>' : '';
		}

		return '<ul' . $this->rootAttributes( $node, $ctx, array( 'saha-social' ) ) . '>' . $items . '</ul>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', 'font-size', $node->prop( 'size' ) );
		$css->set( ' a', 'color', $node->prop( 'color' ) );
		$css->set( '', 'gap', $node->prop( 'gap' ) );
	}
}
