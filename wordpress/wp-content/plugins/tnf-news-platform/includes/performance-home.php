<?php
/**
 * Homepage and front-end performance (all devices, not only mobile).
 *
 * @package TNF_News_Platform
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Bootstrap performance hooks.
 */
function tnf_register_performance_home(): void {
	add_action('pre_get_posts', 'tnf_perf_homepage_query_flags', 20);
	add_filter('style_loader_tag', 'tnf_perf_async_google_fonts', 10, 2);
	add_action('wp_enqueue_scripts', 'tnf_perf_front_dequeue_bloat', 99);
	add_action('wp_enqueue_scripts', 'tnf_perf_auth_page_dequeue_heavy_assets', 150);
	add_filter('wp_get_attachment_image_src', 'tnf_perf_fix_missing_attachment_src', 10, 4);
	add_filter('post_thumbnail_url', 'tnf_perf_fix_missing_thumbnail_url', 10, 3);

	if (tnf_perf_is_local_dev()) {
		add_filter('pre_http_request', 'tnf_perf_local_block_slow_external_http', 10, 3);
		add_filter('automatic_updater_disabled', '__return_true');
		add_action('admin_init', 'tnf_perf_local_disable_site_health_async', 1);
	}
}

/**
 * Local XAMPP / localhost development.
 */
function tnf_perf_is_local_dev(): bool {
	if (defined('TNF_LOCAL_DEV') && TNF_LOCAL_DEV) {
		return true;
	}

	$host = wp_parse_url(home_url(), PHP_URL_HOST);
	if (! is_string($host) || $host === '') {
		return false;
	}

	$host = strtolower($host);

	return $host === 'localhost' || $host === '127.0.0.1' || str_ends_with($host, '.local');
}

/**
 * Stop wp-admin waiting on WordPress.org update/API checks (common local timeout).
 *
 * @param false|array<string,mixed>|WP_Error $preempt Preempt value.
 * @param array<string,mixed>                $args    Request args.
 * @param string                             $url     Target URL.
 * @return false|array<string,mixed>|WP_Error
 */
function tnf_perf_local_block_slow_external_http($preempt, array $args, string $url) {
	unset($args);

	if ($preempt !== false) {
		return $preempt;
	}

	if (
		str_contains($url, 'api.wordpress.org')
		|| str_contains($url, 'downloads.wordpress.org')
		|| str_contains($url, 'w.org')
	) {
		return new WP_Error('tnf_local_http_blocked', 'External WordPress API blocked on local dev');
	}

	return $preempt;
}

/**
 * Site Health background tests slow down first wp-admin load.
 */
function tnf_perf_local_disable_site_health_async(): void {
	if (! is_admin()) {
		return;
	}
	remove_action('wp_dashboard_setup', 'wp_dashboard_site_health');
}

/**
 * Login/register pages do not need home JS/CSS bundles.
 */
function tnf_perf_auth_page_dequeue_heavy_assets(): void {
	if (is_admin() || ! function_exists('tnf_is_auth_page') || ! tnf_is_auth_page()) {
		return;
	}

	wp_dequeue_script('tnf-child-home-news');
	wp_dequeue_style('tnf-child-home-news');
}

/**
 * Local placeholder — no external picsum.photos (was slowing every card load).
 */
function tnf_placeholder_image_url(): string {
	static $url = null;
	if (is_string($url) && $url !== '') {
		return $url;
	}

	$rel = 'assets/images/tnf-placeholder-news.svg';
	$abs = TNF_NEWS_PLATFORM_PATH . $rel;
	if (is_readable($abs)) {
		$url = TNF_NEWS_PLATFORM_URL . $rel;
	} else {
		$url = 'data:image/svg+xml,' . rawurlencode(
			'<svg xmlns="http://www.w3.org/2000/svg" width="640" height="360"><rect fill="#e9ecf1" width="100%" height="100%"/></svg>'
		);
	}

	return $url;
}

/**
 * True when this environment should not wait on remote production media.
 */
function tnf_perf_skip_remote_upload_urls(): bool {
	if (defined('TNF_FORCE_LOCAL_PLACEHOLDERS') && TNF_FORCE_LOCAL_PLACEHOLDERS) {
		return true;
	}

	$home = home_url();
	return str_contains($home, 'localhost') || str_contains($home, '127.0.0.1');
}

/**
 * @param string $url Attachment or thumbnail URL.
 */
function tnf_perf_upload_url_is_unreachable(string $url): bool {
	$url = trim($url);
	if ($url === '') {
		return true;
	}

	$parts = wp_parse_url($url);
	if (! is_array($parts) || empty($parts['path'])) {
		return false;
	}

	$path = (string) $parts['path'];
	if (! str_contains($path, '/wp-content/uploads/')) {
		if (tnf_perf_skip_remote_upload_urls()) {
			$home_host = wp_parse_url(home_url(), PHP_URL_HOST);
			$url_host  = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
			if ($url_host !== '' && $home_host !== '' && $url_host !== strtolower((string) $home_host)) {
				return true;
			}
		}

		return false;
	}

	$upload_dir = wp_upload_dir();
	$baseurl    = isset($upload_dir['baseurl']) ? (string) $upload_dir['baseurl'] : '';
	$basedir    = isset($upload_dir['basedir']) ? (string) $upload_dir['basedir'] : '';
	if ($baseurl === '' || $basedir === '') {
		return tnf_perf_skip_remote_upload_urls();
	}

	$rel = ltrim(str_replace(untrailingslashit($baseurl), '', $url), '/');
	if ($rel === '' || str_contains($rel, '..')) {
		return true;
	}

	$local = wp_normalize_path($basedir . '/' . $rel);

	return ! is_readable($local);
}

/**
 * @param array<int,mixed>|false $image       Image data.
 * @param int                    $attachment_id Attachment ID.
 * @param string|int[]           $size        Size.
 * @param bool                   $icon          Icon flag.
 * @return array<int,mixed>|false
 */
function tnf_perf_fix_missing_attachment_src($image, $attachment_id, $size, $icon) {
	unset($attachment_id, $size, $icon);

	if (! is_array($image) || empty($image[0]) || ! is_string($image[0])) {
		return $image;
	}

	if (tnf_perf_upload_url_is_unreachable($image[0])) {
		$image[0] = tnf_placeholder_image_url();
	}

	return $image;
}

/**
 * @param string|false $url         Thumbnail URL.
 * @param int|WP_Post|null $post_id Post ID.
 * @param string|int[]   $size      Size.
 * @return string|false
 */
function tnf_perf_fix_missing_thumbnail_url($url, $post_id, $size) {
	unset($post_id, $size);

	if (! is_string($url) || $url === '') {
		return $url;
	}

	if (tnf_perf_upload_url_is_unreachable($url)) {
		return tnf_placeholder_image_url();
	}

	return $url;
}

/**
 * Faster WP_Query on homepage (skip SQL_CALC_FOUND_ROWS).
 *
 * @param WP_Query $query Query.
 */
function tnf_perf_homepage_query_flags(WP_Query $query): void {
	if (is_admin() || (! is_front_page() && ! is_home())) {
		return;
	}

	$query->set('no_found_rows', true);
}

/**
 * Non-blocking Google Fonts (child theme handle).
 *
 * @param string $html   Link tag.
 * @param string $handle Handle.
 */
function tnf_perf_async_google_fonts(string $html, string $handle): string {
	if ($handle !== 'tnf-devanagari-fonts' || str_contains($html, 'media=')) {
		return $html;
	}

	return str_replace(
		"rel='stylesheet'",
		"rel='stylesheet' media='print' onload=\"this.media='all'\"",
		$html
	);
}

/**
 * Drop heavy core CSS on public TNF routes (desktop + mobile).
 */
function tnf_perf_front_dequeue_bloat(): void {
	if (is_admin()) {
		return;
	}

	if (function_exists('tnf_perf_should_dequeue_block_library') && tnf_perf_should_dequeue_block_library()) {
		wp_dequeue_style('wp-block-library');
		wp_dequeue_style('wp-block-library-theme');
		wp_dequeue_style('classic-theme-styles');
	}

	if (! is_user_logged_in()) {
		wp_dequeue_style('dashicons');
	}

	wp_deregister_script('wp-embed');
	wp_dequeue_script('wp-embed');
}
