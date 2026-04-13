<?php
/**
 * Block patterns for TNF archive layouts.
 *
 * @package TNF_News_Platform
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Register pattern category and patterns.
 */
function tnf_register_block_patterns(): void {
	register_block_pattern_category(
		'tnf-news-platform',
		array(
			'label' => __('TNF News Platform', 'tnf-news-platform'),
		)
	);

	tnf_register_category_archive_block_pattern();
	tnf_register_cpt_archive_block_pattern();
}
add_action('init', 'tnf_register_block_patterns', 8);

/**
 * Category/tag archive grid (inherits main query → tnf_news).
 */
function tnf_register_category_archive_block_pattern(): void {
	$file = TNF_NEWS_PLATFORM_PATH . 'patterns/category-archive-grid.html';
	if (! is_readable($file)) {
		return;
	}

	$content = (string) file_get_contents($file);
	if ($content === '') {
		return;
	}

	register_block_pattern(
		'tnf-news-platform/category-archive-grid',
		array(
			'title'      => __('TNF category archive grid', 'tnf-news-platform'),
			'categories' => array('tnf-news-platform'),
			'content'    => $content,
		)
	);
}

/**
 * CPT archive grid (news / video / PDF archives).
 */
function tnf_register_cpt_archive_block_pattern(): void {
	$file = TNF_NEWS_PLATFORM_PATH . 'patterns/cpt-archive-grid.html';
	if (! is_readable($file)) {
		return;
	}

	$content = (string) file_get_contents($file);
	if ($content === '') {
		return;
	}

	register_block_pattern(
		'tnf-news-platform/cpt-archive-grid',
		array(
			'title'      => __('TNF CPT archive grid', 'tnf-news-platform'),
			'categories' => array('tnf-news-platform'),
			'content'    => $content,
		)
	);
}

/**
 * Enqueue archive grid styles on taxonomy and TNF CPT archives.
 */
function tnf_enqueue_tnf_archive_block_styles(): void {
	if (is_admin()) {
		return;
	}

	if (
		! is_category()
		&& ! is_tag()
		&& ! is_post_type_archive(array( 'tnf_news', 'tnf_video', 'tnf_pdf_report' ))
	) {
		return;
	}

	$path = TNF_NEWS_PLATFORM_PATH . 'assets/css/frontend-category-archive.css';
	if (! is_readable($path)) {
		return;
	}

	wp_enqueue_style(
		'tnf-category-archive',
		TNF_NEWS_PLATFORM_URL . 'assets/css/frontend-category-archive.css',
		array(),
		(string) filemtime($path)
	);
}
add_action('wp_enqueue_scripts', 'tnf_enqueue_tnf_archive_block_styles', 15);
