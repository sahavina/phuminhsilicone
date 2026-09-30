<?php
/**
 * Seeder: dữ liệu mẫu cho staging / local QA.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Seeder.
 *
 * - Chỉ chạy qua WP-CLI (`wp saha seed`) — không có nút trong admin.
 * - Idempotent: chạy lại không tạo trùng (so theo slug / SKU).
 * - Mọi thứ tạo ra đều được đánh dấu (`_saha_seed`) để `wp saha unseed` gỡ
 *   đúng dữ liệu mẫu, KHÔNG đụng dữ liệu thật.
 * - Không gửi email trong lúc seed.
 *
 * Tên thương hiệu / mã sản phẩm dùng để kiểm thử tìm kiếm (spec §58), không
 * phải thông tin kỹ thuật chính thức — không dùng dữ liệu này ở production.
 */
final class Seeder {

	/**
	 * Đánh dấu dữ liệu mẫu.
	 */
	public const MARK = '_saha_seed';

	/**
	 * Tiền tố tên khách trong báo giá/lead mẫu.
	 */
	public const CRM_PREFIX = '[Mẫu] ';

	/**
	 * Đếm số bản ghi đã tạo.
	 *
	 * @var array<string, int>
	 */
	private array $created = array();

	/**
	 * Chạy seed.
	 *
	 * @param array<string, mixed> $options homepage_layout (đường dẫn file), set_front (bool), with_crm (bool).
	 * @return array<string, int>
	 */
	public function run( array $options = array() ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			throw new \RuntimeException( 'Cần kích hoạt WooCommerce trước khi seed.' );
		}

		$this->created = array();

		add_filter( 'saha_mail_enabled', '__return_false', 999 );

		$brands       = $this->seed_brands();
		$categories   = $this->seed_categories();
		$applications = $this->seed_applications();

		$this->seed_products( $brands, $categories, $applications );
		$this->seed_pages( (string) ( $options['homepage_layout'] ?? '' ), ! empty( $options['set_front'] ) );
		$this->seed_blog();

		if ( ! empty( $options['with_crm'] ) ) {
			$this->seed_crm();
		}

		remove_filter( 'saha_mail_enabled', '__return_false', 999 );

		Cache::bump();
		flush_rewrite_rules( false );

		return $this->created;
	}

	/**
	 * Tăng bộ đếm.
	 *
	 * @param string $key Loại.
	 */
	private function count( string $key ): void {
		$this->created[ $key ] = ( $this->created[ $key ] ?? 0 ) + 1;
	}

	/*
	 * ---------------------------------------------------------------------
	 * Taxonomy
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Tạo (hoặc lấy) term, đánh dấu là dữ liệu mẫu nếu mới tạo.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $name     Tên.
	 * @param string $slug     Slug.
	 * @param int    $parent   Term cha.
	 * @param string $description Mô tả.
	 */
	private function term( string $taxonomy, string $name, string $slug, int $parent = 0, string $description = '' ): int {
		$existing = get_term_by( 'slug', $slug, $taxonomy );

		if ( $existing instanceof \WP_Term ) {
			return (int) $existing->term_id;
		}

		$result = wp_insert_term(
			$name,
			$taxonomy,
			array(
				'slug'        => $slug,
				'parent'      => $parent,
				'description' => $description,
			)
		);

		if ( is_wp_error( $result ) ) {
			return 0;
		}

		$term_id = (int) $result['term_id'];

		update_term_meta( $term_id, self::MARK, 1 );
		$this->count( $taxonomy );

		return $term_id;
	}

	/**
	 * Thương hiệu.
	 *
	 * @return array<string, int> slug => term_id
	 */
	private function seed_brands(): array {
		$brands = array(
			'apollo'  => array( 'Apollo', 'Keo silicone, PU Foam cho nhôm kính và xây dựng.', '' ),
			'bamboo'  => array( 'Bamboo', 'Keo silicone dân dụng và công trình.', '' ),
			'wacker'  => array( 'Wacker', 'Silicone kỹ thuật cho công trình và công nghiệp.', 'Đức' ),
			'dowsil'  => array( 'Dowsil', 'Silicone kết cấu và chống thời tiết.', 'Mỹ' ),
			'loctite' => array( 'Loctite', 'Keo công nghiệp: khoá ren, kín ren, giữ đồng trục, dán nhanh, tạo gioăng.', 'Đức' ),
		);

		$out = array();

		foreach ( $brands as $slug => [ $name, $short, $country ] ) {
			$term_id = $this->term( Taxonomies::BRAND, $name, $slug );

			if ( $term_id <= 0 ) {
				continue;
			}

			$out[ $slug ] = $term_id;

			if ( '' === (string) get_term_meta( $term_id, 'saha_brand_short_description', true ) ) {
				update_term_meta( $term_id, 'saha_brand_short_description', $short );
				update_term_meta( $term_id, 'saha_brand_meta_description', $name . ' chính hãng tại Tổng Kho Keo Dán SAHA. ' . $short );

				if ( '' !== $country ) {
					update_term_meta( $term_id, 'saha_brand_country', $country );
				}
			}
		}

		return $out;
	}

	/**
	 * Danh mục sản phẩm theo cây spec §8.
	 *
	 * @return array<string, int> slug => term_id
	 */
	private function seed_categories(): array {
		$tree = array(
			'keo-silicone'    => array(
				'Keo Silicone',
				array(
					'keo-silicone-apollo' => 'Silicone Apollo',
					'keo-silicone-bamboo' => 'Silicone Bamboo',
					'keo-silicone-wacker' => 'Silicone Wacker',
					'keo-silicone-dowsil' => 'Silicone Dowsil',
				),
			),
			'keo-cong-nghiep' => array(
				'Keo công nghiệp',
				array(
					'khoa-ren'        => 'Khoá ren',
					'kin-ren'         => 'Kín ren',
					'giu-dong-truc'   => 'Giữ đồng trục',
					'dan-nhanh'       => 'Dán nhanh',
					'tao-gioang'      => 'Tạo gioăng',
					'chong-ket'       => 'Chống kẹt',
				),
			),
			'pu-foam'         => array( 'PU Foam', array() ),
			'keo-ab'          => array( 'Keo AB', array() ),
			'keo-502'         => array( 'Keo 502', array() ),
			'chat-tay'        => array( 'Chất tẩy', array() ),
			'son-xit'         => array( 'Sơn xịt', array() ),
			'chong-tham'      => array( 'Chống thấm', array() ),
		);

		$out   = array();
		$order = 0;

		foreach ( $tree as $slug => [ $name, $children ] ) {
			$parent_id = $this->term( 'product_cat', $name, $slug );

			if ( $parent_id <= 0 ) {
				continue;
			}

			$out[ $slug ] = $parent_id;

			// Thứ tự kéo-thả của WooCommerce — để test sắp xếp danh mục (PHASE-5 test 9).
			if ( '' === (string) get_term_meta( $parent_id, 'order', true ) ) {
				update_term_meta( $parent_id, 'order', $order );
			}

			++$order;

			foreach ( $children as $child_slug => $child_name ) {
				$child_id = $this->term( 'product_cat', $child_name, $child_slug, $parent_id );

				if ( $child_id > 0 ) {
					$out[ $child_slug ] = $child_id;
				}
			}
		}

		return $out;
	}

	/**
	 * Ứng dụng (spec §68).
	 *
	 * @return array<string, int>
	 */
	private function seed_applications(): array {
		$apps = array(
			'nhom-kinh'  => 'Nhôm kính',
			'xay-dung'   => 'Xây dựng',
			'o-to'       => 'Ô tô',
			'co-khi'     => 'Cơ khí',
			'noi-that'   => 'Nội thất',
			'dien-tu'    => 'Điện tử',
		);

		$out = array();

		foreach ( $apps as $slug => $name ) {
			$term_id = $this->term( Taxonomies::APPLICATION, $name, $slug );

			if ( $term_id > 0 ) {
				$out[ $slug ] = $term_id;
			}
		}

		return $out;
	}

	/*
	 * ---------------------------------------------------------------------
	 * Sản phẩm
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Định nghĩa sản phẩm mẫu.
	 *
	 * Cột: name, sku, category, brand, applications, price, featured, stock, availability, unit.
	 *
	 * @return array<int, array<int, mixed>>
	 */
	private function product_definitions(): array {
		return array(
			array( 'Keo khoá ren Loctite 243', 'LOCTITE-243', 'khoa-ren', 'loctite', array( 'co-khi', 'o-to' ), 320000, true, 'instock', '', 'chai' ),
			array( 'Keo khoá ren Loctite 262', 'LOCTITE-262', 'khoa-ren', 'loctite', array( 'co-khi' ), 360000, false, 'instock', '', 'chai' ),
			array( 'Keo khoá ren Loctite 222', 'LOCTITE-222', 'khoa-ren', 'loctite', array( 'co-khi', 'dien-tu' ), 310000, false, 'instock', '', 'chai' ),
			array( 'Keo khoá ren Loctite 270', 'LOCTITE-270', 'khoa-ren', 'loctite', array( 'co-khi', 'o-to' ), 380000, false, 'outofstock', '', 'chai' ),
			array( 'Keo kín ren Loctite 542', 'LOCTITE-542', 'kin-ren', 'loctite', array( 'co-khi' ), 340000, false, 'instock', '', 'chai' ),
			array( 'Keo kín ren Loctite 577', 'LOCTITE-577', 'kin-ren', 'loctite', array( 'co-khi' ), 420000, true, 'instock', 'contact', 'chai' ),
			array( 'Keo giữ đồng trục Loctite 638', 'LOCTITE-638', 'giu-dong-truc', 'loctite', array( 'co-khi', 'o-to' ), 450000, false, 'instock', '', 'chai' ),
			array( 'Keo dán nhanh Loctite 401', 'LOCTITE-401', 'dan-nhanh', 'loctite', array( 'dien-tu', 'noi-that' ), 150000, true, 'instock', '', 'tuýp' ),
			array( 'Keo tạo gioăng Loctite 5699', 'LOCTITE-5699', 'tao-gioang', 'loctite', array( 'o-to' ), 390000, false, 'instock', '', 'tuýp' ),
			array( 'Mỡ chống kẹt Loctite LB 8023', 'LOCTITE-8023', 'chong-ket', 'loctite', array( 'co-khi' ), 520000, false, 'instock', 'contact', 'hộp' ),
			array( 'Apollo Silicone A500', 'APOLLO-A500', 'keo-silicone-apollo', 'apollo', array( 'nhom-kinh', 'xay-dung' ), 65000, true, 'instock', '', 'chai' ),
			array( 'Apollo Silicone A300', 'APOLLO-A300', 'keo-silicone-apollo', 'apollo', array( 'nhom-kinh' ), 55000, false, 'instock', '', 'chai' ),
			array( 'Apollo Silicone A100 axit', 'APOLLO-A100', 'keo-silicone-apollo', 'apollo', array( 'nhom-kinh', 'noi-that' ), 45000, false, 'instock', '', 'chai' ),
			array( 'Bọt nở PU Foam Apollo', 'APOLLO-FOAM', 'pu-foam', 'apollo', array( 'xay-dung', 'nhom-kinh' ), 85000, true, 'instock', '', 'chai' ),
			array( 'Keo silicone Bamboo trung tính', 'BAMBOO-N', 'keo-silicone-bamboo', 'bamboo', array( 'nhom-kinh', 'noi-that' ), 50000, false, 'instock', '', 'chai' ),
			array( 'Keo silicone Bamboo chống nấm mốc', 'BAMBOO-AF', 'keo-silicone-bamboo', 'bamboo', array( 'noi-that' ), 60000, false, 'outofstock', '', 'chai' ),
			array( 'Silicone Wacker GN trung tính', 'WACKER-GN', 'keo-silicone-wacker', 'wacker', array( 'nhom-kinh', 'xay-dung' ), 120000, false, 'instock', '', 'chai' ),
			array( 'Dowsil 791 chống thời tiết', 'DOWSIL-791', 'keo-silicone-dowsil', 'dowsil', array( 'nhom-kinh', 'xay-dung' ), 140000, true, 'instock', '', 'chai' ),
			array( 'Dowsil 732 đa năng', 'DOWSIL-732', 'keo-silicone-dowsil', 'dowsil', array( 'co-khi', 'dien-tu' ), 210000, false, 'instock', 'contact', 'tuýp' ),
			array( 'Keo AB epoxy trong suốt', 'KEO-AB-01', 'keo-ab', '', array( 'noi-that', 'co-khi' ), 35000, false, 'instock', '', 'bộ' ),
			array( 'Keo 502 dán nhanh', 'KEO-502', 'keo-502', '', array( 'noi-that' ), 8000, false, 'instock', '', 'tuýp' ),
			array( 'Chất tẩy keo silicone', 'CHAT-TAY-01', 'chat-tay', '', array( 'xay-dung' ), 45000, false, 'instock', '', 'chai' ),
			array( 'Sơn xịt đa năng màu đen', 'SON-XIT-DEN', 'son-xit', '', array( 'noi-that', 'o-to' ), 30000, false, 'instock', '', 'chai' ),
			array( 'Keo chống thấm gốc PU', 'CHONG-THAM-PU', 'chong-tham', '', array( 'xay-dung' ), 180000, false, 'instock', '', 'thùng' ),
		);
	}

	/**
	 * Tạo sản phẩm.
	 *
	 * @param array<string, int> $brands       Thương hiệu.
	 * @param array<string, int> $categories   Danh mục.
	 * @param array<string, int> $applications Ứng dụng.
	 */
	private function seed_products( array $brands, array $categories, array $applications ): void {
		foreach ( $this->product_definitions() as $index => $row ) {
			[ $name, $sku, $cat, $brand, $apps, $price, $featured, $stock, $availability, $unit ] = $row;

			if ( wc_get_product_id_by_sku( $sku ) ) {
				continue;
			}

			$product = new \WC_Product_Simple();
			$product->set_name( $name );
			$product->set_sku( $sku );
			$product->set_status( 'publish' );
			$product->set_regular_price( (string) $price );
			$product->set_featured( (bool) $featured );
			$product->set_stock_status( $stock );
			$product->set_menu_order( $index );
			$product->set_short_description( sprintf( '%s — sản phẩm mẫu phục vụ kiểm thử, không phải thông số chính thức.', $name ) );
			$product->set_description( '<p>' . esc_html( $name ) . ' — nội dung mẫu để kiểm thử bố cục trang sản phẩm.</p>' );

			if ( isset( $categories[ $cat ] ) ) {
				$product->set_category_ids( array( $categories[ $cat ] ) );
			}

			$product->update_meta_data( self::MARK, '1' );
			$product->update_meta_data( '_saha_unit', $unit );

			if ( '' !== $availability ) {
				$product->update_meta_data( '_saha_availability', $availability );
			}

			// Một nửa sản phẩm có bảng thông số + ứng dụng + lưu ý, nửa còn lại để trống
			// → kiểm tra "field trống không render" (spec §5).
			if ( 0 === $index % 2 ) {
				$product->update_meta_data(
					'_saha_specs',
					array(
						array(
							'label' => 'Quy cách',
							'value' => '50 ml',
						),
						array(
							'label' => 'Nhiệt độ làm việc',
							'value' => '-55 °C đến 150 °C',
						),
					)
				);
				$product->update_meta_data( '_saha_application_text', '<p>Nội dung ứng dụng mẫu.</p>' );
				$product->update_meta_data( '_saha_warning', '<p>Lưu ý mẫu: đọc kỹ hướng dẫn trước khi dùng.</p>' );
			}

			$product_id = $product->save();

			if ( $product_id <= 0 ) {
				continue;
			}

			if ( '' !== $brand && isset( $brands[ $brand ] ) ) {
				wp_set_object_terms( $product_id, array( $brands[ $brand ] ), Taxonomies::BRAND );
			}

			$app_ids = array_values( array_filter( array_map( static fn( string $slug ): int => $applications[ $slug ] ?? 0, $apps ) ) );

			if ( $app_ids ) {
				wp_set_object_terms( $product_id, $app_ids, Taxonomies::APPLICATION );
			}

			$this->count( 'product' );
		}
	}

	/*
	 * ---------------------------------------------------------------------
	 * Trang & blog
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Tạo page nếu slug chưa tồn tại.
	 *
	 * @param string $title   Tiêu đề.
	 * @param string $slug    Slug.
	 * @param string $content Nội dung.
	 */
	private function page( string $title, string $slug, string $content ): int {
		$existing = get_page_by_path( $slug );

		if ( $existing instanceof \WP_Post ) {
			return (int) $existing->ID;
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => $content,
				'meta_input'   => array( self::MARK => 1 ),
			),
			true
		);

		if ( is_wp_error( $page_id ) ) {
			return 0;
		}

		$this->count( 'page' );

		return (int) $page_id;
	}

	/**
	 * Trang báo giá, liên hệ, thương hiệu, trang chủ.
	 *
	 * @param string $layout_file Đường dẫn layout UX Builder cho trang chủ.
	 * @param bool   $set_front   Đặt làm trang chủ (ghi đè cấu hình Reading hiện tại).
	 */
	private function seed_pages( string $layout_file, bool $set_front ): void {
		$quote_id = $this->page( 'Yêu cầu báo giá', 'bao-gia', '[saha_quote_form title="Yêu cầu báo giá"]' );
		$this->page( 'Liên hệ', 'lien-he', '[saha_contact_form title="Liên hệ tư vấn"]' );
		$this->page( 'Thương hiệu', 'thuong-hieu', '[saha_brand_grid title="Thương hiệu" show_count="1"]' );

		if ( $quote_id > 0 && '' === (string) Settings::get( 'quote_page_url', '' ) ) {
			$settings                   = Settings::all();
			$settings['quote_page_url'] = (string) get_permalink( $quote_id );
			update_option( Settings::OPTION, $settings );
		}

		if ( '' === $layout_file ) {
			return;
		}

		if ( ! is_readable( $layout_file ) ) {
			throw new \RuntimeException( 'Không đọc được file layout: ' . $layout_file );
		}

		$home_id = $this->page( 'Trang chủ', 'trang-chu', (string) file_get_contents( $layout_file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file cục bộ do người chạy CLI chỉ định.

		// Không ghi đè cấu hình trang chủ đang có trừ khi được yêu cầu rõ.
		if ( $home_id > 0 && ( $set_front || 'page' !== get_option( 'show_on_front' ) ) ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $home_id );
		}
	}

	/**
	 * Chuyên mục blog (spec §21) + bài mẫu cho khối bài viết liên quan.
	 */
	private function seed_blog(): void {
		$cats = array(
			'kien-thuc-keo'    => 'Kiến thức keo',
			'huong-dan'        => 'Hướng dẫn',
			'tu-van'           => 'Tư vấn',
			'tin-doanh-nghiep' => 'Tin doanh nghiệp',
		);

		$ids = array();

		foreach ( $cats as $slug => $name ) {
			$ids[ $slug ] = $this->term( 'category', $name, $slug );
		}

		$posts = array(
			array( 'Cách chọn keo silicone cho nhôm kính', 'kien-thuc-keo' ),
			array( 'Phân biệt silicone axit và trung tính', 'kien-thuc-keo' ),
			array( 'Hướng dẫn thi công bọt nở PU Foam', 'huong-dan' ),
			array( 'Khi nào dùng keo khoá ren cường độ trung bình', 'tu-van' ),
		);

		foreach ( $posts as [ $title, $cat ] ) {
			$slug = sanitize_title( $title );

			if ( get_page_by_path( $slug, OBJECT, 'post' ) ) {
				continue;
			}

			$post_id = wp_insert_post(
				array(
					'post_type'     => 'post',
					'post_status'   => 'publish',
					'post_title'    => $title,
					'post_name'     => $slug,
					'post_content'  => '<p>Bài viết mẫu phục vụ kiểm thử bố cục blog và khối bài viết liên quan.</p>',
					'post_category' => array_filter( array( $ids[ $cat ] ?? 0 ) ),
					'meta_input'    => array( self::MARK => 1 ),
				),
				true
			);

			if ( ! is_wp_error( $post_id ) ) {
				$this->count( 'post' );
			}
		}
	}

	/**
	 * Báo giá + lead mẫu cho màn hình CRM. Không gửi email.
	 */
	private function seed_crm(): void {
		$product_id = (int) wc_get_product_id_by_sku( 'LOCTITE-243' );

		$quotes = array(
			array( 'Anh Nam', '0900000001', 'Xưởng cơ khí Minh Phát', '2 thùng' ),
			array( 'Chị Hoa', '0900000002', 'Nhôm kính Hoàng Gia', '50 chai' ),
			array( 'Anh Tuấn', '0900000003', '', '10 chai' ),
		);

		foreach ( $quotes as [ $name, $phone, $company, $qty ] ) {
			$check = Quote::validate(
				array(
					'name'       => self::CRM_PREFIX . $name,
					'phone'      => $phone,
					'company'    => $company,
					'product_id' => $product_id,
					'quantity'   => $qty,
					'message'    => 'Dữ liệu mẫu phục vụ kiểm thử CRM.',
				)
			);

			if ( ! $check['errors'] && Quote::create( $check['data'] )['id'] > 0 ) {
				$this->count( 'quote' );
			}
		}

		$check = Lead::validate(
			array(
				'name'    => self::CRM_PREFIX . 'Công ty Xây dựng An Phú',
				'phone'   => '0900000004',
				'message' => 'Cần tư vấn keo chống thấm cho công trình. Dữ liệu mẫu.',
				'source'  => 'contact',
			)
		);

		if ( ! $check['errors'] && Lead::create( $check['data'] )['id'] > 0 ) {
			$this->count( 'lead' );
		}
	}

	/*
	 * ---------------------------------------------------------------------
	 * Gỡ dữ liệu mẫu
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Gỡ đúng dữ liệu mẫu đã đánh dấu. Không đụng dữ liệu thật.
	 *
	 * @return array<string, int>
	 */
	public function purge(): array {
		global $wpdb;

		$removed = array();

		$post_ids = get_posts(
			array(
				'post_type'      => array( 'product', 'page', 'post' ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => self::MARK, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- lệnh CLI chạy tay.
			)
		);

		foreach ( $post_ids as $post_id ) {
			$type = (string) get_post_type( $post_id );

			if ( (int) get_option( 'page_on_front' ) === (int) $post_id ) {
				update_option( 'show_on_front', 'posts' );
				update_option( 'page_on_front', 0 );
			}

			if ( wp_delete_post( (int) $post_id, true ) ) {
				$removed[ $type ] = ( $removed[ $type ] ?? 0 ) + 1;
			}
		}

		$taxonomies = array( Taxonomies::BRAND, Taxonomies::APPLICATION, 'product_cat', 'category' );

		foreach ( $taxonomies as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'fields'     => 'ids',
					'meta_key'   => self::MARK, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- lệnh CLI chạy tay.
				)
			);

			// Xoá con trước cha để không để lại term mồ côi.
			foreach ( array_reverse( is_wp_error( $terms ) ? array() : $terms ) as $term_id ) {
				if ( true === wp_delete_term( (int) $term_id, $taxonomy ) ) {
					$removed[ $taxonomy ] = ( $removed[ $taxonomy ] ?? 0 ) + 1;
				}
			}
		}

		$like = $wpdb->esc_like( self::CRM_PREFIX ) . '%';

		foreach ( array( Quote::table() => 'customer_name', Lead::table() => 'name' ) as $table => $column ) {
			if ( ! Migrator::table_exists( $table ) ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tên bảng/cột nội bộ.
			$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE `{$column}` LIKE %s", $like ) );

			if ( is_int( $deleted ) && $deleted > 0 ) {
				$removed[ basename( $table ) ] = $deleted;
			}
		}

		Cache::bump();
		Quote::flush_new_count();

		return $removed;
	}
}
