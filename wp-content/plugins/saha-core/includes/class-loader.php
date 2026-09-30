<?php
/**
 * Service container + module bootstrap.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Loader: giữ instance các service và gọi register() của từng module.
 *
 * Module chỉ cần có method register() để gắn hook. Không module nào được gọi
 * trực tiếp module khác qua `new` — luôn lấy qua Loader::get().
 */
final class Loader {

	/**
	 * Singleton.
	 *
	 * @var Loader|null
	 */
	private static ?Loader $instance = null;

	/**
	 * Service đã khởi tạo.
	 *
	 * @var array<string, object>
	 */
	private array $services = array();

	/**
	 * Đã chạy run() chưa.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Không cho new từ ngoài.
	 */
	private function __construct() {}

	/**
	 * Lấy singleton.
	 */
	public static function instance(): Loader {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Danh sách module của phase hiện tại.
	 *
	 * Thêm module của phase sau vào đây, không rải `new` khắp source.
	 *
	 * @return array<string, class-string>
	 */
	private function modules(): array {
		$modules = array(
			'logger'     => Logger::class,
			'settings'   => Settings::class,
			'security'   => Security::class,
			'roles'      => Roles::class,
			'install'    => Install::class,
			'taxonomies' => Taxonomies::class,
			'brand'      => Brand::class,
			'product'    => Product::class,
			'search'     => Search::class,
			'filter'     => Filter::class,
			'api'        => Api::class,
			'quote'      => Quote::class,
			'lead'       => Lead::class,
			'customer'   => Customer::class,
			'mailer'     => Mailer::class,
			'forms'      => Form_Handler::class,
			'crm'        => Crm::class,
			'admin'      => Admin::class,
		);

		/**
		 * Cho phép thêm/bớt module (dùng cho test hoặc add-on).
		 *
		 * @param array<string, class-string> $modules Map key => class.
		 */
		return (array) apply_filters( 'saha_core_modules', $modules );
	}

	/**
	 * Khởi tạo và register toàn bộ module.
	 */
	public function run(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		foreach ( $this->modules() as $key => $class_name ) {
			if ( ! class_exists( $class_name ) ) {
				continue;
			}

			$service = new $class_name();

			$this->services[ $key ] = $service;

			if ( method_exists( $service, 'register' ) ) {
				$service->register();
			}
		}

		/**
		 * Toàn bộ service đã sẵn sàng.
		 *
		 * @param Loader $loader Container.
		 */
		do_action( 'saha_core_loaded', $this );
	}

	/**
	 * Lấy service theo key.
	 *
	 * @param string $key Key module.
	 * @return object|null
	 */
	public function get( string $key ): ?object {
		return $this->services[ $key ] ?? null;
	}

	/**
	 * Service đã đăng ký chưa.
	 *
	 * @param string $key Key module.
	 */
	public function has( string $key ): bool {
		return isset( $this->services[ $key ] );
	}
}
