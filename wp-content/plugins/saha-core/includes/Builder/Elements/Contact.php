<?php
/**
 * Element: Liên hệ (hotline / email).
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\CssRules;
use Saha\Core\Builder\Icons;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * Contact — hotline (link tel:) hoặc email (mailto:). Để trống = lấy từ SAHA → Cấu hình
 * (một nguồn: đổi hotline một chỗ, header/footer đổi theo).
 */
final class Contact extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'contact',
			'name'           => __( 'Hotline / Email', 'saha-core' ),
			'icon'           => 'phone',
			'category'       => 'header',
			'allowedParents' => self::CONTENT_PARENTS,
			'controls'       => array(
				'kind'  => array(
					'type'    => 'select',
					'label'   => __( 'Loại', 'saha-core' ),
					'section' => 'content',
					'default' => 'phone',
					'options' => array(
						'phone'      => __( 'Hotline miền Bắc', 'saha-core' ),
						'phone_south' => __( 'Hotline miền Nam', 'saha-core' ),
						'email'      => __( 'Email', 'saha-core' ),
					),
				),
				'value' => array(
					'type'      => 'text',
					'label'     => __( 'Số điện thoại / email', 'saha-core' ),
					'section'   => 'content',
					'maxLength' => 120,
					'help'      => __( 'Để trống = lấy từ SAHA → Cấu hình.', 'saha-core' ),
				),
				'label' => array(
					'type'      => 'text',
					'label'     => __( 'Chữ đứng trước (ví dụ "Hotline:")', 'saha-core' ),
					'section'   => 'content',
					'maxLength' => 60,
				),
				'style' => array(
					'type'    => 'select',
					'label'   => __( 'Kiểu', 'saha-core' ),
					'section' => 'style',
					'default' => 'text',
					'options' => array(
						'text'   => __( 'Chữ + icon', 'saha-core' ),
						'button' => __( 'Nút', 'saha-core' ),
					),
				),
				'color' => array(
					'type'    => 'color',
					'label'   => __( 'Màu', 'saha-core' ),
					'section' => 'style',
				),
				'typography' => array(
					'type'    => 'typography',
					'label'   => __( 'Kiểu chữ', 'saha-core' ),
					'section' => 'style',
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
		$kind  = (string) $this->prop( $node, 'kind' );
		$value = trim( (string) $node->prop( 'value', '' ) );

		if ( '' === $value ) {
			$value = match ( $kind ) {
				'email'       => function_exists( 'saha_get_setting' ) ? (string) saha_get_setting( 'email', '' ) : '',
				'phone_south' => function_exists( 'saha_hotline' ) ? saha_hotline( 'south' ) : '',
				default       => function_exists( 'saha_hotline' ) ? saha_hotline( 'north' ) : '',
			};
		}

		if ( '' === $value ) {
			return $ctx->editor ? '<span' . $this->rootAttributes( $node, $ctx, array( 'saha-contact-el', 'saha-image--empty' ) ) . '>' . esc_html__( 'Chưa nhập hotline/email trong SAHA → Cấu hình.', 'saha-core' ) . '</span>' : '';
		}

		$is_email = 'email' === $kind;
		$href     = $is_email ? 'mailto:' . antispambot( $value ) : ( function_exists( 'saha_tel_href' ) ? saha_tel_href( $value ) : 'tel:' . preg_replace( '/[^\d+]/', '', $value ) );
		$label    = (string) $node->prop( 'label', '' );
		$button   = 'button' === $this->prop( $node, 'style' );
		$classes  = array( 'saha-contact-el' );

		if ( $button ) {
			$classes[] = 'saha-btn';
			$classes[] = 'saha-btn--primary';
		}

		return '<a href="' . esc_url( $href, array( 'tel', 'mailto' ) ) . '"' . $this->rootAttributes( $node, $ctx, $classes ) . '>'
			. Icons::svg( $is_email ? 'mail' : 'phone' )
			. ( '' !== $label ? '<span class="saha-contact-el__label">' . esc_html( $label ) . '</span> ' : '' )
			. '<span class="saha-contact-el__value">' . esc_html( $is_email ? antispambot( $value ) : $value ) . '</span></a>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', 'color', $node->prop( 'color' ) );
		$css->typography( '', $node->prop( 'typography' ) );
	}
}
