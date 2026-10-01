<?php
/**
 * Ô chọn biến thể: màu / ảnh / chữ.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\WooCommerce;

use Saha\Core\ThemeOptions\Repository as ThemeOptions;

defined( 'ABSPATH' ) || exit;

/**
 * Swatches (TECHNICAL-DESIGN §5.3, §10).
 *
 * - Kiểu ô của thuộc tính (`select | label | color | image`) lưu ở
 *   `saha_woocommerce_settings['swatches'][ pa_xxx ]` — bảng thuộc tính của WooCommerce không có meta.
 * - Màu / ảnh của từng giá trị: term meta `saha_swatch_color`, `saha_swatch_image_id`.
 * - Hiển thị: **giữ `<select>` gốc** (ẩn khỏi mắt, vẫn trong form) và vẽ ô bấm đồng bộ với nó →
 *   script biến thể của WooCommerce (giá, ảnh, tồn kho, giá trị không còn) chạy nguyên.
 * - Ô là nhóm radio (`role=radiogroup`, `aria-checked`, phím mũi tên) — dùng được bằng bàn phím.
 */
final class Swatches {

	public const OPTION      = 'saha_woocommerce_settings';
	public const COLOR_META  = 'saha_swatch_color';
	public const IMAGE_META  = 'saha_swatch_image_id';
	public const TYPES       = array( 'select', 'label', 'color', 'image' );
	public const SCRIPT      = 'saha-swatches';

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_filter( 'woocommerce_dropdown_variation_attribute_options_html', array( $this, 'render' ), 20, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );

		if ( is_admin() ) {
			add_action( 'woocommerce_after_add_attribute_fields', array( $this, 'attributeFieldAdd' ) );
			add_action( 'woocommerce_after_edit_attribute_fields', array( $this, 'attributeFieldEdit' ) );
			add_action( 'woocommerce_attribute_added', array( $this, 'attributeSave' ), 10, 2 );
			add_action( 'woocommerce_attribute_updated', array( $this, 'attributeSave' ), 10, 2 );
			add_action( 'admin_init', array( $this, 'termHooks' ) );
		}
	}

	/**
	 * Đang bật (Theme Options → Cửa hàng; theme không hỗ trợ Theme Options → bật).
	 */
	public static function enabled(): bool {
		return ! current_theme_supports( 'saha-theme-options' ) || (bool) ThemeOptions::get( 'shop.swatches', true );
	}

	/**
	 * Kiểu ô của một taxonomy thuộc tính.
	 *
	 * @param string $taxonomy pa_xxx.
	 */
	public static function type( string $taxonomy ): string {
		$settings = get_option( self::OPTION, array() );
		$type     = is_array( $settings ) ? (string) ( $settings['swatches'][ $taxonomy ] ?? 'select' ) : 'select';

		return in_array( $type, self::TYPES, true ) ? $type : 'select';
	}

	/**
	 * Lưu kiểu ô.
	 *
	 * @param string $taxonomy pa_xxx.
	 * @param string $type     Kiểu.
	 */
	public static function setType( string $taxonomy, string $type ): void {
		$settings = get_option( self::OPTION, array() );
		$settings = is_array( $settings ) ? $settings : array();

		$settings['swatches'][ $taxonomy ] = in_array( $type, self::TYPES, true ) ? $type : 'select';

		update_option( self::OPTION, $settings, true );
	}

	/**
	 * Nhãn kiểu ô.
	 *
	 * @return array<string, string>
	 */
	private static function labels(): array {
		return array(
			'select' => __( 'Danh sách thả xuống (mặc định)', 'saha-core' ),
			'label'  => __( 'Ô chữ', 'saha-core' ),
			'color'  => __( 'Ô màu', 'saha-core' ),
			'image'  => __( 'Ô ảnh', 'saha-core' ),
		);
	}

	/**
	 * Chuẩn hoá mã màu (#rgb / #rrggbb), '' nếu sai.
	 *
	 * @param mixed $value Giá trị.
	 */
	public static function color( $value ): string {
		$value = strtolower( trim( (string) $value ) );

		return (bool) preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $value ) ? $value : '';
	}

	/*
	 * ---------------------------------------------------------------------
	 * Frontend
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Ô bấm + select gốc (ẩn).
	 *
	 * @param string               $html Select của WooCommerce.
	 * @param array<string, mixed> $args Tham số wc_dropdown_variation_attribute_options().
	 */
	public function render( $html, $args ): string {
		$html      = (string) $html;
		$args      = (array) $args;
		$attribute = (string) ( $args['attribute'] ?? '' );
		$product   = $args['product'] ?? null;

		if ( ! self::enabled() || ! taxonomy_exists( $attribute ) || ! $product instanceof \WC_Product ) {
			return $html;
		}

		$type = self::type( $attribute );

		if ( 'select' === $type ) {
			return $html;
		}

		$options  = array_map( 'strval', (array) ( $args['options'] ?? array() ) );
		$selected = (string) ( $args['selected'] ?? '' );
		$buttons  = '';

		foreach ( wc_get_product_terms( $product->get_id(), $attribute, array( 'fields' => 'all' ) ) as $term ) {
			if ( ! in_array( (string) $term->slug, $options, true ) ) {
				continue;
			}

			$on    = $selected === (string) $term->slug;
			$inner = esc_html( $term->name );

			if ( 'color' === $type ) {
				$color = self::color( get_term_meta( $term->term_id, self::COLOR_META, true ) );
				$inner = '<span class="saha-swatch__color" style="background:' . esc_attr( '' !== $color ? $color : '#e5e7eb' ) . '"></span><span class="screen-reader-text">' . esc_html( $term->name ) . '</span>';
			} elseif ( 'image' === $type ) {
				$image = (int) get_term_meta( $term->term_id, self::IMAGE_META, true );
				$img   = $image > 0 ? (string) wp_get_attachment_image( $image, 'thumbnail', false, array( 'class' => 'saha-swatch__img', 'alt' => '' ) ) : '';
				$inner = ( '' !== $img ? $img : '<span class="saha-swatch__color"></span>' ) . '<span class="screen-reader-text">' . esc_html( $term->name ) . '</span>';
			}

			$buttons .= '<button type="button" class="saha-swatch saha-swatch--' . esc_attr( $type ) . '" role="radio" aria-checked="' . ( $on ? 'true' : 'false' ) . '" tabindex="' . ( $on ? '0' : '-1' ) . '" data-value="' . esc_attr( $term->slug ) . '" title="' . esc_attr( $term->name ) . '">' . $inner . '</button>';
		}

		if ( '' === $buttons ) {
			return $html;
		}

		$label = wc_attribute_label( $attribute, $product );

		return '<div class="saha-swatches saha-swatches--' . esc_attr( $type ) . '" role="radiogroup" aria-label="' . esc_attr( $label ) . '" data-saha-swatches data-attribute="' . esc_attr( 'attribute_' . sanitize_title( $attribute ) ) . '">' . $buttons . '</div>'
			. '<div class="saha-swatches__select">' . $html . '</div>';
	}

	/**
	 * Script + CSS: trang sản phẩm (và trang có Xem nhanh).
	 */
	public function enqueue(): void {
		if ( ! self::enabled() ) {
			return;
		}

		wp_register_style( self::SCRIPT, SAHA_CORE_URL . 'public/assets/css/swatches.css', array(), SAHA_CORE_VERSION );
		wp_register_script(
			self::SCRIPT,
			SAHA_CORE_URL . 'public/assets/js/swatches.js',
			array( 'jquery' ),
			SAHA_CORE_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		if ( function_exists( 'is_product' ) && is_product() ) {
			wp_enqueue_style( self::SCRIPT );
			wp_enqueue_script( self::SCRIPT );
		}
	}

	/*
	 * ---------------------------------------------------------------------
	 * Admin: kiểu ô của thuộc tính (Sản phẩm → Thuộc tính)
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Ô chọn kiểu ở form "Thêm thuộc tính".
	 */
	public function attributeFieldAdd(): void {
		echo '<div class="form-field"><label for="saha_swatch_type">' . esc_html__( 'Kiểu chọn trên trang sản phẩm', 'saha-core' ) . '</label>';
		$this->typeSelect( 'select' );
		echo '<p class="description">' . esc_html__( 'Ô màu / ảnh: đặt màu, ảnh ở từng giá trị (Cấu hình giá trị).', 'saha-core' ) . '</p></div>';
	}

	/**
	 * Ô chọn kiểu ở form "Sửa thuộc tính".
	 */
	public function attributeFieldEdit(): void {
		$id   = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- chỉ đọc để hiển thị.
		$name = $id > 0 ? wc_attribute_taxonomy_name_by_id( $id ) : '';

		echo '<tr class="form-field"><th scope="row" valign="top"><label for="saha_swatch_type">' . esc_html__( 'Kiểu chọn trên trang sản phẩm', 'saha-core' ) . '</label></th><td>';
		$this->typeSelect( '' !== $name ? self::type( $name ) : 'select' );
		echo '<p class="description">' . esc_html__( 'Ô màu / ảnh: đặt màu, ảnh ở từng giá trị (Cấu hình giá trị).', 'saha-core' ) . '</p></td></tr>';
	}

	/**
	 * Select kiểu ô.
	 *
	 * @param string $current Kiểu đang dùng.
	 */
	private function typeSelect( string $current ): void {
		echo '<select name="saha_swatch_type" id="saha_swatch_type">';

		foreach ( self::labels() as $value => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $current, $value, false ), esc_html( $label ) );
		}

		echo '</select>';
	}

	/**
	 * Lưu kiểu ô khi thêm/sửa thuộc tính (WooCommerce đã kiểm nonce + quyền trước khi gọi hook).
	 *
	 * @param int                  $id   ID thuộc tính.
	 * @param array<string, mixed> $data Dữ liệu thuộc tính.
	 */
	public function attributeSave( $id, $data ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce đã check_admin_referer trước khi bắn hook này.
		if ( ! isset( $_POST['saha_swatch_type'] ) || ! current_user_can( 'manage_product_terms' ) ) {
			return;
		}

		$slug = (string) ( ( (array) $data )['attribute_name'] ?? '' );

		if ( '' !== $slug ) {
			self::setType( wc_attribute_taxonomy_name( $slug ), sanitize_key( wp_unslash( $_POST['saha_swatch_type'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
	}

	/*
	 * ---------------------------------------------------------------------
	 * Admin: màu / ảnh của từng giá trị
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Gắn ô màu/ảnh cho mọi taxonomy thuộc tính.
	 */
	public function termHooks(): void {
		if ( ! function_exists( 'wc_get_attribute_taxonomy_names' ) ) {
			return;
		}

		foreach ( wc_get_attribute_taxonomy_names() as $taxonomy ) {
			add_action( $taxonomy . '_add_form_fields', array( $this, 'termFieldsAdd' ) );
			add_action( $taxonomy . '_edit_form_fields', array( $this, 'termFieldsEdit' ), 10, 2 );
			add_action( 'created_' . $taxonomy, array( $this, 'termSave' ) );
			add_action( 'edited_' . $taxonomy, array( $this, 'termSave' ) );
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'termAssets' ) );
	}

	/**
	 * Thư viện ảnh trên màn hình sửa giá trị thuộc tính.
	 *
	 * @param string $hook Màn hình.
	 */
	public function termAssets( $hook ): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) && $screen && 0 === strpos( (string) $screen->taxonomy, 'pa_' ) ) {
			wp_enqueue_media();
		}
	}

	/**
	 * Ô màu + ảnh (form thêm giá trị).
	 *
	 * @param string $taxonomy Taxonomy.
	 */
	public function termFieldsAdd( $taxonomy ): void {
		$type = self::type( (string) $taxonomy );

		if ( ! in_array( $type, array( 'color', 'image' ), true ) ) {
			return;
		}

		wp_nonce_field( 'saha_swatch_term', 'saha_swatch_nonce' );
		echo '<div class="form-field">';
		$this->termInputs( $type, '', 0 );
		echo '</div>';
	}

	/**
	 * Ô màu + ảnh (form sửa giá trị).
	 *
	 * @param \WP_Term $term     Term.
	 * @param string   $taxonomy Taxonomy.
	 */
	public function termFieldsEdit( $term, $taxonomy ): void {
		$type = self::type( (string) $taxonomy );

		if ( ! $term instanceof \WP_Term || ! in_array( $type, array( 'color', 'image' ), true ) ) {
			return;
		}

		wp_nonce_field( 'saha_swatch_term', 'saha_swatch_nonce' );
		echo '<tr class="form-field"><th scope="row">' . esc_html__( 'Ô chọn', 'saha-core' ) . '</th><td>';
		$this->termInputs( $type, (string) get_term_meta( $term->term_id, self::COLOR_META, true ), (int) get_term_meta( $term->term_id, self::IMAGE_META, true ) );
		echo '</td></tr>';
	}

	/**
	 * Ô nhập.
	 *
	 * @param string $type  color | image.
	 * @param string $color Màu hiện tại.
	 * @param int    $image Ảnh hiện tại.
	 */
	private function termInputs( string $type, string $color, int $image ): void {
		if ( 'color' === $type ) {
			printf(
				'<label for="saha-swatch-color">%1$s</label> <input type="color" id="saha-swatch-color" name="saha_swatch_color" value="%2$s">',
				esc_html__( 'Màu', 'saha-core' ),
				esc_attr( '' !== self::color( $color ) ? self::color( $color ) : '#cccccc' )
			);
			return;
		}

		$preview = $image > 0 ? (string) wp_get_attachment_image( $image, 'thumbnail', false, array( 'style' => 'max-width:60px;height:auto;display:block;margin:6px 0' ) ) : '';

		printf(
			'<label for="saha-swatch-image">%1$s</label> <span class="saha-swatch-preview">%2$s</span><input type="number" min="0" class="small-text" id="saha-swatch-image" name="saha_swatch_image_id" value="%3$d"> <button type="button" class="button" data-saha-swatch-media>%4$s</button>'
			. '<script>document.querySelectorAll("[data-saha-swatch-media]").forEach(function(b){b.addEventListener("click",function(){if(!window.wp||!wp.media){return;}var f=wp.media({multiple:false,library:{type:"image"}});f.on("select",function(){var a=f.state().get("selection").first().toJSON();document.getElementById("saha-swatch-image").value=a.id;});f.open();});});</script>',
			esc_html__( 'Ảnh (ID)', 'saha-core' ),
			$preview, // phpcs:ignore WordPress.Security.EscapeOutput -- wp_get_attachment_image đã escape.
			(int) $image,
			esc_html__( 'Chọn ảnh', 'saha-core' )
		);
	}

	/**
	 * Lưu màu / ảnh của giá trị.
	 *
	 * @param int $term_id Term.
	 */
	public function termSave( $term_id ): void {
		$nonce = isset( $_POST['saha_swatch_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['saha_swatch_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'saha_swatch_term' ) || ! current_user_can( 'manage_product_terms' ) ) {
			return;
		}

		if ( isset( $_POST['saha_swatch_color'] ) ) {
			$color = self::color( sanitize_text_field( wp_unslash( $_POST['saha_swatch_color'] ) ) );
			'' !== $color ? update_term_meta( (int) $term_id, self::COLOR_META, $color ) : delete_term_meta( (int) $term_id, self::COLOR_META );
		}

		if ( isset( $_POST['saha_swatch_image_id'] ) ) {
			$image = absint( wp_unslash( $_POST['saha_swatch_image_id'] ) );
			$image > 0 && wp_attachment_is_image( $image ) ? update_term_meta( (int) $term_id, self::IMAGE_META, $image ) : delete_term_meta( (int) $term_id, self::IMAGE_META );
		}
	}
}
