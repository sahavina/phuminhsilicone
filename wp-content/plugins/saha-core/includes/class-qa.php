<?php
/**
 * QA: kiểm tra tự động cấu hình + dữ liệu trên site đang chạy (spec §56, §98).
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Qa.
 *
 * Chỉ ĐỌC — không ghi dữ liệu, không gửi mail, không gọi mạng ra ngoài
 * (ngoại lệ duy nhất: tăng thế hệ cache, vô hại). Chạy được ở production.
 *
 * Dùng ở: SAHA → Kiểm tra hệ thống, và `wp saha qa`.
 */
final class Qa {

	public const PASS = 'pass';
	public const WARN = 'warn';
	public const FAIL = 'fail';
	public const SKIP = 'skip';

	/**
	 * Kết quả đang gom.
	 *
	 * @var array<int, array{group: string, label: string, status: string, detail: string}>
	 */
	private array $results = array();

	/**
	 * Chạy toàn bộ kiểm tra.
	 *
	 * @return array<int, array{group: string, label: string, status: string, detail: string}>
	 */
	public function run(): array {
		$this->results = array();

		$this->check_environment();
		$this->check_database();
		$this->check_roles();
		$this->check_settings();
		$this->check_taxonomies();
		$this->check_rest_routes();
		$this->check_builder();
		$this->check_woocommerce();
		$this->check_search();
		$this->check_seo();
		$this->check_security();
		$this->check_performance();

		/**
		 * Cho add-on bổ sung kiểm tra.
		 *
		 * @param array<int, array<string, string>> $results Kết quả.
		 */
		return (array) apply_filters( 'saha_qa_results', $this->results );
	}

	/**
	 * Đếm theo trạng thái.
	 *
	 * @param array<int, array<string, string>> $results Kết quả.
	 * @return array<string, int>
	 */
	public static function summary( array $results ): array {
		$out = array_fill_keys( array( self::PASS, self::WARN, self::FAIL, self::SKIP ), 0 );

		foreach ( $results as $row ) {
			$status = (string) ( $row['status'] ?? self::SKIP );

			if ( isset( $out[ $status ] ) ) {
				++$out[ $status ];
			}
		}

		return $out;
	}

	/**
	 * Ghi một kết quả.
	 *
	 * @param string $group  Nhóm.
	 * @param string $label  Nội dung kiểm tra.
	 * @param string $status pass|warn|fail|skip.
	 * @param string $detail Chi tiết / cách sửa.
	 */
	private function add( string $group, string $label, string $status, string $detail = '' ): void {
		$this->results[] = array(
			'group'  => $group,
			'label'  => $label,
			'status' => $status,
			'detail' => $detail,
		);
	}

	/**
	 * Ghi pass/fail theo điều kiện.
	 *
	 * @param string $group     Nhóm.
	 * @param string $label     Nội dung.
	 * @param bool   $ok        Điều kiện đạt.
	 * @param string $on_fail   Chi tiết khi không đạt.
	 * @param string $fail_type fail | warn.
	 * @param string $on_pass   Chi tiết khi đạt.
	 */
	private function expect( string $group, string $label, bool $ok, string $on_fail = '', string $fail_type = self::FAIL, string $on_pass = '' ): void {
		$this->add( $group, $label, $ok ? self::PASS : $fail_type, $ok ? $on_pass : $on_fail );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Môi trường
	 * ---------------------------------------------------------------------
	 */

	private function check_environment(): void {
		$g = 'Môi trường';

		$this->expect( $g, 'PHP ≥ ' . SAHA_CORE_MIN_PHP, version_compare( PHP_VERSION, SAHA_CORE_MIN_PHP, '>=' ), 'Đang dùng PHP ' . PHP_VERSION, self::FAIL, PHP_VERSION );
		$this->expect( $g, 'WordPress ≥ ' . SAHA_CORE_MIN_WP, version_compare( get_bloginfo( 'version' ), SAHA_CORE_MIN_WP, '>=' ), 'Đang dùng ' . get_bloginfo( 'version' ), self::FAIL, get_bloginfo( 'version' ) );
		$this->expect( $g, 'WooCommerce đang hoạt động', class_exists( 'WooCommerce' ), 'Cần cho toàn bộ module sản phẩm.', self::FAIL, defined( 'WC_VERSION' ) ? WC_VERSION : '' );

		$theme  = wp_get_theme();
		$parent = $theme->parent();

		// SCC: saha-theme là giao diện chính; flatsome-child đã đóng băng (vẫn chạy được).
		if ( 'saha-theme' === $theme->get_template() ) {
			$this->add( $g, 'Theme SAHA Theme đang bật', self::PASS, $theme->get( 'Version' ) );
		} elseif ( 'flatsome' === $theme->get_template() && $parent instanceof \WP_Theme && $parent->exists() ) {
			$this->add( $g, 'Theme SAHA Theme đang bật', self::WARN, 'Đang dùng Flatsome Child (đã đóng băng, không phát triển thêm). Giao diện → Giao diện → kích hoạt SAHA Theme.' );
		} else {
			$this->add( $g, 'Theme SAHA Theme đang bật', self::WARN, 'Theme hiện tại: ' . $theme->get( 'Name' ) . ' (template: ' . $theme->get_template() . ').' );
		}

		$this->expect( $g, 'Permalink dạng đẹp (không phải ?p=123)', '' !== (string) get_option( 'permalink_structure' ), 'Settings → Permalinks: chọn Post name hoặc /tin-tuc/%postname%/.' );

		$provider = Seo::provider();
		$this->add(
			$g,
			'Plugin SEO',
			self::PROVIDER_LABELS[ $provider ] === 'none' ? self::WARN : self::PASS,
			self::PROVIDER_LABELS[ $provider ] === 'none' ? 'Chưa có Rank Math/Yoast — đang dùng fallback tối thiểu (spec §22 khuyến nghị dùng plugin SEO).' : self::PROVIDER_LABELS[ $provider ]
		);

		$env = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		$this->add( $g, 'Môi trường (WP_ENVIRONMENT_TYPE)', self::PASS, $env );
	}

	/**
	 * Nhãn plugin SEO.
	 */
	private const PROVIDER_LABELS = array(
		Seo::PROVIDER_RANK_MATH => 'Rank Math',
		Seo::PROVIDER_YOAST     => 'Yoast SEO',
		Seo::PROVIDER_NONE      => 'none',
	);

	/*
	 * ---------------------------------------------------------------------
	 * Database
	 * ---------------------------------------------------------------------
	 */

	private function check_database(): void {
		global $wpdb;

		$g = 'Database';

		$installed = (string) get_option( Install::DB_VERSION_OPTION, '0' );
		$this->expect( $g, 'DB version = ' . SAHA_CORE_DB_VERSION, version_compare( $installed, SAHA_CORE_DB_VERSION, '>=' ), 'Đang là ' . $installed . ' — vào wp-admin một lần để migration chạy.' );

		$expected_indexes = array(
			'quotes'      => array( 'phone', 'product_id', 'status', 'assigned_user_id', 'created_at' ),
			'leads'       => array( 'phone', 'source', 'status', 'assigned_user_id', 'created_at' ),
			'logs'        => array( 'level', 'channel', 'created_at' ),
			'search_logs' => array( 'query', 'created_at' ),
			'quote_items' => array( 'quote_id', 'product_id' ),
		);

		foreach ( $expected_indexes as $name => $columns ) {
			$table = Migrator::table( $name );

			if ( ! Migrator::table_exists( $table ) ) {
				$this->add( $g, "Bảng {$table}", self::FAIL, 'Chưa tồn tại — tắt/bật lại plugin hoặc vào wp-admin để migration chạy.' );
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tên bảng nội bộ.
			$rows    = (array) $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A );
			$indexed = array_unique( array_map( static fn( array $r ): string => (string) $r['Column_name'], $rows ) );
			$missing = array_diff( $columns, $indexed );

			$this->expect( $g, "Bảng {$table} + index", ! $missing, 'Thiếu index: ' . implode( ', ', $missing ), self::FAIL, count( $columns ) . ' index' );
		}

		foreach ( array( 'quotes', 'leads' ) as $name ) {
			$table = Migrator::table( $name );

			if ( ! Migrator::table_exists( $table ) ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tên bảng nội bộ.
			$has_notes = null !== $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", 'notes' ) );
			$this->expect( $g, "Cột notes trong {$table} (migration 005)", $has_notes, 'Migration 005 chưa chạy.' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- đọc metadata bảng.
		$status    = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->posts ), ARRAY_A );
		$collation = is_array( $status ) ? (string) ( $status['Collation'] ?? '' ) : '';

		$this->expect(
			$g,
			'Collation không phân biệt dấu (tìm "keo" ra "kéo")',
			'' !== $collation && false === strpos( $collation, '_bin' ) && false !== strpos( $collation, '_ci' ),
			'Collation hiện tại: ' . $collation . ' — tìm kiếm tiếng Việt sẽ phân biệt dấu (xem PHASE-3 §11).',
			self::WARN,
			$collation
		);
	}

	/*
	 * ---------------------------------------------------------------------
	 * Quyền
	 * ---------------------------------------------------------------------
	 */

	private function check_roles(): void {
		$g = 'Quyền';

		$admin   = get_role( 'administrator' );
		$missing = array();

		foreach ( Roles::all_caps() as $cap ) {
			if ( ! $admin || ! $admin->has_cap( $cap ) ) {
				$missing[] = $cap;
			}
		}

		$this->expect( $g, 'Administrator có đủ 7 capability manage_saha*', ! $missing, 'Thiếu: ' . implode( ', ', $missing ) );

		foreach ( array_keys( Roles::custom_roles() ) as $slug ) {
			$this->expect( $g, "Role {$slug}", null !== get_role( $slug ), 'Chưa tạo — tắt/bật lại plugin.' );
		}

		$sales = get_role( 'saha_sales' );

		if ( $sales ) {
			$this->expect( $g, 'Sales KHÔNG có quyền cấu hình', ! $sales->has_cap( Roles::CAP_SETTINGS ), 'Role Sales đang có manage_saha_settings.' );
			$this->expect( $g, 'Sales có quyền báo giá + lead', $sales->has_cap( Roles::CAP_QUOTES ) && $sales->has_cap( Roles::CAP_LEADS ), 'Role Sales thiếu quyền báo giá/lead.' );
		}

		foreach ( array( 'saha_content_manager', 'saha_seo_manager', 'saha_warehouse' ) as $slug ) {
			$role = get_role( $slug );

			if ( $role && class_exists( 'WooCommerce' ) ) {
				$this->expect( $g, "{$slug} sửa được sản phẩm WooCommerce", $role->has_cap( 'edit_products' ) && $role->has_cap( 'edit_others_products' ), 'Thiếu edit_products — role không mở được màn hình sản phẩm.' );
			}
		}

		$assignable = Repository::assignable_users( Roles::CAP_QUOTES );
		$this->expect( $g, 'Có ít nhất 1 người nhận phân công báo giá', count( $assignable ) > 0, 'Tạo user role Sales để gán báo giá.', self::WARN, count( $assignable ) . ' người' );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Cấu hình
	 * ---------------------------------------------------------------------
	 */

	private function check_settings(): void {
		$g = 'Cấu hình';

		foreach ( array( 'hotline_north' => 'Hotline miền Bắc', 'hotline_south' => 'Hotline miền Nam' ) as $key => $label ) {
			$value = (string) Settings::get( $key );
			$this->expect( $g, $label, strlen( Security::tel_digits( $value ) ) >= 8, 'Chưa nhập hoặc không hợp lệ.', self::FAIL, $value );
		}

		$recipient = saha_quote_recipient();
		$this->expect( $g, 'Email nhận báo giá hợp lệ', (bool) is_email( $recipient ), 'Không có email hợp lệ — báo giá sẽ không được thông báo.', self::FAIL, $recipient );

		$this->expect( $g, 'Số Zalo', '' !== saha_zalo_url(), 'Chưa nhập — nút Zalo sẽ bị ẩn.', self::WARN );

		$quote_url = saha_quote_page_url();

		if ( '' === $quote_url ) {
			$this->add( $g, 'Trang yêu cầu báo giá', self::WARN, 'Chưa cấu hình — nút "Báo giá" ở trang không có modal sẽ dẫn tới hotline.' );
		} else {
			$page_id = url_to_postid( $quote_url );
			$content = $page_id ? (string) get_post_field( 'post_content', $page_id ) : '';

			$this->expect(
				$g,
				'Trang yêu cầu báo giá có form',
				// Không dùng has_shortcode(): shortcode do theme đăng ký — plugin-only / CLI sẽ báo sai.
				$page_id > 0 && 'publish' === get_post_status( $page_id ) && false !== strpos( $content, '[saha_quote_form' ),
				'URL ' . $quote_url . ' không phải trang đã xuất bản có [saha_quote_form].',
				self::WARN,
				$quote_url
			);
		}

		$this->add( $g, 'Chế độ catalogue', self::PASS, saha_is_catalogue_mode() ? 'Bật (ẩn giá, dùng CTA báo giá)' : 'Tắt (bán hàng đầy đủ)' );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Taxonomy
	 * ---------------------------------------------------------------------
	 */

	private function check_taxonomies(): void {
		$g = 'Taxonomy';

		foreach ( array( Taxonomies::BRAND => 'thuong-hieu', Taxonomies::APPLICATION => 'ung-dung' ) as $taxonomy => $slug ) {
			$object = get_taxonomy( $taxonomy );

			if ( ! $object ) {
				$this->add( $g, $taxonomy, self::FAIL, 'Chưa đăng ký.' );
				continue;
			}

			$rewrite = is_array( $object->rewrite ) ? (string) ( $object->rewrite['slug'] ?? '' ) : '';

			$this->expect( $g, "{$taxonomy} public", (bool) $object->public, 'Taxonomy không public — không index được.' );

			// Nếu WooCommerce đã có product_brand native thì slug là của WooCommerce — chỉ cảnh báo.
			$this->expect( $g, "{$taxonomy} URL /{$slug}/", $rewrite === $slug, 'Slug hiện tại: /' . $rewrite . '/', self::WARN );

			$count = wp_count_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
			$this->expect( $g, "Có dữ liệu {$taxonomy}", ! is_wp_error( $count ) && (int) $count > 0, 'Chưa có term nào.', self::WARN, is_wp_error( $count ) ? '' : $count . ' term' );
		}
	}

	/*
	 * ---------------------------------------------------------------------
	 * REST
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Builder runtime: quyền, ghi CSS, render thử (SCC mốc 1.2).
	 */
	private function check_builder(): void {
		$g = 'Builder';

		foreach ( array( 'administrator', 'editor' ) as $slug ) {
			$role = get_role( $slug );
			$this->expect( $g, "{$slug} có quyền " . Roles::CAP_BUILDER, $role && $role->has_cap( Roles::CAP_BUILDER ), 'Thiếu — vào wp-admin bằng admin một lần để role tự cập nhật.' );
		}

		$uploads = wp_upload_dir( null, false );
		$dir     = empty( $uploads['error'] ) ? trailingslashit( $uploads['basedir'] ) . 'saha/css' : '';
		$this->expect(
			$g,
			'Ghi được file CSS (uploads/saha/css)',
			'' !== $dir && wp_is_writable( is_dir( $dir ) ? $dir : $uploads['basedir'] ),
			'Không ghi được — CSS layout sẽ in inline (chậm hơn, không cache trình duyệt).',
			self::WARN
		);

		// Khối mẫu của bảng "Thêm": mọi khối phải qua được Sanitizer (bị loại = editor không hiện khối đó).
		$patterns_all   = count( Builder\Patterns::all() );
		$patterns_valid = count( Builder\Patterns::forClient() );
		$this->expect(
			$g,
			'Khối mẫu hợp lệ',
			$patterns_all === $patterns_valid,
			sprintf( '%1$d/%2$d khối hợp lệ — khối lỗi bị ẩn khỏi bảng Thêm (thường do đổi element / StoreKit).', $patterns_valid, $patterns_all ),
			self::WARN,
			sprintf( '%d khối', $patterns_valid )
		);

		// Render thử một tài liệu mẫu qua đúng đường sanitize → render → CSS của production.
		$sample = ( new Builder\Sanitizer() )->document(
			array(
				'elements' => array(
					array(
						'type'     => 'section',
						'children' => array(
							array(
								'type'  => 'heading',
								'props' => array(
									'text'  => 'QA <script>',
									'color' => '#123456',
								),
							),
						),
					),
				),
			)
		);
		$html    = null === $sample['document'] ? '' : ( new Builder\Renderer() )->document( $sample['document'], new Builder\RenderContext( 0, false, false ) );
		$css     = null === $sample['document'] ? '' : ( new Builder\CssGenerator() )->document( $sample['document'] );

		$this->expect( $g, 'Render thử layout mẫu', false !== strpos( $html, '<h2' ) && false === strpos( $html, '<script' ) && false !== strpos( $css, '#123456' ), 'Kết quả render không như mong đợi: ' . wp_strip_all_tags( $html ) );

		$pages = get_posts(
			array(
				'post_type'      => Builder\LayoutRepository::postTypes(),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => Builder\LayoutRepository::META_ENABLED, // phpcs:ignore WordPress.DB.SlowDBQuery -- công cụ QA, chạy tay.
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		$this->add( $g, 'Trang dùng builder', self::PASS, count( $pages ) . ' trang' );

		foreach ( Templates\Repository::layoutTypes() as $type => $label ) {
			$id = Templates\Repository::resolve( $type );
			$this->add(
				$g,
				$label . ' dựng bằng builder',
				null !== $id ? self::PASS : ( current_theme_supports( 'saha-theme-options' ) ? self::WARN : self::SKIP ),
				null !== $id ? get_the_title( $id ) . ' (#' . $id . ')' : __( 'Chưa có — đang dùng bản PHP của theme. Tạo ở SAHA → Header & Footer.', 'saha-core' )
			);
		}

		$this->add(
			$g,
			'Plugin SAHA Builder (trình soạn thảo) đang bật',
			defined( 'SAHA_BUILDER_VERSION' ) ? self::PASS : self::WARN,
			defined( 'SAHA_BUILDER_VERSION' ) ? SAHA_BUILDER_VERSION : __( 'Đang tắt — trang đã dựng vẫn hiển thị nhưng không sửa được.', 'saha-core' )
		);

		// Template nội dung (mốc 2.2): hai template cùng loại, cùng điều kiện, cùng ưu tiên → chọn theo ID, khó đoán.
		$map       = get_option( Templates\Repository::MAP_OPTION, array() );
		$ambiguous = array();
		$count     = 0;

		foreach ( (array) ( $map['types'] ?? array() ) as $type => $rules ) {
			if ( isset( Templates\Repository::contentTypes()[ $type ] ) ) {
				foreach ( $rules as $rule => $values ) {
					foreach ( $values as $value => $ids ) {
						$count += count( $ids );

						if ( count( $ids ) > 1 && (int) ( $map['priority'][ $ids[0] ] ?? 0 ) === (int) ( $map['priority'][ $ids[1] ] ?? 0 ) ) {
							$ambiguous[] = $type . ' ' . $rule . ( '*' === (string) $value ? '' : ':' . $value ) . ' (#' . implode( ', #', $ids ) . ')';
						}
					}
				}
			}
		}

		$this->expect(
			$g,
			'Template nội dung: không trùng điều kiện cùng ưu tiên',
			! $ambiguous,
			__( 'Trùng điều kiện và ưu tiên — template ID nhỏ đang thắng; đặt ưu tiên khác nhau: ', 'saha-core' ) . implode( '; ', $ambiguous ),
			self::WARN,
			$count > 0 ? sprintf( '%d điều kiện', $count ) : __( 'Chưa dùng template nội dung', 'saha-core' )
		);

		$mega   = MegaMenu\Settings::compiled();
		$broken = array_filter( $mega['blocks'], static fn( int $id ): bool => 'publish' !== get_post_status( $id ) );
		$this->expect(
			$g,
			'Mega menu: Block nội dung còn tồn tại',
			! $broken,
			sprintf(
				/* translators: %s: danh sách ID block */
				__( 'Block #%s đã xoá hoặc chưa xuất bản — mục mega sẽ trống. Giao diện → Menu → chọn block khác.', 'saha-core' ),
				implode( ', #', $broken )
			),
			self::WARN,
			$mega['active'] ? count( $mega['blocks'] ) . ' block' : __( 'Chưa dùng mega menu', 'saha-core' )
		);

		$front = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
		$this->expect(
			$g,
			'Trang chủ dựng bằng builder',
			$front > 0 && Builder\LayoutRepository::isEnabled( $front ),
			__( 'Chưa — Trang → Tất cả trang → "Tạo trang chủ mẫu (SAHA Builder)", hoặc wp saha homepage --front.', 'saha-core' ),
			current_theme_supports( 'saha-theme-options' ) ? self::WARN : self::SKIP,
			$front > 0 ? get_the_title( $front ) . ' (#' . $front . ')' : ''
		);

		// Nội dung còn shortcode Flatsome/UX Builder: không hiển thị được trên saha-theme (rủi ro R12).
		$legacy = array();
		foreach ( get_posts(
			array(
				'post_type'      => array( 'page', 'post' ),
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'no_found_rows'  => true,
			)
		) as $post ) {
			if ( ! Builder\LayoutRepository::isEnabled( $post->ID ) && preg_match( '/\[(ux_|section|row|col|featured_box|blog_posts|text_box)\b/', (string) $post->post_content ) ) {
				$legacy[] = get_the_title( $post ) . ' (#' . $post->ID . ')';
			}
		}

		$this->expect(
			$g,
			'Không còn trang dùng shortcode UX Builder (Flatsome)',
			! $legacy,
			sprintf(
				/* translators: %s: danh sách trang */
				__( 'Cần dựng lại bằng builder: %s', 'saha-core' ),
				implode( ', ', array_slice( $legacy, 0, 5 ) ) . ( count( $legacy ) > 5 ? '…' : '' )
			),
			'saha-theme' === get_template() ? self::WARN : self::SKIP
		);
	}

	/**
	 * WooCommerce: chế độ catalogue thật sự chặn mua, hoặc luồng mua hàng đủ điều kiện.
	 */
	private function check_woocommerce(): void {
		$g = 'WooCommerce';

		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_products' ) ) {
			$this->add( $g, 'WooCommerce', self::SKIP, 'Chưa kích hoạt.' );
			return;
		}

		$products = wc_get_products(
			array(
				'status' => 'publish',
				'type'   => 'simple',
				'limit'  => 1,
			)
		);
		$product  = $products[0] ?? null;

		if ( ! $product instanceof \WC_Product ) {
			$this->add( $g, 'Sản phẩm mẫu để kiểm', self::SKIP, 'Chưa có sản phẩm đơn giản nào.' );
			return;
		}

		if ( WooCommerce\CatalogMode::enabled() ) {
			$this->add( $g, 'Chế độ catalogue', self::PASS, 'Bật — luồng báo giá' );
			$this->expect( $g, 'Catalogue: sản phẩm không mua được (cả Store API)', ! $product->is_purchasable(), 'Vẫn mua được — kiểm saha-core WooCommerce\CatalogMode.' );
			$this->expect( $g, 'Catalogue: giá thay bằng "Liên hệ báo giá" (HTML, không chỉ CSS)', false !== strpos( (string) $product->get_price_html(), 'saha-price-hidden' ), 'Giá vẫn in ra HTML.' );
			$this->expect( $g, 'Catalogue: không có nút thêm vào giỏ ở trang sản phẩm', false === has_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart' ), 'Nút vẫn còn.' );
		} else {
			$this->add( $g, 'Chế độ catalogue', self::PASS, 'Tắt — luồng mua hàng' );
			$this->expect( $g, 'Sản phẩm mua được', $product->is_purchasable(), 'Sản phẩm #' . $product->get_id() . ' không mua được (chưa có giá?).', self::WARN );
			$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->get_available_payment_gateways() : array();
			$this->expect( $g, 'Có phương thức thanh toán', ! empty( $gateways ), 'Chưa bật phương thức nào — WooCommerce → Cài đặt → Thanh toán.' );
			$this->expect( $g, 'Nút "Mua ngay" đã gắn', false !== has_action( 'woocommerce_after_add_to_cart_button' ), 'Thiếu hook BuyNow.' );
		}

		$this->check_wc_overrides( $g );

		foreach ( array( 'cart', 'checkout', 'myaccount' ) as $page ) {
			$id = (int) wc_get_page_id( $page );
			$this->expect( $g, "Trang {$page} đã gán", $id > 0 && 'publish' === get_post_status( $id ), 'Chưa gán — WooCommerce → Cài đặt → Nâng cao.', 'myaccount' === $page ? self::WARN : self::FAIL );
		}
	}

	/**
	 * Template WooCommerce theme override cũ hơn bản gốc (rủi ro R7 — WooCommerce đổi
	 * template theo phiên bản). Cùng nguồn dữ liệu với WooCommerce → Trạng thái.
	 *
	 * @param string $g Nhóm.
	 */
	private function check_wc_overrides( string $g ): void {
		if ( ! class_exists( 'WC_Admin_Status' ) ) {
			$status_file = WC()->plugin_path() . '/includes/admin/class-wc-admin-status.php';

			if ( is_readable( $status_file ) ) {
				require_once $status_file;
			}
		}

		if ( ! class_exists( 'WC_Admin_Status' ) || ! method_exists( 'WC_Admin_Status', 'scan_template_files' ) ) {
			$this->add( $g, 'Template WooCommerce override', self::SKIP, 'Không đọc được WC_Admin_Status.' );
			return;
		}

		$core     = WC()->plugin_path() . '/templates/';
		$outdated = array();
		$count    = 0;

		foreach ( \WC_Admin_Status::scan_template_files( $core ) as $file ) {
			foreach ( array( get_stylesheet_directory(), get_template_directory() ) as $dir ) {
				$theme_file = $dir . '/woocommerce/' . $file;

				if ( ! is_readable( $theme_file ) ) {
					continue;
				}

				++$count;
				$theirs = \WC_Admin_Status::get_file_version( $theme_file );
				$ours   = \WC_Admin_Status::get_file_version( $core . $file );

				if ( $ours && ( ! $theirs || version_compare( $theirs, $ours, '<' ) ) ) {
					$outdated[] = str_replace( '\\', '/', $file ) . ' (' . ( $theirs ? $theirs : '?' ) . ' < ' . $ours . ')';
				}
				break;
			}
		}

		$this->expect(
			$g,
			'Template WooCommerce override không lỗi thời',
			! $outdated,
			'Cần cập nhật: ' . implode( ', ', $outdated ),
			self::WARN,
			0 === $count ? 'Theme không override template nào' : $count . ' file'
		);
	}

	/**
	 * REST route đã đăng ký và đều có permission_callback.
	 */
	private function check_rest_routes(): void {
		$g      = 'REST API';
		$routes = rest_get_server()->get_routes( Api::NAMESPACE );

		$expected = array(
			'/saha/v1/nonce',
			'/saha/v1/search',
			'/saha/v1/products',
			'/saha/v1/products/(?P<id>\d+)',
			'/saha/v1/brands',
			'/saha/v1/brands/(?P<slug>[a-z0-9\-_]+)',
			'/saha/v1/quote',
			'/saha/v1/quote/list',
			'/saha/v1/newsletter',
			'/saha/v1/contact',
			'/saha/v1/settings',
			'/saha/v1/blocks',
			'/saha/v1/builder/elements',
			'/saha/v1/builder/patterns',
			'/saha/v1/builder/(?P<id>\d+)',
			'/saha/v1/builder/save',
			'/saha/v1/builder/render',
			'/saha/v1/builder/lock/(?P<id>\d+)',
		);

		foreach ( $expected as $route ) {
			$this->expect( $g, $route, isset( $routes[ $route ] ), 'Route chưa được đăng ký.' );
		}

		// Spec §29: mọi route phải có permission_callback.
		$missing = array();

		foreach ( $routes as $route => $handlers ) {
			// Route index của namespace do WordPress core tự tạo — không phải route của SAHA.
			if ( '/' . Api::NAMESPACE === $route ) {
				continue;
			}

			foreach ( (array) $handlers as $handler ) {
				if ( is_array( $handler ) && empty( $handler['permission_callback'] ) ) {
					$missing[] = $route;
				}
			}
		}

		$this->expect( $g, 'Mọi route saha/v1 có permission_callback', ! $missing, 'Thiếu: ' . implode( ', ', array_unique( $missing ) ) );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Tìm kiếm (spec §58)
	 * ---------------------------------------------------------------------
	 */

	private function check_search(): void {
		$g = 'Tìm kiếm';

		if ( ! post_type_exists( 'product' ) ) {
			$this->add( $g, 'Tìm kiếm sản phẩm', self::SKIP, 'WooCommerce chưa bật.' );
			return;
		}

		// QA chỉ đọc: không để các truy vấn kiểm thử lọt vào thống kê "từ khoá phổ biến".
		$no_log = static fn( $value, $key ) => 'enable_search_log' === $key ? false : $value;
		add_filter( 'saha_core_setting', $no_log, 999, 2 );

		try {
			$this->run_search_cases();
		} finally {
			remove_filter( 'saha_core_setting', $no_log, 999 );
		}
	}

	/**
	 * Các case tìm kiếm của spec §58.
	 */
	private function run_search_cases(): void {
		$g = 'Tìm kiếm';

		$cases = array(
			'243'         => '243',
			'apollo a500' => 'a500',
		);

		foreach ( $cases as $query => $needle ) {
			// PHP tự đổi key '243' thành int 243 — phải ép lại string (strict_types).
			$query  = (string) $query;
			$result = Search::search( $query, 1, 5 );
			$first  = (string) ( $result['items'][0]['name'] ?? '' );
			$sku    = (string) ( $result['items'][0]['sku'] ?? '' );

			if ( 0 === $result['total'] ) {
				$this->add( $g, "\"{$query}\" ra sản phẩm chứa \"{$needle}\" đầu tiên", self::SKIP, 'Chưa có sản phẩm phù hợp để kiểm tra (chạy `wp saha seed`).' );
				continue;
			}

			$ok = false !== mb_stripos( $first . ' ' . $sku, $needle );
			$this->expect( $g, "\"{$query}\" ra sản phẩm chứa \"{$needle}\" đầu tiên", $ok, 'Kết quả đầu: ' . $first, self::FAIL, $first );
		}

		$lower = Search::search( 'apollo', 1, 10 );
		$upper = Search::search( 'APOLLO', 1, 10 );
		$this->expect( $g, 'Không phân biệt hoa/thường', wp_list_pluck( $lower['items'], 'id' ) === wp_list_pluck( $upper['items'], 'id' ), 'Kết quả "apollo" và "APOLLO" khác nhau.' );

		$none = Search::search( 'zzqxyw-khong-ton-tai', 1, 5 );
		$this->expect( $g, 'Không có kết quả → rỗng, không lỗi', 0 === $none['total'], 'Trả về ' . $none['total'] . ' kết quả.' );

		global $wpdb;

		$wpdb->last_error = '';
		Search::search( "' OR 1=1 -- ", 1, 5 );
		$this->expect( $g, 'Ký tự đặc biệt / SQL injection không gây lỗi SQL', '' === $wpdb->last_error, 'Lỗi SQL: ' . $wpdb->last_error );
	}

	/*
	 * ---------------------------------------------------------------------
	 * SEO
	 * ---------------------------------------------------------------------
	 */

	private function check_seo(): void {
		$g = 'SEO';

		$this->expect( $g, 'Site cho phép index (Settings → Reading)', '1' === (string) get_option( 'blog_public' ), 'Đang bật "Discourage search engines" — tắt khi lên production.', 'production' === wp_get_environment_type() ? self::FAIL : self::WARN );

		$robots = (string) apply_filters( 'robots_txt', "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n", true );

		$this->expect( $g, 'robots.txt chặn tìm kiếm nội bộ (?s=)', false !== strpos( $robots, '?s=' ), 'Thiếu dòng Disallow cho ?s=' );
		$this->expect( $g, 'robots.txt KHÔNG chặn CSS/JS/theme/plugin/uploads', ! self::robots_blocks_assets( $robots ), 'robots.txt đang chặn tài nguyên Google cần để render.' );

		$source = Seo::product_schema_source();
		$this->add( $g, 'Nguồn Product schema', self::PASS, 'seo_plugin' === $source ? 'Plugin SEO (WooCommerce schema tắt)' : 'WooCommerce' );

		$this->add( $g, 'Nguồn breadcrumb', self::PASS, Seo::breadcrumb_provider() );

		if ( taxonomy_exists( Taxonomies::BRAND ) ) {
			$brands  = get_terms( array( 'taxonomy' => Taxonomies::BRAND, 'hide_empty' => true, 'number' => 50 ) );
			$missing = array();

			foreach ( is_wp_error( $brands ) ? array() : $brands as $term ) {
				$data = Brand::get( $term );

				if ( '' === trim( (string) ( $data['meta_description'] ?? '' ) ) && '' === trim( (string) ( $data['short_description'] ?? '' ) ) ) {
					$missing[] = $term->name;
				}
			}

			$this->expect( $g, 'Thương hiệu có mô tả / meta description', ! $missing, 'Thiếu: ' . implode( ', ', array_slice( $missing, 0, 10 ) ), self::WARN );
		}
	}

	/**
	 * robots.txt có chặn tài nguyên cần để render không.
	 *
	 * Không tính các thư mục riêng tư mà WooCommerce chủ động chặn
	 * (wc-logs, woocommerce_uploads, woocommerce_transient_files) — chặn chúng là đúng.
	 *
	 * @param string $robots Nội dung robots.txt.
	 */
	public static function robots_blocks_assets( string $robots ): bool {
		foreach ( preg_split( '/\r?\n/', $robots ) ?: array() as $line ) {
			if ( ! preg_match( '#^\s*Disallow:\s*(\S+)#i', $line, $m ) ) {
				continue;
			}

			$path = $m[1];

			if ( preg_match( '#/uploads/(wc-logs|woocommerce_uploads|woocommerce_transient_files)/#', $path ) ) {
				continue;
			}

			if ( preg_match( '#(\.css|\.js)(\$|\*)?$|/wp-includes/?$|/wp-content/?$|/wp-content/(themes|plugins|uploads)/?$#', $path ) ) {
				return true;
			}
		}

		return false;
	}

	/*
	 * ---------------------------------------------------------------------
	 * Bảo mật
	 * ---------------------------------------------------------------------
	 */

	private function check_security(): void {
		$g = 'Bảo mật';

		$mimes = array_keys( get_allowed_mime_types() );
		$bad   = array();

		foreach ( $mimes as $group ) {
			foreach ( explode( '|', (string) $group ) as $ext ) {
				if ( in_array( strtolower( $ext ), array( 'php', 'phtml', 'phar', 'exe', 'sh' ), true ) ) {
					$bad[] = $ext;
				}
			}
		}

		$this->expect( $g, 'Không cho upload file thực thi (spec §75)', ! $bad, 'Đang cho phép: ' . implode( ', ', $bad ) );

		$production = 'production' === wp_get_environment_type();
		$displaying = defined( 'WP_DEBUG' ) && WP_DEBUG && ( ! defined( 'WP_DEBUG_DISPLAY' ) || WP_DEBUG_DISPLAY );

		$this->expect(
			$g,
			'Không hiển thị lỗi PHP ra frontend (spec §40)',
			! ( $production && $displaying ),
			'WP_DEBUG_DISPLAY đang bật trên production — đặt define( \'WP_DEBUG_DISPLAY\', false ).',
			self::FAIL,
			$displaying ? 'debug display bật (môi trường ' . wp_get_environment_type() . ')' : ''
		);

		$this->expect( $g, 'Không xoá dữ liệu khi gỡ plugin (mặc định an toàn)', ! Settings::get( 'delete_data_on_uninstall', false ), '"Xoá dữ liệu khi gỡ plugin" đang BẬT.', self::WARN );

		$this->expect( $g, 'Chỉ tài khoản tin cậy có thể cấu hình', ! get_option( 'users_can_register' ) || 'administrator' !== get_option( 'default_role' ), 'Cho phép đăng ký với role mặc định administrator!' );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Hiệu năng
	 * ---------------------------------------------------------------------
	 */

	private function check_performance(): void {
		$g = 'Hiệu năng';

		$this->add( $g, 'Object cache ngoài (Redis/Memcached)', wp_using_ext_object_cache() ? self::PASS : self::WARN, wp_using_ext_object_cache() ? 'Đang dùng' : 'Chưa có — cache SAHA nằm trong bảng options (vẫn chạy, chậm hơn).' );

		$this->expect( $g, 'Cron dọn log đã lên lịch', false !== wp_next_scheduled( Maintenance::CRON_HOOK ), 'Chưa có lịch — vào wp-admin một lần.' );

		$this->expect( $g, 'WP-Cron đang chạy', ! ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ), 'DISABLE_WP_CRON bật — đảm bảo có cron hệ thống gọi wp-cron.php.', self::WARN );

		$this->add( $g, 'Thế hệ cache hiện tại', self::PASS, (string) Cache::generation() );

		if ( function_exists( 'saha_is_catalogue_mode' ) && saha_is_catalogue_mode() ) {
			$this->add( $g, 'Cart fragments bị tắt ở chế độ catalogue', self::PASS, 'Xem tab Network: không có ?wc-ajax=get_refreshed_fragments.' );
		}
	}
}
