<?php
/**
 * Global settings.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Settings: một option duy nhất, có schema, sanitize theo type.
 *
 * Mọi giá trị công ty/hotline/Zalo/email phải đọc từ đây — không hardcode template.
 */
final class Settings {

	/**
	 * Tên option.
	 */
	public const OPTION = 'saha_core_settings';

	/**
	 * Option group của Settings API.
	 */
	public const GROUP = 'saha_core_settings_group';

	/**
	 * Cache trong request.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $cache = null;

	/**
	 * Schema các field: key => [ type, label, section, default, description ].
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function schema(): array {
		$schema = array(
			'company_name'             => array(
				'type'    => 'text',
				'label'   => __( 'Tên công ty', 'saha-core' ),
				'section' => 'company',
				'default' => 'Công ty TNHH Thương mại Dịch vụ Trực tuyến SAHA',
			),
			'site_brand'               => array(
				'type'    => 'text',
				'label'   => __( 'Tên thương hiệu website', 'saha-core' ),
				'section' => 'company',
				'default' => 'Tổng Kho Keo Dán SAHA',
			),
			'address'                  => array(
				'type'    => 'textarea',
				'label'   => __( 'Địa chỉ', 'saha-core' ),
				'section' => 'company',
				'default' => '',
			),
			'website'                  => array(
				'type'    => 'url',
				'label'   => __( 'Website', 'saha-core' ),
				'section' => 'company',
				'default' => 'https://tongkhokeodan.com',
			),
			'hotline_north'            => array(
				'type'    => 'phone',
				'label'   => __( 'Hotline miền Bắc', 'saha-core' ),
				'section' => 'contact',
				'default' => '0966.75.3382',
			),
			'hotline_south'            => array(
				'type'    => 'phone',
				'label'   => __( 'Hotline miền Nam', 'saha-core' ),
				'section' => 'contact',
				'default' => '0966.79.3669',
			),
			'email'                    => array(
				'type'    => 'email',
				'label'   => __( 'Email liên hệ', 'saha-core' ),
				'section' => 'contact',
				'default' => '',
			),
			'quote_email'              => array(
				'type'        => 'email',
				'label'       => __( 'Email nhận yêu cầu báo giá', 'saha-core' ),
				'section'     => 'contact',
				'default'     => '',
				'description' => __( 'Để trống sẽ dùng email quản trị của website.', 'saha-core' ),
			),
			'zalo_phone'               => array(
				'type'        => 'phone',
				'label'       => __( 'Số Zalo', 'saha-core' ),
				'section'     => 'contact',
				'default'     => '',
				'description' => __( 'Dùng để sinh link zalo.me, không hardcode trong template.', 'saha-core' ),
			),
			'facebook'                 => array(
				'type'    => 'url',
				'label'   => __( 'Facebook', 'saha-core' ),
				'section' => 'contact',
				'default' => '',
			),
			'default_cta'              => array(
				'type'    => 'text',
				'label'   => __( 'Nhãn CTA mặc định', 'saha-core' ),
				'section' => 'behaviour',
				'default' => 'Yêu cầu báo giá',
			),
			'catalogue_mode'           => array(
				'type'        => 'bool',
				'label'       => __( 'Chế độ catalogue', 'saha-core' ),
				'section'     => 'behaviour',
				'default'     => true,
				'description' => __( 'Ẩn giá và giỏ hàng, thay bằng CTA báo giá. Tắt để chạy bán hàng đầy đủ.', 'saha-core' ),
			),
			'enable_material_taxonomy' => array(
				'type'        => 'bool',
				'label'       => __( 'Bật taxonomy vật liệu', 'saha-core' ),
				'section'     => 'behaviour',
				'default'     => false,
				'description' => __( 'Chỉ bật khi thực sự dùng để lọc sản phẩm.', 'saha-core' ),
			),
			'enable_search_log'        => array(
				'type'        => 'bool',
				'label'       => __( 'Ghi log tìm kiếm', 'saha-core' ),
				'section'     => 'behaviour',
				'default'     => false,
				'description' => __( 'Chỉ lưu từ khoá và số kết quả, không lưu người dùng/IP.', 'saha-core' ),
			),
			'log_to_database'          => array(
				'type'        => 'bool',
				'label'       => __( 'Ghi log hệ thống vào database', 'saha-core' ),
				'section'     => 'system',
				'default'     => true,
				'description' => __( 'Tắt sẽ chỉ ghi vào debug.log khi WP_DEBUG_LOG bật.', 'saha-core' ),
			),
			'delete_data_on_uninstall' => array(
				'type'        => 'bool',
				'label'       => __( 'Xoá dữ liệu khi gỡ plugin', 'saha-core' ),
				'section'     => 'system',
				'default'     => false,
				'description' => __( 'Nguy hiểm: xoá toàn bộ báo giá, lead và log khi xoá plugin.', 'saha-core' ),
			),
		);

		/**
		 * Cho phép add-on bổ sung setting.
		 *
		 * @param array<string, array<string, mixed>> $schema Schema hiện tại.
		 */
		return (array) apply_filters( 'saha_core_settings_schema', $schema );
	}

	/**
	 * Các section hiển thị trên trang cấu hình.
	 *
	 * @return array<string, string>
	 */
	public static function sections(): array {
		return array(
			'company'   => __( 'Thông tin công ty', 'saha-core' ),
			'contact'   => __( 'Liên hệ & CTA', 'saha-core' ),
			'behaviour' => __( 'Hành vi website', 'saha-core' ),
			'system'    => __( 'Hệ thống', 'saha-core' ),
		);
	}

	/**
	 * Giá trị mặc định.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		$defaults = array();

		foreach ( self::schema() as $key => $field ) {
			$defaults[ $key ] = $field['default'] ?? '';
		}

		return $defaults;
	}

	/**
	 * Toàn bộ setting đã merge default.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
		}

		return self::$cache;
	}

	/**
	 * Lấy một setting.
	 *
	 * @param string $key     Key.
	 * @param mixed  $fallback Giá trị trả về nếu không có.
	 * @return mixed
	 */
	public static function get( string $key, $fallback = '' ) {
		$all = self::all();

		if ( ! array_key_exists( $key, $all ) ) {
			return $fallback;
		}

		$value = $all[ $key ];

		/**
		 * Lọc giá trị setting khi đọc.
		 *
		 * @param mixed  $value Giá trị.
		 * @param string $key   Key.
		 */
		return apply_filters( 'saha_core_setting', $value, $key );
	}

	/**
	 * Ghi defaults lần đầu, không ghi đè giá trị admin đã sửa.
	 */
	public static function install_defaults(): void {
		$stored = get_option( self::OPTION, null );

		if ( null === $stored || ! is_array( $stored ) ) {
			add_option( self::OPTION, self::defaults() );
			self::$cache = null;
			return;
		}

		$merged = wp_parse_args( $stored, self::defaults() );

		if ( $merged !== $stored ) {
			update_option( self::OPTION, $merged );
			self::$cache = null;
		}
	}

	/**
	 * Đăng ký option với Settings API.
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'update_option_' . self::OPTION, array( $this, 'flush_cache' ) );
	}

	/**
	 * register_setting với sanitize callback.
	 */
	public function register_setting(): void {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Xoá cache trong request sau khi lưu.
	 */
	public function flush_cache(): void {
		self::$cache = null;
	}

	/**
	 * Sanitize theo type trong schema. Field lạ bị loại bỏ.
	 *
	 * @param mixed $input Dữ liệu thô từ form.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ): array {
		$schema = self::schema();
		$clean  = self::all();

		if ( ! is_array( $input ) ) {
			return $clean;
		}

		foreach ( $schema as $key => $field ) {
			$type = (string) ( $field['type'] ?? 'text' );

			if ( 'bool' === $type ) {
				$clean[ $key ] = ! empty( $input[ $key ] );
				continue;
			}

			if ( ! isset( $input[ $key ] ) ) {
				continue;
			}

			$clean[ $key ] = Security::sanitize_by_type( $input[ $key ], $type );
		}

		return $clean;
	}
}
