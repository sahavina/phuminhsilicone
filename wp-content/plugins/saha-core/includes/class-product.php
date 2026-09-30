<?php
/**
 * Product custom fields (WooCommerce product data panels) + product service.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Product: bổ sung field ngành keo vào sản phẩm WooCommerce.
 *
 * KHÔNG tạo CPT sản phẩm mới. KHÔNG tạo field trùng WooCommerce attribute
 * (màu/dung tích/xuất xứ/quy cách dùng pa_* — spec §6).
 *
 * Field được chia panel để admin dễ dùng (spec §46).
 */
final class Product {

	/**
	 * Prefix post meta.
	 */
	private const META_PREFIX = '_saha_';

	/**
	 * Nonce.
	 */
	private const NONCE_ACTION = 'saha_save_product_meta';
	private const NONCE_FIELD  = 'saha_product_nonce';

	/**
	 * Trạng thái hàng tuỳ biến, map sang WooCommerce stock status (spec §66).
	 *
	 * @return array<string, string>
	 */
	public static function availability_options(): array {
		return array(
			''         => __( '— Theo tồn kho WooCommerce —', 'saha-core' ),
			'in_stock' => __( 'Sẵn hàng', 'saha-core' ),
			'contact'  => __( 'Liên hệ', 'saha-core' ),
			'out'      => __( 'Hết hàng', 'saha-core' ),
		);
	}

	/**
	 * Loại tài liệu (spec §67).
	 *
	 * @return array<string, string>
	 */
	public static function document_types(): array {
		return array(
			'tds'        => __( 'TDS — Thông số kỹ thuật', 'saha-core' ),
			'sds'        => __( 'SDS — An toàn hoá chất', 'saha-core' ),
			'catalogue'  => __( 'Catalogue', 'saha-core' ),
			'manual'     => __( 'Hướng dẫn sử dụng', 'saha-core' ),
			'other'      => __( 'Tài liệu khác', 'saha-core' ),
		);
	}

	/**
	 * Panel và field: panel_key => [ label, fields => [ key => [type,label,...] ] ].
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function panels(): array {
		$panels = array(
			'info'  => array(
				'label'  => __( 'Thông tin SAHA', 'saha-core' ),
				'fields' => array(
					'unit'         => array(
						'type'  => 'text',
						'label' => __( 'Đơn vị tính', 'saha-core' ),
						'hint'  => __( 'Ví dụ: tuýp, chai, thùng.', 'saha-core' ),
					),
					'product_line' => array(
						'type'  => 'text',
						'label' => __( 'Dòng sản phẩm', 'saha-core' ),
					),
					'availability' => array(
						'type'    => 'select',
						'label'   => __( 'Tình trạng hàng', 'saha-core' ),
						'options' => 'availability',
					),
				),
			),
			'specs' => array(
				'label'  => __( 'Thông số', 'saha-core' ),
				'fields' => array(
					'specs' => array(
						'type'  => 'repeater',
						'label' => __( 'Thông số kỹ thuật', 'saha-core' ),
						'hint'  => __( 'Mỗi dòng một thông số: tên và giá trị.', 'saha-core' ),
					),
				),
			),
			'usage' => array(
				'label'  => __( 'Ứng dụng & hướng dẫn', 'saha-core' ),
				'fields' => array(
					'application_text' => array(
						'type'  => 'html',
						'label' => __( 'Ứng dụng', 'saha-core' ),
					),
					'usage'            => array(
						'type'  => 'html',
						'label' => __( 'Hướng dẫn sử dụng', 'saha-core' ),
					),
					'warning'          => array(
						'type'  => 'html',
						'label' => __( 'Lưu ý', 'saha-core' ),
					),
				),
			),
			'docs'  => array(
				'label'  => __( 'Tài liệu', 'saha-core' ),
				'fields' => array(
					'docs' => array(
						'type'  => 'documents',
						'label' => __( 'Tài liệu PDF', 'saha-core' ),
						'hint'  => __( 'Lưu attachment ID từ Media Library, không lưu URL tuyệt đối.', 'saha-core' ),
					),
				),
			),
			'cta'   => array(
				'label'  => __( 'CTA', 'saha-core' ),
				'fields' => array(
					'cta_mode'  => array(
						'type'    => 'select',
						'label'   => __( 'Chế độ CTA', 'saha-core' ),
						'options' => 'cta',
					),
					'cta_label' => array(
						'type'  => 'text',
						'label' => __( 'Nhãn CTA riêng', 'saha-core' ),
						'hint'  => __( 'Để trống sẽ dùng nhãn mặc định trong SAHA → Cấu hình.', 'saha-core' ),
					),
				),
			),
		);

		/**
		 * Lọc panel/field sản phẩm.
		 *
		 * @param array<string, array<string, mixed>> $panels Panel definition.
		 */
		return (array) apply_filters( 'saha_product_panels', $panels );
	}

	/**
	 * Options cho field select.
	 *
	 * @param string $set Tên bộ option.
	 * @return array<string, string>
	 */
	public static function options( string $set ): array {
		if ( 'availability' === $set ) {
			return self::availability_options();
		}

		if ( 'cta' === $set ) {
			return array(
				''        => __( '— Theo cấu hình chung —', 'saha-core' ),
				'quote'   => __( 'Yêu cầu báo giá', 'saha-core' ),
				'hotline' => __( 'Chỉ hiện hotline', 'saha-core' ),
				'hidden'  => __( 'Ẩn CTA', 'saha-core' ),
			);
		}

		return array();
	}

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_tabs' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_panels' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save' ), 10, 1 );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Service API
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Lấy một meta đã sanitize.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $key        Key không prefix.
	 * @return mixed
	 */
	public static function get_meta( int $product_id, string $key ) {
		return get_post_meta( $product_id, self::META_PREFIX . $key, true );
	}

	/**
	 * Thông số kỹ thuật dạng [ [label, value], … ].
	 *
	 * @param int $product_id Product ID.
	 * @return array<int, array<string, string>>
	 */
	public static function get_specs( int $product_id ): array {
		$raw = self::get_meta( $product_id, 'specs' );

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();

		foreach ( $raw as $row ) {
			$label = trim( (string) ( $row['label'] ?? '' ) );
			$value = trim( (string) ( $row['value'] ?? '' ) );

			if ( '' === $label && '' === $value ) {
				continue;
			}

			$out[] = array(
				'label' => $label,
				'value' => $value,
			);
		}

		return $out;
	}

	/**
	 * Tài liệu kèm theo, chỉ trả về attachment còn tồn tại.
	 *
	 * @param int $product_id Product ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_documents( int $product_id ): array {
		$raw = self::get_meta( $product_id, 'docs' );

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$types = self::document_types();
		$out   = array();

		foreach ( $raw as $row ) {
			$attachment_id = absint( $row['id'] ?? 0 );

			if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
				continue;
			}

			$type = (string) ( $row['type'] ?? 'other' );
			$url  = wp_get_attachment_url( $attachment_id );

			if ( ! $url ) {
				continue;
			}

			$out[] = array(
				'id'        => $attachment_id,
				'type'      => $type,
				'type_label' => $types[ $type ] ?? $types['other'],
				'title'     => (string) ( $row['title'] ?? get_the_title( $attachment_id ) ),
				'url'       => $url,
			);
		}

		return $out;
	}

	/**
	 * Nhãn tình trạng hàng. Ưu tiên field tuỳ biến, fallback WooCommerce stock status.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function get_availability_label( int $product_id ): string {
		$custom  = (string) self::get_meta( $product_id, 'availability' );
		$options = self::availability_options();

		if ( '' !== $custom && isset( $options[ $custom ] ) ) {
			return $options[ $custom ];
		}

		if ( ! function_exists( 'wc_get_product' ) ) {
			return '';
		}

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return '';
		}

		return 'outofstock' === $product->get_stock_status()
			? $options['out']
			: $options['in_stock'];
	}

	/**
	 * Dữ liệu product card, dùng cho grid/search/API.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>
	 */
	public static function get_card_data( int $product_id ): array {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return array();
		}

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return array();
		}

		$brand = Brand::get_for_product( $product_id );

		$data = array(
			'id'           => $product_id,
			'name'         => $product->get_name(),
			'url'          => get_permalink( $product_id ),
			'sku'          => $product->get_sku(),
			'thumbnail_id' => (int) $product->get_image_id(),
			'brand'        => $brand['name'] ?? '',
			'brand_url'    => $brand['url'] ?? '',
			'availability' => self::get_availability_label( $product_id ),
			'unit'         => (string) self::get_meta( $product_id, 'unit' ),
		);

		/**
		 * Lọc dữ liệu product card.
		 *
		 * @param array<string, mixed> $data       Dữ liệu.
		 * @param int                  $product_id Product ID.
		 */
		return (array) apply_filters( 'saha_product_card_data', $data, $product_id );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Admin UI
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Thêm tab vào product data.
	 *
	 * @param array<string, array<string, mixed>> $tabs Tab hiện có.
	 * @return array<string, array<string, mixed>>
	 */
	public function add_tabs( array $tabs ): array {
		$priority = 80;

		foreach ( self::panels() as $key => $panel ) {
			$tabs[ 'saha_' . $key ] = array(
				'label'    => (string) $panel['label'],
				'target'   => 'saha_' . $key . '_panel',
				'class'    => array(),
				'priority' => $priority,
			);

			$priority += 2;
		}

		return $tabs;
	}

	/**
	 * Render toàn bộ panel.
	 */
	public function render_panels(): void {
		if ( ! current_user_can( Roles::CAP_PRODUCTS ) ) {
			return;
		}

		global $post;

		$product_id = $post instanceof \WP_Post ? (int) $post->ID : 0;
		$panels     = self::panels();

		require SAHA_CORE_PATH . 'admin/views/product-panels.php';
	}

	/**
	 * Lưu meta qua CRUD object của WooCommerce.
	 *
	 * WooCommerce đã verify nonce của metabox sản phẩm; ta vẫn kiểm tra
	 * capability và nonce riêng để phòng trường hợp gọi từ luồng khác.
	 *
	 * @param \WC_Product $product Product object.
	 */
	public function save( $product ): void {
		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		if ( ! current_user_can( Roles::CAP_PRODUCTS ) ) {
			return;
		}

		if ( ! Security::guard_admin_action( Roles::CAP_PRODUCTS, self::NONCE_ACTION, self::NONCE_FIELD ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify ở guard_admin_action().
		$bag = isset( $_POST['saha_product'] ) && is_array( $_POST['saha_product'] )
			? (array) wp_unslash( $_POST['saha_product'] )
			: array();

		foreach ( self::panels() as $panel ) {
			foreach ( (array) $panel['fields'] as $key => $field ) {
				$type = (string) ( $field['type'] ?? 'text' );

				if ( ! array_key_exists( $key, $bag ) && 'repeater' !== $type && 'documents' !== $type ) {
					continue;
				}

				$value = $this->sanitize_field( $bag[ $key ] ?? array(), $type, $field );

				if ( '' === $value || array() === $value ) {
					$product->delete_meta_data( self::META_PREFIX . $key );
					continue;
				}

				$product->update_meta_data( self::META_PREFIX . $key, $value );
			}
		}

		/**
		 * Meta sản phẩm SAHA vừa được gán (chưa save xuống DB).
		 *
		 * @param \WC_Product $product Product.
		 */
		do_action( 'saha_product_meta_saved', $product );
	}

	/**
	 * Sanitize một field theo type.
	 *
	 * @param mixed                $raw   Giá trị thô.
	 * @param string               $type  Type.
	 * @param array<string, mixed> $field Field definition.
	 * @return mixed
	 */
	private function sanitize_field( $raw, string $type, array $field ) {
		if ( 'repeater' === $type ) {
			return $this->sanitize_repeater( $raw );
		}

		if ( 'documents' === $type ) {
			return $this->sanitize_documents( $raw );
		}

		if ( 'select' === $type ) {
			$options = self::options( (string) ( $field['options'] ?? '' ) );
			$value   = sanitize_text_field( (string) $raw );

			return isset( $options[ $value ] ) ? $value : '';
		}

		return Security::sanitize_by_type( $raw, $type );
	}

	/**
	 * Sanitize repeater thông số.
	 *
	 * @param mixed $raw Dữ liệu thô.
	 * @return array<int, array<string, string>>
	 */
	private function sanitize_repeater( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$label = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
			$value = sanitize_text_field( (string) ( $row['value'] ?? '' ) );

			if ( '' === $label && '' === $value ) {
				continue;
			}

			$out[] = array(
				'label' => $label,
				'value' => $value,
			);
		}

		return array_slice( $out, 0, 80 );
	}

	/**
	 * Sanitize danh sách tài liệu, chỉ giữ attachment thật.
	 *
	 * @param mixed $raw Dữ liệu thô.
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_documents( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$types = self::document_types();
		$out   = array();

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$attachment_id = absint( $row['id'] ?? 0 );

			if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
				continue;
			}

			$type = sanitize_key( (string) ( $row['type'] ?? 'other' ) );

			$out[] = array(
				'id'    => $attachment_id,
				'type'  => isset( $types[ $type ] ) ? $type : 'other',
				'title' => sanitize_text_field( (string) ( $row['title'] ?? '' ) ),
			);
		}

		return array_slice( $out, 0, 20 );
	}

	/**
	 * Nonce field cho view.
	 */
	public static function nonce_field(): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
	}
}
