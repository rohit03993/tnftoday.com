<?php
/**
 * Open Graph / social share preview images for news, PDF, and video.
 *
 * @package TNF_News_Platform
 */

if (! defined('ABSPATH')) {
	exit;
}

add_action('template_redirect', 'tnf_social_preview_prewarm_on_view', 5);
add_action('wp_head', 'tnf_social_preview_output_meta', 1);
add_action('save_post', 'tnf_social_preview_prewarm_on_save', 99, 3);
add_filter('wpseo_opengraph_image', 'tnf_social_preview_filter_seo_image', 999);
add_filter('wpseo_twitter_image', 'tnf_social_preview_filter_seo_image', 999);
add_filter('rank_math/opengraph/facebook/image', 'tnf_social_preview_filter_seo_image', 999);
add_filter('rank_math/opengraph/twitter/image', 'tnf_social_preview_filter_seo_image', 999);
add_filter('rest_post_dispatch', 'tnf_rest_og_allow_crawler_headers', 15, 3);
add_filter('oembed_response_data', 'tnf_social_preview_oembed_thumbnail', 10, 4);

/**
 * WhatsApp / Facebook will not use og:image URLs marked noindex (default on all REST routes).
 *
 * @param WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response Response.
 * @param WP_REST_Server                                $server   Server.
 * @param WP_REST_Request                               $request  Request.
 * @return WP_REST_Response|WP_HTTP_Response|WP_Error|mixed
 */
function tnf_rest_og_allow_crawler_headers($response, WP_REST_Server $server, WP_REST_Request $request) {
	unset($server);
	if (! ( $response instanceof WP_REST_Response )) {
		return $response;
	}

	$route = (string) $request->get_route();
	if (! preg_match('#^/tnf/v1/pdf-report/\d+/(?:page-og|clip-og)$#', $route)) {
		return $response;
	}

	$response->remove_header('X-Robots-Tag');

	return $response;
}

/**
 * Post types that receive TNF social preview resolution (news, PDF, video).
 *
 * @return array<int,string>
 */
function tnf_social_preview_post_types(): array {
	$news = function_exists('tnf_listing_news_post_types')
		? tnf_listing_news_post_types()
		: array('tnf_news', 'post');

	$types = array_values(
		array_unique(
			array_merge(
				$news,
				array('tnf_pdf_report', 'tnf_video')
			)
		)
	);

	return (array) apply_filters('tnf_social_preview_post_types', $types);
}

/**
 * News article types only (tnf_news + core post).
 *
 * @return array<int,string>
 */
function tnf_social_preview_news_post_types(): array {
	$all = tnf_social_preview_post_types();

	return array_values(
		array_filter(
			$all,
			static function (string $type): bool {
				return $type !== 'tnf_pdf_report' && $type !== 'tnf_video';
			}
		)
	);
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

	// WhatsApp / Facebook skip wp-json URLs (REST sends X-Robots-Tag: noindex).
	$path = isset($parts['path']) ? strtolower((string) $parts['path']) : '';
	if (str_contains($path, '/wp-json/') || str_contains($path, 'wp-json')) {
		return false;
	}

	return true;
}

/**
 * True when a URL must never be used as og:image (REST API endpoints).
 */
function tnf_social_preview_is_rest_image_url(string $url): bool {
	$url = trim($url);
	if ($url === '') {
		return false;
	}

	$parts = wp_parse_url($url);
	if (! is_array($parts) || empty($parts['path'])) {
		return false;
	}

	$path = strtolower((string) $parts['path']);

	return str_contains($path, '/wp-json/') || preg_match('#/pdf-report/\d+/(?:page-og|clip-og)#', $path) === 1;
}

/**
 * Site-wide fallback og:image (explicit filter only — never the theme custom logo).
 */
function tnf_social_preview_default_image_url(): string {
	$filtered = apply_filters('tnf_social_preview_default_image_url', '');
	if (is_string($filtered) && $filtered !== '' && tnf_social_preview_is_public_image_url($filtered)) {
		return $filtered;
	}

	return '';
}

/** WhatsApp drops previews when og:image is larger than ~300 KB. */
const TNF_SOCIAL_PREVIEW_MAX_IMAGE_BYTES = 300000;

/**
 * File size in bytes for a local attachment (0 if unknown).
 */
function tnf_social_preview_attachment_bytes(int $attachment_id): int {
	if ($attachment_id <= 0) {
		return 0;
	}

	$path = get_attached_file($attachment_id);
	if (! is_string($path) || $path === '' || ! is_readable($path)) {
		return 0;
	}

	return (int) filesize($path);
}

/**
 * Featured image URL when it is crawler-safe (prefers sizes under WhatsApp limit).
 */
function tnf_social_preview_featured_image_url(int $post_id): string {
	if ($post_id <= 0 || ! has_post_thumbnail($post_id)) {
		return '';
	}

	$thumb_id = (int) get_post_thumbnail_id($post_id);
	$sizes    = array('medium_large', 'large', 'full');

	foreach ($sizes as $size) {
		$url = get_the_post_thumbnail_url($post_id, $size);
		if (! is_string($url) || $url === '' || ! tnf_social_preview_is_public_image_url($url)) {
			continue;
		}

		if ('full' === $size && tnf_social_preview_attachment_bytes($thumb_id) > TNF_SOCIAL_PREVIEW_MAX_IMAGE_BYTES) {
			continue;
		}

		return $url;
	}

	return '';
}

/**
 * Filename for the social og JPEG (hash changes when image changes — busts WhatsApp cache).
 */
function tnf_social_preview_og_basename(int $post_id, string $sig = ''): string {
	$post_id = (int) $post_id;
	if ($sig === '') {
		$sig = tnf_social_preview_content_sig($post_id);
	}

	$hash = substr(md5($sig !== '' ? $sig : 'post|' . $post_id), 0, 8);

	return 'tnf-og-' . $post_id . '-' . $hash . '.jpg';
}

/**
 * Paths for a persisted WhatsApp-friendly JPEG under uploads (not wp-json).
 *
 * @return array{file: string, url: string}|null
 */
function tnf_social_preview_og_upload_paths(int $post_id): ?array {
	$post_id = (int) $post_id;
	if ($post_id <= 0) {
		return null;
	}

	$upload = wp_upload_dir();
	if (! empty($upload['error'])) {
		return null;
	}

	$dir      = trailingslashit($upload['basedir']) . 'tnf-social-og';
	$basename = tnf_social_preview_og_basename($post_id);

	return array(
		'file' => trailingslashit($dir) . $basename,
		'url'  => trailingslashit($upload['baseurl']) . 'tnf-social-og/' . $basename,
	);
}

/**
 * @deprecated Use tnf_social_preview_og_upload_paths()
 */
function tnf_pdf_report_social_og_upload_paths(int $post_id): ?array {
	return tnf_social_preview_og_upload_paths($post_id);
}

/**
 * Cache-bust signature when featured image, embed, or PDF changes.
 */
function tnf_social_preview_content_sig(int $post_id): string {
	$post_id = (int) $post_id;
	$post    = get_post($post_id);
	if (! $post instanceof WP_Post) {
		return '';
	}

	if ('tnf_pdf_report' === $post->post_type) {
		$pdf_sig = (string) get_post_meta($post_id, '_tnf_pdf_last_sig', true);

		return $pdf_sig !== '' ? $pdf_sig : md5('pdf|' . $post_id . '|' . (string) $post->post_modified_gmt);
	}

	$thumb_id = (int) get_post_thumbnail_id($post_id);
	if ($thumb_id > 0 && ! tnf_social_preview_is_site_logo_attachment($thumb_id)) {
		$path  = get_attached_file($thumb_id);
		$mtime = ( is_string($path) && $path !== '' && is_readable($path) ) ? (string) filemtime($path) : (string) time();

		return md5('thumb|' . $thumb_id . '|' . $mtime);
	}

	$embed = (string) get_post_meta($post_id, 'tnf_embed_url', true);
	if ($embed !== '' && function_exists('tnf_youtube_id_from_url') && tnf_youtube_id_from_url($embed) !== '') {
		return md5('yt|' . $embed);
	}

	return md5('post|' . $post_id . '|' . (string) $post->post_modified_gmt);
}

/**
 * Delete cached social og JPEG so the next view rebuilds it.
 */
function tnf_social_preview_bust_cached_image(int $post_id): void {
	$post_id = (int) $post_id;
	if ($post_id <= 0) {
		return;
	}

	delete_post_meta($post_id, '_tnf_social_og_sig');

	$upload = wp_upload_dir();
	if (! empty($upload['error'])) {
		return;
	}

	$dir = trailingslashit($upload['basedir']) . 'tnf-social-og';
	if (! is_dir($dir)) {
		return;
	}

	$patterns = array(
		$dir . '/tnf-og-' . $post_id . '-*.jpg',
		$dir . '/tnf-og-' . $post_id . '.jpg',
	);

	foreach ($patterns as $pattern) {
		$matches = glob($pattern);
		if (! is_array($matches)) {
			continue;
		}
		foreach ($matches as $file) {
			if (is_string($file) && is_file($file)) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				@unlink($file);
			}
		}
	}
}

/**
 * Regenerate share image when a published article is saved.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post.
 * @param bool    $update  Update flag.
 */
function tnf_social_preview_prewarm_on_save($post_id, $post, $update): void {
	unset($update);

	$post_id = (int) $post_id;
	if ($post_id <= 0 || wp_is_post_revision($post_id) || ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )) {
		return;
	}

	if (! $post instanceof WP_Post) {
		$post = get_post($post_id);
	}

	if (! $post instanceof WP_Post || 'publish' !== $post->post_status) {
		return;
	}

	if (! in_array($post->post_type, tnf_social_preview_post_types(), true)) {
		return;
	}

	tnf_social_preview_bust_cached_image($post_id);
	tnf_social_preview_og_public_url($post_id);
}

/**
 * Build the social og JPEG before wp_head (so crawlers always get a file URL).
 */
function tnf_social_preview_prewarm_on_view(): void {
	if (is_admin() || ! is_singular(tnf_social_preview_post_types())) {
		return;
	}

	$post_id = (int) get_queried_object_id();
	if ($post_id <= 0) {
		return;
	}

	tnf_social_preview_og_public_url($post_id);
}

/**
 * Cached social JPEG must be WhatsApp-sized (reject old logo-sized 641×337 files).
 */
function tnf_social_preview_og_file_is_whatsapp_ready(string $file): bool {
	if ($file === '' || ! is_readable($file)) {
		return false;
	}

	$info = function_exists('wp_getimagesize') ? wp_getimagesize($file) : @getimagesize($file);
	if (! is_array($info) || empty($info[0]) || empty($info[1])) {
		return false;
	}

	$w = (int) $info[0];
	$h = (int) $info[1];

	// Cropped output must be ~1200×630; logo fallback was ~641×337.
	if ($w < 1000 || $h < 500) {
		return false;
	}

	$ratio = $w / max(1, $h);

	return $ratio >= 1.5 && $ratio <= 2.2;
}

/**
 * Public URL for a social og file, with cache-bust query arg for WhatsApp.
 */
function tnf_social_preview_og_public_url_with_bust(string $url, string $file): string {
	if ($url === '' || $file === '' || ! is_readable($file)) {
		return $url;
	}

	return add_query_arg('v', (string) (int) filemtime($file), $url);
}

/**
 * Write JPEG bytes to the social-og upload path when valid for WhatsApp.
 */
function tnf_social_preview_write_og_upload_file(int $post_id, string $jpeg): string {
	$post_id = (int) $post_id;
	if ($post_id <= 0 || $jpeg === '') {
		return '';
	}

	$paths = tnf_social_preview_og_upload_paths($post_id);
	if ($paths === null) {
		return '';
	}

	$dir = dirname($paths['file']);
	if (! wp_mkdir_p($dir)) {
		return '';
	}

	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	if (false === @file_put_contents($paths['file'], $jpeg)) {
		return '';
	}

	if (! tnf_social_preview_og_file_is_whatsapp_ready($paths['file'])) {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@unlink($paths['file']);

		return '';
	}

	$sig = tnf_social_preview_content_sig($post_id);
	if ($sig !== '') {
		update_post_meta($post_id, '_tnf_social_og_sig', $sig);
	}

	if (! tnf_social_preview_is_public_image_url($paths['url'])) {
		return '';
	}

	return tnf_social_preview_og_public_url_with_bust($paths['url'], $paths['file']);
}

/**
 * Crop the post featured image to 1200×630 (most reliable on production).
 */
function tnf_social_preview_jpeg_from_featured_attachment(int $post_id) {
	$post_id = (int) $post_id;
	$thumb_id = (int) get_post_thumbnail_id($post_id);
	if ($thumb_id <= 0 || tnf_social_preview_is_site_logo_attachment($thumb_id)) {
		return new WP_Error('no_thumb', __('No usable featured image', 'tnf-news-platform'), array('status' => 404));
	}

	$path = get_attached_file($thumb_id);
	if (is_string($path) && $path !== '' && is_readable($path)) {
		return tnf_social_preview_jpeg_bytes_from_file($path);
	}

	return tnf_social_preview_jpeg_bytes_from_featured($post_id);
}

/**
 * Build and save og:image under uploads; return public HTTPS URL for WhatsApp.
 */
function tnf_social_preview_og_public_url(int $post_id): string {
	$post_id = (int) $post_id;
	if ($post_id <= 0) {
		return '';
	}

	$paths = tnf_social_preview_og_upload_paths($post_id);
	if ($paths === null) {
		return '';
	}

	$sig        = tnf_social_preview_content_sig($post_id);
	$stored_sig = (string) get_post_meta($post_id, '_tnf_social_og_sig', true);

	if (is_readable($paths['file']) && ! tnf_social_preview_og_file_is_whatsapp_ready($paths['file'])) {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@unlink($paths['file']);
		delete_post_meta($post_id, '_tnf_social_og_sig');
	}

	$have_fresh = is_readable($paths['file'])
		&& tnf_social_preview_og_file_is_whatsapp_ready($paths['file'])
		&& ( time() - (int) filemtime($paths['file']) ) < 7 * DAY_IN_SECONDS
		&& ( $sig === '' || $stored_sig === $sig );

	if ($have_fresh && tnf_social_preview_is_public_image_url($paths['url'])) {
		return tnf_social_preview_og_public_url_with_bust($paths['url'], $paths['file']);
	}

	// 1) Featured file on disk → 1200×630 (works even when PDF worker pages are missing).
	if (has_post_thumbnail($post_id)) {
		$from_feat = tnf_social_preview_jpeg_from_featured_attachment($post_id);
		if (! is_wp_error($from_feat) && is_string($from_feat) && $from_feat !== '') {
			$written = tnf_social_preview_write_og_upload_file($post_id, $from_feat);
			if ($written !== '') {
				return $written;
			}
		}
	}

	// 2) Download featured from public URL (when local path missing in container).
	$feat_url = tnf_social_preview_featured_content_url($post_id);
	if ($feat_url !== '') {
		$from_url = tnf_social_preview_jpeg_bytes_from_url($feat_url);
		if (! is_wp_error($from_url) && is_string($from_url) && $from_url !== '') {
			$written = tnf_social_preview_write_og_upload_file($post_id, $from_url);
			if ($written !== '') {
				return $written;
			}
		}
	}

	// 3) YouTube poster for news/video embeds (no featured image).
	$yt_url = tnf_social_preview_youtube_poster_url($post_id);
	if ($yt_url !== '') {
		$from_yt = tnf_social_preview_jpeg_bytes_from_url($yt_url);
		if (! is_wp_error($from_yt) && is_string($from_yt) && $from_yt !== '') {
			$written = tnf_social_preview_write_og_upload_file($post_id, $from_yt);
			if ($written !== '') {
				return $written;
			}
		}
	}

	// 4) PDF page manifest / previews only.
	$post = get_post($post_id);
	if ($post instanceof WP_Post && 'tnf_pdf_report' === $post->post_type && function_exists('tnf_pdf_report_build_page_og_jpeg')) {
		$jpeg = tnf_pdf_report_build_page_og_jpeg($post_id);
		if (! is_wp_error($jpeg) && is_string($jpeg) && $jpeg !== '') {
			$written = tnf_social_preview_write_og_upload_file($post_id, $jpeg);
			if ($written !== '') {
				return $written;
			}
		}
	}

	return '';
}

/**
 * @deprecated Use tnf_social_preview_og_public_url()
 */
function tnf_pdf_report_social_og_public_url(int $post_id): string {
	return tnf_social_preview_og_public_url($post_id);
}

/**
 * Normalize an image URL for comparison (strip query, lowercase host).
 */
function tnf_social_preview_normalize_image_url(string $url): string {
	$url = trim($url);
	if ($url === '') {
		return '';
	}

	$parts = wp_parse_url($url);
	if (! is_array($parts) || empty($parts['path'])) {
		return $url;
	}

	$path = strtolower((string) $parts['path']);

	return (string) ( $parts['scheme'] ?? 'https' ) . '://' . strtolower((string) ( $parts['host'] ?? '' )) . $path;
}

/**
 * Whether a URL is the theme/site logo (Yoast default), not article content.
 */
function tnf_social_preview_url_is_site_branding(string $url): bool {
	$url = trim($url);
	if ($url === '') {
		return false;
	}

	$norm = tnf_social_preview_normalize_image_url($url);
	if (preg_match('#/(custom-logo|site-logo|tnf-today-logo|cropped-cropped-tnf-today-logo)#i', $norm)) {
		return true;
	}

	$logo_id = (int) get_theme_mod('custom_logo');
	if ($logo_id > 0) {
		foreach (array('full', 'large', 'medium', 'thumbnail') as $size) {
			$logo_url = wp_get_attachment_image_url($logo_id, $size);
			if (is_string($logo_url) && $logo_url !== ''
				&& tnf_social_preview_normalize_image_url($logo_url) === $norm) {
				return true;
			}
		}
	}

	// Site-wide masthead strip (wide banner), not per-article photos.
	if (preg_match('#/tnf-today\.jpe?g$#i', $norm) || preg_match('#/tnf-today-[0-9]+x[0-9]+\.jpe?g$#i', $norm)) {
		return true;
	}

	return false;
}

/**
 * Whether the featured image is the site logo (not e-paper page 1).
 */
function tnf_social_preview_is_site_logo_attachment(int $attachment_id): bool {
	$attachment_id = (int) $attachment_id;
	if ($attachment_id <= 0) {
		return false;
	}

	$logo_id = (int) get_theme_mod('custom_logo');

	return $logo_id > 0 && $logo_id === $attachment_id;
}

/**
 * Featured image URL for any post (not the site logo).
 */
function tnf_social_preview_featured_content_url(int $post_id): string {
	if ($post_id <= 0 || ! has_post_thumbnail($post_id)) {
		return '';
	}

	$thumb_id = (int) get_post_thumbnail_id($post_id);
	if (tnf_social_preview_is_site_logo_attachment($thumb_id)) {
		return '';
	}

	return tnf_social_preview_featured_image_url($post_id);
}

/**
 * @deprecated Use tnf_social_preview_featured_content_url()
 */
function tnf_social_preview_pdf_featured_content_url(int $post_id): string {
	return tnf_social_preview_featured_content_url($post_id);
}

/**
 * YouTube poster URL from tnf_embed_url meta (news + video).
 */
function tnf_social_preview_youtube_poster_url(int $post_id): string {
	if ($post_id <= 0 || ! function_exists('tnf_youtube_id_from_url')) {
		return '';
	}

	$embed = (string) get_post_meta($post_id, 'tnf_embed_url', true);
	$yt    = tnf_youtube_id_from_url($embed);
	if ($yt === '') {
		return '';
	}

	foreach (array('maxresdefault', 'hqdefault') as $size) {
		$url = 'https://i.ytimg.com/vi/' . $yt . '/' . $size . '.jpg';
		if (tnf_social_preview_is_public_image_url($url)) {
			return $url;
		}
	}

	return '';
}

/**
 * Cropped 1200×630 share image (all post types).
 */
function tnf_social_preview_cropped_share_image_url(int $post_id): string {
	if ($post_id <= 0) {
		return '';
	}

	if (has_post_thumbnail($post_id) && tnf_social_preview_is_site_logo_attachment((int) get_post_thumbnail_id($post_id))) {
		return '';
	}

	return tnf_social_preview_og_public_url($post_id);
}

/**
 * Share image for news articles (tnf_news + post).
 */
function tnf_social_preview_news_image_url(int $post_id): string {
	$post_id = (int) $post_id;
	if ($post_id <= 0) {
		return '';
	}

	$cropped = tnf_social_preview_cropped_share_image_url($post_id);
	if ($cropped !== '') {
		return $cropped;
	}

	// Raw tall featured URLs look like the logo in WhatsApp — only cropped uploads or YouTube.
	return tnf_social_preview_youtube_poster_url($post_id);
}

/**
 * Share image for PDF reports.
 */
function tnf_social_preview_pdf_image_url(int $post_id): string {
	if ($post_id <= 0) {
		return '';
	}

	return tnf_social_preview_cropped_share_image_url($post_id);
}

/**
 * Share image for videos.
 */
function tnf_social_preview_video_image_url(int $post_id): string {
	if ($post_id <= 0) {
		return '';
	}

	$cropped = tnf_social_preview_cropped_share_image_url($post_id);
	if ($cropped !== '') {
		return $cropped;
	}

	if (function_exists('tnf_video_card_thumbnail_url')) {
		$thumb = tnf_video_card_thumbnail_url($post_id);
		if (is_string($thumb) && $thumb !== '' && tnf_social_preview_is_public_image_url($thumb)) {
			return $thumb;
		}
	}

	return tnf_social_preview_youtube_poster_url($post_id);
}

/**
 * Legacy REST page-og URL — do not use in og:image (WhatsApp ignores wp-json).
 *
 * @deprecated Use tnf_social_preview_og_public_url() (uploads JPEG).
 */
function tnf_pdf_report_page_og_rest_url(int $post_id): string {
	return tnf_social_preview_og_public_url((int) $post_id);
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

	if ('tnf_pdf_report' === $post->post_type) {
		$url = tnf_social_preview_pdf_image_url($post_id);
	} elseif ('tnf_video' === $post->post_type) {
		$url = tnf_social_preview_video_image_url($post_id);
	} elseif (in_array($post->post_type, tnf_social_preview_news_post_types(), true)) {
		$url = tnf_social_preview_news_image_url($post_id);
	} else {
		$url = tnf_social_preview_cropped_share_image_url($post_id);
		if ($url === '') {
			$url = tnf_social_preview_featured_content_url($post_id);
		}
	}

	if ($url === '' || ! tnf_social_preview_is_public_image_url($url)) {
		$url = tnf_social_preview_default_image_url();
	}

	$url = (string) apply_filters('tnf_social_preview_image_url', $url, $post_id, $post);

	if (tnf_social_preview_is_rest_image_url($url)) {
		$url = tnf_social_preview_og_public_url($post_id);
	}

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
		if ($clip !== '' && ! tnf_social_preview_is_rest_image_url($clip) && tnf_social_preview_is_public_image_url($clip)) {
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
		$url = is_string($url) ? trim($url) : '';
		if ($url !== '' && tnf_social_preview_url_is_site_branding($url)) {
			return '';
		}

		return $url;
	}

	// Always beat Yoast/Rank Math site-default logo on news, posts, PDF, and video singles.
	if (is_singular(tnf_social_preview_post_types())) {
		return $ours;
	}

	$url = is_string($url) ? trim($url) : '';
	if ($url === '' || ! tnf_social_preview_is_public_image_url($url) || tnf_social_preview_url_is_site_branding($url)) {
		return $ours;
	}

	return $url;
}

/**
 * oEmbed thumbnail (some apps use this instead of og:image).
 *
 * @param array<string,mixed> $data   oEmbed data.
 * @param WP_Post             $post   Post object.
 * @param int                 $width  Requested width.
 * @param int                 $height Requested height.
 * @return array<string,mixed>
 */
function tnf_social_preview_oembed_thumbnail(array $data, $post, $width, $height): array {
	unset($width, $height);

	if (! $post instanceof WP_Post || ! in_array($post->post_type, tnf_social_preview_post_types(), true)) {
		return $data;
	}

	$url = tnf_social_preview_image_url((int) $post->ID);
	if ($url === '') {
		return $data;
	}

	$data['thumbnail_url']    = $url;
	$data['thumbnail_width']  = 1200;
	$data['thumbnail_height'] = 630;

	return $data;
}

/**
 * Description text for link previews (WhatsApp requires og:description).
 */
function tnf_social_preview_description_for_post(int $post_id): string {
	if ($post_id <= 0) {
		return '';
	}

	$excerpt = get_the_excerpt($post_id);
	$text    = is_string($excerpt) ? trim(wp_strip_all_tags($excerpt)) : '';
	if ($text !== '') {
		return wp_trim_words($text, 40, '…');
	}

	$post = get_post($post_id);
	if ($post instanceof WP_Post && $post->post_content !== '') {
		$text = trim(wp_strip_all_tags($post->post_content));
		if ($text !== '') {
			return wp_trim_words($text, 40, '…');
		}
	}

	$title = get_the_title($post_id);

	return is_string($title) ? trim($title) : '';
}

/**
 * Width, height, MIME for og:image (helps WhatsApp accept the preview).
 *
 * @return array{width: int, height: int, type: string}
 */
function tnf_social_preview_image_dimensions(string $url): array {
	$empty = array(
		'width'  => 0,
		'height' => 0,
		'type'   => 'image/jpeg',
	);

	$url = trim($url);
	if ($url === '') {
		return $empty;
	}

	$parts = wp_parse_url($url);
	$home  = wp_parse_url(home_url('/'));
	if (is_array($parts) && is_array($home) && ! empty($parts['path']) && ! empty($home['host'])
		&& isset($parts['host']) && strtolower((string) $parts['host']) === strtolower((string) $home['host'])
	) {
		$rel = ltrim((string) $parts['path'], '/');
		if (str_starts_with($rel, 'wp-content/uploads/')) {
			$upload = wp_upload_dir();
			$local  = trailingslashit($upload['basedir']) . substr($rel, strlen('wp-content/uploads/'));
			if (is_readable($local)) {
				$info = function_exists('wp_getimagesize') ? wp_getimagesize($local) : @getimagesize($local);
				if (is_array($info) && ! empty($info[0]) && ! empty($info[1])) {
					return array(
						'width'  => (int) $info[0],
						'height' => (int) $info[1],
						'type'   => ! empty($info['mime']) ? (string) $info['mime'] : 'image/jpeg',
					);
				}
			}
		}
	}

	if (preg_match('#/tnf-social-og/tnf-og-\d+(?:-[a-f0-9]{8})?\.jpg#', $url)) {
		$parts = wp_parse_url($url);
		$path  = is_array($parts) && isset($parts['path']) ? (string) $parts['path'] : '';
		$rel   = ltrim($path, '/');
		if (str_starts_with($rel, 'wp-content/uploads/')) {
			$upload = wp_upload_dir();
			$local  = trailingslashit($upload['basedir']) . substr($rel, strlen('wp-content/uploads/'));
			$info   = is_readable($local) ? ( function_exists('wp_getimagesize') ? wp_getimagesize($local) : @getimagesize($local) ) : false;
			if (is_array($info) && ! empty($info[0]) && ! empty($info[1]) && (int) $info[0] >= 1000) {
				return array(
					'width'  => (int) $info[0],
					'height' => (int) $info[1],
					'type'   => ! empty($info['mime']) ? (string) $info['mime'] : 'image/jpeg',
				);
			}
		}

		return array(
			'width'  => 1200,
			'height' => 630,
			'type'   => 'image/jpeg',
		);
	}

	if (preg_match('#/pdf-report/\d+/page-og#', $url)) {
		return array(
			'width'  => 1200,
			'height' => 630,
			'type'   => 'image/jpeg',
		);
	}

	return $empty;
}

/**
 * Output Open Graph meta for WhatsApp / Facebook (when no SEO plugin handles tags).
 */
function tnf_social_preview_output_meta(): void {
	if (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION')) {
		return;
	}

	if (! is_singular(tnf_social_preview_post_types())) {
		return;
	}

	$post_id = (int) get_queried_object_id();
	$url     = tnf_social_preview_image_url_for_request();
	$title   = $post_id > 0 ? get_the_title($post_id) : '';
	$desc    = tnf_social_preview_description_for_post($post_id);
	$site    = get_bloginfo('name');
	$permalink = $post_id > 0 ? get_permalink($post_id) : '';

	if ($url === '') {
		return;
	}

	if ($title !== '') {
		echo '<meta property="og:title" content="' . esc_attr($title) . "\" />\n";
		echo '<meta name="twitter:title" content="' . esc_attr($title) . "\" />\n";
	}

	if ($desc !== '') {
		echo '<meta property="og:description" content="' . esc_attr($desc) . "\" />\n";
		echo '<meta name="twitter:description" content="' . esc_attr($desc) . "\" />\n";
	}

	if (is_string($site) && $site !== '') {
		echo '<meta property="og:site_name" content="' . esc_attr($site) . "\" />\n";
	}

	if (is_string($permalink) && $permalink !== '') {
		echo '<meta property="og:url" content="' . esc_url($permalink) . "\" />\n";
	}

	$post_obj = $post_id > 0 ? get_post($post_id) : null;
	if ($post_obj instanceof WP_Post && $post_obj->post_modified_gmt !== '') {
		$updated = gmdate('c', strtotime($post_obj->post_modified_gmt));
		if (is_string($updated) && $updated !== '') {
			echo '<meta property="article:modified_time" content="' . esc_attr($updated) . "\" />\n";
			echo '<meta property="og:updated_time" content="' . esc_attr($updated) . "\" />\n";
		}
	}

	echo "<meta property=\"og:type\" content=\"article\" />\n";
	echo '<meta property="og:locale" content="' . esc_attr(str_replace('_', '-', (string) get_locale())) . "\" />\n";

	echo '<meta property="og:image" content="' . esc_url($url) . "\" />\n";
	echo '<meta property="og:image:secure_url" content="' . esc_url($url) . "\" />\n";
	echo '<meta name="twitter:image" content="' . esc_url($url) . "\" />\n";
	echo "<meta name=\"twitter:card\" content=\"summary_large_image\" />\n";

	$dims = tnf_social_preview_image_dimensions($url);
	if ($dims['width'] > 0 && $dims['height'] > 0) {
		echo '<meta property="og:image:width" content="' . esc_attr((string) $dims['width']) . "\" />\n";
		echo '<meta property="og:image:height" content="' . esc_attr((string) $dims['height']) . "\" />\n";
	}
	if ($dims['type'] !== '') {
		echo '<meta property="og:image:type" content="' . esc_attr($dims['type']) . "\" />\n";
	}

	if ($title !== '') {
		echo '<meta property="og:image:alt" content="' . esc_attr($title) . "\" />\n";
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
 * Crop and resize for WhatsApp / Facebook (1.91:1, max 1200×630, under ~300 KB).
 *
 * @param WP_Image_Editor $editor Image editor instance.
 */
function tnf_social_preview_prepare_editor($editor): void {
	if (! is_object($editor) || ! method_exists($editor, 'set_quality')) {
		return;
	}

	$editor->set_quality(82);

	$size = $editor->get_size();
	if (! is_array($size) || empty($size['width']) || empty($size['height'])) {
		return;
	}

	$w = (int) $size['width'];
	$h = (int) $size['height'];
	if ($w < 1 || $h < 1) {
		return;
	}

	$target_w     = 1200;
	$target_h     = 630;
	$target_ratio = $target_w / $target_h;
	$current      = $w / $h;

	if ($current > $target_ratio) {
		$new_w = (int) round($h * $target_ratio);
		$x     = (int) floor(( $w - $new_w ) / 2);
		$crop  = $editor->crop($x, 0, max(1, $new_w), $h);
		if (is_wp_error($crop)) {
			return;
		}
	} elseif ($current < $target_ratio) {
		$new_h = (int) round($w / $target_ratio);
		$y     = (int) floor(( $h - $new_h ) / 2);
		// Portrait / tall images: skip masthead band at the top (newspaper layouts).
		if ($h > $w * 1.15) {
			$y = (int) max(0, min((int) floor($h * 0.18), $h - $new_h));
		}
		$crop  = $editor->crop(0, $y, $w, max(1, $new_h));
		if (is_wp_error($crop)) {
			return;
		}
	}

	$resize = $editor->resize($target_w, $target_h, true);
	if (is_wp_error($resize)) {
		return;
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

	$out_w = isset($saved['width']) ? (int) $saved['width'] : 0;
	$out_h = isset($saved['height']) ? (int) $saved['height'] : 0;
	if ($out_w < 1000 || $out_h < 500) {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@unlink($file);

		return new WP_Error('small', __('Image too small after resize', 'tnf-news-platform'), array('status' => 500));
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

	if (tnf_social_preview_pdf_featured_content_url($post_id) !== '') {
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

	// Do not use site logo / masthead for PDF posts when a PDF file is attached.
	if ($aid <= 0 && ( tnf_social_preview_default_image_url() !== '' || tnf_social_preview_brand_asset_path() !== '' )) {
		return true;
	}

	return false;
}

/**
 * Build or read cached JPEG for PDF social sharing (WhatsApp / Facebook crawlers).
 *
 * Order: rendered page 1 → featured image (not site logo) → PDF preview → logo only if no PDF attached.
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

	$thumb_id = (int) get_post_thumbnail_id($post_id);
	if ($thumb_id > 0 && ! tnf_social_preview_is_site_logo_attachment($thumb_id)) {
		$sources[] = array(
			'key' => 'featured:' . (string) $thumb_id,
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

	// Last resort: site logo only when this post has no PDF file attached.
	if ($aid <= 0) {
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
