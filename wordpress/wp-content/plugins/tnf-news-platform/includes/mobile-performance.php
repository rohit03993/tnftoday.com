<?php
/**
 * Mobile + Capacitor WebView performance (frontend only).
 *
 * @package TNF_News_Platform
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Register performance hooks.
 */
function tnf_register_mobile_performance(): void {
	add_action('wp_enqueue_scripts', 'tnf_perf_dequeue_bloat', 100);
	add_action('wp_enqueue_scripts', 'tnf_perf_tune_scripts', 110);
	add_action('wp_enqueue_scripts', 'tnf_perf_tune_styles', 110);
	add_filter('script_loader_tag', 'tnf_perf_defer_script_tag', 10, 2);
	add_filter('style_loader_tag', 'tnf_perf_async_style_tag', 10, 2);
	add_filter('wp_get_attachment_image_attributes', 'tnf_perf_attachment_image_attrs', 10, 3);
	add_filter('the_content', 'tnf_perf_lazy_content_images', 20);
	add_action('wp_head', 'tnf_perf_resource_hints', 0);
	add_filter('body_class', 'tnf_perf_body_class');
	add_action('wp_enqueue_scripts', 'tnf_enqueue_mobile_perf_styles', 42);
	add_action('save_post', 'tnf_perf_flush_breaking_ticker_cache', 20);
}

/**
 * Phone browsers and Capacitor shell.
 */
function tnf_is_lightweight_client(): bool {
	if (function_exists('tnf_is_capacitor_app') && tnf_is_capacitor_app()) {
		return true;
	}
	return function_exists('wp_is_mobile') && wp_is_mobile();
}

/**
 * Preconnect / dns-prefetch for fewer round trips on first paint.
 */
function tnf_perf_resource_hints(): void {
	if (is_admin() || ! tnf_is_lightweight_client()) {
		return;
	}

	$host = wp_parse_url(home_url(), PHP_URL_HOST);
	if (is_string($host) && $host !== '') {
		echo '<link rel="preconnect" href="https://' . esc_attr($host) . '" crossorigin />' . "\n";
	}

	echo '<link rel="dns-prefetch" href="https://fonts.googleapis.com" />' . "\n";
	echo '<link rel="dns-prefetch" href="https://fonts.gstatic.com" />' . "\n";

	if (is_singular('tnf_pdf_report')) {
		echo '<link rel="dns-prefetch" href="https://cdnjs.cloudflare.com" />' . "\n";
	}

	if (is_singular('tnf_video') || is_page('videos') || is_post_type_archive('tnf_video')) {
		echo '<link rel="dns-prefetch" href="https://www.youtube-nocookie.com" />' . "\n";
		echo '<link rel="dns-prefetch" href="https://i.ytimg.com" />' . "\n";
	}
}

/**
 * Remove low-value core assets on mobile front-end.
 */
function tnf_perf_dequeue_bloat(): void {
	if (is_admin()) {
		return;
	}

	if (tnf_perf_should_dequeue_block_library()) {
		wp_dequeue_style('wp-block-library');
		wp_dequeue_style('wp-block-library-theme');
		wp_dequeue_style('classic-theme-styles');
	}

	if (! is_user_logged_in()) {
		wp_dequeue_style('dashicons');
	}

	if (! tnf_is_lightweight_client()) {
		return;
	}

	wp_deregister_script('wp-embed');
	wp_dequeue_script('wp-embed');

	remove_action('wp_head', 'print_emoji_detection_script', 7);
	remove_action('admin_print_scripts', 'print_emoji_detection_script');
	remove_action('wp_print_styles', 'print_emoji_styles');
	remove_action('admin_print_styles', 'print_emoji_styles');
	add_filter('emoji_svg_url', '__return_false');
}

/**
 * Defer non-critical plugin scripts; keep order via footer loading where already true.
 *
 * @param array<string> $handles Script handles.
 */
function tnf_perf_defer_handles(): array {
	$handles = array(
		'tnf-frontend-chrome',
		'tnf-child-home-news',
		'tnf-mobile-app-bridge',
		'tnf-epaper-viewer',
	);

	return apply_filters('tnf_perf_defer_handles', $handles);
}

/**
 * @param string $tag    Script tag.
 * @param string $handle Handle.
 */
function tnf_perf_defer_script_tag(string $tag, string $handle): string {
	if (! tnf_is_lightweight_client()) {
		return $tag;
	}

	if (! in_array($handle, tnf_perf_defer_handles(), true)) {
		return $tag;
	}

	if (str_contains($tag, ' defer') || str_contains($tag, ' async')) {
		return $tag;
	}

	return str_replace(' src=', ' defer src=', $tag);
}

/**
 * Lower priority for home-news JS on non-home routes.
 */
function tnf_perf_tune_scripts(): void {
	if (is_admin() || ! tnf_is_lightweight_client()) {
		return;
	}

	if (! is_front_page() && ! is_home()) {
		wp_dequeue_script('tnf-child-home-news');
	}
}

/**
 * Drop heavy CSS on routes that do not need it (mobile + app).
 */
function tnf_perf_tune_styles(): void {
	if (is_admin() || ! tnf_is_lightweight_client()) {
		return;
	}

	if (! is_front_page() && ! is_home()) {
		wp_dequeue_script('tnf-child-home-news');
	}
}

/**
 * TNF singles/archives use plugin CSS; block library is ~100KB+ extra on mobile.
 */
function tnf_perf_should_dequeue_block_library(): bool {
	if (is_admin()) {
		return false;
	}

	if (is_front_page() || is_home()) {
		return true;
	}

	if (
		is_singular(array( 'tnf_news', 'tnf_video', 'tnf_pdf_report' ))
		|| is_post_type_archive(array( 'tnf_news', 'tnf_video', 'tnf_pdf_report' ))
		|| is_category()
		|| is_tag()
	) {
		return true;
	}

	return (bool) apply_filters('tnf_perf_dequeue_block_library', false);
}

/**
 * Non-blocking Google Fonts + defer home bundle off homepage (header CSS loads first).
 *
 * @param string $tag    Link tag.
 * @param string $handle Handle.
 */
function tnf_perf_async_style_tag(string $tag, string $handle): string {
	$async_handles = array('tnf-devanagari-fonts');
	if (tnf_is_lightweight_client() && ! is_front_page() && ! is_home()) {
		$async_handles[] = 'tnf-child-home-news';
	}

	if (! in_array($handle, $async_handles, true)) {
		return $tag;
	}

	if (str_contains($tag, 'media=')) {
		return (string) preg_replace(
			'/media=(["\'])all\1/',
			'media=$1print$1 onload="this.media=\'all\'"',
			$tag,
			1
		);
	}

	return str_replace(
		"rel='stylesheet'",
		"rel='stylesheet' media='print' onload=\"this.media='all'\"",
		$tag
	);
}

/**
 * @param int $post_id Post ID.
 */
function tnf_perf_flush_breaking_ticker_cache(int $post_id): void {
	if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
		return;
	}

	$type = get_post_type($post_id);
	if (! is_string($type) || ! in_array($type, tnf_listing_news_post_types(), true)) {
		return;
	}

	delete_transient('tnf_breaking_ticker_html');
}

/**
 * @param array<string,string> $attr       Attributes.
 * @param WP_Post              $attachment Attachment.
 * @param string|int[]         $size       Size.
 * @return array<string,string>
 */
function tnf_perf_attachment_image_attrs(array $attr, WP_Post $attachment, $size): array {
	if (! tnf_is_lightweight_client()) {
		return $attr;
	}

	if (! isset($attr['loading'])) {
		$attr['loading'] = 'lazy';
	}
	if (! isset($attr['decoding'])) {
		$attr['decoding'] = 'async';
	}

	return $attr;
}

/**
 * Lazy-load images inside post content on mobile.
 *
 * @param string $content Content HTML.
 */
function tnf_perf_lazy_content_images(string $content): string {
	if (! tnf_is_lightweight_client() || $content === '' || is_feed()) {
		return $content;
	}

	if (! preg_match('/<img\b/i', $content)) {
		return $content;
	}

	return (string) preg_replace_callback(
		'/<img\b([^>]*?)>/i',
		static function (array $m): string {
			$attrs = $m[1];
			if (stripos($attrs, 'loading=') !== false) {
				return $m[0];
			}
			return '<img' . $attrs . ' loading="lazy" decoding="async">';
		},
		$content
	);
}

/**
 * @param array<int,string> $classes Body classes.
 * @return array<int,string>
 */
function tnf_perf_body_class(array $classes): array {
	if (tnf_is_lightweight_client()) {
		$classes[] = 'tnf-mobile-perf';
	}
	if (function_exists('tnf_is_capacitor_app') && tnf_is_capacitor_app()) {
		$classes[] = 'tnf-mobile-perf--app';
	}
	return $classes;
}

/**
 * Lightweight perf CSS layer.
 */
function tnf_enqueue_mobile_perf_styles(): void {
	if (is_admin() || ! tnf_is_lightweight_client()) {
		return;
	}

	$path = TNF_NEWS_PLATFORM_PATH . 'assets/css/frontend-mobile-perf.css';
	if (! is_readable($path)) {
		return;
	}

	$deps = array();
	if (wp_style_is('tnf-frontend-mobile', 'registered') || wp_style_is('tnf-frontend-mobile', 'enqueued')) {
		$deps[] = 'tnf-frontend-mobile';
	} else {
		$deps[] = 'tnf-frontend-chrome';
	}

	wp_enqueue_style(
		'tnf-mobile-perf',
		TNF_NEWS_PLATFORM_URL . 'assets/css/frontend-mobile-perf.css',
		$deps,
		(string) filemtime($path)
	);
}
