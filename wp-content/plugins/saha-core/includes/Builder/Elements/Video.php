<?php
/**
 * Element: video (YouTube, Vimeo, file mp4).
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
 * Video — YouTube / Vimeo hiện **ảnh bìa + nút phát**; iframe chỉ tải khi bấm (elements.js,
 * `[data-saha-video]`) → trang nhẹ, không cookie bên thứ ba trước khi xem (YouTube dùng
 * youtube-nocookie.com). Không JS: nút là link mở video ở trang gốc. File mp4: `<video controls preload="none">`.
 */
final class Video extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'video',
			'name'           => __( 'Video', 'saha-core' ),
			'icon'           => 'video-alt3',
			'category'       => 'content',
			'allowedParents' => self::CONTENT_PARENTS,
			'controls'       => array(
				'url'    => array(
					'type'      => 'text',
					'label'     => __( 'Link video (YouTube, Vimeo hoặc file .mp4)', 'saha-core' ),
					'section'   => 'content',
					'default'   => '',
					'maxLength' => 500,
				),
				'title'  => array(
					'type'      => 'text',
					'label'     => __( 'Tên video (cho trình đọc màn hình)', 'saha-core' ),
					'section'   => 'content',
					'default'   => __( 'Video giới thiệu', 'saha-core' ),
					'maxLength' => 150,
				),
				'poster' => array(
					'type'    => 'media',
					'label'   => __( 'Ảnh bìa (trống = ảnh của YouTube)', 'saha-core' ),
					'section' => 'content',
				),
				'ratio'  => array(
					'type'    => 'select',
					'label'   => __( 'Tỉ lệ khung', 'saha-core' ),
					'section' => 'style',
					'default' => '16/9',
					'options' => array(
						'16/9' => '16:9',
						'4/3'  => '4:3',
						'1/1'  => '1:1',
						'9/16' => __( '9:16 (dọc)', 'saha-core' ),
					),
				),
				'radius' => array(
					'type'    => 'size',
					'label'   => __( 'Bo góc', 'saha-core' ),
					'section' => 'style',
					'units'   => array( 'px' ),
					'min'     => 0,
					'max'     => 40,
				),
			),
		);
	}

	/**
	 * Nhận dạng link.
	 *
	 * @param string $url Link.
	 * @return array{provider: string, id: string, embed: string, watch: string}|null
	 */
	public static function parse( string $url ): ?array {
		$url = trim( $url );

		if ( preg_match( '~^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m ) ) {
			return array(
				'provider' => 'youtube',
				'id'       => $m[1],
				'embed'    => 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?autoplay=1&rel=0',
				'watch'    => 'https://www.youtube.com/watch?v=' . $m[1],
			);
		}

		if ( preg_match( '~^https?://(?:www\.|player\.)?vimeo\.com/(?:video/)?(\d{5,12})~', $url, $m ) ) {
			return array(
				'provider' => 'vimeo',
				'id'       => $m[1],
				'embed'    => 'https://player.vimeo.com/video/' . $m[1] . '?autoplay=1&dnt=1',
				'watch'    => 'https://vimeo.com/' . $m[1],
			);
		}

		if ( preg_match( '~^https?://[^\s"\'<>]+\.(?:mp4|webm)(?:\?[^\s"\'<>]*)?$~i', $url ) ) {
			return array(
				'provider' => 'file',
				'id'       => '',
				'embed'    => $url,
				'watch'    => $url,
			);
		}

		return null;
	}

	/**
	 * Render.
	 *
	 * @param Node          $node    Node.
	 * @param RenderContext $ctx     Ngữ cảnh.
	 * @param string        $content HTML con (không dùng).
	 */
	public function render( Node $node, RenderContext $ctx, string $content ): string {
		$video = self::parse( (string) $this->prop( $node, 'url' ) );

		if ( ! $video ) {
			return $ctx->editor
				? '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-video', 'saha-image--empty' ) ) . '>' . esc_html__( 'Dán link YouTube, Vimeo hoặc file .mp4', 'saha-core' ) . '</div>'
				: '';
		}

		$title  = trim( (string) $this->prop( $node, 'title' ) );
		$poster = $node->prop( 'poster' );
		$pid    = is_array( $poster ) ? (int) ( $poster['id'] ?? 0 ) : 0;
		$style  = '--saha-video-ratio:' . (string) $this->prop( $node, 'ratio' );

		if ( 'file' === $video['provider'] ) {
			$src = $pid > 0 ? (string) wp_get_attachment_image_url( $pid, 'large' ) : '';

			return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-video', 'saha-video--file' ), array( 'style' => $style ) ) . '>'
				. '<video class="saha-video__media" controls preload="none" playsinline' . ( '' !== $src ? ' poster="' . esc_url( $src ) . '"' : '' ) . ( '' !== $title ? ' aria-label="' . esc_attr( $title ) . '"' : '' ) . '>'
				. '<source src="' . esc_url( $video['embed'] ) . '"></video></div>';
		}

		$img = $pid > 0
			? (string) wp_get_attachment_image( $pid, 'large', false, array( 'class' => 'saha-video__poster', 'alt' => '', 'loading' => 'lazy' ) )
			: ( 'youtube' === $video['provider']
				? '<img class="saha-video__poster" src="' . esc_url( 'https://i.ytimg.com/vi/' . $video['id'] . '/hqdefault.jpg' ) . '" alt="" loading="lazy" width="480" height="360">'
				: '' );

		/* translators: %s: tên video */
		$label = sprintf( __( 'Phát video: %s', 'saha-core' ), '' !== $title ? $title : __( 'video', 'saha-core' ) );

		return '<div' . $this->rootAttributes(
			$node,
			$ctx,
			array( 'saha-video', 'saha-video--' . $video['provider'] ),
			array(
				'style'           => $style,
				'data-saha-video' => $video['embed'],
				'data-title'      => '' !== $title ? $title : 'Video',
			)
		) . '>'
			. $img
			. '<a class="saha-video__play" href="' . esc_url( $video['watch'] ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( $label ) . '">' . Icons::svg( 'play' ) . '</a>'
			. '</div>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', '--saha-video-radius', $node->prop( 'radius' ) );
	}
}
