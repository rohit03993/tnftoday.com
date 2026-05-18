<?php
/**
 * Open Graph / social share preview images for TNF content types.
 *
 * @package TNF_News_Platform
 */

if (! defined('ABSPATH')) {
	exit;
}

add_action('wp_head', 'tnf_social_preview_output_meta', 2);
add_filter('wpseo_opengraph_image', 'tnf_social_preview_filter_seo_image', 99);
add_filter('wpseo_twitter_image', 'tnf_social_preview_filter_seo_image', 99);
add_filter('rank_math/opengraph/facebook/image', 'tnf_social_preview_filter_seo_image', 99);
add_filter('rank_math/opengraph/twitter/image', 'tnf_social_preview_filter_seo_image', 99);

/**
 * Post types that receive TNF social preview resolution.
 *
 * @return array<int,string>
 */
function tnf_social_preview_post_types(): array {
	$types = array('tnf_news', 'tnf_pdf_report', 'tnf_video');

	return (array) apply_filters('tnf_social_preview_post_types', $types);
}

/**
 * Whether a URL is suitable for og:image (public, stable, not expiring presigns).
 */
function tnf_social_preview_is_public_image_url(string $url): bool {
	$url = trim($url);
	if ($url === '' || ! wp_http_validate_url($url)) {
		return false;
	}

	$parts = wp_parse_url($url);
	if (! is_array($parts) || empty($parts['host'])) {
		return false;
	}

	$host  = strtolower((string) $parts['host']);
	$query = isset($parts['query']) ? (string) $parts['query'] : '';
	if ($query !== '' && preg_match('/(^|&)(X-Amz-|x-amz-)/', $query)) {
		return false;
	}

	$home = wp_parse_url(home_url('/'));
	if (is_array($home) && ! empty($home['host']) && $host === strtolower((string) $home['host'])) {
		return true;
	}

	if (in_array($host, array('minio', 'fastapi', 'wordpress'), true)) {
		return false;
	}

	$scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : '';
	if ($scheme !== 'http' && $scheme !== 'https') {
		return false;
	}

	return true;
}

/**
 * Site-wide fallback og:image (custom logo, filter, or empty).
 */
function tnf_social_preview_default_image_url(): string {
	$filtered = apply_filters('tnf_social_preview_default_image_url', '');
	if (is_string($filtered) && $filtered !== '' && tnf_social_preview_is_public_image_url($filtered)) {
		return $filtered;
	}

	$logo_id = (int) get_theme_mod('custom_logo');
	if ($logo_id > 0) {
		$logo = wp_get_attachment_image_url($logo_id, 'full');
		if (is_string($logo) && $logo !== '' && tnf_social_preview_is_public_image_url($logo)) {
			return $logo;
		}
	}

	return '';
}

/**
 * Featured image URL when it is crawler-safe.
 */
function tnf_social_preview_featured_image_url(int $post_id): string {
	if ($post_id <= 0 || ! has_post_thumbnail($post_id)) {
		return '';
	}

	foreach (array('large', 'medium_large', 'full') as $size) {
		$url = get_the_post_thumbnail_url($post_id, $size);
		if (is_string($url) && $url !== '' && tnf_social_preview_is_public_image_url($url)) {
			return $url;
		}
	}

	return '';
}

/**
 * Stable REST URL that returns a JPEG of PDF page 1 for social crawlers.
 */
function tnf_pdf_report_page_og_rest_url(int $post_id): string {
	if ($post_id <= 0) {
		return '';
	}

	return rest_url('tnf/v1/pdf-report/' . $post_id . '/page-og');
}

/**
 * Best share image URL for a single post (no clip query params).
 */
function tnf_social_preview_image_url(int $post_id): string {
	$post_id = (int) $post_id;
	if ($post_id <= 0) {
		return '';
	}

	$post = get_post($post_id);
	if (! $post instanceof WP_Post || 'publish' !== $post->post_status) {
		return '';
	}

	if (! in_array($post->post_type, tnf_social_preview_post_types(), true)) {
		return '';
	}

	$featured = tnf_social_preview_featured_image_url($post_id);
	if ($featured !== '') {
		$url = $featured;
	} elseif ('tnf_pdf_report' === $post->post_type) {
		$aid = (int) get_post_meta($post_id, 'tnf_pdf_attachment_id', true);
		if ($aid > 0 && function_exists('tnf_pdf_attachment_preview_image_url')) {
			$preview = tnf_pdf_attachment_preview_image_url($aid);
			if (is_string($preview) && $preview !== '' && tnf_social_preview_is_public_image_url($preview)) {
				$url = $preview;
			} elseif (tnf_pdf_report_can_serve_page_og($post_id)) {
				$url = tnf_pdf_report_page_og_rest_url($post_id);
			} else {
				$url = '';
			}
		} elseif (tnf_pdf_report_can_serve_page_og($post_id)) {
			$url = tnf_pdf_report_page_og_rest_url($post_id);
		} else {
			$url = '';
		}
	} elseif ('tnf_video' === $post->post_type && function_exists('tnf_video_card_thumbnail_url')) {
		$thumb = tnf_video_card_thumbnail_url($post_id);
		$url   = ( is_string($thumb) && $thumb !== '' && tnf_social_preview_is_public_image_url($thumb) ) ? $thumb : '';
	} else {
		$url = '';
	}

	if ($url === '' || ! tnf_social_preview_is_public_image_url($url)) {
		$url = tnf_social_preview_default_image_url();
	}

	$url = (string) apply_filters('tnf_social_preview_image_url', $url, $post_id, $post);

	return ( is_string($url) && tnf_social_preview_is_public_image_url($url) ) ? $url : '';
}

/**
 * Share image for the current front-end request (clip URLs take priority).
 */
function tnf_social_preview_image_url_for_request(): string {
	if (is_admin()) {
		return '';
	}

	if (function_exists('tnf_epaper_clip_og_url_for_request')) {
		$clip = tnf_epaper_clip_og_url_for_request();
		if ($clip !== '') {
			return $clip;
		}
	}

	if (! is_singular(tnf_social_preview_post_types())) {
		return '';
	}

	$post_id = (int) get_queried_object_id();

	return tnf_social_preview_image_url($post_id);
}

/**
 * Yoast / Rank Math: prefer TNF-resolved image when plugin URL is missing or not crawler-safe.
 *
 * @param string $url Default image from SEO plugin.
 */
function tnf_social_preview_filter_seo_image($url): string {
	$ours = tnf_social_preview_image_url_for_request();
	if ($ours === '') {
		return is_string($url) ? $url : '';
	}

	$url = is_string($url) ? trim($url) : '';
	if ($url === '' || ! tnf_social_preview_is_public_image_url($url)) {
		return $ours;
	}

	if (function_exists('tnf_epaper_clip_og_url_for_request') && tnf_epaper_clip_og_url_for_request() !== '') {
		return $ours;
	}

	if (is_singular('tnf_pdf_report')) {
		return $ours;
	}

	return $url;
}

/**
 * Output og:image meta when no SEO plugin handles social tags.
 */
function tnf_social_preview_output_meta(): void {
	if (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION')) {
		return;
	}

	$url = tnf_social_preview_image_url_for_request();
	if ($url === '') {
		return;
	}

	$post_id = (int) get_queried_object_id();
	$title   = $post_id > 0 ? get_the_title($post_id) : '';

	echo '<meta property="og:image" content="' . esc_url($url) . "\" />\n";
	echo '<meta property="og:image:secure_url" content="' . esc_url($url) . "\" />\n";
	echo '<meta name="twitter:image" content="' . esc_url($url) . "\" />\n";
	echo "<meta name=\"twitter:card\" content=\"summary_large_image\" />\n";
	if ($title !== '') {
		echo '<meta property="og:image:alt" content="' . esc_attr($title) . "\" />\n";
	}

	if ($post_id > 0 && is_singular(tnf_social_preview_post_types())) {
		$permalink = get_permalink($post_id);
		$excerpt   = get_the_excerpt($post_id);
		if ($title !== '') {
			echo '<meta property="og:title" content="' . esc_attr($title) . "\" />\n";
			echo '<meta name="twitter:title" content="' . esc_attr($title) . "\" />\n";
		}
		if (is_string($excerpt) && $excerpt !== '') {
			echo '<meta property="og:description" content="' . esc_attr(wp_strip_all_tags($excerpt)) . "\" />\n";
			echo '<meta name="twitter:description" content="' . esc_attr(wp_strip_all_tags($excerpt)) . "\" />\n";
		}
		if (is_string($permalink) && $permalink !== '') {
			echo '<meta property="og:url" content="' . esc_url($permalink) . "\" />\n";
		}
		echo "<meta property=\"og:type\" content=\"article\" />\n";
	}
}

/**
 * Plugin branded fallback image path (used when PDF pages are not ready yet).
 */
function tnf_social_preview_brand_asset_path(): string {
	$rel = 'assets/images/tnf-clip-masthead.png';
	$abs = TNF_NEWS_PLATFORM_PATH . $rel;

	return ( is_readable($abs) && is_file($abs) ) ? $abs : '';
}

/**
 * Cache key segment for page-og JPEG generation.
 */
function tnf_pdf_report_page_og_cache_key(int $post_id, string $source): string {
	return hash('sha256', 'page-og|' . (string) $post_id . '|' . $source);
}

/**
 * Read/write cached page-og JPEG bytes.
 *
 * @return string|null Cached bytes or null on miss.
 */
function tnf_pdf_report_page_og_cache_read(int $post_id, string $source): ?string {
	$upload = wp_upload_dir();
	if (! empty($upload['error'])) {
		return null;
	}

	$cache_dir  = trailingslashit($upload['basedir']) . 'tnf-epaper-page-og-cache';
	$cache_file = trailingslashit($cache_dir) . tnf_pdf_report_page_og_cache_key($post_id, $source) . '.jpg';
	if (! is_readable($cache_file) || ( time() - (int) filemtime($cache_file) ) >= 7 * DAY_IN_SECONDS) {
		return null;
	}

	$cached = file_get_contents($cache_file);

	return ( is_string($cached) && $cached !== '' ) ? $cached : null;
}

/**
 * @param string $source Cache key segment.
 * @param string $bytes  JPEG bytes.
 */
function tnf_pdf_report_page_og_cache_write(int $post_id, string $source, string $bytes): void {
	$upload = wp_upload_dir();
	if (! empty($upload['error']) || $bytes === '') {
		return;
	}

	$cache_dir = trailingslashit($upload['basedir']) . 'tnf-epaper-page-og-cache';
	if (! wp_mkdir_p($cache_dir)) {
		return;
	}

	$cache_file = trailingslashit($cache_dir) . tnf_pdf_report_page_og_cache_key($post_id, $source) . '.jpg';
	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	@file_put_contents($cache_file, $bytes);
}

/**
 * Resize image editor output for social crawlers (WhatsApp, Facebook).
 *
 * @param WP_Image_Editor $editor Image editor instance.
 */
function tnf_social_preview_prepare_editor($editor): void {
	if (! is_object($editor) || ! method_exists($editor, 'set_quality')) {
		return;
	}

	$editor->set_quality(85);

	$size = $editor->get_size();
	if (! is_array($size) || empty($size['width']) || empty($size['height'])) {
		return;
	}

	$w = (int) $size['width'];
	$h = (int) $size['height'];
	if (max($w, $h) > 1200) {
		$editor->resize(1200, 1200, false);
	} elseif (min($w, $h) > 0 && min($w, $h) < 200) {
		$scale = 200 / min($w, $h);
		$nw    = (int) min(1200, ceil($w * $scale));
		$nh    = (int) min(1200, ceil($h * $scale));
		$editor->resize($nw, $nh, false);
	}
}

/**
 * Convert a local image file to optimized JPEG bytes.
 *
 * @return string|WP_Error
 */
function tnf_social_preview_jpeg_bytes_from_file(string $path) {
	if ($path === '' || ! is_readable($path)) {
		return new WP_Error('missing', __('Image file not found', 'tnf-news-platform'), array('status' => 404));
	}

	$editor = wp_get_image_editor($path);
	if (is_wp_error($editor)) {
		return $editor;
	}

	tnf_social_preview_prepare_editor($editor);

	$out_path = trailingslashit(get_temp_dir()) . 'tnf-og-' . wp_generate_password(16, false, false) . '.jpg';
	$saved    = $editor->save($out_path, 'image/jpeg');
	if (is_wp_error($saved)) {
		return $saved;
	}

	$file = isset($saved['path']) ? (string) $saved['path'] : '';
	if ($file === '' || ! is_readable($file)) {
		return new WP_Error('save', __('Could not save preview image', 'tnf-news-platform'), array('status' => 500));
	}

	$bytes = file_get_contents($file);
	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	@unlink($file);

	return ( is_string($bytes) && $bytes !== '' ) ? $bytes : new WP_Error('read', __('Could not read preview image', 'tnf-news-platform'), array('status' => 500));
}

/**
 * Download a remote image URL and return JPEG bytes for og:image consumers.
 *
 * @return string|WP_Error
 */
function tnf_social_preview_jpeg_bytes_from_url(string $url) {
	$url = trim($url);
	if ($url === '') {
		return new WP_Error('missing', __('Image URL missing', 'tnf-news-platform'), array('status' => 404));
	}

	$parts = wp_parse_url($url);
	$home  = wp_parse_url(home_url('/'));
	if (is_array($parts) && is_array($home) && ! empty($parts['host']) && ! empty($home['host'])
		&& strtolower((string) $parts['host']) === strtolower((string) $home['host'])
		&& ! empty($parts['path'])
	) {
		$rel = ltrim((string) $parts['path'], '/');
		if (str_starts_with($rel, 'wp-content/uploads/')) {
			$upload = wp_upload_dir();
			$local  = trailingslashit($upload['basedir']) . substr($rel, strlen('wp-content/uploads/'));
			if (is_readable($local)) {
				return tnf_social_preview_jpeg_bytes_from_file($local);
			}
		}
	}

	$tmp = download_url(esc_url_raw($url), 60);
	if (is_wp_error($tmp)) {
		return $tmp;
	}

	$bytes = tnf_social_preview_jpeg_bytes_from_file($tmp);
	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	@unlink($tmp);

	return $bytes;
}

/**
 * JPEG bytes from the post featured image attachment.
 *
 * @return string|WP_Error
 */
function tnf_social_preview_jpeg_bytes_from_featured(int $post_id) {
	$thumb_id = (int) get_post_thumbnail_id($post_id);
	if ($thumb_id <= 0) {
		return new WP_Error('no_thumb', __('No featured image', 'tnf-news-platform'), array('status' => 404));
	}

	$path = get_attached_file($thumb_id);
	if (! is_string($path) || $path === '' || ! is_readable($path)) {
		$url = wp_get_attachment_image_url($thumb_id, 'large');
		if (is_string($url) && $url !== '') {
			return tnf_social_preview_jpeg_bytes_from_url($url);
		}

		return new WP_Error('no_thumb', __('No featured image', 'tnf-news-platform'), array('status' => 404));
	}

	return tnf_social_preview_jpeg_bytes_from_file($path);
}

/**
 * Whether page-og endpoint can return a real image (never JSON) for crawlers.
 */
function tnf_pdf_report_can_serve_page_og(int $post_id): bool {
	$post_id = (int) $post_id;
	if ($post_id <= 0) {
		return false;
	}

	if (has_post_thumbnail($post_id)) {
		return true;
	}

	if (function_exists('tnf_pdf_report_viewer_pages') && tnf_pdf_report_viewer_pages($post_id) !== array()) {
		return true;
	}

	$aid = (int) get_post_meta($post_id, 'tnf_pdf_attachment_id', true);
	if ($aid > 0 && function_exists('tnf_pdf_attachment_preview_image_url')) {
		$preview = tnf_pdf_attachment_preview_image_url($aid);
		if (is_string($preview) && $preview !== '') {
			return true;
		}
	}

	if (tnf_social_preview_default_image_url() !== '' || tnf_social_preview_brand_asset_path() !== '') {
		return true;
	}

	return false;
}

/**
 * Build or read cached JPEG for PDF social sharing (WhatsApp / Facebook crawlers).
 *
 * Order: rendered page 1 → featured image → PDF preview → site logo → plugin brand asset.
 *
 * @return string|WP_Error Raw JPEG bytes.
 */
function tnf_pdf_report_build_page_og_jpeg(int $post_id) {
	$post_id = (int) $post_id;
	if ($post_id <= 0) {
		return new WP_Error('bad_id', __('Invalid post', 'tnf-news-platform'), array('status' => 400));
	}

	$sources = array();

	if (function_exists('tnf_pdf_report_viewer_pages')) {
		$pages = tnf_pdf_report_viewer_pages($post_id);
		if ($pages !== array() && ! empty($pages[0]['url'])) {
			$sources[] = array(
				'key' => 'page:' . (string) $pages[0]['url'],
				'run' => static function () use ($pages) {
					return tnf_social_preview_jpeg_bytes_from_url((string) $pages[0]['url']);
				},
			);
		}
	}

	if (has_post_thumbnail($post_id)) {
		$sources[] = array(
			'key' => 'featured:' . (string) get_post_thumbnail_id($post_id),
			'run' => static function () use ($post_id) {
				return tnf_social_preview_jpeg_bytes_from_featured($post_id);
			},
		);
	}

	$aid = (int) get_post_meta($post_id, 'tnf_pdf_attachment_id', true);
	if ($aid > 0 && function_exists('tnf_pdf_attachment_preview_image_url')) {
		$preview = tnf_pdf_attachment_preview_image_url($aid);
		if (is_string($preview) && $preview !== '') {
			$sources[] = array(
				'key' => 'pdf-preview:' . $preview,
				'run' => static function () use ($preview) {
					return tnf_social_preview_jpeg_bytes_from_url($preview);
				},
			);
		}
	}

	$default_url = tnf_social_preview_default_image_url();
	if ($default_url !== '') {
		$sources[] = array(
			'key' => 'default:' . $default_url,
			'run' => static function () use ($default_url) {
				return tnf_social_preview_jpeg_bytes_from_url($default_url);
			},
		);
	}

	$brand = tnf_social_preview_brand_asset_path();
	if ($brand !== '') {
		$sources[] = array(
			'key' => 'brand:' . $brand,
			'run' => static function () use ($brand) {
				return tnf_social_preview_jpeg_bytes_from_file($brand);
			},
		);
	}

	foreach ($sources as $source) {
		$key = (string) $source['key'];
		$cached = tnf_pdf_report_page_og_cache_read($post_id, $key);
		if (is_string($cached)) {
			return $cached;
		}

		$bytes = call_user_func($source['run']);
		if (is_string($bytes) && $bytes !== '') {
			tnf_pdf_report_page_og_cache_write($post_id, $key, $bytes);

			return $bytes;
		}
	}

	return new WP_Error('no_preview', __('Could not build share preview image', 'tnf-news-platform'), array('status' => 503));
}
