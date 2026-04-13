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
 * Custom logo in Customizer (Site Identity) and for the_custom_logo() in the TNF header.
 *
 * Block themes like Twenty Twenty-Five often omit this, so only "Site Icon" appears until declared.
 */
function tnf_child_theme_support(): void {
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 120,
			'width'       => 400,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);
}
add_action('after_setup_theme', 'tnf_child_theme_support', 11);

/**
 * Customizer: logo height + optional masthead line (Site Identity section).
 */
function tnf_child_customize_register(WP_Customize_Manager $wp_customize): void {
	$wp_customize->add_setting(
		'tnf_logo_max_height',
		array(
			'default'           => 52,
			'sanitize_callback' => static function ($value): int {
				$n = absint($value);
				if ($n < 24) {
					return 52;
				}
				if ($n > 200) {
					return 200;
				}
				return $n;
			},
			'transport'         => 'refresh',
		)
	);
	$wp_customize->add_control(
		'tnf_logo_max_height',
		array(
			'label'       => __('TNF header: logo max height (px)', 'tnf-twentytwentyfive-child'),
			'description' => __('Applies to the custom logo in the white masthead. Range 24–200.', 'tnf-twentytwentyfive-child'),
			'section'     => 'title_tagline',
			'type'        => 'number',
			'input_attrs' => array(
				'min'  => 24,
				'max'  => 200,
				'step' => 1,
			),
			'priority'    => 88,
		)
	);

	$wp_customize->add_setting(
		'tnf_masthead_tagline',
		array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_textarea_field',
			'transport'         => 'refresh',
		)
	);
	$wp_customize->add_control(
		'tnf_masthead_tagline',
		array(
			'label'       => __('TNF header: line below site name', 'tnf-twentytwentyfive-child'),
			'description' => __('Optional text under the title in the masthead. Leave empty to hide. (Separate from the global “Tagline” field above.)', 'tnf-twentytwentyfive-child'),
			'section'     => 'title_tagline',
			'type'        => 'textarea',
			'priority'    => 90,
		)
	);
}
add_action('customize_register', 'tnf_child_customize_register');

/**
 * Output logo max-height as CSS variable for .tnf-home-news header chrome.
 */
function tnf_child_header_customizer_css(): void {
	if (is_admin()) {
		return;
	}
	$h = absint(get_theme_mod('tnf_logo_max_height', 52));
	if ($h < 24 || $h > 200) {
		$h = 52;
	}
	wp_add_inline_style(
		'tnf-child-home-news',
		'.tnf-site-chrome.tnf-home-news{--tnf-logo-max-height:' . $h . 'px;}'
	);
}
add_action('wp_enqueue_scripts', 'tnf_child_header_customizer_css', 35);

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
