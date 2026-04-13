<?php
/**
 * TNF child theme: canonical templates + assets (stable across parent theme updates).
 *
 * @package TNF_Twenty_Twenty_Five_Child
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Hindi + Latin web fonts.
 */
function tnf_child_enqueue_fonts(): void {
	if (is_admin()) {
		return;
	}
	wp_enqueue_style(
		'tnf-devanagari-fonts',
		'https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;500;600;700;800&family=Noto+Sans:wght@400;600;700&display=swap',
		array(),
		null
	);
	wp_style_add_data('tnf-devanagari-fonts', 'crossorigin', 'anonymous');
}
add_action('wp_enqueue_scripts', 'tnf_child_enqueue_fonts', 5);

/**
 * @param array<int, string|array<string, mixed>> $urls          URLs.
 * @param string                                $relation_type Relation type.
 * @return array<int, string|array<string, mixed>>
 */
function tnf_child_font_preconnect(array $urls, string $relation_type): array {
	if ($relation_type !== 'preconnect') {
		return $urls;
	}
	$urls[] = array(
		'href'        => 'https://fonts.googleapis.com',
		'crossorigin' => 'anonymous',
	);
	$urls[] = array(
		'href'        => 'https://fonts.gstatic.com',
		'crossorigin' => 'anonymous',
	);
	return $urls;
}
add_filter('wp_resource_hints', 'tnf_child_font_preconnect', 10, 2);

/**
 * Replace parent’s TNF home assets with this theme’s copies (child is source of truth).
 */
function tnf_child_enqueue_assets(): void {
	$child_ver = wp_get_theme()->get('Version');

	wp_enqueue_style(
		'tnf-child-style',
		get_stylesheet_uri(),
		array('twentytwentyfive-style', 'tnf-devanagari-fonts'),
		$child_ver
	);

	if (wp_style_is('twentytwentyfive-tnf-home-news', 'enqueued')) {
		wp_dequeue_style('twentytwentyfive-tnf-home-news');
	}
	if (wp_script_is('twentytwentyfive-tnf-home-news', 'enqueued')) {
		wp_dequeue_script('twentytwentyfive-tnf-home-news');
	}

	$css_path = get_stylesheet_directory() . '/assets/css/tnf-home-news.css';
	$js_path  = get_stylesheet_directory() . '/assets/js/tnf-home-news.js';
	$css_ver  = is_readable($css_path) ? (string) filemtime($css_path) : $child_ver;
	$js_ver   = is_readable($js_path) ? (string) filemtime($js_path) : $child_ver;

	wp_enqueue_style(
		'tnf-child-home-news',
		get_stylesheet_directory_uri() . '/assets/css/tnf-home-news.css',
		array('tnf-child-style'),
		$css_ver
	);

	wp_enqueue_script(
		'tnf-child-home-news',
		get_stylesheet_directory_uri() . '/assets/js/tnf-home-news.js',
		array(),
		$js_ver,
		true
	);
}
add_action('wp_enqueue_scripts', 'tnf_child_enqueue_assets', 30);
