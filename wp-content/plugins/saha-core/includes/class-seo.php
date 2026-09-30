<?php
/**
 * SEO bridge: tích hợp Rank Math / Yoast, fallback tối thiểu khi không có plugin SEO.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Seo.
 *
 * Nguyên tắc (spec §22):
 * - KHÔNG tự xây lại SEO plugin. Rank Math / Yoast quyết định title, meta,
 *   canonical, OpenGraph, schema, sitemap.
 * - Plugin chỉ (1) cấp dữ liệu SAHA cho chúng — SEO title / meta description
 *   của thương hiệu, (2) xử lý URL mà chúng không biết — URL lọc `saha_*`,
 *   (3) tránh schema trùng giữa WooCommerce và plugin SEO.
 * - Khi KHÔNG có plugin SEO nào: xuất fallback tối thiểu (description,
 *   canonical cho archive, OpenGraph cơ bản) để site không trống meta.
 *
 * Thứ tự ưu tiên cho title/description của thương hiệu:
 *   ô nhập trong plugin SEO  >  ô SEO của SAHA  >  mặc định plugin SEO.
 */
final class Seo {

	public const PROVIDER_RANK_MATH = 'rank_math';
	public const PROVIDER_YOAST     = 'yoast';
	public const PROVIDER_NONE      = 'none';

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		// Robots + canonical cho URL lọc / tìm kiếm — áp cho mọi provider.
		add_filter( 'wp_robots', array( $this, 'filter_wp_robots' ) );
		add_filter( 'rank_math/frontend/robots', array( $this, 'filter_rank_math_robots' ) );
		add_filter( 'wpseo_robots_array', array( $this, 'filter_yoast_robots' ) );

		add_filter( 'rank_math/frontend/canonical', array( $this, 'filter_canonical' ) );
		add_filter( 'wpseo_canonical', array( $this, 'filter_canonical' ) );

		// Title / description thương hiệu.
		add_filter( 'rank_math/frontend/title', array( $this, 'filter_rank_math_title' ) );
		add_filter( 'rank_math/frontend/description', array( $this, 'filter_rank_math_description' ) );
		add_filter( 'wpseo_title', array( $this, 'filter_yoast_title' ) );
		add_filter( 'wpseo_metadesc', array( $this, 'filter_yoast_description' ) );
		add_filter( 'pre_get_document_title', array( $this, 'filter_document_title' ), 20 );

		// Fallback khi không có plugin SEO.
		add_action( 'wp_head', array( $this, 'output_fallback_meta' ), 2 );

		// Schema sản phẩm: chỉ một nguồn (spec §24).
		add_filter( 'woocommerce_structured_data_product', array( $this, 'maybe_disable_wc_product_schema' ), 99 );

		// robots.txt + sitemap (spec §50, §51).
		add_filter( 'robots_txt', array( $this, 'filter_robots_txt' ), 20, 2 );
		add_filter( 'wp_sitemaps_add_provider', array( $this, 'filter_core_sitemap_providers' ), 10, 2 );
		add_filter( 'wp_sitemaps_taxonomies', array( $this, 'filter_core_sitemap_taxonomies' ) );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Nhận diện
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Plugin SEO đang hoạt động.
	 */
	public static function provider(): string {
		if ( defined( 'RANK_MATH_VERSION' ) || class_exists( '\RankMath' ) ) {
			return self::PROVIDER_RANK_MATH;
		}

		if ( defined( 'WPSEO_VERSION' ) ) {
			return self::PROVIDER_YOAST;
		}

		return self::PROVIDER_NONE;
	}

	/**
	 * Nguồn breadcrumb duy nhất được dùng (spec §71 — không render 2 breadcrumb).
	 *
	 * @return string rank_math | yoast | woocommerce | none
	 */
	public static function breadcrumb_provider(): string {
		if ( function_exists( 'rank_math_the_breadcrumbs' ) && self::rank_math_breadcrumbs_enabled() ) {
			return 'rank_math';
		}

		if ( function_exists( 'yoast_breadcrumb' ) && self::yoast_breadcrumbs_enabled() ) {
			return 'yoast';
		}

		return class_exists( 'WooCommerce' ) ? 'woocommerce' : 'none';
	}

	/**
	 * Rank Math đã bật breadcrumb trong cài đặt chưa.
	 */
	private static function rank_math_breadcrumbs_enabled(): bool {
		if ( class_exists( '\RankMath\Helper' ) && method_exists( '\RankMath\Helper', 'is_breadcrumbs_enabled' ) ) {
			return (bool) \RankMath\Helper::is_breadcrumbs_enabled();
		}

		if ( class_exists( '\RankMath\Helper' ) && method_exists( '\RankMath\Helper', 'get_settings' ) ) {
			return (bool) \RankMath\Helper::get_settings( 'general.breadcrumbs' );
		}

		return false;
	}

	/**
	 * Yoast đã bật breadcrumb chưa.
	 */
	private static function yoast_breadcrumbs_enabled(): bool {
		if ( current_theme_supports( 'yoast-seo-breadcrumbs' ) ) {
			return true;
		}

		if ( class_exists( '\WPSEO_Options' ) && method_exists( '\WPSEO_Options', 'get' ) ) {
			return (bool) \WPSEO_Options::get( 'breadcrumbs-enable' );
		}

		return false;
	}

	/**
	 * Request hiện tại có phải URL lọc / sắp xếp / tìm kiếm nội bộ không.
	 *
	 * Các URL này nhân bản nội dung archive → noindex, follow + canonical về
	 * archive gốc (spec §10, §51).
	 */
	public static function is_parameterized_listing(): bool {
		if ( is_admin() ) {
			return false;
		}

		if ( is_search() ) {
			return true;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- chỉ kiểm tra có tham số hay không.
		foreach ( array_keys( Filter::allowed_vars() ) as $var ) {
			if ( isset( $_GET[ $var ] ) && '' !== $_GET[ $var ] ) {
				return true;
			}
		}

		foreach ( array( 'orderby', 'min_price', 'max_price', 'rating_filter', Form_Handler::RESULT_VAR ) as $var ) {
			if ( isset( $_GET[ $var ] ) ) {
				return true;
			}
		}

		// Bộ lọc layered nav của WooCommerce: filter_pa_*.
		foreach ( array_keys( $_GET ) as $key ) {
			if ( 0 === strpos( (string) $key, 'filter_' ) || 0 === strpos( (string) $key, 'query_type_' ) ) {
				return true;
			}
		}
		// phpcs:enable

		return false;
	}

	/**
	 * Thương hiệu đang xem (nếu là trang thương hiệu).
	 *
	 * @return array<string, mixed>
	 */
	private static function current_brand(): array {
		if ( ! is_tax( Taxonomies::BRAND ) ) {
			return array();
		}

		$term = get_queried_object();

		return $term instanceof \WP_Term ? Brand::get( $term ) : array();
	}

	/*
	 * ---------------------------------------------------------------------
	 * Robots
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Robots của WordPress core (dùng khi không có plugin SEO, hoặc Yoast bản dùng wp_robots).
	 *
	 * @param array<string, bool|string> $robots Directive.
	 * @return array<string, bool|string>
	 */
	public function filter_wp_robots( array $robots ): array {
		if ( ! self::is_parameterized_listing() ) {
			return $robots;
		}

		unset( $robots['index'] );
		$robots['noindex'] = true;
		$robots['follow']  = true;

		return $robots;
	}

	/**
	 * Robots của Rank Math.
	 *
	 * @param array<string, string> $robots Directive.
	 * @return array<string, string>
	 */
	public function filter_rank_math_robots( $robots ): array {
		$robots = is_array( $robots ) ? $robots : array();

		if ( ! self::is_parameterized_listing() ) {
			return $robots;
		}

		$robots['index']  = 'noindex';
		$robots['follow'] = 'follow';

		return $robots;
	}

	/**
	 * Robots của Yoast.
	 *
	 * @param array<string, string> $robots Directive.
	 * @return array<string, string>
	 */
	public function filter_yoast_robots( $robots ): array {
		$robots = is_array( $robots ) ? $robots : array();

		if ( ! self::is_parameterized_listing() ) {
			return $robots;
		}

		$robots['index']  = 'noindex';
		$robots['follow'] = 'follow';

		return $robots;
	}

	/*
	 * ---------------------------------------------------------------------
	 * Canonical
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Canonical cho URL lọc: về archive gốc, GIỮ số trang (spec §23 —
	 * pagination canonical đúng: trang 2 canonical về chính trang 2).
	 *
	 * @param string|false $canonical Canonical do plugin SEO tính.
	 * @return string|false
	 */
	public function filter_canonical( $canonical ) {
		if ( ! self::is_parameterized_listing() || is_search() ) {
			return $canonical;
		}

		$clean = self::clean_archive_url();

		return '' !== $clean ? $clean : $canonical;
	}

	/**
	 * URL archive hiện tại, bỏ mọi tham số lọc, giữ số trang.
	 */
	public static function clean_archive_url(): string {
		if ( ! is_tax() && ! is_post_type_archive( 'product' ) && ! ( function_exists( 'is_shop' ) && is_shop() ) ) {
			return '';
		}

		$base  = Filter::current_base_url();
		$paged = max( 1, (int) get_query_var( 'paged' ) );

		if ( $paged <= 1 ) {
			return $base;
		}

		global $wp_rewrite;

		if ( $wp_rewrite instanceof \WP_Rewrite && $wp_rewrite->using_permalinks() ) {
			return user_trailingslashit( trailingslashit( $base ) . $wp_rewrite->pagination_base . '/' . $paged );
		}

		return add_query_arg( 'paged', $paged, $base );
	}

	/*
	 * ---------------------------------------------------------------------
	 * Title / description thương hiệu
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Rank Math: dùng SEO title của SAHA khi ô Rank Math của term để trống.
	 *
	 * @param string $title Title hiện tại.
	 */
	public function filter_rank_math_title( $title ) {
		$brand = self::current_brand();

		if ( ! $brand || '' === (string) $brand['seo_title'] ) {
			return $title;
		}

		if ( '' !== (string) get_term_meta( (int) $brand['id'], 'rank_math_title', true ) ) {
			return $title;
		}

		return self::brand_title( $brand );
	}

	/**
	 * Rank Math: meta description.
	 *
	 * @param string $description Description hiện tại.
	 */
	public function filter_rank_math_description( $description ) {
		$brand = self::current_brand();

		if ( ! $brand ) {
			return $description;
		}

		if ( '' !== (string) get_term_meta( (int) $brand['id'], 'rank_math_description', true ) ) {
			return $description;
		}

		$ours = self::brand_description( $brand );

		return '' !== $ours ? $ours : $description;
	}

	/**
	 * Yoast: title.
	 *
	 * @param string $title Title hiện tại.
	 */
	public function filter_yoast_title( $title ) {
		$brand = self::current_brand();

		if ( ! $brand || '' === (string) $brand['seo_title'] ) {
			return $title;
		}

		if ( '' !== self::yoast_term_value( (int) $brand['id'], 'title' ) ) {
			return $title;
		}

		return self::brand_title( $brand );
	}

	/**
	 * Yoast: meta description.
	 *
	 * @param string $description Description hiện tại.
	 */
	public function filter_yoast_description( $description ) {
		$brand = self::current_brand();

		if ( ! $brand || '' !== self::yoast_term_value( (int) $brand['id'], 'desc' ) ) {
			return $description;
		}

		$ours = self::brand_description( $brand );

		return '' !== $ours ? $ours : $description;
	}

	/**
	 * Không có plugin SEO: title của trang thương hiệu cho thẻ <title>.
	 *
	 * @param string $title Title (rỗng = để WordPress tự tính).
	 */
	public function filter_document_title( $title ) {
		if ( self::PROVIDER_NONE !== self::provider() ) {
			return $title;
		}

		$brand = self::current_brand();

		if ( ! $brand || '' === (string) $brand['seo_title'] ) {
			return $title;
		}

		return self::brand_title( $brand );
	}

	/**
	 * Giá trị SEO Yoast đã nhập cho term.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $key     title | desc.
	 */
	private static function yoast_term_value( int $term_id, string $key ): string {
		if ( ! class_exists( '\WPSEO_Taxonomy_Meta' ) || ! method_exists( '\WPSEO_Taxonomy_Meta', 'get_term_meta' ) ) {
			return '';
		}

		$value = \WPSEO_Taxonomy_Meta::get_term_meta( $term_id, Taxonomies::BRAND, $key );

		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * Title thương hiệu, thêm số trang khi phân trang để không trùng title.
	 *
	 * @param array<string, mixed> $brand Dữ liệu thương hiệu.
	 */
	private static function brand_title( array $brand ): string {
		$title = wp_strip_all_tags( (string) $brand['seo_title'] );
		$paged = (int) get_query_var( 'paged' );

		if ( $paged > 1 ) {
			/* translators: 1: title, 2: số trang */
			$title = sprintf( __( '%1$s - Trang %2$d', 'saha-core' ), $title, $paged );
		}

		return $title;
	}

	/**
	 * Description thương hiệu: meta description → mô tả ngắn → mô tả term.
	 *
	 * @param array<string, mixed> $brand Dữ liệu thương hiệu.
	 */
	private static function brand_description( array $brand ): string {
		foreach ( array( 'meta_description', 'short_description', 'description' ) as $key ) {
			$value = trim( wp_strip_all_tags( (string) ( $brand[ $key ] ?? '' ) ) );

			if ( '' !== $value ) {
				return self::trim_description( $value );
			}
		}

		return '';
	}

	/**
	 * Cắt description khoảng 160 ký tự, không cắt giữa từ.
	 *
	 * @param string $text Văn bản.
	 */
	private static function trim_description( string $text ): string {
		$text = preg_replace( '/\s+/u', ' ', $text ) ?? $text;

		if ( mb_strlen( $text ) <= 160 ) {
			return $text;
		}

		$cut   = mb_substr( $text, 0, 157 );
		$space = mb_strrpos( $cut, ' ' );

		return rtrim( false !== $space ? mb_substr( $cut, 0, $space ) : $cut, " ,.;:-" ) . '…';
	}

	/*
	 * ---------------------------------------------------------------------
	 * Fallback khi không có plugin SEO
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Meta description, canonical cho archive, OpenGraph cơ bản.
	 *
	 * Chỉ chạy khi KHÔNG có Rank Math / Yoast — tránh xuất trùng (spec §22).
	 * WordPress core đã tự xuất canonical cho trang đơn (rel_canonical), nên
	 * ở đây chỉ bổ sung cho archive.
	 */
	public function output_fallback_meta(): void {
		/**
		 * Tắt hẳn fallback SEO (ví dụ khi dùng plugin SEO khác Rank Math/Yoast).
		 *
		 * @param bool $enabled Có xuất không.
		 */
		if ( self::PROVIDER_NONE !== self::provider() || ! apply_filters( 'saha_seo_fallback_enabled', true ) ) {
			return;
		}

		$meta = self::fallback_meta();

		if ( '' !== $meta['description'] ) {
			printf( "<meta name=\"description\" content=\"%s\">\n", esc_attr( $meta['description'] ) );
		}

		if ( '' !== $meta['canonical'] ) {
			printf( "<link rel=\"canonical\" href=\"%s\">\n", esc_url( $meta['canonical'] ) );
		}

		if ( '' === $meta['title'] ) {
			return;
		}

		printf( "<meta property=\"og:locale\" content=\"%s\">\n", esc_attr( str_replace( '-', '_', get_bloginfo( 'language' ) ) ) );
		printf( "<meta property=\"og:site_name\" content=\"%s\">\n", esc_attr( (string) Settings::get( 'site_brand', get_bloginfo( 'name' ) ) ) );
		printf( "<meta property=\"og:type\" content=\"%s\">\n", esc_attr( $meta['type'] ) );
		printf( "<meta property=\"og:title\" content=\"%s\">\n", esc_attr( $meta['title'] ) );

		if ( '' !== $meta['description'] ) {
			printf( "<meta property=\"og:description\" content=\"%s\">\n", esc_attr( $meta['description'] ) );
		}

		if ( '' !== $meta['url'] ) {
			printf( "<meta property=\"og:url\" content=\"%s\">\n", esc_url( $meta['url'] ) );
		}

		if ( '' !== $meta['image'] ) {
			printf( "<meta property=\"og:image\" content=\"%s\">\n", esc_url( $meta['image'] ) );
		}
	}

	/**
	 * Tính meta fallback cho trang hiện tại.
	 *
	 * @return array{title: string, description: string, canonical: string, url: string, image: string, type: string}
	 */
	public static function fallback_meta(): array {
		$meta = array(
			'title'       => '',
			'description' => '',
			'canonical'   => '',
			'url'         => '',
			'image'       => '',
			'type'        => 'website',
		);

		if ( is_singular() ) {
			$post_id = (int) get_queried_object_id();

			$meta['title'] = wp_strip_all_tags( get_the_title( $post_id ) );
			$meta['url']   = (string) get_permalink( $post_id );
			$meta['type']  = is_singular( 'post' ) ? 'article' : 'website';

			$excerpt = has_excerpt( $post_id )
				? get_the_excerpt( $post_id )
				: wp_trim_words( wp_strip_all_tags( strip_shortcodes( (string) get_post_field( 'post_content', $post_id ) ) ), 40, '' );

			$meta['description'] = self::trim_description( wp_strip_all_tags( (string) $excerpt ) );

			$thumb = get_post_thumbnail_id( $post_id );

			if ( $thumb ) {
				$meta['image'] = (string) wp_get_attachment_image_url( (int) $thumb, 'large' );
			}

			// Canonical trang đơn do core rel_canonical() lo.
			return $meta;
		}

		if ( is_tax() || is_category() || is_tag() ) {
			$term = get_queried_object();

			if ( $term instanceof \WP_Term ) {
				$meta['title'] = $term->name;
				$meta['url']   = self::clean_archive_url();

				if ( '' === $meta['url'] ) {
					$link        = get_term_link( $term );
					$meta['url'] = is_wp_error( $link ) ? '' : $link;
				}

				$brand = Taxonomies::BRAND === $term->taxonomy ? Brand::get( $term ) : array();

				$meta['description'] = $brand
					? self::brand_description( $brand )
					: self::trim_description( wp_strip_all_tags( (string) $term->description ) );

				$image_id = $brand ? (int) ( $brand['banner_id'] ?: $brand['logo_id'] ) : (int) get_term_meta( $term->term_id, 'thumbnail_id', true );

				if ( $image_id > 0 ) {
					$meta['image'] = (string) wp_get_attachment_image_url( $image_id, 'large' );
				}

				$meta['canonical'] = self::is_parameterized_listing() ? self::clean_archive_url() : $meta['url'];
			}

			return $meta;
		}

		if ( function_exists( 'is_shop' ) && is_shop() ) {
			$meta['title']     = wp_strip_all_tags( (string) get_the_title( (int) wc_get_page_id( 'shop' ) ) );
			$meta['url']       = self::clean_archive_url();
			$meta['canonical'] = $meta['url'];

			return $meta;
		}

		if ( is_front_page() || is_home() ) {
			$meta['title']       = (string) Settings::get( 'site_brand', get_bloginfo( 'name' ) );
			$meta['description'] = self::trim_description( (string) get_bloginfo( 'description' ) );
			$meta['url']         = home_url( '/' );
		}

		return $meta;
	}

	/*
	 * ---------------------------------------------------------------------
	 * Schema
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Tắt Product schema của WooCommerce khi plugin SEO đã xuất Product schema,
	 * tránh 2 khối Product trùng nhau (spec §24).
	 *
	 * Cấu hình: SAHA → Cấu hình → Nguồn schema sản phẩm.
	 *
	 * @param array<string, mixed> $markup Dữ liệu schema WooCommerce.
	 * @return array<string, mixed>
	 */
	public function maybe_disable_wc_product_schema( $markup ) {
		return self::product_schema_source() === 'woocommerce' ? $markup : array();
	}

	/**
	 * Ai xuất Product schema.
	 *
	 * auto: Rank Math có module WooCommerce xuất Product schema → nhường Rank Math.
	 *       Yoast bản miễn phí không xuất Product schema → giữ WooCommerce.
	 *
	 * @return string woocommerce | seo_plugin
	 */
	public static function product_schema_source(): string {
		$setting = (string) Settings::get( 'product_schema_source', 'auto' );

		if ( in_array( $setting, array( 'woocommerce', 'seo_plugin' ), true ) ) {
			return $setting;
		}

		return self::PROVIDER_RANK_MATH === self::provider() ? 'seo_plugin' : 'woocommerce';
	}

	/*
	 * ---------------------------------------------------------------------
	 * robots.txt & sitemap
	 * ---------------------------------------------------------------------
	 */

	/**
	 * robots.txt: chặn trang kết quả tìm kiếm và endpoint form nội bộ.
	 *
	 * KHÔNG chặn CSS/JS/uploads (spec §51) và KHÔNG chặn URL lọc — URL lọc
	 * dùng noindex + canonical, vì nếu disallow thì Google không đọc được
	 * noindex/canonical đó.
	 *
	 * @param string $output Nội dung robots.txt.
	 * @param bool   $public Site đang cho phép index.
	 */
	public function filter_robots_txt( $output, $public ): string {
		$output = (string) $output;

		if ( ! $public ) {
			return $output;
		}

		$path  = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$path  = '' === $path ? '/' : trailingslashit( $path );
		$lines = array(
			'Disallow: ' . $path . '?s=',
			'Disallow: ' . $path . '*?s=',
			'Disallow: ' . $path . 'search/',
			'Disallow: ' . $path . 'wp-admin/admin-post.php',
			'Disallow: ' . $path . '*?' . Form_Handler::RESULT_VAR . '=',
		);

		/**
		 * Lọc các dòng robots.txt do SAHA bổ sung.
		 *
		 * @param string[] $lines Dòng Disallow/Allow.
		 */
		$lines = (array) apply_filters( 'saha_robots_txt_lines', $lines );

		$block = "\n# SAHA\n" . implode( "\n", array_map( 'strval', $lines ) ) . "\n";

		// Chèn vào nhóm "User-agent: *" mà WordPress đã tạo.
		if ( false !== strpos( $output, 'User-agent: *' ) ) {
			$insert = ltrim( $block, "\n" );
			$result = preg_replace_callback(
				'/User-agent: \*\r?\n(?:[^\r\n]+\r?\n)*/',
				static fn( array $m ): string => $m[0] . $insert,
				$output,
				1
			);

			return is_string( $result ) ? $result : $output . $block;
		}

		return $output . "User-agent: *\n" . ltrim( $block, "\n" );
	}

	/**
	 * Sitemap core (khi không có plugin SEO): bỏ sitemap user — không lộ
	 * tên tài khoản quản trị (spec §50: không sitemap dữ liệu nội bộ).
	 *
	 * @param mixed  $provider Provider.
	 * @param string $name     Tên provider.
	 * @return mixed
	 */
	public function filter_core_sitemap_providers( $provider, $name ) {
		return 'users' === $name ? false : $provider;
	}

	/**
	 * Sitemap core: giữ danh mục sản phẩm, thương hiệu, ứng dụng; bỏ tag sản phẩm
	 * (mỏng nội dung) và taxonomy nội bộ.
	 *
	 * @param array<string, \WP_Taxonomy> $taxonomies Taxonomy.
	 * @return array<string, \WP_Taxonomy>
	 */
	public function filter_core_sitemap_taxonomies( $taxonomies ): array {
		$taxonomies = is_array( $taxonomies ) ? $taxonomies : array();

		/**
		 * Taxonomy bị loại khỏi sitemap core.
		 *
		 * @param string[] $excluded Tên taxonomy.
		 */
		$excluded = (array) apply_filters( 'saha_sitemap_excluded_taxonomies', array( 'product_tag', 'product_shipping_class', 'post_format' ) );

		foreach ( $excluded as $taxonomy ) {
			unset( $taxonomies[ $taxonomy ] );
		}

		return $taxonomies;
	}
}
