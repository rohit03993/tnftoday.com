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
	add_filter('script_loader_tag', 'tnf_perf_defer_script_tag', 10, 2);
	add_filter('wp_get_attachment_image_attributes', 'tnf_perf_attachment_image_attrs', 10, 3);
	add_filter('the_content', 'tnf_perf_lazy_content_images', 20);
	add_action('wp_head', 'tnf_perf_resource_hints', 0);
	add_filter('body_class', 'tnf_perf_body_class');
	add_action('wp_enqueue_scripts', 'tnf_enqueue_mobile_perf_styles', 42);
}

/**
 * Phone browsers and Capacitor shell.
 */
function tnf_is_lightweight_client(): bool {
	if (function_exists('tnf_is_capacitor_app') && tnf_is_capacitor_app()) {
		return true;
	}
	return wp_is_mobile();
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
}

/**
 * Remove low-value core assets on mobile front-end.
 */
function tnf_perf_dequeue_bloat(): void {
	if (is_admin() || ! tnf_is_lightweight_client()) {
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
