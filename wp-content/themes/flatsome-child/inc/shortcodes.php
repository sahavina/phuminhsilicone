<?php
/**
 * Shortcode presentation. Chỉ render dữ liệu lấy từ plugin/settings.
 *
 * @package Flatsome_Child_Saha
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * [saha_hotline region="north|south|all" label="1"]
 *
 * Hotline lấy từ settings, href tel: chỉ chứa digits (spec §85).
 */
add_shortcode(
	'saha_hotline',
	static function ( $atts ): string {
		$atts = shortcode_atts(
			array(
				'region' => 'all',
				'label'  => '1',
			),
			is_array( $atts ) ? $atts : array(),
			'saha_hotline'
		);

		$hotlines = saha_theme_hotlines();

		if ( 'all' !== $atts['region'] ) {
			$hotlines = array_values(
				array_filter(
					$hotlines,
					static fn( array $item ): bool => $item['region'] === $atts['region']
				)
			);
		}

		if ( ! $hotlines ) {
			return '';
		}

		$show_label = '1' === (string) $atts['label'];
		$out        = '<span class="saha-hotline-group">';

		foreach ( $hotlines as $hotline ) {
			$out .= sprintf(
				'<a class="saha-hotline" href="%1$s" data-saha-event="click_phone" data-saha-region="%2$s">%3$s<span class="saha-hotline__number">%4$s</span></a>',
				esc_url( $hotline['href'] ),
				esc_attr( $hotline['region'] ),
				$show_label ? '<span class="saha-hotline__label">' . esc_html( $hotline['label'] ) . '</span>' : '',
				esc_html( $hotline['number'] )
			);
		}

		return $out . '</span>';
	}
);

/**
 * [saha_zalo text="Chat Zalo"]
 */
add_shortcode(
	'saha_zalo',
	static function ( $atts ): string {
		$atts = shortcode_atts(
			array( 'text' => __( 'Chat Zalo', 'flatsome-child' ) ),
			is_array( $atts ) ? $atts : array(),
			'saha_zalo'
		);

		$url = saha_theme_zalo_url();

		if ( '' === $url ) {
			return '';
		}

		return sprintf(
			'<a class="saha-zalo" href="%1$s" target="_blank" rel="noopener nofollow" data-saha-event="click_zalo">%2$s</a>',
			esc_url( $url ),
			esc_html( (string) $atts['text'] )
		);
	}
);

/**
 * [saha_company field="company_name|address|website|email"]
 *
 * Tránh hardcode thông tin công ty trong UX Builder block.
 */
add_shortcode(
	'saha_company',
	static function ( $atts ): string {
		$atts = shortcode_atts(
			array( 'field' => 'company_name' ),
			is_array( $atts ) ? $atts : array(),
			'saha_company'
		);

		$allowed = array( 'company_name', 'site_brand', 'address', 'website', 'email' );
		$field   = sanitize_key( (string) $atts['field'] );

		if ( ! in_array( $field, $allowed, true ) ) {
			return '';
		}

		$value = (string) saha_theme_setting( $field );

		if ( '' === $value ) {
			return '';
		}

		if ( 'website' === $field ) {
			return sprintf( '<a class="saha-company-website" href="%1$s">%2$s</a>', esc_url( $value ), esc_html( $value ) );
		}

		if ( 'email' === $field ) {
			return sprintf( '<a class="saha-company-email" href="mailto:%1$s">%2$s</a>', esc_attr( $value ), esc_html( $value ) );
		}

		return '<span class="saha-company-' . esc_attr( $field ) . '">' . esc_html( $value ) . '</span>';
	}
);
